<?php

namespace Fedale\CalendarBundle\Twig\Components;

use Fedale\CalendarBundle\Contract\CalendarViewInterface;
use Fedale\CalendarBundle\Contract\TimezoneResolverInterface;
use Fedale\CalendarBundle\Definition\CalendarRegistry;
use Fedale\CalendarBundle\Intent\CalendarIntent;
use Fedale\CalendarBundle\Intent\ClickEvent;
use Fedale\CalendarBundle\Intent\DeleteEvent;
use Fedale\CalendarBundle\Intent\IntentDispatcher;
use Fedale\CalendarBundle\Intent\IntentResult;
use Fedale\CalendarBundle\Intent\MoveEvent;
use Fedale\CalendarBundle\Intent\Rejection;
use Fedale\CalendarBundle\Intent\ResizeEvent;
use Fedale\CalendarBundle\Intent\SelectRange;
use Fedale\CalendarBundle\Model\CalendarQuery;
use Fedale\CalendarBundle\Options\CalendarOptions;
use Fedale\CalendarBundle\Options\CalendarOptionsResolver;
use Fedale\CalendarBundle\Options\ToolbarButton;
use Fedale\CalendarBundle\Source\EventCollector;
use Fedale\CalendarBundle\Source\ResourceCollector;
use Fedale\CalendarBundle\Time\DateFormatter;
use Fedale\CalendarBundle\Time\Days;
use Fedale\CalendarBundle\View\ViewContext;
use Fedale\CalendarBundle\View\ViewRegistry;
use Psr\Clock\ClockInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The calendar: `<twig:Fedale:Calendar calendar="shifts" />`.
 *
 * Its whole state is the calendar id, the view (a variant name), the focused
 * date and the collapsed resource rows; view and date live in the URL, so
 * every period has a link and the browser history works. Navigation is a
 * round trip: the server re-renders the view, the browser never builds the
 * grid.
 *
 * Gestures (select, move, resize, click, delete) arrive as actions carrying
 * wall-clock times; they become intents for the source's handler, and the
 * calendar re-renders from the sources whatever the answer.
 */
#[AsLiveComponent(name: 'Fedale:Calendar', template: '@FedaleCalendar/components/Calendar.html.twig')]
final class Calendar
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public string $calendar = '';

    /** null = the calendar's default view. */
    #[LiveProp(writable: true, url: true)]
    public ?string $view = null;

    /** Focused day as Y-m-d; null = today. */
    #[LiveProp(writable: true, url: true)]
    public ?string $date = null;

    /**
     * Keys of the collapsed timeline rows (groups and parent resources). Kept
     * on the server so it survives re-renders and navigation, and collapsed
     * rows are not rendered at all.
     *
     * @var list<string>
     */
    #[LiveProp]
    public array $collapsed = [];

    /** The answer to the intent of this request, shown above the view; never kept across requests. */
    public ?IntentResult $result = null;

    /** @var array<string, object> view model per calendar|view|date, built once per render */
    private array $models = [];

    public function __construct(
        private readonly CalendarRegistry $calendars,
        private readonly CalendarOptionsResolver $optionsResolver,
        private readonly ViewRegistry $views,
        private readonly EventCollector $collector,
        private readonly ResourceCollector $resourceCollector,
        private readonly DateFormatter $formatter,
        private readonly TimezoneResolverInterface $timezoneResolver,
        private readonly ClockInterface $clock,
        private readonly IntentDispatcher $intents,
    ) {
    }

    #[LiveAction]
    public function previous(): void
    {
        $this->step(-1);
    }

    #[LiveAction]
    public function next(): void
    {
        $this->step(1);
    }

    #[LiveAction]
    public function today(): void
    {
        $this->date = null;
    }

    #[LiveAction]
    public function changeView(#[LiveArg] string $view): void
    {
        $options = $this->getOptions();
        if (\in_array($view, $options->enabledViews, true)) {
            // The default view stays out of the URL.
            $this->view = $view === $options->defaultView ? null : $view;
        }
    }

    #[LiveAction]
    public function toggle(#[LiveArg] string $key): void
    {
        $this->collapsed = \in_array($key, $this->collapsed, true)
            ? array_values(array_diff($this->collapsed, [$key]))
            : [...$this->collapsed, $key];
    }

    /*
     * Intent actions. Every argument is a string, as the browser sends it:
     * times are wall-clock Y-m-d\TH:i (Y-m-d when allDay is '1') in the
     * display timezone, end exclusive; '' stands for "none".
     */

    #[LiveAction]
    public function select(#[LiveArg] string $start, #[LiveArg] string $end, #[LiveArg] string $allDay = '', #[LiveArg] string $resource = ''): void
    {
        $this->apply(fn(\DateTimeZone $tz) => new SelectRange($this->calendar, $this->wallTime($start, $tz, $allDay), $this->wallTime($end, $tz, $allDay), $allDay === '1', $this->nullable($resource)));
    }

    #[LiveAction]
    public function moveEvent(#[LiveArg] string $source, #[LiveArg] string $id, #[LiveArg] string $start, #[LiveArg] string $end, #[LiveArg] string $allDay = '', #[LiveArg] string $version = '', #[LiveArg] string $fromResource = '', #[LiveArg] string $toResource = ''): void
    {
        $this->apply(fn(\DateTimeZone $tz) => new MoveEvent($this->calendar, $source, $id, $this->nullable($version), $this->wallTime($start, $tz, $allDay), $this->wallTime($end, $tz, $allDay), $this->nullable($fromResource), $this->nullable($toResource)));
    }

    #[LiveAction]
    public function resizeEvent(#[LiveArg] string $source, #[LiveArg] string $id, #[LiveArg] string $start, #[LiveArg] string $end, #[LiveArg] string $allDay = '', #[LiveArg] string $version = ''): void
    {
        $this->apply(fn(\DateTimeZone $tz) => new ResizeEvent($this->calendar, $source, $id, $this->nullable($version), $this->wallTime($start, $tz, $allDay), $this->wallTime($end, $tz, $allDay)));
    }

    #[LiveAction]
    public function clickEvent(#[LiveArg] string $source, #[LiveArg] string $id, #[LiveArg] string $version = ''): void
    {
        $this->apply(fn() => new ClickEvent($this->calendar, $source, $id, $this->nullable($version)));
    }

    #[LiveAction]
    public function deleteEvent(#[LiveArg] string $source, #[LiveArg] string $id, #[LiveArg] string $version = ''): void
    {
        $this->apply(fn() => new DeleteEvent($this->calendar, $source, $id, $this->nullable($version)));
    }

    public function getOptions(): CalendarOptions
    {
        return $this->optionsResolver->resolve($this->calendar);
    }

    public function getLabel(): ?string
    {
        return $this->calendars->get($this->calendar)->label();
    }

    /** The view to render: the requested one if enabled, else the default (a stale or forged URL never breaks the page). */
    public function getActiveView(): string
    {
        $options = $this->getOptions();

        return $this->view !== null && \in_array($this->view, $options->enabledViews, true)
            ? $this->view
            : $options->defaultView;
    }

    public function getContext(): ViewContext
    {
        $options = $this->getOptions();
        $timezone = $options->timezone !== null ? new \DateTimeZone($options->timezone) : $this->timezoneResolver->resolve();
        $now = $this->clock->now()->setTimezone($timezone);
        $today = Days::start($now);

        return new ViewContext(
            date: $this->parseDate($timezone) ?? $today,
            today: $today,
            options: $options,
            timezone: $timezone,
            locale: $options->locale ?? $this->formatter->defaultLocale(),
            variant: $options->variant($this->getActiveView()),
            now: $now,
            collapsed: $this->collapsed,
        );
    }

    public function getTitle(): string
    {
        return $this->activeView()->title($this->getContext());
    }

    /** Whether the period on screen contains today, to disable the "today" button. */
    public function isShowingToday(): bool
    {
        $context = $this->getContext();

        return $this->activeView()->range($context)->contains($context->today);
    }

    public function getModel(): object
    {
        $context = $this->getContext();
        $key = $this->calendar.'|'.$this->getActiveView().'|'.$context->date->format('Y-m-d').'|'.implode(',', $this->collapsed);

        if (!isset($this->models[$key])) {
            $view = $this->activeView();
            $query = new CalendarQuery($view->range($context), $context->timezone);
            $this->models[$key] = $view->build(
                $context,
                $this->collector->collect($context->options, $query),
                $view->usesResources($context->variant) ? $this->resourceCollector->collect($context->options, $query) : [],
            );
        }

        return $this->models[$key];
    }

    public function getViewTemplate(): string
    {
        return $this->activeView()->template();
    }

    /**
     * The toolbar, zone by zone (start, center, end), as groups of items:
     * ['type' => prev|next|today|title] , ['type' => 'view', 'name', 'label'],
     * or ['type' => 'button', 'button' => ToolbarButton].
     *
     * @return array<string, list<list<array{type: string, name?: string, label?: string|null, button?: ToolbarButton}>>>
     */
    public function getToolbar(): array
    {
        $options = $this->getOptions();
        $toolbar = [];
        foreach ($options->toolbar as $zone => $groups) {
            foreach ($groups as $group) {
                $items = [];
                foreach ($group as $item) {
                    if ($item === 'views') {
                        if (\count($options->enabledViews) > 1) {
                            foreach ($options->enabledViews as $name) {
                                $items[] = ['type' => 'view', 'name' => $name, 'label' => $options->variant($name)->label];
                            }
                        }
                    } elseif (isset($options->buttons[$item])) {
                        $items[] = ['type' => 'button', 'button' => $options->buttons[$item]];
                    } elseif (isset($options->variants[$item])) {
                        $items[] = ['type' => 'view', 'name' => $item, 'label' => $options->variant($item)->label];
                    } else {
                        $items[] = ['type' => $item];
                    }
                }
                if ($items !== []) {
                    $toolbar[$zone][] = $items;
                }
            }
        }

        return $toolbar;
    }

    /**
     * Step of drags and selections in minutes for the active view: whole days
     * where the view has no time axis (month, list, day-wide timeline slots).
     */
    public function getSnapMinutes(): int
    {
        $variant = $this->getContext()->variant;
        $daySlots = $variant->type === 'timeline' && $variant->param('slotMinutes', 60) >= 1440;

        return \in_array($variant->type, ['timeline', 'timeGrid'], true) && !$daySlots
            ? $this->getOptions()->snapMinutes
            : 1440;
    }

    /** ICU skeleton for event times, from the `timeFormat` option. */
    public function getTimeSkeleton(): string
    {
        return $this->getOptions()->timeFormat->skeleton();
    }

    private function activeView(): CalendarViewInterface
    {
        return $this->views->get($this->getOptions()->variant($this->getActiveView())->type);
    }

    private function step(int $step): void
    {
        $this->date = $this->activeView()->navigate($this->getContext(), $step)->format('Y-m-d');
    }

    /**
     * @param \Closure(\DateTimeZone): CalendarIntent $build
     */
    private function apply(\Closure $build): void
    {
        $context = $this->getContext();
        try {
            $intent = $build($context->timezone);
        } catch (\InvalidArgumentException) {
            $this->result = IntentResult::rejected(Rejection::Validation, 'intent.invalid', translationDomain: 'CalendarBundle');

            return;
        }

        // Fresh items of the period on screen: the dispatcher checks the
        // intent against them, not against what the browser claims.
        $query = new CalendarQuery($this->activeView()->range($context), $context->timezone);
        $this->result = $this->intents->dispatch($context->options, $intent, $this->collector->collect($context->options, $query));
        $this->models = [];

        if ($this->result->url !== null) {
            $this->dispatchBrowserEvent('cal:open', ['url' => $this->result->url, 'frame' => $this->result->frame]);
        }
    }

    /** A wall-clock time from the browser, in the display timezone; rejects anything else. */
    private function wallTime(string $value, \DateTimeZone $timezone, string $allDay): \DateTimeImmutable
    {
        $format = $allDay === '1' ? '!Y-m-d' : '!Y-m-d\TH:i';
        $time = \DateTimeImmutable::createFromFormat($format, $value, $timezone);
        if ($time === false || $time->format(substr($format, 1)) !== $value) {
            throw new \InvalidArgumentException(sprintf('Not a valid calendar time: "%s".', $value));
        }

        return $time;
    }

    private function nullable(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }

    /** The `date` prop as a day, or null when absent or not a real Y-m-d date. */
    private function parseDate(\DateTimeZone $timezone): ?\DateTimeImmutable
    {
        if ($this->date === null) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->date, $timezone);

        return $date !== false && $date->format('Y-m-d') === $this->date ? $date : null;
    }
}

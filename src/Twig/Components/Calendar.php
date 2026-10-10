<?php

namespace Fedale\CalendarBundle\Twig\Components;

use Fedale\CalendarBundle\Contract\CalendarViewInterface;
use Fedale\CalendarBundle\Contract\TimezoneResolverInterface;
use Fedale\CalendarBundle\Definition\CalendarRegistry;
use Fedale\CalendarBundle\Model\CalendarQuery;
use Fedale\CalendarBundle\Options\CalendarOptions;
use Fedale\CalendarBundle\Options\CalendarOptionsResolver;
use Fedale\CalendarBundle\Source\EventCollector;
use Fedale\CalendarBundle\Time\DateFormatter;
use Fedale\CalendarBundle\Time\Days;
use Fedale\CalendarBundle\View\ViewContext;
use Fedale\CalendarBundle\View\ViewRegistry;
use Psr\Clock\ClockInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The calendar: `<twig:Fedale:Calendar calendar="shifts" />`.
 *
 * Its whole state is the calendar id, the view and the focused date; view and
 * date live in the URL, so every period has a link and the browser history
 * works. Navigation is a round trip: the server re-renders the view, the
 * browser never builds the grid.
 */
#[AsLiveComponent(name: 'Fedale:Calendar', template: '@FedaleCalendar/components/Calendar.html.twig')]
final class Calendar
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $calendar = '';

    /** null = the calendar's default view. */
    #[LiveProp(writable: true, url: true)]
    public ?string $view = null;

    /** Focused day as Y-m-d; null = today. */
    #[LiveProp(writable: true, url: true)]
    public ?string $date = null;

    /** @var array<string, object> view model per calendar|view|date, built once per render */
    private array $models = [];

    public function __construct(
        private readonly CalendarRegistry $calendars,
        private readonly CalendarOptionsResolver $optionsResolver,
        private readonly ViewRegistry $views,
        private readonly EventCollector $collector,
        private readonly DateFormatter $formatter,
        private readonly TimezoneResolverInterface $timezoneResolver,
        private readonly ClockInterface $clock,
    ) {
    }

    #[LiveAction]
    public function previous(): void
    {
        $this->move(-1);
    }

    #[LiveAction]
    public function next(): void
    {
        $this->move(1);
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
        $today = Days::start($this->clock->now()->setTimezone($timezone));

        return new ViewContext(
            date: $this->parseDate($timezone) ?? $today,
            today: $today,
            options: $options,
            timezone: $timezone,
            locale: $options->locale ?? $this->formatter->defaultLocale(),
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
        $key = $this->calendar.'|'.$this->getActiveView().'|'.$context->date->format('Y-m-d');

        if (!isset($this->models[$key])) {
            $view = $this->activeView();
            $query = new CalendarQuery($view->range($context), $context->timezone);
            $this->models[$key] = $view->build($context, $this->collector->collect($context->options, $query));
        }

        return $this->models[$key];
    }

    public function getViewTemplate(): string
    {
        return $this->activeView()->template();
    }

    /** ICU skeleton for event times, from the `timeFormat` option. */
    public function getTimeSkeleton(): string
    {
        return $this->getOptions()->timeFormat->skeleton();
    }

    private function activeView(): CalendarViewInterface
    {
        return $this->views->get($this->getActiveView());
    }

    private function move(int $step): void
    {
        $this->date = $this->activeView()->navigate($this->getContext(), $step)->format('Y-m-d');
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

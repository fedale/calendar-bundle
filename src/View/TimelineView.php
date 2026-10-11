<?php

namespace Fedale\CalendarBundle\View;

use Fedale\CalendarBundle\Attribute\AsCalendarView;
use Fedale\CalendarBundle\Contract\CalendarViewInterface;
use Fedale\CalendarBundle\Model\CalendarResource;
use Fedale\CalendarBundle\Model\DateRange;
use Fedale\CalendarBundle\Options\ResourceColumn;
use Fedale\CalendarBundle\Options\ViewRange;
use Fedale\CalendarBundle\Options\ViewVariant;
use Fedale\CalendarBundle\Time\DateFormatter;
use Fedale\CalendarBundle\Time\Days;
use Fedale\CalendarBundle\View\Timeline\LaneLayout;
use Fedale\CalendarBundle\View\Timeline\TimeAxis;
use Fedale\CalendarBundle\View\Timeline\TimelineRow;
use Fedale\CalendarBundle\View\Timeline\TimelineSegment;
use Fedale\CalendarBundle\View\Timeline\TimelineViewModel;

/**
 * Resources down the side, time across. Parameters:
 *
 * - `range`: day, week or month (default day);
 * - `slotMinutes`: width of a slot, a divisor of 1440 (default 60);
 * - `dayStart` / `dayEnd`: the window of each day shown, HH:MM (default
 *   00:00 / 24:00);
 * - `scrollTime`: HH:MM the view scrolls to when today is not on screen
 *   (default 08:00).
 *
 * Resources come from the calendar's sources ({@see \Fedale\CalendarBundle\Contract\ResourceSourceInterface}),
 * nested by `parentId` and optionally grouped by the `display.resources.groupBy`
 * field. Events show on the rows of their `resourceIds`; background events
 * without resources span every row.
 */
#[AsCalendarView('timeline')]
final class TimelineView implements CalendarViewInterface
{
    private const PARAMS = ['range', 'slotMinutes', 'dayStart', 'dayEnd', 'scrollTime'];

    public function __construct(private readonly DateFormatter $formatter)
    {
    }

    public function validateVariant(ViewVariant $variant): void
    {
        $unknown = array_diff(array_keys($variant->params), self::PARAMS);
        if ($unknown !== []) {
            throw new \InvalidArgumentException(sprintf('Calendar view "%s" (timeline) takes only %s, got "%s".', $variant->name, implode(', ', self::PARAMS), implode('", "', $unknown)));
        }
        ViewRange::fromVariant($variant, ViewRange::Day);
        [$slot, $start, $end] = WindowParams::window($variant, 60);
        if (($end - $start) % $slot !== 0) {
            throw new \InvalidArgumentException(sprintf('Calendar view "%s": the window %s-%s must be a whole number of %d-minute slots.', $variant->name, $variant->param('dayStart', '00:00'), $variant->param('dayEnd', '24:00'), $slot));
        }
        WindowParams::minutes($variant, 'scrollTime', '08:00');
    }

    public function usesResources(ViewVariant $variant): bool
    {
        return true;
    }

    public function range(ViewContext $context): DateRange
    {
        return $this->viewRange($context)->range($context->date, $context->options->firstDay);
    }

    public function navigate(ViewContext $context, int $step): \DateTimeImmutable
    {
        return $this->viewRange($context)->navigate($context->date, $step);
    }

    public function title(ViewContext $context): string
    {
        return $this->viewRange($context)->title($this->formatter, $context->date, $context->options->firstDay, $context->locale);
    }

    public function build(ViewContext $context, array $items, array $resources = []): TimelineViewModel
    {
        $options = $context->options;
        $range = $this->range($context);
        [$slotMinutes, $dayStart, $dayEnd] = WindowParams::window($context->variant, 60);

        $days = [];
        for ($day = $range->start; $day < $range->end; $day = Days::add($day, 1)) {
            if (!$options->isHidden($day)) {
                $days[] = $day;
            }
        }
        $axis = new TimeAxis($days, $dayStart, $dayEnd);

        // Place every item once; rows then pick theirs by resource id.
        $foreground = [];
        $backgroundByResource = [];
        $bands = [];
        foreach ($items as $item) {
            $span = $axis->span($item->start, $item->end);
            if ($span === null) {
                continue;
            }
            $before = $item->start < $range->start;
            $after = $item->end > $range->end;
            if (!$item->isBackground()) {
                foreach ($item->event->resourceIds as $resourceId) {
                    $foreground[$resourceId][] = [$item, $span[0], $span[1], $before, $after];
                }
                continue;
            }
            $band = new TimelineSegment($item, $span[0], $span[1], 0, $before, $after);
            if ($item->event->resourceIds === []) {
                $bands[] = $band;
            }
            foreach ($item->event->resourceIds as $resourceId) {
                $backgroundByResource[$resourceId][] = $band;
            }
        }

        $rows = [];
        $collapsed = array_flip($context->collapsed);
        $tree = ResourceTree::of($resources);
        $addResource = function (CalendarResource $resource, int $depth) use (&$addResource, &$rows, $options, $foreground, $backgroundByResource, $collapsed, $tree): void {
            $key = 'r:'.$resource->id;
            $hasChildren = $tree->hasChildren($resource);
            $isCollapsed = $hasChildren && isset($collapsed[$key]);
            $rows[] = new TimelineRow(
                key: $key,
                isGroup: false,
                label: $resource->title,
                resource: $resource,
                cells: array_map(fn(ResourceColumn $column) => $column->value($resource), $options->resourceColumns),
                depth: $depth,
                hasChildren: $hasChildren,
                collapsed: $isCollapsed,
                layout: LaneLayout::layout($foreground[$resource->id] ?? [], $options->rowMaxEvents),
                bands: $backgroundByResource[$resource->id] ?? [],
            );
            if (!$isCollapsed) {
                foreach ($tree->children[$resource->id] ?? [] as $child) {
                    $addResource($child, $depth + 1);
                }
            }
        };

        if ($options->resourceGroupBy === null) {
            foreach ($tree->roots as $root) {
                $addResource($root, 0);
            }
        } else {
            $groupColumn = new ResourceColumn($options->resourceGroupBy);
            $grouped = [];
            foreach ($tree->roots as $root) {
                $grouped[$groupColumn->value($root)][] = $root;
            }
            uksort($grouped, fn($a, $b) => strnatcasecmp((string) $a, (string) $b));
            foreach ($grouped as $value => $members) {
                $key = 'g:'.$value;
                $rows[] = new TimelineRow($key, true, (string) $value, null, [], 0, true, isset($collapsed[$key]));
                if (!isset($collapsed[$key])) {
                    foreach ($members as $member) {
                        $addResource($member, 1);
                    }
                }
            }
        }

        // Closed hours shade every row; only day-wide slots are too coarse to show them.
        $closed = [];
        if ($options->businessHours->isDefined() && $slotMinutes < 1440) {
            foreach ($days as $day) {
                foreach ($options->businessHours->closed($day, $dayStart, $dayEnd) as [$from, $to]) {
                    $span = $axis->span($day->setTime(intdiv($from, 60), $from % 60), $to === 1440 ? Days::add($day, 1) : $day->setTime(intdiv($to, 60), $to % 60));
                    if ($span !== null) {
                        $closed[] = $span;
                    }
                }
            }
        }

        $now = $axis->exactPosition($context->now->setTimezone($context->timezone));
        $scrollTo = $now ?? ($slotMinutes < 1440 && $days !== []
            ? $axis->position($days[0]->setTime(...$this->hoursAndMinutes(WindowParams::minutes($context->variant, 'scrollTime', '08:00'))))
            : 0.0);

        return new TimelineViewModel(
            groups: $axis->groups($this->viewRange($context) === ViewRange::Month),
            slots: $axis->slots($slotMinutes),
            daySlots: $slotMinutes >= 1440,
            rows: $rows,
            bands: $bands,
            closed: $closed,
            columns: $options->resourceColumns,
            areaLabel: $options->resourceLabel,
            areaWidth: $options->resourceAreaWidth,
            now: $now,
            windows: $axis->windows(),
            scrollTo: $scrollTo,
            axis: $axis->describe(),
        );
    }

    public function template(): string
    {
        return '@FedaleCalendar/view/timeline.html.twig';
    }

    private function viewRange(ViewContext $context): ViewRange
    {
        return ViewRange::fromVariant($context->variant, ViewRange::Day);
    }

    /**
     * @return array{int, int}
     */
    private function hoursAndMinutes(int $minutes): array
    {
        return [intdiv($minutes, 60), $minutes % 60];
    }
}

<?php

namespace Fedale\CalendarBundle\View;

use Fedale\CalendarBundle\Attribute\AsCalendarView;
use Fedale\CalendarBundle\Contract\CalendarViewInterface;
use Fedale\CalendarBundle\Model\CalendarResource;
use Fedale\CalendarBundle\Model\DateRange;
use Fedale\CalendarBundle\Options\ViewRange;
use Fedale\CalendarBundle\Options\ViewVariant;
use Fedale\CalendarBundle\Source\CalendarItem;
use Fedale\CalendarBundle\Time\DateFormatter;
use Fedale\CalendarBundle\Time\Days;
use Fedale\CalendarBundle\View\Layout\RowLayout;
use Fedale\CalendarBundle\View\Layout\RowSegment;
use Fedale\CalendarBundle\View\Layout\WeekRowLayout;
use Fedale\CalendarBundle\View\TimeGrid\ColumnLayout;
use Fedale\CalendarBundle\View\TimeGrid\GridColumn;
use Fedale\CalendarBundle\View\TimeGrid\GridEvent;
use Fedale\CalendarBundle\View\TimeGrid\TimeGridViewModel;
use Fedale\CalendarBundle\View\Timeline\TimeAxis;

/**
 * Days as columns, hours down the side, with an all-day row on top. With
 * `resources`, every resource gets its own columns: resources above, their
 * days below. Parameters:
 *
 * - `range`: day or week (default week);
 * - `slotMinutes`: height of a slot, a divisor of 1440 (default 30);
 * - `dayStart` / `dayEnd`: the window of each day shown, HH:MM (default
 *   00:00 / 24:00);
 * - `scrollTime`: HH:MM the view scrolls to when "now" is not on screen
 *   (default 08:00);
 * - `resources`: one column per resource and day (default false);
 * - `allDaySlot`: show the all-day row (default true);
 * - `allDayMaxEvents`: lanes of the all-day row; the rest goes into "+N
 *   more" (default 3).
 *
 * Timed events crossing midnight are split over the columns of the days they
 * cover; all-day events go to the all-day row.
 */
#[AsCalendarView('timeGrid')]
final class TimeGridView implements CalendarViewInterface
{
    private const PARAMS = ['range', 'slotMinutes', 'dayStart', 'dayEnd', 'scrollTime', 'resources', 'allDaySlot', 'allDayMaxEvents'];

    public function __construct(private readonly DateFormatter $formatter)
    {
    }

    public function validateVariant(ViewVariant $variant): void
    {
        $unknown = array_diff(array_keys($variant->params), self::PARAMS);
        if ($unknown !== []) {
            throw new \InvalidArgumentException(sprintf('Calendar view "%s" (timeGrid) takes only %s, got "%s".', $variant->name, implode(', ', self::PARAMS), implode('", "', $unknown)));
        }
        if ($this->viewRange($variant) === ViewRange::Month) {
            throw new \InvalidArgumentException(sprintf('Calendar view "%s" (timeGrid) takes a "range" of day or week.', $variant->name));
        }
        [$slot, $start, $end] = WindowParams::window($variant, 30);
        if (($end - $start) % $slot !== 0) {
            throw new \InvalidArgumentException(sprintf('Calendar view "%s": the window %s-%s must be a whole number of %d-minute slots.', $variant->name, $variant->param('dayStart', '00:00'), $variant->param('dayEnd', '24:00'), $slot));
        }
        WindowParams::minutes($variant, 'scrollTime', '08:00');
        foreach (['resources', 'allDaySlot'] as $flag) {
            if (!\is_bool($variant->param($flag, false))) {
                throw new \InvalidArgumentException(sprintf('Calendar view "%s" takes "%s" as true or false.', $variant->name, $flag));
            }
        }
        $max = $variant->param('allDayMaxEvents', 3);
        if (!\is_int($max) || $max < 1) {
            throw new \InvalidArgumentException(sprintf('Calendar view "%s" takes "allDayMaxEvents" as a positive integer.', $variant->name));
        }
    }

    public function usesResources(ViewVariant $variant): bool
    {
        return $variant->param('resources', false) === true;
    }

    public function range(ViewContext $context): DateRange
    {
        return $this->viewRange($context->variant)->range($context->date, $context->options->firstDay);
    }

    public function navigate(ViewContext $context, int $step): \DateTimeImmutable
    {
        return $this->viewRange($context->variant)->navigate($context->date, $step);
    }

    public function title(ViewContext $context): string
    {
        return $this->viewRange($context->variant)->title($this->formatter, $context->date, $context->options->firstDay, $context->locale);
    }

    public function build(ViewContext $context, array $items, array $resources = []): TimeGridViewModel
    {
        $options = $context->options;
        $variant = $context->variant;
        [$slotMinutes, $dayStart, $dayEnd] = WindowParams::window($variant, 30);
        $range = $this->range($context);

        $days = [];
        for ($day = $range->start; $day < $range->end; $day = Days::add($day, 1)) {
            if (!$options->isHidden($day)) {
                $days[] = $day;
            }
        }

        // One column per day, or per resource and day (resources above).
        $byResource = $this->usesResources($variant);
        $columnResources = $byResource ? ResourceTree::of($resources)->flatten() : [null];

        $timed = [];
        $allDay = [];
        $backgrounds = [];
        foreach ($items as $item) {
            if ($item->isBackground()) {
                $backgrounds[] = $item;
            } elseif ($item->isAllDay()) {
                $allDay[] = $item;
            } else {
                $timed[] = $item;
            }
        }

        $now = $context->now->setTimezone($context->timezone);
        $windowMinutes = $dayEnd - $dayStart;
        $columns = [];
        $windows = [];
        foreach ($columnResources as $resource) {
            foreach ($days as $day) {
                $axis = new TimeAxis([$day], $dayStart, $dayEnd);
                $windowStart = $day->setTime(intdiv($dayStart, 60), $dayStart % 60);
                $windowEnd = $dayEnd === 1440 ? Days::add($day, 1) : $day->setTime(intdiv($dayEnd, 60), $dayEnd % 60);

                $placed = [];
                foreach ($timed as $item) {
                    if (!$this->belongs($item, $resource) || $item->end <= $windowStart || $item->start >= $windowEnd) {
                        continue;
                    }
                    $span = $axis->span($item->start, $item->end);
                    if ($span !== null) {
                        $placed[] = [$item, $span[0], $span[1], $item->start < $windowStart, $item->end > $windowEnd];
                    }
                }

                $bands = [];
                foreach ($backgrounds as $item) {
                    $belongs = $item->event->resourceIds === [] || $this->belongs($item, $resource);
                    if (!$belongs || $item->end <= $windowStart || $item->start >= $windowEnd) {
                        continue;
                    }
                    $span = $axis->span($item->start, $item->end);
                    if ($span !== null) {
                        $bands[] = new GridEvent($item, $span[0], $span[1]);
                    }
                }

                $closed = array_map(
                    fn(array $interval) => [(float) ($interval[0] - $dayStart) / $windowMinutes, (float) ($interval[1] - $interval[0]) / $windowMinutes],
                    $options->businessHours->isDefined() ? $options->businessHours->closed($day, $dayStart, $dayEnd) : [],
                );

                $isToday = Days::same($day, $context->today);
                $nowPosition = $isToday ? $axis->exactPosition($now) : null;
                if ($isToday && $windows === []) {
                    $windows[] = [$windowStart->getTimestamp() * 1000, $windowEnd->getTimestamp() * 1000, 0.0, 1.0];
                }

                $columns[] = new GridColumn(
                    day: $day,
                    resource: $resource,
                    isToday: $isToday,
                    isWeekend: (int) $day->format('N') >= 6,
                    events: ColumnLayout::layout($placed),
                    bands: $bands,
                    closed: $closed,
                    now: $nowPosition,
                );
            }
        }

        [$allDayLayout, $allDayItems] = $variant->param('allDaySlot', true)
            ? $this->allDayRow($days, $columnResources, $allDay, $variant->param('allDayMaxEvents', 3))
            : [null, []];

        $gutter = (new TimeAxis([$days[0] ?? $range->start], $dayStart, $dayEnd))->slots($slotMinutes);
        $nowInView = array_filter($columns, fn(GridColumn $column) => $column->now !== null);
        $scrollTime = WindowParams::minutes($variant, 'scrollTime', '08:00');
        $scrollTo = $nowInView !== []
            ? reset($nowInView)->now
            : max(0.0, min(1.0, ($scrollTime - $dayStart) / $windowMinutes));

        return new TimeGridViewModel(
            columns: $columns,
            resourceHeaders: $byResource ? array_map(fn(CalendarResource $resource) => ['resource' => $resource, 'span' => \count($days)], $columnResources) : [],
            allDay: $allDayLayout,
            allDayItems: $allDayItems,
            slots: $gutter,
            windows: $windows,
            scrollTo: (float) $scrollTo,
        );
    }

    public function template(): string
    {
        return '@FedaleCalendar/view/time_grid.html.twig';
    }

    /**
     * The all-day row over every column. With resources, each resource's days
     * are contiguous columns, so each resource is laid out on its own and its
     * segments shifted to its first column.
     *
     * @param list<\DateTimeImmutable>    $days
     * @param list<CalendarResource|null> $columnResources
     * @param list<CalendarItem>          $items
     *
     * @return array{RowLayout, array<int, list<CalendarItem>>}
     */
    private function allDayRow(array $days, array $columnResources, array $items, int $maxLanes): array
    {
        $segments = [];
        $overflow = [];
        $overflowItems = [];
        $laneCount = 0;
        foreach ($columnResources as $index => $resource) {
            $offset = $index * \count($days);
            $own = array_values(array_filter($items, fn(CalendarItem $item) => $this->belongs($item, $resource)));
            $layout = WeekRowLayout::layout($days, $own, $maxLanes);
            foreach ($layout->segments as $segment) {
                $segments[] = new RowSegment($segment->item, $segment->start + $offset, $segment->span, $segment->lane, $segment->continuesBefore, $segment->continuesAfter);
            }
            foreach ($layout->overflow as $column => $hidden) {
                $overflow[$column + $offset] = $hidden;
                $overflowItems[$column + $offset] = array_values(array_filter($own, fn(CalendarItem $item) => $item->coversDay($days[$column])));
            }
            $laneCount = max($laneCount, $layout->laneCount);
        }

        return [new RowLayout($segments, $overflow, $laneCount), $overflowItems];
    }

    private function belongs(CalendarItem $item, ?CalendarResource $resource): bool
    {
        return $resource === null || \in_array($resource->id, $item->event->resourceIds, true);
    }

    private function viewRange(ViewVariant $variant): ViewRange
    {
        return ViewRange::fromVariant($variant, ViewRange::Week);
    }
}

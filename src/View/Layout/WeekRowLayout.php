<?php

namespace Fedale\CalendarBundle\View\Layout;

use Fedale\CalendarBundle\Source\CalendarItem;

/**
 * Lays items out as horizontal bars over a row of day columns (a week of the
 * month view): each item takes the lowest lane free on every column it
 * covers, so a multi-day event stays one straight bar.
 *
 * The columns are the visible days only: an item covering a hidden day just
 * spans across it, and an item covering only hidden days is left out.
 */
final class WeekRowLayout
{
    /**
     * @param list<\DateTimeImmutable> $days     the visible days of the row, in order
     * @param list<CalendarItem>       $items    in display order ({@see CalendarItem::compare()})
     * @param int|null                 $maxLanes lanes available; items needing a higher lane overflow. null = unlimited
     */
    public static function layout(array $days, array $items, ?int $maxLanes = null): RowLayout
    {
        $dates = array_map(fn(\DateTimeImmutable $day) => $day->format('Y-m-d'), $days);

        /** @var array<int, array<int, true>> $occupied lane => column => taken */
        $occupied = [];
        $segments = [];
        $overflow = [];
        $laneCount = 0;

        foreach ($items as $item) {
            $from = $item->firstDate;
            $to = $item->lastDate;
            if ($to < $dates[0] || $from > $dates[\count($dates) - 1]) {
                continue;
            }

            $columns = [];
            foreach ($dates as $column => $date) {
                if ($date >= $from && $date <= $to) {
                    $columns[] = $column;
                }
            }
            if ($columns === []) {
                continue;
            }
            $start = $columns[0];
            $end = $columns[\count($columns) - 1];

            $lane = 0;
            while (self::isTaken($occupied[$lane] ?? [], $start, $end)) {
                ++$lane;
            }

            if ($maxLanes !== null && $lane >= $maxLanes) {
                foreach ($columns as $column) {
                    $overflow[$column][] = $item;
                }
                continue;
            }

            for ($column = $start; $column <= $end; ++$column) {
                $occupied[$lane][$column] = true;
            }
            $laneCount = max($laneCount, $lane + 1);
            $segments[] = new RowSegment(
                item: $item,
                start: $start,
                span: $end - $start + 1,
                lane: $lane,
                // Also true when the item starts or ends on a hidden day of this row.
                continuesBefore: $from < $dates[$start],
                continuesAfter: $to > $dates[$end],
            );
        }

        return new RowLayout($segments, $overflow, $laneCount);
    }

    /**
     * @param array<int, true> $lane
     */
    private static function isTaken(array $lane, int $start, int $end): bool
    {
        for ($column = $start; $column <= $end; ++$column) {
            if (isset($lane[$column])) {
                return true;
            }
        }

        return false;
    }
}

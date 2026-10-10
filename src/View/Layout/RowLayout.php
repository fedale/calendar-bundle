<?php

namespace Fedale\CalendarBundle\View\Layout;

use Fedale\CalendarBundle\Source\CalendarItem;

/**
 * Result of {@see WeekRowLayout}: the segments that fit in the lanes, and per
 * column the items that did not fit (the "+N more" of that day).
 */
final readonly class RowLayout
{
    /**
     * @param list<RowSegment>               $segments
     * @param array<int, list<CalendarItem>> $overflow  column => items not shown in that column
     * @param int                            $laneCount lanes actually used by $segments
     */
    public function __construct(
        public array $segments,
        public array $overflow,
        public int $laneCount,
    ) {
    }

    public function overflowCount(int $column): int
    {
        return \count($this->overflow[$column] ?? []);
    }
}

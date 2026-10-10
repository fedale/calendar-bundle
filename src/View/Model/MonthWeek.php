<?php

namespace Fedale\CalendarBundle\View\Model;

use Fedale\CalendarBundle\View\Layout\RowSegment;

final readonly class MonthWeek
{
    /**
     * @param list<MonthDay>   $days        the visible days
     * @param list<RowSegment> $segments    foreground bars, one lane each
     * @param list<RowSegment> $backgrounds background bands (their lane is meaningless)
     */
    public function __construct(
        public int $number,
        public array $days,
        public array $segments,
        public array $backgrounds,
        public int $laneCount,
        public bool $hasOverflow,
    ) {
    }
}

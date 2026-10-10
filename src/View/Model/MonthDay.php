<?php

namespace Fedale\CalendarBundle\View\Model;

use Fedale\CalendarBundle\Source\CalendarItem;

final readonly class MonthDay
{
    /**
     * @param list<CalendarItem> $items every foreground item of the day, filled only when some overflow (for the "+N more" popover)
     */
    public function __construct(
        public \DateTimeImmutable $date,
        public int $column,
        public bool $inMonth,
        public bool $isToday,
        public bool $isWeekend,
        public int $overflowCount,
        public array $items,
    ) {
    }
}

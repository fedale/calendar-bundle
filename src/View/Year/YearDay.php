<?php

namespace Fedale\CalendarBundle\View\Year;

/**
 * One day of a mini month: how many events cover it, and how dense that is
 * on a 0-4 scale relative to the busiest day of the year.
 */
final readonly class YearDay
{
    public function __construct(
        public \DateTimeImmutable $date,
        public bool $inMonth,
        public bool $isToday,
        public int $count,
        public int $level,
    ) {
    }
}

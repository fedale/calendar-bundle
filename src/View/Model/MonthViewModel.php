<?php

namespace Fedale\CalendarBundle\View\Model;

final readonly class MonthViewModel
{
    /**
     * @param list<\DateTimeImmutable> $weekdays one day per visible column, for the headers
     * @param list<MonthWeek>          $weeks
     */
    public function __construct(
        public array $weekdays,
        public array $weeks,
        public bool $weekNumbers,
    ) {
    }
}

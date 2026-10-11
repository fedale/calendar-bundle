<?php

namespace Fedale\CalendarBundle\View\Year;

final readonly class YearMonth
{
    /**
     * @param list<list<YearDay>> $weeks the visible days, week by week
     */
    public function __construct(
        public \DateTimeImmutable $start,
        public array $weeks,
    ) {
    }
}

<?php

namespace Fedale\CalendarBundle\View\Year;

final readonly class YearViewModel
{
    /**
     * @param list<\DateTimeImmutable> $weekdays one per visible column, for the headers
     * @param list<YearMonth>          $months
     * @param string|null              $dayView  the variant a day opens in; null = days are not links
     */
    public function __construct(
        public array $weekdays,
        public array $months,
        public ?string $dayView,
    ) {
    }
}

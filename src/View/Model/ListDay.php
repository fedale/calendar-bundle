<?php

namespace Fedale\CalendarBundle\View\Model;

final readonly class ListDay
{
    /**
     * @param list<ListEntry> $entries
     */
    public function __construct(
        public \DateTimeImmutable $date,
        public bool $isToday,
        public array $entries,
    ) {
    }
}

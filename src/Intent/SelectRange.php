<?php

namespace Fedale\CalendarBundle\Intent;

/**
 * The user selected an empty range, typically to create something there.
 * For all-day selections, start and end are midnights and end is exclusive.
 */
final readonly class SelectRange extends CalendarIntent
{
    public function __construct(
        string $calendar,
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
        public bool $allDay = false,
        public ?string $resourceId = null,
    ) {
        parent::__construct($calendar);
    }
}

<?php

namespace Fedale\CalendarBundle\Intent;

/**
 * The user dragged the end of an event: same start, new end.
 */
final readonly class ResizeEvent extends EventIntent
{
    public function __construct(
        string $calendar,
        string $source,
        string $eventId,
        ?string $version,
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
    ) {
        parent::__construct($calendar, $source, $eventId, $version);
    }
}

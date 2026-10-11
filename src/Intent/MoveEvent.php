<?php

namespace Fedale\CalendarBundle\Intent;

/**
 * The user dragged an event to a new time, and possibly to another resource
 * (a timeline row or a resource column). The duration is kept.
 */
final readonly class MoveEvent extends EventIntent
{
    public function __construct(
        string $calendar,
        string $source,
        string $eventId,
        ?string $version,
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
        /** The resource the event was dragged from; null outside resource views. */
        public ?string $fromResourceId = null,
        /** The resource it was dropped on; equal to fromResourceId when it stayed on its row. */
        public ?string $toResourceId = null,
    ) {
        parent::__construct($calendar, $source, $eventId, $version);
    }

    public function changesResource(): bool
    {
        return $this->fromResourceId !== $this->toResourceId;
    }
}

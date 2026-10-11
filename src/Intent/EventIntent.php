<?php

namespace Fedale\CalendarBundle\Intent;

/**
 * An intent about one existing event, identified by its source and id.
 * `version` is the event's version when the user acted on it, so the
 * handler can refuse to apply a change over a newer state.
 */
abstract readonly class EventIntent extends CalendarIntent
{
    public function __construct(
        string $calendar,
        public string $source,
        public string $eventId,
        public ?string $version = null,
    ) {
        parent::__construct($calendar);
    }
}

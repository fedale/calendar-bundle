<?php

namespace Fedale\CalendarBundle\Model;

/**
 * Which interactions the UI enables on an event. The source computes them,
 * typically from the user's permissions and the domain rules; the calendar
 * only offers what is enabled here and never guesses.
 */
final readonly class EventInteractions
{
    public function __construct(
        public bool $movable = false,
        public bool $resizable = false,
        public bool $clickable = false,
        public bool $deletable = false,
    ) {
    }
}

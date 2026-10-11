<?php

namespace Fedale\CalendarBundle\Intent;

/**
 * Something the user asked the calendar to do. The calendar never performs
 * it: it checks what it can (the event is on screen, the interaction is
 * enabled, the version is current) and hands the intent to the source's
 * {@see \Fedale\CalendarBundle\Contract\IntentHandlerInterface}, where the
 * domain decides.
 *
 * Times are wall-clock times in the display timezone of the calendar.
 */
abstract readonly class CalendarIntent
{
    public function __construct(
        /** Id of the calendar the intent comes from. */
        public string $calendar,
    ) {
    }
}

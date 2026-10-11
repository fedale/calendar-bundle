<?php

namespace Fedale\CalendarBundle\Contract;

use Fedale\CalendarBundle\Intent\CalendarIntent;
use Fedale\CalendarBundle\Intent\IntentResult;

/**
 * Applies the user's intents to the domain. Implemented by an event source
 * (the same class as its {@see EventSourceInterface}, or not): a source
 * without it is read-only.
 *
 * This is where the domain rules run (overlaps, capacity, permissions,
 * concurrency), whatever the UI allowed: the calendar's own checks are a
 * first filter, never the authority.
 */
interface IntentHandlerInterface
{
    public function handle(CalendarIntent $intent): IntentResult;
}

<?php

namespace Fedale\CalendarBundle\Contract;

use Fedale\CalendarBundle\Model\CalendarEvent;

/**
 * Optional companion of {@see EventSourceInterface}: finds one event by id,
 * without a date range.
 *
 * The UI never needs it: an intent from the page concerns an event the
 * calendar has just fetched for the period on screen. Clients without a
 * page (the API Platform bridge) do: with it, their intents get the same
 * checks as the UI's (interaction enabled, version current) before the
 * handler; without it, only the handler decides.
 */
interface EventFinderInterface
{
    /** The event as the current user may see it; null when it does not exist or is hidden. */
    public function findEvent(string $id): ?CalendarEvent;
}

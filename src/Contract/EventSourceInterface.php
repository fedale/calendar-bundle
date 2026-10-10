<?php

namespace Fedale\CalendarBundle\Contract;

use Fedale\CalendarBundle\Model\CalendarEvent;
use Fedale\CalendarBundle\Model\CalendarQuery;

/**
 * Feeds a calendar with events. Implemented by the application, usually as an
 * adapter over a domain module, and registered with
 * {@see \Fedale\CalendarBundle\Attribute\AsCalendarSource}.
 *
 * The source owns everything domain-related: it filters what the current user
 * may see, sets each event's visibility and interactions, and expands
 * recurrences within the requested range.
 */
interface EventSourceInterface
{
    /**
     * Events overlapping $query->range. Returning events outside of it is
     * harmless (they are dropped), but costs a needless fetch.
     *
     * @return iterable<CalendarEvent>
     */
    public function getEvents(CalendarQuery $query): iterable;
}

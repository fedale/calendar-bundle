<?php

namespace Fedale\CalendarBundle\Model;

/**
 * How much of an event the current user may see, decided by the source.
 */
enum EventVisibility: string
{
    case Full = 'full';
    /** Only the time slot is shown, under a generic "Busy" label; title, url and payload are withheld. */
    case BusyOnly = 'busy_only';
    /** The event is not shown at all. Sources should rather not return it; the calendar drops it defensively. */
    case Hidden = 'hidden';
}

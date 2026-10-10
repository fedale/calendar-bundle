<?php

namespace Fedale\CalendarBundle\View\Model;

/** How an item appears on one day of the list view. */
enum ListEntryKind: string
{
    case AllDay = 'all_day';
    /** Starts and ends on this day. */
    case Timed = 'timed';
    /** Starts on this day, ends on a later one. */
    case Starts = 'starts';
    /** Started earlier, ends on this day. */
    case Ends = 'ends';
    /** Started earlier, ends later: covers the whole day. */
    case Continues = 'continues';
}

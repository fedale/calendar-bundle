<?php

namespace Fedale\CalendarBundle\Options;

/** The period one page of the list view covers. */
enum ListRange: string
{
    case Week = 'week';
    case Month = 'month';
}

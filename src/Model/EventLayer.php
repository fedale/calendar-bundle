<?php

namespace Fedale\CalendarBundle\Model;

/**
 * How an event is drawn: a regular block, or a band behind the regular ones
 * (availability, rest periods, closures).
 */
enum EventLayer: string
{
    case Foreground = 'foreground';
    case Background = 'background';
}

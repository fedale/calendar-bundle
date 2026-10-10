<?php

namespace Fedale\CalendarBundle\Attribute;

/**
 * Registers a {@see \Fedale\CalendarBundle\Contract\CalendarViewInterface}
 * under a name, the one a calendar lists in `display.views.enabled`. Reusing a
 * built-in name replaces the built-in view.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsCalendarView
{
    public function __construct(public readonly string $name)
    {
    }
}

<?php

namespace Fedale\CalendarBundle\Attribute;

/**
 * Registers an {@see \Fedale\CalendarBundle\Contract\EventSourceInterface}
 * under a key, the one a calendar lists in `integration.sources`.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsCalendarSource
{
    public function __construct(public readonly string $key)
    {
    }
}

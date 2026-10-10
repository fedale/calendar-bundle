<?php

namespace Fedale\CalendarBundle\Attribute;

/**
 * Registers a {@see \Fedale\CalendarBundle\Contract\CalendarDefinitionInterface}
 * under an id, the one passed to the component: `<twig:Fedale:Calendar calendar="shifts" />`.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsCalendar
{
    public function __construct(public readonly string $id)
    {
    }
}

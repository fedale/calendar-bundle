<?php

namespace Fedale\CalendarBundle\Definition;

use Fedale\CalendarBundle\Contract\CalendarDefinitionInterface;

/**
 * Convenience base for {@see CalendarDefinitionInterface}: no label, no
 * overrides. A concrete calendar usually overrides viewConfig() only.
 */
abstract class AbstractCalendar implements CalendarDefinitionInterface
{
    public function label(): ?string
    {
        return null;
    }

    public function viewConfig(): array
    {
        return [];
    }
}

<?php

namespace Fedale\CalendarBundle\UiSettings;

/**
 * What the settings dialog needs to know about one calendar without
 * rendering it: its id, its label, and its option tree as configured in
 * code (bundle defaults, YAML and viewConfig(), before the UI settings).
 */
final readonly class CalendarDescriptor
{
    /**
     * @param array<string, mixed> $tree
     */
    public function __construct(
        public string $id,
        public ?string $label,
        public array $tree,
    ) {
    }

    /** @return list<string> */
    public function enabledViews(): array
    {
        return array_values((array) ($this->tree['display']['views']['enabled'] ?? []));
    }

    /** Whether one of the enabled views is of the given type (month, timeGrid, ...). */
    public function hasViewType(string $type): bool
    {
        foreach ($this->enabledViews() as $name) {
            if (($this->tree['display']['views']['variants'][$name]['type'] ?? null) === $type) {
                return true;
            }
        }

        return false;
    }
}

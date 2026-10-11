<?php

namespace Fedale\CalendarBundle\UiSettings;

use Fedale\CalendarBundle\Options\CalendarOptions;

/**
 * The view a calendar opens on (`display.views.default`). Offered for the
 * calendars with more than one view; a value a calendar does not enable is
 * skipped for that calendar.
 */
final class DefaultViewUiSetting extends AbstractUiSetting
{
    public function key(): string
    {
        return 'default_view';
    }

    public function label(): string
    {
        return 'ui_settings.default_view.label';
    }

    protected function optionPath(): string
    {
        return 'display.views.default';
    }

    public function supportsCalendar(CalendarDescriptor $calendar): bool
    {
        return \count($calendar->enabledViews()) > 1;
    }

    public function choicesFor(?CalendarDescriptor $calendar): array
    {
        $names = $calendar?->enabledViews() ?? array_keys(CalendarOptions::DEFAULTS['display']['views']['variants']);
        $choices = [];
        foreach ($names as $name) {
            $choices['view.'.$name] = $name;
        }

        return $choices;
    }

    public function isApplicable(mixed $value, mixed $context): bool
    {
        return \is_string($value) && \in_array($value, (array) ($context['display']['views']['enabled'] ?? []), true);
    }
}

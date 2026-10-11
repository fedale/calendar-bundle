<?php

namespace Fedale\CalendarBundle\UiSettings;

/**
 * Whether Saturday and Sunday are shown. Edits `display.hiddenDays` rather
 * than replacing it, so other days a calendar hides stay hidden.
 */
final class WeekendsUiSetting extends AbstractUiSetting
{
    private const WEEKEND = [6, 7];

    public function key(): string
    {
        return 'weekends';
    }

    public function label(): string
    {
        return 'ui_settings.weekends.label';
    }

    protected function optionPath(): string
    {
        return 'display.hiddenDays';
    }

    public function choicesFor(?CalendarDescriptor $calendar): array
    {
        return ['ui_settings.weekends.show' => 'show', 'ui_settings.weekends.hide' => 'hide'];
    }

    public function apply(array $tree, mixed $value): array
    {
        $hidden = array_values(array_diff((array) ($tree['display']['hiddenDays'] ?? []), self::WEEKEND));
        if ($value === 'hide') {
            $hidden = [...$hidden, ...self::WEEKEND];
        }

        return parent::apply($tree, $hidden);
    }

    public function isApplicable(mixed $value, mixed $context): bool
    {
        if (!\in_array($value, ['show', 'hide'], true)) {
            return false;
        }
        // Hiding the weekend must leave at least one day to show.
        $hidden = array_unique([...(array) ($context['display']['hiddenDays'] ?? []), ...($value === 'hide' ? self::WEEKEND : [])]);

        return \count($hidden) < 7;
    }
}

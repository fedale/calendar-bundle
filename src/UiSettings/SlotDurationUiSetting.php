<?php

namespace Fedale\CalendarBundle\UiSettings;

/**
 * The length of a time-grid slot, set on every time-grid variant of the
 * calendar (the `slotMinutes` parameter). Skipped for a calendar whose day
 * window is not a whole number of such slots.
 */
final class SlotDurationUiSetting extends AbstractUiSetting
{
    private const MINUTES = [15, 30, 60];

    public function key(): string
    {
        return 'slot_minutes';
    }

    public function label(): string
    {
        return 'ui_settings.slot_minutes.label';
    }

    protected function optionPath(): string
    {
        return 'display.views.variants';
    }

    public function supportsCalendar(CalendarDescriptor $calendar): bool
    {
        return $calendar->hasViewType('timeGrid');
    }

    public function choicesFor(?CalendarDescriptor $calendar): array
    {
        $choices = [];
        foreach (self::MINUTES as $minutes) {
            $choices['ui_settings.slot_minutes.'.$minutes] = $minutes;
        }

        return $choices;
    }

    public function apply(array $tree, mixed $value): array
    {
        foreach ($this->timeGridVariants($tree) as $name) {
            $tree['display']['views']['variants'][$name]['slotMinutes'] = $value;
        }

        return $tree;
    }

    public function isApplicable(mixed $value, mixed $context): bool
    {
        if (!\in_array($value, self::MINUTES, true) || !\is_array($context)) {
            return false;
        }
        foreach ($this->timeGridVariants($context) as $name) {
            $variant = $context['display']['views']['variants'][$name];
            $window = self::minutes($variant['dayEnd'] ?? '24:00') - self::minutes($variant['dayStart'] ?? '00:00');
            if ($window % $value !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $tree
     *
     * @return list<string>
     */
    private function timeGridVariants(array $tree): array
    {
        $names = [];
        foreach ((array) ($tree['display']['views']['variants'] ?? []) as $name => $variant) {
            if (\is_array($variant) && ($variant['type'] ?? null) === 'timeGrid') {
                $names[] = (string) $name;
            }
        }

        return $names;
    }

    private static function minutes(mixed $time): int
    {
        return \is_string($time) && preg_match('/^(\d{2}):(\d{2})$/', $time, $match) === 1 ? (int) $match[1] * 60 + (int) $match[2] : 0;
    }
}

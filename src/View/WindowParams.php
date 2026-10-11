<?php

namespace Fedale\CalendarBundle\View;

use Fedale\CalendarBundle\Options\ViewVariant;

/**
 * The day-window parameters shared by the views with a time axis (timeline,
 * time grid): `slotMinutes`, `dayStart`, `dayEnd`, `scrollTime`.
 */
final class WindowParams
{
    /**
     * @return array{int, int, int} slot minutes, window start, window end (minutes from midnight)
     */
    public static function window(ViewVariant $variant, int $defaultSlot): array
    {
        $slot = $variant->param('slotMinutes', $defaultSlot);
        if (!\is_int($slot) || $slot < 5 || 1440 % $slot !== 0) {
            throw new \InvalidArgumentException(sprintf('Calendar view "%s" takes a "slotMinutes" that divides a day (1440), e.g. 15, 30, 60, 360 or 1440.', $variant->name));
        }
        $start = self::minutes($variant, 'dayStart', '00:00');
        $end = self::minutes($variant, 'dayEnd', '24:00', allowEndOfDay: true);
        if ($end <= $start) {
            throw new \InvalidArgumentException(sprintf('Calendar view "%s": "dayEnd" must be after "dayStart".', $variant->name));
        }

        return [$slot, $start, $end];
    }

    public static function minutes(ViewVariant $variant, string $param, string $default, bool $allowEndOfDay = false): int
    {
        $value = $variant->param($param, $default);
        if ($allowEndOfDay && $value === '24:00') {
            return 1440;
        }
        if (!\is_string($value) || preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value, $match) !== 1) {
            throw new \InvalidArgumentException(sprintf('Calendar view "%s" takes "%s" as HH:MM.', $variant->name, $param));
        }

        return (int) $match[1] * 60 + (int) $match[2];
    }
}

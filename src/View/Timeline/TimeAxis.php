<?php

namespace Fedale\CalendarBundle\View\Timeline;

use Fedale\CalendarBundle\Time\Days;

/**
 * The horizontal time axis of the timeline: the visible days placed side by
 * side, each showing the same window of the day (e.g. 08:00-20:00, or the
 * whole day). It maps instants to fractions of the axis width, so templates
 * position events with percentages.
 *
 * Inside a day the mapping uses wall-clock minutes, so every hour column has
 * the same width even on the 23- and 25-hour days of a DST change. Instants
 * outside the windows (the night of a 08:00-20:00 axis, a hidden day) are
 * clamped to the nearest window edge.
 */
final class TimeAxis
{
    /** @var list<array{date: string, day: \DateTimeImmutable, offset: int}> */
    private array $segments = [];

    private int $total = 0;

    /**
     * @param list<\DateTimeImmutable> $days     the visible days, in order, at midnight
     * @param int                      $dayStart window start, in minutes from midnight
     * @param int                      $dayEnd   window end, in minutes from midnight (1440 = end of day)
     */
    public function __construct(array $days, private readonly int $dayStart = 0, private readonly int $dayEnd = 1440)
    {
        if ($dayEnd <= $dayStart) {
            throw new \InvalidArgumentException('The window of a time axis must end after it starts.');
        }
        foreach ($days as $day) {
            $this->segments[] = ['date' => $day->format('Y-m-d'), 'day' => $day, 'offset' => $this->total];
            $this->total += $dayEnd - $dayStart;
        }
        if ($this->total === 0) {
            throw new \InvalidArgumentException('A time axis needs at least one day.');
        }
    }

    public function windowMinutes(): int
    {
        return $this->dayEnd - $this->dayStart;
    }

    /** Fraction of the axis (0..1) at which $at falls, clamped to the windows. */
    public function position(\DateTimeImmutable $at): float
    {
        $date = $at->format('Y-m-d');
        foreach ($this->segments as $segment) {
            if ($date < $segment['date']) {
                return $segment['offset'] / $this->total;
            }
            if ($date === $segment['date']) {
                $minutes = (int) $at->format('G') * 60 + (int) $at->format('i');
                $minutes = max($this->dayStart, min($this->dayEnd, $minutes));

                return ($segment['offset'] + $minutes - $this->dayStart) / $this->total;
            }
        }

        return 1.0;
    }

    /**
     * Left edge and width (fractions) of [$start, $end); null when nothing of
     * it falls inside the windows.
     *
     * @return array{float, float}|null
     */
    public function span(\DateTimeImmutable $start, \DateTimeImmutable $end): ?array
    {
        $left = $this->position($start);
        $width = $this->position($end) - $left;

        return $width > 1e-9 ? [$left, $width] : null;
    }

    /**
     * Whether $at falls inside a window (not merely clamped onto one), as a
     * fraction of the axis; null otherwise. Used for the "now" line.
     */
    public function exactPosition(\DateTimeImmutable $at): ?float
    {
        $date = $at->format('Y-m-d');
        $minutes = (int) $at->format('G') * 60 + (int) $at->format('i');
        foreach ($this->segments as $segment) {
            if ($segment['date'] === $date && $minutes >= $this->dayStart && $minutes < $this->dayEnd) {
                return $this->position($at);
            }
        }

        return null;
    }

    /**
     * @return list<AxisSlot>
     */
    public function slots(int $slotMinutes): array
    {
        $slots = [];
        foreach ($this->segments as $segment) {
            for ($minute = $this->dayStart; $minute < $this->dayEnd; $minute += $slotMinutes) {
                $length = min($slotMinutes, $this->dayEnd - $minute);
                $slots[] = new AxisSlot(
                    $segment['day']->setTime(intdiv($minute, 60), $minute % 60),
                    ($segment['offset'] + $minute - $this->dayStart) / $this->total,
                    $length / $this->total,
                );
            }
        }

        return $slots;
    }

    /**
     * Header cells one level above the slots: one per day, or one per ISO week.
     *
     * @return list<AxisGroup>
     */
    public function groups(bool $byWeek): array
    {
        $groups = [];
        $dayWidth = ($this->dayEnd - $this->dayStart) / $this->total;
        foreach ($this->segments as $segment) {
            $left = $segment['offset'] / $this->total;
            $key = $byWeek ? $segment['day']->format('o-W') : $segment['date'];
            $last = array_key_last($groups);
            if ($last !== null && $groups[$last]['key'] === $key) {
                $groups[$last]['width'] += $dayWidth;
                continue;
            }
            $groups[] = ['key' => $key, 'day' => $segment['day'], 'left' => $left, 'width' => $dayWidth];
        }

        return array_map(fn(array $group) => new AxisGroup($group['day'], $byWeek, $group['left'], $group['width']), $groups);
    }

    /**
     * The axis for the interaction script: each visible day as [Y-m-d, left,
     * width] and the window in minutes. The script turns a pointer position
     * into a wall-clock time with it, the same way {@see position()} maps
     * times to positions.
     *
     * @return array{from: int, to: int, days: list<array{string, float, float}>}
     */
    public function describe(): array
    {
        $width = ($this->dayEnd - $this->dayStart) / $this->total;

        return [
            'from' => $this->dayStart,
            'to' => $this->dayEnd,
            'days' => array_map(fn(array $segment) => [$segment['date'], $segment['offset'] / $this->total, $width], $this->segments),
        ];
    }

    /**
     * The windows as [start ms, end ms, left, width], for the script that
     * moves the "now" line between renders.
     *
     * @return list<array{int, int, float, float}>
     */
    public function windows(): array
    {
        $windows = [];
        $width = ($this->dayEnd - $this->dayStart) / $this->total;
        foreach ($this->segments as $segment) {
            $start = $segment['day']->setTime(intdiv($this->dayStart, 60), $this->dayStart % 60);
            $end = $this->dayEnd === 1440 ? Days::add($segment['day'], 1) : $segment['day']->setTime(intdiv($this->dayEnd, 60), $this->dayEnd % 60);
            $windows[] = [$start->getTimestamp() * 1000, $end->getTimestamp() * 1000, $segment['offset'] / $this->total, $width];
        }

        return $windows;
    }
}

<?php

namespace Fedale\CalendarBundle\Options;

/**
 * When the business is open, as rules of ISO days and HH:MM times. Views use
 * it to shade the closed part of each day; it never hides or rejects events.
 */
final readonly class BusinessHours
{
    /**
     * @param list<array{days: list<int>, start: int, end: int}> $rules start and end in minutes from midnight
     */
    public function __construct(public array $rules = [])
    {
    }

    public static function fromArray(mixed $value, string $path): self
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException(sprintf('Calendar option "%s" must be a list of ["days" => [...], "start" => "HH:MM", "end" => "HH:MM"].', $path));
        }
        $rules = [];
        foreach ($value as $i => $rule) {
            $rulePath = $path.'.'.$i;
            if (!\is_array($rule) || array_diff(array_keys($rule), ['days', 'start', 'end']) !== [] || !isset($rule['days'], $rule['start'], $rule['end'])) {
                throw new \InvalidArgumentException(sprintf('Calendar option "%s" must have exactly "days", "start" and "end".', $rulePath));
            }
            if (!\is_array($rule['days']) || $rule['days'] === []) {
                throw new \InvalidArgumentException(sprintf('Calendar option "%s.days" must list ISO-8601 day numbers.', $rulePath));
            }
            foreach ($rule['days'] as $day) {
                if (!\is_int($day) || $day < 1 || $day > 7) {
                    throw new \InvalidArgumentException(sprintf('Calendar option "%s.days" takes ISO-8601 day numbers, 1 (Monday) to 7 (Sunday).', $rulePath));
                }
            }
            $start = self::minutes($rule['start'], $rulePath.'.start');
            $end = self::minutes($rule['end'], $rulePath.'.end');
            if ($end <= $start) {
                throw new \InvalidArgumentException(sprintf('Calendar option "%s.end" must be after its start.', $rulePath));
            }
            $rules[] = ['days' => array_values(array_unique($rule['days'])), 'start' => $start, 'end' => $end];
        }

        return new self($rules);
    }

    public function isDefined(): bool
    {
        return $this->rules !== [];
    }

    /**
     * The closed parts of $day within the window [$from, $to) (minutes from
     * midnight), as [start, end] minute pairs. Overlapping rules merge.
     *
     * @return list<array{int, int}>
     */
    public function closed(\DateTimeInterface $day, int $from = 0, int $to = 1440): array
    {
        $isoDay = (int) $day->format('N');
        $open = [];
        foreach ($this->rules as $rule) {
            if (\in_array($isoDay, $rule['days'], true)) {
                $open[] = [max($from, $rule['start']), min($to, $rule['end'])];
            }
        }
        sort($open);

        $closed = [];
        $cursor = $from;
        foreach ($open as [$start, $end]) {
            if ($start > $cursor) {
                $closed[] = [$cursor, $start];
            }
            $cursor = max($cursor, $end);
        }
        if ($cursor < $to) {
            $closed[] = [$cursor, $to];
        }

        return $closed;
    }

    private static function minutes(mixed $value, string $path): int
    {
        if ($value === '24:00') {
            return 1440;
        }
        if (!\is_string($value) || preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value, $match) !== 1) {
            throw new \InvalidArgumentException(sprintf('Calendar option "%s" must be a time as HH:MM.', $path));
        }

        return (int) $match[1] * 60 + (int) $match[2];
    }
}

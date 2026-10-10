<?php

namespace Fedale\CalendarBundle\Model;

/**
 * A half-open time interval [start, end): an event ending exactly at `end`
 * does not belong to the next range, and two adjacent ranges never overlap.
 */
final readonly class DateRange
{
    public function __construct(
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
    ) {
        if ($end <= $start) {
            throw new \InvalidArgumentException(sprintf('The end of a date range must be after its start, got %s and %s.', $start->format(\DATE_ATOM), $end->format(\DATE_ATOM)));
        }
    }

    public function contains(\DateTimeImmutable $at): bool
    {
        return $at >= $this->start && $at < $this->end;
    }

    /** Whether the half-open interval [$start, $end) shares at least an instant with this range. */
    public function overlaps(\DateTimeImmutable $start, \DateTimeImmutable $end): bool
    {
        return $start < $this->end && $end > $this->start;
    }
}

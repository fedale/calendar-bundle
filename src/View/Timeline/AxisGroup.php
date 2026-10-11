<?php

namespace Fedale\CalendarBundle\View\Timeline;

/** A header cell above the slots: a day, or an ISO week starting at $start. */
final readonly class AxisGroup
{
    public function __construct(
        public \DateTimeImmutable $start,
        public bool $isWeek,
        public float $left,
        public float $width,
    ) {
    }
}

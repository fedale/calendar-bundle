<?php

namespace Fedale\CalendarBundle\View\Timeline;

/** One slot of the time axis: its start, and its left edge and width as fractions of the axis. */
final readonly class AxisSlot
{
    public function __construct(
        public \DateTimeImmutable $start,
        public float $left,
        public float $width,
    ) {
    }
}

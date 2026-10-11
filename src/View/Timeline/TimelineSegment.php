<?php

namespace Fedale\CalendarBundle\View\Timeline;

use Fedale\CalendarBundle\Source\CalendarItem;

/**
 * An item placed on a timeline row: left edge and width as fractions of the
 * axis, and its lane. The continuation flags say it goes on beyond the range.
 */
final readonly class TimelineSegment
{
    public function __construct(
        public CalendarItem $item,
        public float $left,
        public float $width,
        public int $lane = 0,
        public bool $continuesBefore = false,
        public bool $continuesAfter = false,
    ) {
    }
}

<?php

namespace Fedale\CalendarBundle\View\TimeGrid;

use Fedale\CalendarBundle\Source\CalendarItem;

/**
 * An item placed in a time-grid column: top and height as fractions of the
 * day window, left and width as fractions of the column. The continuation
 * flags say it starts before or ends after the window.
 */
final readonly class GridEvent
{
    public function __construct(
        public CalendarItem $item,
        public float $top,
        public float $height,
        public float $left = 0.0,
        public float $width = 1.0,
        public bool $continuesBefore = false,
        public bool $continuesAfter = false,
    ) {
    }
}

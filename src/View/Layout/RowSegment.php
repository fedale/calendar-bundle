<?php

namespace Fedale\CalendarBundle\View\Layout;

use Fedale\CalendarBundle\Source\CalendarItem;

/**
 * The part of an item that falls in one row of days: from column $start
 * (0-based) over $span columns, on lane $lane. The continuation flags say
 * whether the item goes on before or after this row, so the template can
 * square off that end of the bar.
 */
final readonly class RowSegment
{
    public function __construct(
        public CalendarItem $item,
        public int $start,
        public int $span,
        public int $lane,
        public bool $continuesBefore,
        public bool $continuesAfter,
    ) {
    }
}

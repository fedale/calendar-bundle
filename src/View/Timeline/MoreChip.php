<?php

namespace Fedale\CalendarBundle\View\Timeline;

use Fedale\CalendarBundle\Source\CalendarItem;

/**
 * "+N more" on a timeline row: the items that did not fit in the lanes,
 * merged where they overlap, shown over the time they cover.
 */
final readonly class MoreChip
{
    /**
     * @param list<CalendarItem> $items
     */
    public function __construct(
        public float $left,
        public float $width,
        public array $items,
    ) {
    }
}

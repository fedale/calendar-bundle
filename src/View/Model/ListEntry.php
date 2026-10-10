<?php

namespace Fedale\CalendarBundle\View\Model;

use Fedale\CalendarBundle\Source\CalendarItem;

final readonly class ListEntry
{
    public function __construct(
        public CalendarItem $item,
        public ListEntryKind $kind,
    ) {
    }
}

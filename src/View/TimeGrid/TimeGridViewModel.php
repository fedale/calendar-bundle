<?php

namespace Fedale\CalendarBundle\View\TimeGrid;

use Fedale\CalendarBundle\Model\CalendarResource;
use Fedale\CalendarBundle\Source\CalendarItem;
use Fedale\CalendarBundle\View\Layout\RowLayout;
use Fedale\CalendarBundle\View\Timeline\AxisSlot;

final readonly class TimeGridViewModel
{
    /**
     * @param list<GridColumn>                                   $columns
     * @param list<array{resource: CalendarResource, span: int}> $resourceHeaders resources above their days; empty without resources
     * @param RowLayout|null                                     $allDay          the all-day row, laid out over the columns; null when hidden
     * @param array<int, list<CalendarItem>>                     $allDayItems     column => every all-day item, for columns that overflow
     * @param list<AxisSlot>                                     $slots           the gutter: one entry per slot of the day window
     * @param list<array{int, int, float, float}>                $windows         for the script moving the "now" line
     */
    public function __construct(
        public array $columns,
        public array $resourceHeaders,
        public ?RowLayout $allDay,
        public array $allDayItems,
        public array $slots,
        public array $windows,
        public float $scrollTo,
    ) {
    }
}

<?php

namespace Fedale\CalendarBundle\View\Timeline;

use Fedale\CalendarBundle\Options\ResourceColumn;

final readonly class TimelineViewModel
{
    /**
     * @param list<AxisGroup>                     $groups  top header row
     * @param list<AxisSlot>                      $slots   bottom header row and grid lines
     * @param list<TimelineRow>                   $rows
     * @param list<TimelineSegment>               $bands   background events without a resource: across every row
     * @param list<array{float, float}>           $closed  closed hours (business hours) as [left, width], across every row
     * @param list<ResourceColumn>                $columns
     * @param list<array{int, int, float, float}> $windows for the script moving the "now" line
     */
    public function __construct(
        public array $groups,
        public array $slots,
        public bool $daySlots,
        public array $rows,
        public array $bands,
        public array $closed,
        public array $columns,
        public ?string $areaLabel,
        public string $areaWidth,
        public ?float $now,
        public array $windows,
        public float $scrollTo,
        /** @var array{from: int, to: int, days: list<array{string, float, float}>} */
        public array $axis = ['from' => 0, 'to' => 1440, 'days' => []],
    ) {
    }
}

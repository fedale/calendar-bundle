<?php

namespace Fedale\CalendarBundle\View\Timeline;

/** Result of {@see LaneLayout}: the placed segments, the "+N more" chips and the lanes used. */
final readonly class RowLayout
{
    /**
     * @param list<TimelineSegment> $segments
     * @param list<MoreChip>        $more
     */
    public function __construct(
        public array $segments = [],
        public array $more = [],
        public int $laneCount = 0,
    ) {
    }
}

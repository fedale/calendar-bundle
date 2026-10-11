<?php

namespace Fedale\CalendarBundle\View\Timeline;

use Fedale\CalendarBundle\Model\CalendarResource;

/**
 * One row of the timeline: a group header (resources sharing a groupBy
 * value) or a resource with its events and background bands.
 */
final readonly class TimelineRow
{
    /**
     * @param string                $key   stable id of the row, sent back to collapse or expand it
     * @param list<string>          $cells one value per resource column (resource rows)
     * @param list<TimelineSegment> $bands background events of this resource
     */
    public function __construct(
        public string $key,
        public bool $isGroup,
        public string $label,
        public ?CalendarResource $resource,
        public array $cells,
        public int $depth,
        public bool $hasChildren,
        public bool $collapsed,
        public RowLayout $layout = new RowLayout(),
        public array $bands = [],
    ) {
    }

    public function isCollapsible(): bool
    {
        return $this->isGroup || $this->hasChildren;
    }
}

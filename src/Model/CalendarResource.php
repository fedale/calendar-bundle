<?php

namespace Fedale\CalendarBundle\Model;

/**
 * Something events are assigned to (an employee, a seat, a room), shown as a
 * row of the timeline or a column of the resource view. A resource with a
 * `parentId` is nested under that resource (a room split into two halves).
 */
final readonly class CalendarResource
{
    /**
     * @param array<string, mixed> $data payload; the timeline reads the fields its columns and groupBy name
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $parentId = null,
        public int $order = 0,
        public EventStyle $style = new EventStyle(),
        public array $data = [],
    ) {
    }
}

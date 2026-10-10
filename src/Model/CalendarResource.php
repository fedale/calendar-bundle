<?php

namespace Fedale\CalendarBundle\Model;

/**
 * Something events are assigned to (an employee, a seat, a room), shown as a
 * row of the timeline or a column of the resource view.
 */
final readonly class CalendarResource
{
    /**
     * @param array<string, mixed> $data opaque payload, never interpreted by the calendar
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $groupId = null,
        public int $order = 0,
        public EventStyle $style = new EventStyle(),
        public array $data = [],
    ) {
    }
}

<?php

namespace Fedale\CalendarBundle\Model;

/**
 * What the calendar asks a source for: the visible range, the timezone it is
 * displayed in, and optional narrowing. `filters` are opaque to the calendar:
 * their meaning is defined by the source that receives them.
 */
final readonly class CalendarQuery
{
    /**
     * @param list<string>         $resourceIds empty = every resource
     * @param array<string, mixed> $filters
     */
    public function __construct(
        public DateRange $range,
        public \DateTimeZone $timezone,
        public array $resourceIds = [],
        public array $filters = [],
    ) {
    }
}

<?php

namespace Fedale\CalendarBundle\Model;

/**
 * The read contract between a source and the calendar: one thing to show on
 * the time grid, detached from any domain entity.
 *
 * Timed events are instants: `start` and `end` may carry any timezone, the
 * calendar converts them to the display timezone. All-day events are dates,
 * not instants: only the Y-m-d of `start` and `end` (exclusive) is read, in the
 * timezone they carry, so a holiday stays on its day wherever it is displayed.
 */
final readonly class CalendarEvent
{
    /**
     * @param string               $id          unique within its source
     * @param list<string>         $resourceIds
     * @param array<string, mixed> $hints       display-only facts computed by the source (e.g. "3 seats left")
     * @param array<string, mixed> $data        opaque payload, never interpreted by the calendar
     */
    public function __construct(
        public string $id,
        public string $title,
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
        public bool $allDay = false,
        public array $resourceIds = [],
        public ?string $type = null,
        public ?string $status = null,
        public EventLayer $layer = EventLayer::Foreground,
        public EventVisibility $visibility = EventVisibility::Full,
        public EventInteractions $interactions = new EventInteractions(),
        public ?string $version = null,
        public EventStyle $style = new EventStyle(),
        public array $hints = [],
        public ?string $url = null,
        public array $data = [],
    ) {
        if ($allDay ? $end->format('Y-m-d') <= $start->format('Y-m-d') : $end <= $start) {
            throw new \InvalidArgumentException(sprintf('Calendar event "%s" must end after it starts.', $id));
        }
    }
}

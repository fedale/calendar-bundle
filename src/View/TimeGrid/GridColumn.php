<?php

namespace Fedale\CalendarBundle\View\TimeGrid;

use Fedale\CalendarBundle\Model\CalendarResource;

/**
 * One column of the time grid: a day, or a day of one resource, with what
 * it shows. Positions are fractions of the day window (top, height) and of
 * the column (left, width).
 */
final readonly class GridColumn
{
    /**
     * @param list<GridEvent>           $events foreground timed events, laid out side by side
     * @param list<GridEvent>           $bands  background events
     * @param list<array{float, float}> $closed closed hours as [top, height]
     */
    public function __construct(
        public \DateTimeImmutable $day,
        public ?CalendarResource $resource,
        public bool $isToday,
        public bool $isWeekend,
        public array $events = [],
        public array $bands = [],
        public array $closed = [],
        public ?float $now = null,
    ) {
    }
}

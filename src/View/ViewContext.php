<?php

namespace Fedale\CalendarBundle\View;

use Fedale\CalendarBundle\Options\CalendarOptions;
use Fedale\CalendarBundle\Options\ViewVariant;

/**
 * Everything a view needs besides the events: the focused day, today and now,
 * the options and the active variant, the timezone and locale the calendar is
 * displayed in, and the keys of the collapsed resource rows. `date` and
 * `today` are midnight in that timezone.
 */
final readonly class ViewContext
{
    /**
     * @param list<string> $collapsed keys of the collapsed groups and parent resources
     */
    public function __construct(
        public \DateTimeImmutable $date,
        public \DateTimeImmutable $today,
        public CalendarOptions $options,
        public \DateTimeZone $timezone,
        public string $locale,
        public ViewVariant $variant,
        public \DateTimeImmutable $now,
        public array $collapsed = [],
    ) {
    }
}

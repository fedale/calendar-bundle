<?php

namespace Fedale\CalendarBundle\View;

use Fedale\CalendarBundle\Options\CalendarOptions;

/**
 * Everything a view needs besides the events: the focused day, today, the
 * options, and the timezone and locale the calendar is displayed in. Both
 * dates are midnight in that timezone.
 */
final readonly class ViewContext
{
    public function __construct(
        public \DateTimeImmutable $date,
        public \DateTimeImmutable $today,
        public CalendarOptions $options,
        public \DateTimeZone $timezone,
        public string $locale,
    ) {
    }
}

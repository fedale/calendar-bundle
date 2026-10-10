<?php

namespace Fedale\CalendarBundle\Model;

/**
 * Presentation hints for an event or a resource. `color` is any CSS color and
 * feeds the `--cal-event-color` custom property; `cssClass` is added as is.
 */
final readonly class EventStyle
{
    public function __construct(
        public ?string $color = null,
        public ?string $cssClass = null,
        public ?string $icon = null,
    ) {
    }
}

<?php

namespace Fedale\CalendarBundle\Options;

/**
 * A custom toolbar button. It either links to a URL (optionally loaded into
 * a Turbo Frame, e.g. the application's "new booking" form), or dispatches a
 * `cal:button` DOM event that the application's own JavaScript handles.
 */
final readonly class ToolbarButton
{
    public function __construct(
        public string $key,
        public string $label,
        public ?string $url = null,
        public ?string $frame = null,
        public ?string $event = null,
    ) {
    }
}

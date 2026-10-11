<?php

namespace Fedale\CalendarBundle\Options;

/**
 * A named configuration of a view type: `timelineWeek` is the `timeline`
 * type with a week range and 6-hour slots. The toolbar and the URL use the
 * name; the view reads the parameters.
 */
final readonly class ViewVariant
{
    /**
     * @param array<string, mixed> $params everything declared besides type and label
     * @param string|null          $label  toolbar label (translation key or text); null = "view.<name>"
     */
    public function __construct(
        public string $name,
        public string $type,
        public array $params = [],
        public ?string $label = null,
    ) {
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }
}

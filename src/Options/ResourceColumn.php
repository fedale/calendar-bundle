<?php

namespace Fedale\CalendarBundle\Options;

use Fedale\CalendarBundle\Model\CalendarResource;

/**
 * One column of the timeline's resource area.
 */
final readonly class ResourceColumn
{
    /**
     * @param string      $field 'title', 'id', or a key of CalendarResource::$data
     * @param string|null $label header (translation key or text); null = none
     */
    public function __construct(
        public string $field,
        public ?string $label = null,
    ) {
    }

    public function value(CalendarResource $resource): string
    {
        $value = match ($this->field) {
            'title' => $resource->title,
            'id' => $resource->id,
            default => $resource->data[$this->field] ?? '',
        };

        return \is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
    }
}

<?php

namespace Fedale\CalendarBundle\Bridge\ApiPlatform\Dto;

/**
 * An empty range: ISO 8601 instants, or dates (Y-m-d, end exclusive) with
 * allDay.
 */
final class SelectionInput
{
    public ?string $start = null;

    public ?string $end = null;

    public bool $allDay = false;

    public ?string $resource = null;
}

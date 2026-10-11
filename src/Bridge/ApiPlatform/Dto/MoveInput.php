<?php

namespace Fedale\CalendarBundle\Bridge\ApiPlatform\Dto;

/**
 * New times of an event (its duration is the client's to keep), and
 * optionally another resource. Times are ISO 8601 instants, or dates
 * (Y-m-d) for all-day events.
 */
final class MoveInput
{
    public ?string $start = null;

    public ?string $end = null;

    /** The resource to move the event to; null = where it is. */
    public ?string $resource = null;

    /** The resource it moves from, when it has several. */
    public ?string $fromResource = null;

    /** The version read; the If-Match header wins over it. */
    public ?string $version = null;
}

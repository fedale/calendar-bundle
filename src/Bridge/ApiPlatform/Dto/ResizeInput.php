<?php

namespace Fedale\CalendarBundle\Bridge\ApiPlatform\Dto;

/**
 * A new end for an event. The start is the event's own, when its source can
 * find events by id; otherwise pass it too.
 */
final class ResizeInput
{
    public ?string $end = null;

    public ?string $start = null;

    /** The version read; the If-Match header wins over it. */
    public ?string $version = null;
}

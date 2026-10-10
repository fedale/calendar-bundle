<?php

namespace Fedale\CalendarBundle\Contract;

/**
 * One calendar of the application, registered under an id with
 * {@see \Fedale\CalendarBundle\Attribute\AsCalendar}. It declares the options
 * the calendar is rendered with; see {@see \Fedale\CalendarBundle\Options\CalendarOptionsResolver}
 * for where they sit in the precedence.
 */
interface CalendarDefinitionInterface
{
    /** Heading of the calendar, a translation key or plain text; null for none. */
    public function label(): ?string;

    /**
     * Option overrides for this calendar, as a partial tree of the bundle
     * defaults (`display`, `integration`).
     *
     * @return array<string, mixed>
     */
    public function viewConfig(): array;
}

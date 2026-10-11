<?php

namespace Fedale\CalendarBundle\Contract;

use Fedale\CalendarBundle\Model\CalendarResource;
use Fedale\CalendarBundle\Model\DateRange;
use Fedale\CalendarBundle\Options\ViewVariant;
use Fedale\CalendarBundle\Source\CalendarItem;
use Fedale\CalendarBundle\View\ViewContext;

/**
 * One type of layout (month, list, timeline, ...). Calendars enable named
 * variants of a type (`timelineDay`, `timelineWeek`), whose parameters reach
 * the view through {@see ViewContext::$variant}.
 *
 * A view only computes: which range to fetch, how to move by one step, and a
 * view model that its template renders. All layout logic lives here, in PHP,
 * so it is unit-tested and the browser never builds the grid.
 *
 * Registered with {@see \Fedale\CalendarBundle\Attribute\AsCalendarView}
 * under its type name.
 */
interface CalendarViewInterface
{
    /** Rejects parameters this view does not understand or accept, naming the variant. */
    public function validateVariant(ViewVariant $variant): void;

    /** Whether build() needs the resources for this variant; they are only fetched when it does. */
    public function usesResources(ViewVariant $variant): bool;

    /** The range to fetch and render around the focused date. */
    public function range(ViewContext $context): DateRange;

    /** The focused date after moving by $step periods (-1 = previous, 1 = next). */
    public function navigate(ViewContext $context, int $step): \DateTimeImmutable;

    /** Heading of the period, already localized. */
    public function title(ViewContext $context): string;

    /**
     * @param list<CalendarItem>     $items     the events overlapping {@see range()}, sorted
     * @param list<CalendarResource> $resources empty unless {@see usesResources()} says otherwise
     */
    public function build(ViewContext $context, array $items, array $resources = []): object;

    /** Twig template rendering the object returned by {@see build()}, exposed as `model`. */
    public function template(): string;
}

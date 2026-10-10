<?php

namespace Fedale\CalendarBundle\Contract;

use Fedale\CalendarBundle\Model\DateRange;
use Fedale\CalendarBundle\Source\CalendarItem;
use Fedale\CalendarBundle\View\ViewContext;

/**
 * One way of laying out a period (month, list, ...). A view only computes:
 * which range to fetch, how to move by one step, and a view model that its
 * template renders. All layout logic lives here, in PHP, so it is unit-tested
 * and the browser never builds the grid.
 *
 * Registered with {@see \Fedale\CalendarBundle\Attribute\AsCalendarView}.
 */
interface CalendarViewInterface
{
    /** The range to fetch and render around the focused date. */
    public function range(ViewContext $context): DateRange;

    /** The focused date after moving by $step periods (-1 = previous, 1 = next). */
    public function navigate(ViewContext $context, int $step): \DateTimeImmutable;

    /** Heading of the period, already localized. */
    public function title(ViewContext $context): string;

    /**
     * @param list<CalendarItem> $items the events overlapping {@see range()}, sorted
     */
    public function build(ViewContext $context, array $items): object;

    /** Twig template rendering the object returned by {@see build()}, exposed as `model`. */
    public function template(): string;
}

<?php

namespace Fedale\CalendarBundle\Source;

use Fedale\CalendarBundle\Model\CalendarEvent;
use Fedale\CalendarBundle\Model\CalendarQuery;
use Fedale\CalendarBundle\Model\EventVisibility;
use Fedale\CalendarBundle\Options\CalendarOptions;

/**
 * Gathers the events of every source of a calendar into one sorted list of
 * {@see CalendarItem}: hidden events dropped, times converted to the display
 * timezone, anything outside the queried range discarded.
 */
class EventCollector
{
    public function __construct(private readonly SourceRegistry $sources)
    {
    }

    /**
     * @return list<CalendarItem>
     */
    public function collect(CalendarOptions $options, CalendarQuery $query): array
    {
        $items = [];
        foreach ($options->sources as $key) {
            foreach ($this->sources->get($key)->getEvents($query) as $event) {
                if (!$event instanceof CalendarEvent) {
                    throw new \UnexpectedValueException(sprintf('Calendar source "%s" must yield %s objects, got %s.', $key, CalendarEvent::class, get_debug_type($event)));
                }
                if ($event->visibility === EventVisibility::Hidden) {
                    continue;
                }
                $item = CalendarItem::fromEvent($key, $event, $query->timezone, $options->nextDayThreshold);
                if ($query->range->overlaps($item->start, $item->end)) {
                    $items[] = $item;
                }
            }
        }

        usort($items, CalendarItem::compare(...));

        return $items;
    }
}

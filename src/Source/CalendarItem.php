<?php

namespace Fedale\CalendarBundle\Source;

use Fedale\CalendarBundle\Model\CalendarEvent;
use Fedale\CalendarBundle\Model\EventLayer;
use Fedale\CalendarBundle\Model\EventVisibility;
use Fedale\CalendarBundle\Time\Days;

/**
 * An event as the views see it: tagged with its source, converted to the
 * display timezone, with the calendar days it covers already worked out.
 *
 * Views and templates read title and url from here, never from the event, so
 * a BusyOnly event cannot leak them.
 */
final readonly class CalendarItem
{
    public function __construct(
        public string $source,
        public CalendarEvent $event,
        /** Start in the display timezone; midnight of the first day for all-day events. */
        public \DateTimeImmutable $start,
        /** Exclusive end in the display timezone; midnight after the last day for all-day events. */
        public \DateTimeImmutable $end,
        public \DateTimeImmutable $firstDay,
        /** Inclusive: the last calendar day the item shows on. */
        public \DateTimeImmutable $lastDay,
    ) {
        // Derived once: layouts compare days and sorting compares spans for
        // every pair of items, so recomputing them is what a busy month pays for.
        $this->firstDate = $firstDay->format('Y-m-d');
        $this->lastDate = $lastDay->format('Y-m-d');
        $this->dayCount = Days::between($firstDay, $lastDay) + 1;
        $this->startTimestamp = $start->getTimestamp();
    }

    /** $firstDay as Y-m-d. */
    public string $firstDate;

    /** $lastDay as Y-m-d. */
    public string $lastDate;

    /** How many calendar days the item spans (1 for a same-day event). */
    public int $dayCount;

    private int $startTimestamp;

    /**
     * @param string $nextDayThreshold H:i; a timed event shows on the day it ends only if it ends at
     *                                 or after this time (the `display.nextDayThreshold` option)
     */
    public static function fromEvent(string $source, CalendarEvent $event, \DateTimeZone $timezone, string $nextDayThreshold = '00:00'): self
    {
        if ($event->allDay) {
            // Dates, not instants: rebuild them in the display timezone from
            // their Y-m-d alone, so they never shift by a day.
            $first = new \DateTimeImmutable($event->start->format('Y-m-d'), $timezone);
            $end = new \DateTimeImmutable($event->end->format('Y-m-d'), $timezone);

            return new self($source, $event, $first, $end, $first, Days::add($end, -1));
        }

        $start = $event->start->setTimezone($timezone);
        $end = $event->end->setTimezone($timezone);
        $firstDay = Days::start($start);
        // The end is exclusive, so an event ending at midnight never shows on
        // the next day; with a threshold, neither does one ending before it
        // (a 22:00-06:00 night shift stays on the day it starts).
        $endTime = $end->format('H:i');
        $lastDay = $endTime !== '00:00' && $endTime >= $nextDayThreshold ? Days::start($end) : Days::add(Days::start($end), -1);

        return new self($source, $event, $start, $end, $firstDay, max($firstDay, $lastDay));
    }

    /** Unique across sources; also a stable DOM id fragment. */
    public function key(): string
    {
        return $this->source.'-'.$this->event->id;
    }

    public function isBusy(): bool
    {
        return $this->event->visibility === EventVisibility::BusyOnly;
    }

    /** null for a BusyOnly event: the template shows a generic label instead. */
    public function title(): ?string
    {
        return $this->isBusy() ? null : $this->event->title;
    }

    public function url(): ?string
    {
        return $this->isBusy() ? null : $this->event->url;
    }

    public function isAllDay(): bool
    {
        return $this->event->allDay;
    }

    public function isBackground(): bool
    {
        return $this->event->layer === EventLayer::Background;
    }

    public function coversDay(\DateTimeInterface $day): bool
    {
        return $this->coversDate($day->format('Y-m-d'));
    }

    /** Same as coversDay(), for a day already formatted as Y-m-d. */
    public function coversDate(string $date): bool
    {
        return $date >= $this->firstDate && $date <= $this->lastDate;
    }

    /**
     * Display order: earlier day first, then all-day before timed, longer
     * before shorter (so multi-day bars take the top lanes), then start time,
     * then title. The key breaks the remaining ties so the order is stable.
     */
    public static function compare(self $a, self $b): int
    {
        return [$a->firstDate, !$a->event->allDay, -$a->dayCount, $a->startTimestamp, (string) $a->title(), $a->key()]
            <=> [$b->firstDate, !$b->event->allDay, -$b->dayCount, $b->startTimestamp, (string) $b->title(), $b->key()];
    }
}

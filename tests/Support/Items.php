<?php

namespace Fedale\CalendarBundle\Tests\Support;

use Fedale\CalendarBundle\Model\CalendarEvent;
use Fedale\CalendarBundle\Model\EventLayer;
use Fedale\CalendarBundle\Source\CalendarItem;

/**
 * Builds events and items in Europe/Rome for the tests.
 */
final class Items
{
    public const TZ = 'Europe/Rome';

    public static function tz(): \DateTimeZone
    {
        return new \DateTimeZone(self::TZ);
    }

    public static function at(string $datetime): \DateTimeImmutable
    {
        return new \DateTimeImmutable($datetime, self::tz());
    }

    public static function event(string $id, string $start, string $end, bool $allDay = false, EventLayer $layer = EventLayer::Foreground): CalendarEvent
    {
        return new CalendarEvent($id, 'Event '.$id, self::at($start), self::at($end), allDay: $allDay, layer: $layer);
    }

    public static function item(string $id, string $start, string $end, bool $allDay = false, EventLayer $layer = EventLayer::Foreground): CalendarItem
    {
        return CalendarItem::fromEvent('test', self::event($id, $start, $end, $allDay, $layer), self::tz());
    }
}

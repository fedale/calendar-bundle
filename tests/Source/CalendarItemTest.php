<?php

namespace Fedale\CalendarBundle\Tests\Source;

use Fedale\CalendarBundle\Model\CalendarEvent;
use Fedale\CalendarBundle\Model\EventVisibility;
use Fedale\CalendarBundle\Source\CalendarItem;
use Fedale\CalendarBundle\Tests\Support\Items;
use PHPUnit\Framework\TestCase;

final class CalendarItemTest extends TestCase
{
    public function testAllDayEventsKeepTheirDatesInAnyTimezone(): void
    {
        // Stored as midnight UTC: read as instants they would fall on the
        // previous day in New York.
        $event = new CalendarEvent('h', 'Holiday', new \DateTimeImmutable('2026-12-25', new \DateTimeZone('UTC')), new \DateTimeImmutable('2026-12-26', new \DateTimeZone('UTC')), allDay: true);

        $item = CalendarItem::fromEvent('test', $event, new \DateTimeZone('America/New_York'));

        self::assertSame('2026-12-25', $item->firstDay->format('Y-m-d'));
        self::assertSame('2026-12-25', $item->lastDay->format('Y-m-d'));
        self::assertSame(1, $item->dayCount);
    }

    public function testTimedEventsAreConvertedToTheDisplayTimezone(): void
    {
        $event = new CalendarEvent('m', 'Call', new \DateTimeImmutable('2026-10-10 23:30', new \DateTimeZone('UTC')), new \DateTimeImmutable('2026-10-11 00:30', new \DateTimeZone('UTC')));

        $item = CalendarItem::fromEvent('test', $event, Items::tz());

        self::assertSame('2026-10-11 01:30', $item->start->format('Y-m-d H:i'));
        self::assertSame('2026-10-11', $item->firstDay->format('Y-m-d'));
    }

    public function testAnEventEndingAtMidnightDoesNotCoverTheNextDay(): void
    {
        $item = Items::item('n', '2026-10-10 20:00', '2026-10-11 00:00');

        self::assertSame('2026-10-10', $item->lastDay->format('Y-m-d'));
        self::assertFalse($item->coversDay(Items::at('2026-10-11')));
    }

    public function testOvernightEventsCoverBothDays(): void
    {
        $item = Items::item('n', '2026-10-10 22:00', '2026-10-11 06:00');

        self::assertSame(2, $item->dayCount);
        self::assertTrue($item->coversDay(Items::at('2026-10-11')));
    }

    public function testANextDayThresholdKeepsShortOvernightEventsOnTheirFirstDay(): void
    {
        $shift = CalendarItem::fromEvent('test', Items::event('night', '2026-10-10 22:00', '2026-10-11 06:00'), Items::tz(), '09:00');
        $longer = CalendarItem::fromEvent('test', Items::event('long', '2026-10-10 22:00', '2026-10-11 09:00'), Items::tz(), '09:00');
        $twoNights = CalendarItem::fromEvent('test', Items::event('two', '2026-10-10 22:00', '2026-10-12 06:00'), Items::tz(), '09:00');

        self::assertSame(1, $shift->dayCount);
        self::assertSame(2, $longer->dayCount);   // ends exactly at the threshold
        self::assertSame(2, $twoNights->dayCount);
        self::assertSame('2026-10-11 06:00', $shift->end->format('Y-m-d H:i')); // the real end is kept
    }

    public function testBusyOnlyWithholdsTitleAndUrl(): void
    {
        $event = new CalendarEvent('b', 'Secret meeting', Items::at('2026-10-10 10:00'), Items::at('2026-10-10 11:00'), visibility: EventVisibility::BusyOnly, url: '/meetings/1');

        $item = CalendarItem::fromEvent('test', $event, Items::tz());

        self::assertTrue($item->isBusy());
        self::assertNull($item->title());
        self::assertNull($item->url());
    }

    public function testOrderPutsAllDayAndLongerItemsFirst(): void
    {
        $items = [
            $timed = Items::item('timed', '2026-10-10 09:00', '2026-10-10 10:00'),
            $long = Items::item('long', '2026-10-10', '2026-10-13', allDay: true),
            $short = Items::item('short', '2026-10-10', '2026-10-11', allDay: true),
            $earlier = Items::item('earlier', '2026-10-09 09:00', '2026-10-09 10:00'),
        ];

        usort($items, CalendarItem::compare(...));

        self::assertSame([$earlier, $long, $short, $timed], $items);
    }

    public function testRejectsEventsEndingBeforeTheyStart(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CalendarEvent('x', 'Broken', Items::at('2026-10-10 10:00'), Items::at('2026-10-10 10:00'));
    }
}

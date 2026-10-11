<?php

namespace Fedale\CalendarBundle\Tests\Integration\Fixtures;

use Fedale\CalendarBundle\Attribute\AsCalendarSource;
use Fedale\CalendarBundle\Contract\EventSourceInterface;
use Fedale\CalendarBundle\Contract\ResourceSourceInterface;
use Fedale\CalendarBundle\Model\CalendarEvent;
use Fedale\CalendarBundle\Model\CalendarQuery;
use Fedale\CalendarBundle\Model\CalendarResource;

#[AsCalendarSource('rooms')]
final class RoomSource implements EventSourceInterface, ResourceSourceInterface
{
    public function getResources(CalendarQuery $query): iterable
    {
        yield new CalendarResource('a', 'Auditorium A', data: ['building' => 'North', 'seats' => 40]);
        yield new CalendarResource('b', 'Auditorium B', data: ['building' => 'North', 'seats' => 40]);
        yield new CalendarResource('b1', 'Room B1', parentId: 'b', data: ['building' => 'North', 'seats' => 10]);
        yield new CalendarResource('c', 'Cinema', data: ['building' => 'South', 'seats' => 120]);
    }

    public function getEvents(CalendarQuery $query): iterable
    {
        $at = fn(string $datetime) => new \DateTimeImmutable($datetime, new \DateTimeZone('Europe/Rome'));

        yield new CalendarEvent('keynote', 'Keynote', $at('2026-10-10 09:00'), $at('2026-10-10 12:00'), resourceIds: ['a', 'c']);
        yield new CalendarEvent('workshop', 'Workshop', $at('2026-10-10 10:00'), $at('2026-10-10 11:00'), resourceIds: ['b1']);
    }
}

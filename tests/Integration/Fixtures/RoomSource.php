<?php

namespace Fedale\CalendarBundle\Tests\Integration\Fixtures;

use Fedale\CalendarBundle\Attribute\AsCalendarSource;
use Fedale\CalendarBundle\Contract\EventFinderInterface;
use Fedale\CalendarBundle\Contract\EventSourceInterface;
use Fedale\CalendarBundle\Contract\IntentHandlerInterface;
use Fedale\CalendarBundle\Contract\ResourceSourceInterface;
use Fedale\CalendarBundle\Intent\CalendarIntent;
use Fedale\CalendarBundle\Intent\IntentResult;
use Fedale\CalendarBundle\Intent\MoveEvent;
use Fedale\CalendarBundle\Intent\Rejection;
use Fedale\CalendarBundle\Intent\SelectRange;
use Fedale\CalendarBundle\Model\CalendarEvent;
use Fedale\CalendarBundle\Model\CalendarQuery;
use Fedale\CalendarBundle\Model\CalendarResource;
use Fedale\CalendarBundle\Model\EventInteractions;

#[AsCalendarSource('rooms')]
final class RoomSource implements EventSourceInterface, ResourceSourceInterface, IntentHandlerInterface, EventFinderInterface
{
    /** @var list<CalendarIntent> what the calendar handed over, for the tests to inspect */
    public static array $handled = [];

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
        yield new CalendarEvent('workshop', 'Workshop', $at('2026-10-10 10:00'), $at('2026-10-10 11:00'), resourceIds: ['b1'], interactions: new EventInteractions(movable: true, resizable: true, deletable: true), version: '7');
    }

    public function findEvent(string $id): ?CalendarEvent
    {
        foreach ($this->getEvents(new \Fedale\CalendarBundle\Model\CalendarQuery(new \Fedale\CalendarBundle\Model\DateRange(new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-11-01')), new \DateTimeZone('Europe/Rome'))) as $event) {
            if ($event->id === $id) {
                return $event;
            }
        }

        return null;
    }

    public function handle(CalendarIntent $intent): IntentResult
    {
        self::$handled[] = $intent;

        return match (true) {
            $intent instanceof SelectRange => IntentResult::openUrl('/bookings/new?room='.$intent->resourceId, 'booking-form'),
            $intent instanceof MoveEvent && $intent->toResourceId === 'c' => IntentResult::rejected(Rejection::Validation, 'The cinema is closed.'),
            default => IntentResult::accepted('Done.'),
        };
    }
}

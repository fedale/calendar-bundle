<?php

namespace Fedale\CalendarBundle\Tests\Integration\Fixtures;

use Fedale\CalendarBundle\Attribute\AsCalendarSource;
use Fedale\CalendarBundle\Contract\EventSourceInterface;
use Fedale\CalendarBundle\Model\CalendarEvent;
use Fedale\CalendarBundle\Model\CalendarQuery;
use Fedale\CalendarBundle\Model\EventLayer;
use Fedale\CalendarBundle\Model\EventStyle;
use Fedale\CalendarBundle\Model\EventVisibility;

#[AsCalendarSource('demo')]
final class DemoSource implements EventSourceInterface
{
    public function getEvents(CalendarQuery $query): iterable
    {
        $at = fn(string $datetime) => new \DateTimeImmutable($datetime, new \DateTimeZone('Europe/Rome'));

        yield new CalendarEvent('standup', 'Stand-up', $at('2026-10-12 09:00'), $at('2026-10-12 09:15'), url: '/events/standup');
        yield new CalendarEvent('trip', 'Business trip', $at('2026-10-14'), $at('2026-10-17'), allDay: true, style: new EventStyle(color: '#16a34a'));
        yield new CalendarEvent('private', 'Doctor', $at('2026-10-13 15:00'), $at('2026-10-13 16:00'), visibility: EventVisibility::BusyOnly);
        yield new CalendarEvent('closed', 'Office closed', $at('2026-10-19'), $at('2026-10-20'), allDay: true, layer: EventLayer::Background);
        yield new CalendarEvent('evil', 'Injected', $at('2026-10-21 10:00'), $at('2026-10-21 11:00'), style: new EventStyle(color: 'red; background: url(x)'));
        yield new CalendarEvent('november', 'Next month', $at('2026-11-03 10:00'), $at('2026-11-03 11:00'));
    }
}

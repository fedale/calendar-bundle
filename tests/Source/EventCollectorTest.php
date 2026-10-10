<?php

namespace Fedale\CalendarBundle\Tests\Source;

use Fedale\CalendarBundle\Contract\EventSourceInterface;
use Fedale\CalendarBundle\Model\CalendarEvent;
use Fedale\CalendarBundle\Model\CalendarQuery;
use Fedale\CalendarBundle\Model\DateRange;
use Fedale\CalendarBundle\Model\EventVisibility;
use Fedale\CalendarBundle\Options\CalendarOptions;
use Fedale\CalendarBundle\Options\OptionsMerger;
use Fedale\CalendarBundle\Source\CalendarItem;
use Fedale\CalendarBundle\Source\EventCollector;
use Fedale\CalendarBundle\Source\SourceRegistry;
use Fedale\CalendarBundle\Tests\Support\Items;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class EventCollectorTest extends TestCase
{
    /**
     * @param array<string, list<mixed>> $eventsBySource
     *
     * @return list<CalendarItem>
     */
    private function collect(array $eventsBySource): array
    {
        $factories = [];
        foreach ($eventsBySource as $key => $events) {
            $source = $this->createStub(EventSourceInterface::class);
            $source->method('getEvents')->willReturn($events);
            $factories[$key] = fn() => $source;
        }
        $options = CalendarOptions::fromArray(OptionsMerger::merge(CalendarOptions::DEFAULTS, ['integration' => ['sources' => array_keys($eventsBySource)]]));
        $query = new CalendarQuery(new DateRange(Items::at('2026-10-01'), Items::at('2026-11-01')), Items::tz());

        return (new EventCollector(new SourceRegistry(new ServiceLocator($factories))))->collect($options, $query);
    }

    public function testMergesSourcesInDisplayOrder(): void
    {
        $items = $this->collect([
            'shifts' => [Items::event('late', '2026-10-05 14:00', '2026-10-05 22:00')],
            'lessons' => [Items::event('early', '2026-10-05 08:00', '2026-10-05 10:00')],
        ]);

        self::assertSame(['lessons-early', 'shifts-late'], array_map(fn(CalendarItem $item) => $item->key(), $items));
    }

    public function testDropsHiddenEventsAndEventsOutsideTheRange(): void
    {
        $items = $this->collect(['shifts' => [
            new CalendarEvent('hidden', 'Hidden', Items::at('2026-10-05 08:00'), Items::at('2026-10-05 09:00'), visibility: EventVisibility::Hidden),
            Items::event('before', '2026-09-30 08:00', '2026-10-01 00:00'),
            Items::event('kept', '2026-10-31 23:00', '2026-11-01 01:00'),
        ]]);

        self::assertSame(['shifts-kept'], array_map(fn(CalendarItem $item) => $item->key(), $items));
    }

    public function testRejectsSourcesYieldingOtherObjects(): void
    {
        $this->expectExceptionMessage('Calendar source "shifts" must yield');

        $this->collect(['shifts' => [new \stdClass()]]);
    }
}

<?php

namespace Fedale\CalendarBundle\Tests\Integration;

use Fedale\CalendarBundle\Intent\MoveEvent;
use Fedale\CalendarBundle\Intent\SelectRange;
use Fedale\CalendarBundle\Tests\Integration\Fixtures\RoomSource;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class InteractionComponentTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        RoomSource::$handled = [];
    }

    public function testInteractiveEventsCarryWhatTheScriptNeeds(): void
    {
        $crawler = $this->createLiveComponent('Fedale:Calendar', ['calendar' => 'rooms'])->render()->crawler();

        $workshop = $crawler->filter('[data-cal-event="rooms-workshop"]');
        self::assertSame('rooms', $workshop->attr('data-cal-source'));
        self::assertSame('2026-10-10T10:00', $workshop->attr('data-cal-start'));
        self::assertSame('7', $workshop->attr('data-cal-version'));
        self::assertSame('b1', $workshop->attr('data-cal-resource'));
        self::assertNotNull($workshop->attr('data-cal-movable'));
        self::assertCount(1, $workshop->filter('[data-cal-resize]'));
        self::assertCount(1, $workshop->filter('[data-cal-delete]'));

        // The keynote enables nothing: no handles, no data for the script.
        self::assertNull($crawler->filter('[data-cal-event="rooms-keynote"]')->first()->attr('data-cal-source'));
        self::assertSame('x', $crawler->filter('.cal-tl-track[data-cal-resource="a"]')->attr('data-cal-track'));
        self::assertSame('true', $crawler->filter('.cal')->attr('data-cal-interact-selectable-value'));
        self::assertSame('15', $crawler->filter('.cal')->attr('data-cal-interact-snap-value'));
    }

    public function testAMoveReachesTheHandlerAsWallClockTimes(): void
    {
        $component = $this->createLiveComponent('Fedale:Calendar', ['calendar' => 'rooms']);
        $component->call('moveEvent', ['source' => 'rooms', 'id' => 'workshop', 'version' => '7', 'start' => '2026-10-10T14:00', 'end' => '2026-10-10T15:00', 'fromResource' => 'b1', 'toResource' => 'a']);

        $intent = RoomSource::$handled[0] ?? null;
        self::assertInstanceOf(MoveEvent::class, $intent);
        self::assertSame('2026-10-10T14:00:00+02:00', $intent->start->format(\DATE_ATOM));
        self::assertSame(['b1', 'a'], [$intent->fromResourceId, $intent->toResourceId]);
        self::assertTrue($intent->changesResource());
        self::assertSame('Done.', trim($component->render()->crawler()->filter('.cal-notice--success')->text()));
    }

    public function testARejectionIsShownAndTheEventStaysWhereItWas(): void
    {
        $component = $this->createLiveComponent('Fedale:Calendar', ['calendar' => 'rooms']);
        $component->call('moveEvent', ['source' => 'rooms', 'id' => 'workshop', 'version' => '7', 'start' => '2026-10-10T14:00', 'end' => '2026-10-10T15:00', 'fromResource' => 'b1', 'toResource' => 'c']);
        $crawler = $component->render()->crawler();

        self::assertSame('alert', $crawler->filter('.cal-notice')->attr('role'));
        self::assertSame('The cinema is closed.', trim($crawler->filter('.cal-notice--validation')->text()));
        self::assertSame('2026-10-10T10:00', $crawler->filter('[data-cal-event="rooms-workshop"]')->attr('data-cal-start'));
    }

    public function testTheCalendarRejectsWhatItCanTellBeforeTheHandler(): void
    {
        $component = $this->createLiveComponent('Fedale:Calendar', ['calendar' => 'rooms']);

        $component->call('moveEvent', ['source' => 'rooms', 'id' => 'workshop', 'version' => '6', 'start' => '2026-10-10T14:00', 'end' => '2026-10-10T15:00']);
        self::assertStringContainsString('modificato nel frattempo', $component->render()->crawler()->filter('.cal-notice--conflict')->text());

        $component->call('moveEvent', ['source' => 'rooms', 'id' => 'keynote', 'start' => '2026-10-10T14:00', 'end' => '2026-10-10T15:00']);
        self::assertCount(1, $component->render()->crawler()->filter('.cal-notice--forbidden'));

        $component->call('resizeEvent', ['source' => 'rooms', 'id' => 'workshop', 'version' => '7', 'start' => '2026-10-10T10:00', 'end' => '2026-10-10 25:00']);
        self::assertCount(1, $component->render()->crawler()->filter('.cal-notice--validation'));

        self::assertSame([], RoomSource::$handled);
    }

    public function testASelectionCanAnswerWithAUrlToOpen(): void
    {
        $component = $this->createLiveComponent('Fedale:Calendar', ['calendar' => 'rooms']);
        $component->call('select', ['start' => '2026-10-10T13:00', 'end' => '2026-10-10T14:30', 'resource' => 'b']);

        $intent = RoomSource::$handled[0] ?? null;
        self::assertInstanceOf(SelectRange::class, $intent);
        self::assertFalse($intent->allDay);
        self::assertSame('b', $intent->resourceId);
        self::assertSame(['url' => '/bookings/new?room=b', 'frame' => 'booking-form'], $component->getDispatchedBrowserEvent($component->render(), 'cal:open')['payload'] ?? null);
    }
}

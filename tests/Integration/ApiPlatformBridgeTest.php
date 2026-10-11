<?php

namespace Fedale\CalendarBundle\Tests\Integration;

use Fedale\CalendarBundle\Intent\DeleteEvent;
use Fedale\CalendarBundle\Intent\MoveEvent;
use Fedale\CalendarBundle\Intent\ResizeEvent;
use Fedale\CalendarBundle\Tests\Integration\Fixtures\RoomSource;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApiPlatformBridgeTest extends WebTestCase
{
    private KernelBrowser $client;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        RoomSource::$handled = [];
        $this->client = static::createClient();
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $body
     *
     * @return array<mixed>
     */
    private function json(string $method, string $uri, array $body = [], array $headers = [], int $status = 200): array
    {
        $server = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }
        $this->client->request($method, '/api'.$uri, server: $server, content: $body === [] ? null : json_encode($body));
        self::assertResponseStatusCodeSame($status, (string) $this->client->getResponse()->getContent());

        return json_decode((string) $this->client->getResponse()->getContent(), true) ?? [];
    }

    public function testListsTheCalendars(): void
    {
        $calendars = $this->json('GET', '/calendars');

        $rooms = array_values(array_filter($calendars, fn(array $calendar) => $calendar['id'] === 'rooms'))[0];
        self::assertSame(['timelineDay', 'timelineWeek', 'resourceTimeGridDay'], $rooms['views']);
        self::assertSame('Europe/Rome', $rooms['timezone']);
        self::assertTrue($rooms['selectable']);
        self::assertSame('demo', $this->json('GET', '/calendars/demo')['id']);
        $this->json('GET', '/calendars/nope', status: 404);
    }

    public function testEventsOfARangeFollowTheCalendarsRules(): void
    {
        // The end is exclusive: the 20th, to include the closure of the 19th.
        $events = $this->json('GET', '/calendars/demo/events?start=2026-10-12T00:00:00%2B02:00&end=2026-10-20T00:00:00%2B02:00');

        $byId = array_column($events, null, 'id');
        self::assertSame(['standup', 'private', 'trip', 'closed'], array_keys($byId));
        self::assertSame('2026-10-12T09:00:00+02:00', $byId['standup']['start']);
        self::assertSame('/events/standup', $byId['standup']['url']);
        // BusyOnly: no title, no url (API Platform leaves null fields out), flagged busy.
        self::assertArrayNotHasKey('title', $byId['private']);
        self::assertArrayNotHasKey('url', $byId['private']);
        self::assertTrue($byId['private']['busy']);
        self::assertTrue($byId['trip']['allDay']);
        self::assertSame('background', $byId['closed']['layer']);
        self::assertArrayNotHasKey('data', $byId['standup']);
    }

    public function testRangesAreRequiredBoundedAndAbsolute(): void
    {
        // Missing parameters are API Platform's validation errors; malformed or
        // unbounded ones are the bridge's.
        $this->json('GET', '/calendars/demo/events', status: 422);
        $this->json('GET', '/calendars/demo/events?start=2026-10-12T00:00:00&end=2026-10-19T00:00:00', status: 400);
        $this->json('GET', '/calendars/demo/events?start=2026-01-01T00:00:00Z&end=2028-01-01T00:00:00Z', status: 400);
    }

    public function testResourcesAndOneEventById(): void
    {
        $resources = $this->json('GET', '/calendars/rooms/resources');
        self::assertSame(['a', 'b', 'b1', 'c'], array_column($resources, 'id'));
        self::assertSame(['seats' => '10', 'building' => 'North'], $resources[2]['fields']);
        self::assertSame('b', $resources[2]['parentId']);

        $workshop = $this->json('GET', '/calendars/rooms/events/rooms/workshop');
        self::assertSame('7', $workshop['version']);
        self::assertTrue($workshop['interactions']['movable']);
        // The demo source cannot find events by id.
        $this->json('GET', '/calendars/demo/events/demo/standup', status: 404);
    }

    public function testAMoveIsAnIntentWithTheVersionFromIfMatch(): void
    {
        $answer = $this->json('POST', '/calendars/rooms/events/rooms/workshop/move', ['start' => '2026-10-10T14:00:00+02:00', 'end' => '2026-10-10T15:00:00+02:00', 'resource' => 'a'], ['If-Match' => '"7"']);

        self::assertTrue($answer['accepted']);
        self::assertSame('Done.', $answer['message']);
        $intent = RoomSource::$handled[0];
        self::assertInstanceOf(MoveEvent::class, $intent);
        self::assertSame(['7', 'b1', 'a'], [$intent->version, $intent->fromResourceId, $intent->toResourceId]);
        self::assertSame('2026-10-10 14:00', $intent->start->format('Y-m-d H:i'));
    }

    public function testRejectionsAreHttpErrors(): void
    {
        // Stale version, checked by the calendar before the handler.
        $this->json('POST', '/calendars/rooms/events/rooms/workshop/move', ['start' => '2026-10-10T14:00:00Z', 'end' => '2026-10-10T15:00:00Z', 'version' => '6'], status: 409);
        // The keynote enables no move.
        $this->json('POST', '/calendars/rooms/events/rooms/keynote/move', ['start' => '2026-10-10T14:00:00Z', 'end' => '2026-10-10T15:00:00Z'], status: 403);
        // The handler refuses the cinema.
        $error = $this->json('POST', '/calendars/rooms/events/rooms/workshop/move', ['start' => '2026-10-10T14:00:00Z', 'end' => '2026-10-10T15:00:00Z', 'resource' => 'c', 'version' => '7'], status: 422);
        self::assertSame('The cinema is closed.', $error['detail'] ?? $error['hydra:description'] ?? null);
        // A source of another calendar is out of reach.
        $this->json('POST', '/calendars/demo/events/rooms/workshop/move', ['start' => '2026-10-10T14:00:00Z', 'end' => '2026-10-10T15:00:00Z', 'version' => '7'], status: 403);

        self::assertCount(1, RoomSource::$handled);
    }

    public function testResizeKeepsTheStartAndDeleteTakesTheVersionFromIfMatch(): void
    {
        $this->json('POST', '/calendars/rooms/events/rooms/workshop/resize', ['end' => '2026-10-10T12:00:00+02:00', 'version' => '7']);
        $resize = RoomSource::$handled[0];
        self::assertInstanceOf(ResizeEvent::class, $resize);
        self::assertSame(['2026-10-10 10:00', '2026-10-10 12:00'], [$resize->start->format('Y-m-d H:i'), $resize->end->format('Y-m-d H:i')]);

        // A deletion answers 204 No Content, without the handler's message.
        $this->json('DELETE', '/calendars/rooms/events/rooms/workshop', headers: ['If-Match' => 'W/"7"'], status: 204);
        self::assertInstanceOf(DeleteEvent::class, RoomSource::$handled[1]);
        self::assertSame('7', RoomSource::$handled[1]->version);
    }

    public function testASelectionAnswersWhereToGoNext(): void
    {
        $answer = $this->json('POST', '/calendars/rooms/selections', ['start' => '2026-10-10T13:00:00+02:00', 'end' => '2026-10-10T14:00:00+02:00', 'resource' => 'b']);

        self::assertSame('/bookings/new?room=b', $answer['url']);
    }
}

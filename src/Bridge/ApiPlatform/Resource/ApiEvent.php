<?php

namespace Fedale\CalendarBundle\Bridge\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use Fedale\CalendarBundle\Bridge\ApiPlatform\Dto\IntentOutput;
use Fedale\CalendarBundle\Bridge\ApiPlatform\Dto\MoveInput;
use Fedale\CalendarBundle\Bridge\ApiPlatform\Dto\ResizeInput;
use Fedale\CalendarBundle\Bridge\ApiPlatform\State\EventProvider;
use Fedale\CalendarBundle\Bridge\ApiPlatform\State\IntentProcessor;

/**
 * An event of a calendar, as the calendar shows it: from the sources, with
 * the same visibility rules as the UI (hidden events left out, BusyOnly ones
 * without title and url) and never the source's opaque payload.
 *
 * Writes are intents for the source's handler, as gestures in the UI are:
 * move, resize, delete. Pass the version you read in `If-Match` (or in the
 * body): a change made meanwhile answers 409.
 */
#[ApiResource(
    shortName: 'CalendarEvent',
    operations: [
        new GetCollection(
            uriTemplate: '/calendars/{calendar}/events',
            uriVariables: ['calendar' => new Link(fromClass: ApiCalendar::class, identifiers: ['id'])],
            paginationEnabled: false,
            provider: EventProvider::class,
            parameters: [
                'start' => new QueryParameter(required: true, schema: ['type' => 'string', 'format' => 'date-time'], description: 'Start of the range (ISO 8601).'),
                'end' => new QueryParameter(required: true, schema: ['type' => 'string', 'format' => 'date-time'], description: 'End of the range, exclusive (ISO 8601).'),
                'resources' => new QueryParameter(schema: ['type' => 'string'], description: 'Comma-separated resource ids to narrow to.'),
            ],
        ),
        new Get(
            uriTemplate: '/calendars/{calendar}/events/{source}/{id}',
            uriVariables: ['calendar', 'source', 'id'],
            provider: EventProvider::class,
        ),
        new Post(
            uriTemplate: '/calendars/{calendar}/events/{source}/{id}/move',
            uriVariables: ['calendar', 'source', 'id'],
            status: 200,
            input: MoveInput::class,
            output: IntentOutput::class,
            read: false,
            processor: IntentProcessor::class,
            name: 'fedale_calendar_event_move',
        ),
        new Post(
            uriTemplate: '/calendars/{calendar}/events/{source}/{id}/resize',
            uriVariables: ['calendar', 'source', 'id'],
            status: 200,
            input: ResizeInput::class,
            output: IntentOutput::class,
            read: false,
            processor: IntentProcessor::class,
            name: 'fedale_calendar_event_resize',
        ),
        new Delete(
            uriTemplate: '/calendars/{calendar}/events/{source}/{id}',
            uriVariables: ['calendar', 'source', 'id'],
            read: false,
            processor: IntentProcessor::class,
        ),
    ],
)]
final class ApiEvent
{
    #[ApiProperty(identifier: true)]
    public string $calendar = '';

    #[ApiProperty(identifier: true)]
    public string $source = '';

    #[ApiProperty(identifier: true)]
    public string $id = '';

    /** null for a BusyOnly event. */
    public ?string $title = null;

    public bool $busy = false;

    /** In the calendar's timezone; midnight for all-day events. */
    public ?\DateTimeImmutable $start = null;

    /** Exclusive. */
    public ?\DateTimeImmutable $end = null;

    public bool $allDay = false;

    /** @var list<string> */
    public array $resourceIds = [];

    public ?string $type = null;

    public ?string $status = null;

    /** foreground or background */
    public string $layer = 'foreground';

    /** @var array{movable: bool, resizable: bool, clickable: bool, deletable: bool} */
    public array $interactions = ['movable' => false, 'resizable' => false, 'clickable' => false, 'deletable' => false];

    public ?string $version = null;

    public ?string $color = null;

    public ?string $cssClass = null;

    /** @var array<string, mixed> */
    public array $hints = [];

    public ?string $url = null;
}

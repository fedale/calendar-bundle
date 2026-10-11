<?php

namespace Fedale\CalendarBundle\Bridge\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use Fedale\CalendarBundle\Bridge\ApiPlatform\State\ResourceProvider;

/**
 * A resource of a calendar (a room, a person): a row of its timeline.
 */
#[ApiResource(
    shortName: 'CalendarResource',
    operations: [
        new GetCollection(
            uriTemplate: '/calendars/{calendar}/resources',
            uriVariables: ['calendar' => new Link(fromClass: ApiCalendar::class, identifiers: ['id'])],
            paginationEnabled: false,
            provider: ResourceProvider::class,
        ),
        new Get(
            uriTemplate: '/calendars/{calendar}/resources/{id}',
            uriVariables: ['calendar', 'id'],
            provider: ResourceProvider::class,
        ),
    ],
)]
final class ApiResourceRow
{
    #[ApiProperty(identifier: true)]
    public string $calendar = '';

    #[ApiProperty(identifier: true)]
    public string $id = '';

    public string $title = '';

    public ?string $parentId = null;

    public int $order = 0;

    public ?string $color = null;

    /** @var array<string, mixed> the fields the calendar shows in its resource columns */
    public array $fields = [];
}

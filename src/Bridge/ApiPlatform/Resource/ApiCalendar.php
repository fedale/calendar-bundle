<?php

namespace Fedale\CalendarBundle\Bridge\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Fedale\CalendarBundle\Bridge\ApiPlatform\State\CalendarProvider;

/**
 * A calendar of the application, as API clients see it: what they need to
 * ask for its events (its id and timezone) and what they can do with it.
 */
#[ApiResource(
    shortName: 'Calendar',
    operations: [
        new GetCollection(uriTemplate: '/calendars', paginationEnabled: false, provider: CalendarProvider::class),
        new Get(uriTemplate: '/calendars/{id}', provider: CalendarProvider::class),
    ],
)]
final class ApiCalendar
{
    #[ApiProperty(identifier: true)]
    public string $id = '';

    public ?string $label = null;

    /** @var list<string> enabled view variants, as in the toolbar */
    public array $views = [];

    public string $defaultView = '';

    /** Timezone the calendar is displayed in; event times are given in it. */
    public string $timezone = '';

    /** Whether empty ranges can be selected (POST .../selections). */
    public bool $selectable = false;
}

<?php

namespace Fedale\CalendarBundle\Bridge\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use Fedale\CalendarBundle\Bridge\ApiPlatform\Dto\IntentOutput;
use Fedale\CalendarBundle\Bridge\ApiPlatform\Dto\SelectionInput;
use Fedale\CalendarBundle\Bridge\ApiPlatform\State\IntentProcessor;

/**
 * Selecting an empty range, as dragging over empty time does in the UI:
 * a SelectRange intent for the calendar's select source (typically to
 * create something there). Needs `behavior.selectable`.
 */
#[ApiResource(
    shortName: 'CalendarSelection',
    operations: [
        new Post(
            uriTemplate: '/calendars/{calendar}/selections',
            uriVariables: ['calendar' => new Link(fromClass: ApiCalendar::class, identifiers: ['id'])],
            status: 200,
            input: SelectionInput::class,
            output: IntentOutput::class,
            read: false,
            processor: IntentProcessor::class,
        ),
    ],
)]
final class ApiSelection
{
    #[ApiProperty(identifier: true)]
    public string $calendar = '';
}

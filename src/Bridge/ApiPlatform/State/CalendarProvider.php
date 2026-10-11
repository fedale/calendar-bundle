<?php

namespace Fedale\CalendarBundle\Bridge\ApiPlatform\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Fedale\CalendarBundle\Bridge\ApiPlatform\Resource\ApiCalendar;
use Fedale\CalendarBundle\Definition\CalendarRegistry;

/**
 * @implements ProviderInterface<ApiCalendar>
 */
final class CalendarProvider implements ProviderInterface
{
    public function __construct(
        private readonly CalendarRegistry $calendars,
        private readonly CalendarApi $api,
    ) {
    }

    /**
     * @return ApiCalendar|list<ApiCalendar>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array
    {
        if ($operation instanceof CollectionOperationInterface) {
            return array_map($this->calendar(...), $this->api->readableIds());
        }

        return $this->calendar($this->api->calendarId($uriVariables, 'id'));
    }

    private function calendar(string $id): ApiCalendar
    {
        $options = $this->api->options($id);
        $calendar = new ApiCalendar();
        $calendar->id = $id;
        $calendar->label = $this->calendars->get($id)->label();
        $calendar->views = $options->enabledViews;
        $calendar->defaultView = $options->defaultView;
        $calendar->timezone = $this->api->timezone($options)->getName();
        $calendar->selectable = $options->selectable;

        return $calendar;
    }
}

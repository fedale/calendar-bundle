<?php

namespace Fedale\CalendarBundle\Tests\Integration\Fixtures;

use Fedale\CalendarBundle\Attribute\AsCalendar;
use Fedale\CalendarBundle\Definition\AbstractCalendar;

#[AsCalendar('rooms')]
final class RoomCalendar extends AbstractCalendar
{
    public function viewConfig(): array
    {
        return [
            'display' => [
                'views' => [
                    'enabled' => ['timelineDay', 'timelineWeek', 'resourceTimeGridDay'],
                    'default' => 'timelineDay',
                    'variants' => ['resourceTimeGridDay' => ['label' => 'Columns']],
                ],
                'theme' => 'bootstrap5',
                'businessHours' => [['days' => [1, 2, 3, 4, 5, 6], 'start' => '08:00', 'end' => '20:00']],
                'toolbar' => ['start' => 'add prev,today,next', 'center' => 'title', 'end' => 'views'],
                'buttons' => ['add' => ['label' => 'Add event', 'event' => 'rooms:add']],
                'resources' => [
                    'label' => 'Rooms',
                    'columns' => [['field' => 'title', 'label' => 'Room'], ['field' => 'seats', 'label' => 'Seats']],
                    'groupBy' => 'building',
                ],
            ],
            'behavior' => ['selectable' => true],
            'integration' => ['sources' => ['rooms']],
        ];
    }
}

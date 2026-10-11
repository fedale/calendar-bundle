<?php

namespace Fedale\CalendarBundle\Tests\Integration\Fixtures;

use Fedale\CalendarBundle\Attribute\AsCalendar;
use Fedale\CalendarBundle\Definition\AbstractCalendar;

#[AsCalendar('demo')]
final class DemoCalendar extends AbstractCalendar
{
    public function label(): string
    {
        return 'Demo calendar';
    }

    public function viewConfig(): array
    {
        return [
            'display' => [
                'views' => ['enabled' => ['month', 'listMonth', 'timeGridWeek', 'year']],
                'toolbar' => ['end' => 'views settings'],
            ],
            'integration' => ['sources' => ['demo']],
        ];
    }
}

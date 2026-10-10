<?php

namespace Fedale\CalendarBundle\Tests\View;

use Fedale\CalendarBundle\Options\CalendarOptions;
use Fedale\CalendarBundle\Options\OptionsMerger;
use Fedale\CalendarBundle\Tests\Support\Items;
use Fedale\CalendarBundle\Time\DateFormatter;
use Fedale\CalendarBundle\View\ViewContext;
use PHPUnit\Framework\TestCase;

abstract class ViewTestCase extends TestCase
{
    /**
     * @param array<string, mixed> $display
     */
    protected function context(string $date, array $display = [], string $today = '2026-10-10', string $locale = 'it'): ViewContext
    {
        return new ViewContext(
            date: Items::at($date),
            today: Items::at($today),
            options: CalendarOptions::fromArray(OptionsMerger::merge(CalendarOptions::DEFAULTS, ['display' => $display])),
            timezone: Items::tz(),
            locale: $locale,
        );
    }

    protected function formatter(): DateFormatter
    {
        return new DateFormatter();
    }
}

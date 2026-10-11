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
     * @param list<string>         $collapsed
     */
    protected function context(string $date, array $display = [], string $today = '2026-10-10', string $locale = 'it', string $view = 'month', string $now = '2026-10-10 12:00', array $collapsed = []): ViewContext
    {
        $options = CalendarOptions::fromArray(OptionsMerger::merge(CalendarOptions::DEFAULTS, ['display' => $display]));

        return new ViewContext(
            date: Items::at($date),
            today: Items::at($today),
            options: $options,
            timezone: Items::tz(),
            locale: $locale,
            variant: $options->variant($view),
            now: Items::at($now),
            collapsed: $collapsed,
        );
    }

    protected function formatter(): DateFormatter
    {
        return new DateFormatter();
    }
}

<?php

namespace Fedale\CalendarBundle\Tests\Options;

use Fedale\CalendarBundle\Options\CalendarOptions;
use Fedale\CalendarBundle\Options\ListRange;
use Fedale\CalendarBundle\Options\OptionsMerger;
use Fedale\CalendarBundle\Options\TimeFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CalendarOptionsTest extends TestCase
{
    public function testDefaultsAreValid(): void
    {
        $options = CalendarOptions::fromArray(CalendarOptions::DEFAULTS);

        self::assertSame(['month', 'list'], $options->enabledViews);
        self::assertSame('month', $options->defaultView);
        self::assertSame(1, $options->firstDay);
        self::assertSame(TimeFormat::H24, $options->timeFormat);
        self::assertSame(ListRange::Month, $options->listRange);
        self::assertSame(3, $options->dayMaxEvents);
    }

    public function testRejectsUnknownKeysWithTheirPath(): void
    {
        $this->expectExceptionMessage('Unknown calendar option "display.month.dayMaxEvent".');

        CalendarOptions::fromArray(OptionsMerger::merge(CalendarOptions::DEFAULTS, ['display' => ['month' => ['dayMaxEvent' => 2]]]));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidTrees(): iterable
    {
        yield 'default view not enabled' => [['display' => ['views' => ['enabled' => ['list'], 'default' => 'month']]], 'display.views.default'];
        yield 'no view enabled' => [['display' => ['views' => ['enabled' => []]]], 'display.views.enabled'];
        yield 'first day out of range' => [['display' => ['firstDay' => 0]], 'display.firstDay'];
        yield 'every day hidden' => [['display' => ['hiddenDays' => [1, 2, 3, 4, 5, 6, 7]]], 'display.hiddenDays'];
        yield 'bad timezone' => [['display' => ['timezone' => 'Mars/Olympus']], 'display.timezone'];
        yield 'bad next day threshold' => [['display' => ['nextDayThreshold' => '9:00']], 'display.nextDayThreshold'];
        yield 'bad time format' => [['display' => ['timeFormat' => '25h']], 'display.timeFormat'];
        yield 'non positive dayMaxEvents' => [['display' => ['month' => ['dayMaxEvents' => 0]]], 'display.month.dayMaxEvents'];
        yield 'subtree given a scalar' => [['display' => ['views' => 'month']], 'display.views'];
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('invalidTrees')]
    public function testRejectsInvalidValues(array $override, string $path): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"'.$path.'"');

        CalendarOptions::fromArray(OptionsMerger::merge(CalendarOptions::DEFAULTS, $override));
    }

    public function testIsHiddenUsesIsoDays(): void
    {
        $options = CalendarOptions::fromArray(OptionsMerger::merge(CalendarOptions::DEFAULTS, ['display' => ['hiddenDays' => [6, 7]]]));

        self::assertTrue($options->isHidden(new \DateTimeImmutable('2026-10-10'))); // Saturday
        self::assertFalse($options->isHidden(new \DateTimeImmutable('2026-10-12'))); // Monday
    }
}

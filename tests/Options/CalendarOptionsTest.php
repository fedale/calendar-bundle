<?php

namespace Fedale\CalendarBundle\Tests\Options;

use Fedale\CalendarBundle\Options\CalendarOptions;
use Fedale\CalendarBundle\Options\OptionsMerger;
use Fedale\CalendarBundle\Options\TimeFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CalendarOptionsTest extends TestCase
{
    public function testDefaultsAreValid(): void
    {
        $options = CalendarOptions::fromArray(CalendarOptions::DEFAULTS);

        self::assertSame(['month', 'listMonth'], $options->enabledViews);
        self::assertSame('timeline', $options->variant('timelineWeek')->type);
        self::assertSame(['range' => 'week', 'slotMinutes' => 360], $options->variant('timelineWeek')->params);
        self::assertSame([['prev', 'today', 'next']], $options->toolbar['start']);
        self::assertSame('month', $options->defaultView);
        self::assertSame(1, $options->firstDay);
        self::assertSame(TimeFormat::H24, $options->timeFormat);
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
        yield 'default view not enabled' => [['display' => ['views' => ['enabled' => ['listMonth'], 'default' => 'month']]], 'display.views.default'];
        yield 'undeclared view enabled' => [['display' => ['views' => ['enabled' => ['month', 'agenda']]]], 'display.views.enabled'];
        yield 'variant without type' => [['display' => ['views' => ['variants' => ['agenda' => ['range' => 'week']]]]], 'display.views.variants.agenda'];
        yield 'unknown toolbar item' => [['display' => ['toolbar' => ['start' => 'prev,add']]], 'display.toolbar.start'];
        yield 'button with url and event' => [['display' => ['buttons' => ['add' => ['label' => 'Add', 'url' => '/new', 'event' => 'x']]]], 'display.buttons.add'];
        yield 'unsafe area width' => [['display' => ['resources' => ['width' => '10rem; color: red']]], 'display.resources.width'];
        yield 'column without field' => [['display' => ['resources' => ['columns' => [['label' => 'Room']]]]], 'display.resources.columns.0'];
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

    public function testCustomVariantsButtonsAndToolbar(): void
    {
        $options = CalendarOptions::fromArray(OptionsMerger::merge(CalendarOptions::DEFAULTS, ['display' => [
            'views' => [
                'enabled' => ['timelineDay', 'fortnight'],
                'default' => 'timelineDay',
                'variants' => [
                    'timelineDay' => ['dayStart' => '08:00'],
                    'fortnight' => ['type' => 'timeline', 'range' => 'week', 'label' => 'Two weeks'],
                ],
            ],
            'buttons' => ['add' => ['label' => 'Add event', 'event' => 'rooms:add']],
            'toolbar' => ['start' => 'add prev,today,next', 'end' => 'timelineDay fortnight'],
        ]]));

        // A built-in variant overridden key by key keeps its other parameters.
        self::assertSame(['range' => 'day', 'slotMinutes' => 60, 'dayStart' => '08:00'], $options->variant('timelineDay')->params);
        self::assertSame('Two weeks', $options->variant('fortnight')->label);
        self::assertSame([['add'], ['prev', 'today', 'next']], $options->toolbar['start']);
        self::assertSame('rooms:add', $options->buttons['add']->event);
    }

    public function testIsHiddenUsesIsoDays(): void
    {
        $options = CalendarOptions::fromArray(OptionsMerger::merge(CalendarOptions::DEFAULTS, ['display' => ['hiddenDays' => [6, 7]]]));

        self::assertTrue($options->isHidden(new \DateTimeImmutable('2026-10-10'))); // Saturday
        self::assertFalse($options->isHidden(new \DateTimeImmutable('2026-10-12'))); // Monday
    }
}

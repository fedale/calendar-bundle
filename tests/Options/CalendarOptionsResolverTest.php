<?php

namespace Fedale\CalendarBundle\Tests\Options;

use Fedale\CalendarBundle\Contract\CalendarViewInterface;
use Fedale\CalendarBundle\Contract\EventSourceInterface;
use Fedale\CalendarBundle\Definition\AbstractCalendar;
use Fedale\CalendarBundle\Definition\CalendarRegistry;
use Fedale\CalendarBundle\Options\CalendarOptionsResolver;
use Fedale\CalendarBundle\Source\SourceRegistry;
use Fedale\CalendarBundle\View\ViewRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class CalendarOptionsResolverTest extends TestCase
{
    /**
     * @param array<string, mixed>                $viewConfig
     * @param array<string, mixed>                $defaults
     * @param array<string, array<string, mixed>> $calendarConfigs
     */
    private function resolver(array $viewConfig, array $defaults = [], array $calendarConfigs = []): CalendarOptionsResolver
    {
        $definition = new class($viewConfig) extends AbstractCalendar {
            /** @param array<string, mixed> $config */
            public function __construct(private readonly array $config)
            {
            }

            public function viewConfig(): array
            {
                return $this->config;
            }
        };
        $source = $this->createStub(EventSourceInterface::class);
        $view = $this->createStub(CalendarViewInterface::class);

        return new CalendarOptionsResolver(
            new CalendarRegistry(new ServiceLocator(['shifts' => fn() => $definition])),
            new ViewRegistry(new ServiceLocator(['month' => fn() => $view, 'list' => fn() => $view])),
            new SourceRegistry(new ServiceLocator(['shifts' => fn() => $source])),
            $defaults,
            $calendarConfigs,
        );
    }

    public function testPrecedenceIsDefaultsThenYamlThenViewConfig(): void
    {
        $options = $this->resolver(
            viewConfig: ['display' => ['firstDay' => 7]],
            defaults: ['display' => ['firstDay' => 3, 'weekNumbers' => true, 'timeFormat' => '12h']],
            calendarConfigs: ['shifts' => ['display' => ['firstDay' => 5, 'timeFormat' => '24h']]],
        )->resolve('shifts');

        self::assertSame(7, $options->firstDay);          // viewConfig()
        self::assertSame('24h', $options->timeFormat->value); // calendars.<id>
        self::assertTrue($options->weekNumbers);          // defaults
        self::assertSame(3, $options->dayMaxEvents);      // bundle default
    }

    public function testNamesTheCalendarInValidationErrors(): void
    {
        $this->expectExceptionMessage('Invalid options for calendar "shifts": Unknown calendar option "display.colour".');

        $this->resolver(['display' => ['colour' => 'red']])->resolve('shifts');
    }

    public function testRejectsUnknownViews(): void
    {
        $this->expectExceptionMessage('Calendar "shifts" enables the unknown view "year"');

        $this->resolver(['display' => ['views' => ['enabled' => ['month', 'year']]]])->resolve('shifts');
    }

    public function testRejectsUnknownSources(): void
    {
        $this->expectExceptionMessage('Calendar "shifts" uses the unknown source "lessons"');

        $this->resolver(['integration' => ['sources' => ['shifts', 'lessons']]])->resolve('shifts');
    }

    public function testRejectsUnknownCalendars(): void
    {
        $this->expectExceptionMessage('Unknown calendar "seats"');

        $this->resolver([])->resolve('seats');
    }
}

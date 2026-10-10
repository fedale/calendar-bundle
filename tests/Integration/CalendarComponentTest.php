<?php

namespace Fedale\CalendarBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class CalendarComponentTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testRendersTheMonthOfTodayByDefault(): void
    {
        $crawler = $this->createLiveComponent('Fedale:Calendar', ['calendar' => 'demo'])->render()->crawler();

        self::assertSame('ottobre 2026', $crawler->filter('.cal-toolbar__title')->text());
        self::assertSame('Demo calendar', $crawler->filter('.cal')->attr('aria-label'));
        self::assertCount(5, $crawler->filter('.cal-month__week'));
        self::assertSame('10', $crawler->filter('[aria-current="date"]')->text());
        self::assertNotNull($crawler->filter('[data-live-action-param="today"]')->attr('disabled'));

        $standup = $crawler->filter('[data-cal-event="demo-standup"]');
        self::assertSame('a', $standup->nodeName());
        self::assertSame('/events/standup', $standup->attr('href'));
        self::assertSame('09:00', $standup->filter('.cal-event__time')->text());

        $trip = $crawler->filter('[data-cal-event="demo-trip"]');
        self::assertStringContainsString('--cal-span: 3;', (string) $trip->attr('style'));
        self::assertStringContainsString('--cal-event-color: #16a34a;', (string) $trip->attr('style'));

        self::assertCount(1, $crawler->filter('.cal-band'));
        self::assertCount(0, $crawler->filter('[data-cal-event="demo-november"]'));
    }

    public function testBusyEventsShowNeitherTitleNorLink(): void
    {
        $html = $this->createLiveComponent('Fedale:Calendar', ['calendar' => 'demo'])->render()->toString();

        self::assertStringNotContainsString('Doctor', $html);
        self::assertStringContainsString('Occupato', $html);
    }

    public function testUnsafeColorsAreDropped(): void
    {
        $crawler = $this->createLiveComponent('Fedale:Calendar', ['calendar' => 'demo'])->render()->crawler();

        self::assertStringNotContainsString('url(x)', (string) $crawler->filter('[data-cal-event="demo-evil"]')->attr('style'));
    }

    public function testNavigatesAndSwitchesView(): void
    {
        $component = $this->createLiveComponent('Fedale:Calendar', ['calendar' => 'demo']);

        $component->call('next');
        self::assertSame('2026-11-01', $component->component()->date);
        self::assertSame('novembre 2026', $component->render()->crawler()->filter('.cal-toolbar__title')->text());
        self::assertNull($component->render()->crawler()->filter('[data-live-action-param="today"]')->attr('disabled'));

        $component->call('changeView', ['view' => 'list']);
        $crawler = $component->render()->crawler();
        self::assertSame('list', $component->component()->view);
        self::assertCount(1, $crawler->filter('.cal-list__day'));
        self::assertStringContainsString('Next month', $crawler->filter('.cal-list')->text());

        $component->call('today');
        self::assertNull($component->component()->date);
        self::assertSame('ottobre 2026', $component->render()->crawler()->filter('.cal-toolbar__title')->text());
    }

    public function testFallsBackOnStaleOrForgedUrlState(): void
    {
        $crawler = $this->createLiveComponent('Fedale:Calendar', ['calendar' => 'demo', 'view' => 'year', 'date' => '2026-02-31'])->render()->crawler();

        self::assertSame('ottobre 2026', $crawler->filter('.cal-toolbar__title')->text());
        self::assertCount(1, $crawler->filter('.cal-month'));
    }

    public function testListViewDescribesMultiDayEvents(): void
    {
        $crawler = $this->createLiveComponent('Fedale:Calendar', ['calendar' => 'demo', 'view' => 'list'])->render()->crawler();

        // 12, 13, 14-16 (three-day trip), 21.
        self::assertCount(6, $crawler->filter('.cal-list__day'));
        self::assertSame('Tutto il giorno', $crawler->filter('.cal-list__entry--all_day .cal-list__time')->first()->text());
        self::assertSame('09:00 – 09:15', $crawler->filter('.cal-list__entry--timed .cal-list__time')->first()->text());
        // The time column already shows it: the event itself must not repeat it.
        self::assertCount(0, $crawler->filter('.cal-list .cal-event__time'));
    }
}

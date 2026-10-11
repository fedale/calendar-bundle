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

        $component->call('changeView', ['view' => 'listMonth']);
        $crawler = $component->render()->crawler();
        self::assertSame('listMonth', $component->component()->view);
        self::assertCount(1, $crawler->filter('.cal-list__day'));
        self::assertStringContainsString('Next month', $crawler->filter('.cal-list')->text());

        $component->call('today');
        self::assertNull($component->component()->date);
        self::assertSame('ottobre 2026', $component->render()->crawler()->filter('.cal-toolbar__title')->text());
    }

    public function testFallsBackOnStaleOrForgedUrlState(): void
    {
        $crawler = $this->createLiveComponent('Fedale:Calendar', ['calendar' => 'demo', 'view' => 'agenda', 'date' => '2026-02-31'])->render()->crawler();

        self::assertSame('ottobre 2026', $crawler->filter('.cal-toolbar__title')->text());
        self::assertCount(1, $crawler->filter('.cal-month'));
    }

    public function testTimeGridWeekPlacesTimedAndAllDayEvents(): void
    {
        $crawler = $this->createLiveComponent('Fedale:Calendar', ['calendar' => 'demo', 'view' => 'timeGridWeek', 'date' => '2026-10-14'])->render()->crawler();

        self::assertSame('12 ott – 18 ott 2026', $crawler->filter('.cal-toolbar__title')->text());
        self::assertCount(7, $crawler->filter('.cal-tg__day'));
        // One label per full hour, the midnight at the top left out: 01:00 to 23:00.
        self::assertCount(23, $crawler->filter('.cal-tg__labels .cal-tg__label'));
        // Stand-up, Monday 09:00-09:15, in the first column.
        $standup = $crawler->filter('.cal-tg__col')->eq(0)->filter('[data-cal-event="demo-standup"]');
        self::assertStringContainsString('--cal-top: 37.5%; --cal-height: 1.0417%;', (string) $standup->attr('style'));
        // The three-day trip is a bar in the all-day row, Wednesday to Friday.
        self::assertStringContainsString('--cal-start: 3; --cal-span: 3;', (string) $crawler->filter('.cal-tg__all-day-content [data-cal-event="demo-trip"]')->attr('style'));
    }

    public function testYearViewOpensADayInTheDayView(): void
    {
        $component = $this->createLiveComponent('Fedale:Calendar', ['calendar' => 'demo', 'view' => 'year']);
        $crawler = $component->render()->crawler();

        self::assertSame('2026', $crawler->filter('.cal-toolbar__title')->text());
        self::assertCount(12, $crawler->filter('.cal-year__month'));
        // The trip covers the 14th to the 16th; other October days have one event at most.
        $day = $crawler->filter('[data-live-date-param="2026-10-14"]');
        self::assertStringContainsString('cal-year__day--level-4', (string) $day->attr('class'));
        // No day view enabled: days open in the month.
        self::assertSame('month', $day->attr('data-live-view-param'));

        $component->call('goTo', ['date' => '2026-10-14', 'view' => 'month']);
        self::assertSame(['2026-10-14', null], [$component->component()->date, $component->component()->view]);
    }

    public function testAThemeDressesTheControls(): void
    {
        $crawler = $this->createLiveComponent('Fedale:Calendar', ['calendar' => 'rooms'])->render()->crawler();

        self::assertSame('btn btn-outline-secondary', $crawler->filter('[data-live-action-param="today"]')->attr('class'));
        self::assertSame('btn btn-outline-secondary active', $crawler->filter('[data-live-view-param="timelineDay"]')->attr('class'));
        self::assertSame('btn btn-primary', $crawler->filter('[data-controller="cal-button"]')->attr('class'));
        self::assertStringContainsString('btn-group', (string) $crawler->filter('.cal-toolbar__zone--end .cal-toolbar__group')->attr('class'));
    }

    public function testListViewDescribesMultiDayEvents(): void
    {
        $crawler = $this->createLiveComponent('Fedale:Calendar', ['calendar' => 'demo', 'view' => 'listMonth'])->render()->crawler();

        // 12, 13, 14-16 (three-day trip), 21.
        self::assertCount(6, $crawler->filter('.cal-list__day'));
        self::assertSame('Tutto il giorno', $crawler->filter('.cal-list__entry--all_day .cal-list__time')->first()->text());
        self::assertSame('09:00 – 09:15', $crawler->filter('.cal-list__entry--timed .cal-list__time')->first()->text());
        // The time column already shows it: the event itself must not repeat it.
        self::assertCount(0, $crawler->filter('.cal-list .cal-event__time'));
    }
}

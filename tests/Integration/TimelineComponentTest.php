<?php

namespace Fedale\CalendarBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class TimelineComponentTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /** @return list<string> */
    private function rowLabels(Crawler $crawler): array
    {
        return $crawler->filter('.cal-tl-body .cal-tl-row .cal-tl-area')->each(fn(Crawler $area) => preg_replace('/\s+/', ' ', trim($area->text())));
    }

    public function testRendersGroupedNestedRowsWithTheirEvents(): void
    {
        $crawler = $this->createLiveComponent('Fedale:Calendar', ['calendar' => 'rooms'])->render()->crawler();

        self::assertSame('Sabato 10 ottobre 2026', ucfirst($crawler->filter('.cal-toolbar__title')->text()));
        self::assertSame(['North', 'Auditorium A 40', 'Auditorium B 40', 'Room B1 10', 'South', 'Cinema 120'], $this->rowLabels($crawler));
        self::assertSame('Rooms', $crawler->filter('.cal-tl-area__label')->text());
        self::assertCount(24, $crawler->filter('.cal-tl-head__slot'));

        // The keynote is booked in two rooms: one bar on each row.
        self::assertCount(2, $crawler->filter('[data-cal-event="rooms-keynote"]'));
        self::assertStringContainsString('--cal-left: 37.5%; --cal-width: 12.5%;', (string) $crawler->filter('[data-cal-event="rooms-keynote"]')->attr('style'));

        // Frozen clock: 12:00 is half way through the day.
        self::assertStringContainsString('--cal-left: 50%;', (string) $crawler->filter('.cal-tl-now')->attr('style'));
    }

    public function testCollapsingAGroupOrAParentHidesItsRows(): void
    {
        $component = $this->createLiveComponent('Fedale:Calendar', ['calendar' => 'rooms']);

        $component->call('toggle', ['key' => 'r:b']);
        self::assertSame(['North', 'Auditorium A 40', 'Auditorium B 40', 'South', 'Cinema 120'], $this->rowLabels($component->render()->crawler()));

        $component->call('toggle', ['key' => 'g:North']);
        self::assertSame(['North', 'South', 'Cinema 120'], $this->rowLabels($component->render()->crawler()));
        self::assertSame('false', $component->render()->crawler()->filter('[data-live-key-param="g:North"]')->attr('aria-expanded'));

        $component->call('toggle', ['key' => 'g:North']);
        self::assertCount(5, $this->rowLabels($component->render()->crawler()));
    }

    public function testToolbarFollowsItsLayoutWithTheCustomButton(): void
    {
        $crawler = $this->createLiveComponent('Fedale:Calendar', ['calendar' => 'rooms'])->render()->crawler();

        $start = $crawler->filter('.cal-toolbar__zone--start .cal-toolbar__group');
        self::assertCount(2, $start);
        self::assertSame('Add event', trim($start->eq(0)->text()));
        self::assertSame('rooms:add', $start->eq(0)->filter('button')->attr('data-cal-button-event-value'));
        self::assertSame(['Giorno', 'Settimana'], $crawler->filter('.cal-toolbar__zone--end button')->each(fn(Crawler $button) => trim($button->text())));
    }

    public function testWeekVariantGroupsTheHeaderByDay(): void
    {
        $component = $this->createLiveComponent('Fedale:Calendar', ['calendar' => 'rooms']);
        $component->call('changeView', ['view' => 'timelineWeek']);
        $crawler = $component->render()->crawler();

        self::assertCount(7, $crawler->filter('.cal-tl-head__group'));
        self::assertCount(28, $crawler->filter('.cal-tl-head__slot'));
    }
}

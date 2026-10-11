<?php

namespace Fedale\CalendarBundle\Tests\Integration;

use Fedale\CalendarBundle\Tests\Integration\Fixtures\MemoryScopedStore;
use Fedale\CalendarBundle\UiSettings\UiSettingsResolver;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class UiSettingsTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        MemoryScopedStore::$data = [];
    }

    /**
     * @param array<string, string|null> $values
     */
    private function store(string $scope, array $values): void
    {
        foreach ($values as $key => $value) {
            MemoryScopedStore::$data[UiSettingsResolver::NAMESPACE][0][$scope][$key] = $value;
        }
    }

    private function render(string $calendar = 'demo'): Crawler
    {
        return $this->createLiveComponent('Fedale:Calendar', ['calendar' => $calendar])->render()->crawler();
    }

    public function testGlobalValuesApplyToEveryCalendarAndCalendarValuesWin(): void
    {
        $this->store('global', ['first_day' => '7', 'default_view' => 'listMonth']);
        $this->store('calendar:demo', ['default_view' => 'timeGridWeek']);

        $crawler = $this->render();

        // Per calendar: the week grid; global: weeks start on Sunday (the 4th).
        self::assertCount(1, $crawler->filter('.cal-tg'));
        self::assertSame('4 ott – 10 ott 2026', $crawler->filter('.cal-toolbar__title')->text());
    }

    public function testValuesACalendarCannotHonourAreSkipped(): void
    {
        // The rooms calendar enables no list view: the global value falls through.
        $this->store('global', ['default_view' => 'listMonth']);

        self::assertCount(1, $this->render('rooms')->filter('.cal-timeline'));
    }

    public function testSettingsThatEditAnOptionKeepTheRest(): void
    {
        $this->store('calendar:demo', ['weekends' => 'hide', 'week_numbers' => 'show', 'time_format' => '12h']);

        $crawler = $this->render();

        self::assertCount(5, $crawler->filter('.cal-month__head .cal-month__weekday'));
        self::assertCount(5, $crawler->filter('.cal-month__week .cal-month__week-number'));
        // ICU puts a narrow no-break space before AM.
        self::assertSame('9:00 AM', str_replace("\u{202F}", ' ', $crawler->filter('[data-cal-event="demo-standup"] .cal-event__time')->text()));
    }

    public function testTheToolbarOffersTheDialogWhenEnabled(): void
    {
        $crawler = $this->render();

        self::assertCount(1, $crawler->filter('[data-action="cal-ui-settings#open"]'));
        self::assertSame('/calendar/_settings/?scope=demo', $crawler->filter('.cal')->attr('data-cal-ui-settings-url-value'));
        self::assertCount(1, $crawler->filter('dialog.cal-dialog[data-live-ignore]'));
    }

    public function testTheDialogFormSavesAScope(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/calendar/_settings/?scope=demo');

        self::assertResponseIsSuccessful();
        self::assertSame(['demo', '_global', 'rooms'], array_values(array_intersect(['demo', '_global', 'rooms'], $crawler->filter('select[name="scope"] option')->each(fn(Crawler $option) => $option->attr('value')))) ?: []);
        // The demo calendar has a month and a time grid: week numbers and slots are offered.
        self::assertCount(1, $crawler->filter('select[name="cal_ui_settings[week_numbers]"]'));
        self::assertCount(1, $crawler->filter('select[name="cal_ui_settings[slot_minutes]"]'));

        $form = $crawler->filter('form.cal-settings__form')->form(['cal_ui_settings[first_day]' => '7', 'cal_ui_settings[slot_minutes]' => '60']);
        $crawler = $client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-cal-ui-settings-saved]'));
        self::assertSame(['first_day' => '7', 'slot_minutes' => '60'], MemoryScopedStore::$data[UiSettingsResolver::NAMESPACE][0]['calendar:demo']);
    }

    public function testTheRoomsCalendarIsNotOfferedMonthOnlySettings(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/calendar/_settings/?scope=rooms');

        self::assertCount(0, $crawler->filter('select[name="cal_ui_settings[week_numbers]"]'));
        self::assertCount(1, $crawler->filter('select[name="cal_ui_settings[default_view]"]'));
    }
}

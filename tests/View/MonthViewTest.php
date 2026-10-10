<?php

namespace Fedale\CalendarBundle\Tests\View;

use Fedale\CalendarBundle\Model\EventLayer;
use Fedale\CalendarBundle\Tests\Support\Items;
use Fedale\CalendarBundle\View\Model\MonthDay;
use Fedale\CalendarBundle\View\MonthView;

final class MonthViewTest extends ViewTestCase
{
    public function testRangeCoversWholeWeeks(): void
    {
        $view = new MonthView($this->formatter());

        // October 2026: Thursday 1 to Saturday 31.
        $monday = $view->range($this->context('2026-10-10'));
        self::assertSame('2026-09-28', $monday->start->format('Y-m-d'));
        self::assertSame('2026-11-02', $monday->end->format('Y-m-d'));

        $sunday = $view->range($this->context('2026-10-10', ['firstDay' => 7]));
        self::assertSame('2026-09-27', $sunday->start->format('Y-m-d'));
        self::assertSame('2026-11-01', $sunday->end->format('Y-m-d'));
    }

    public function testNavigatesByMonthFromAnyDay(): void
    {
        $view = new MonthView($this->formatter());

        self::assertSame('2026-11-01', $view->navigate($this->context('2026-10-31'), 1)->format('Y-m-d'));
        self::assertSame('2026-09-01', $view->navigate($this->context('2026-10-31'), -1)->format('Y-m-d'));
    }

    public function testTitleIsLocalized(): void
    {
        $view = new MonthView($this->formatter());

        self::assertSame('ottobre 2026', $view->title($this->context('2026-10-10')));
        self::assertSame('October 2026', $view->title($this->context('2026-10-10', locale: 'en')));
    }

    public function testBuildsWeeksWithDayFlags(): void
    {
        $model = (new MonthView($this->formatter()))->build($this->context('2026-10-10', ['hiddenDays' => [7]]), []);

        self::assertCount(5, $model->weeks);
        self::assertCount(6, $model->weekdays);
        self::assertSame(40, $model->weeks[0]->number);

        $days = $model->weeks[0]->days;
        self::assertFalse($days[0]->inMonth);              // Mon 28 September
        self::assertTrue($days[3]->inMonth);               // Thu 1 October
        self::assertTrue($days[5]->isWeekend);             // Sat 3 October

        $today = array_values(array_filter($model->weeks[1]->days, fn(MonthDay $day) => $day->isToday));
        self::assertSame('2026-10-10', $today[0]->date->format('Y-m-d'));
    }

    public function testSplitsForegroundAndBackgroundAndListsOverflowingDays(): void
    {
        $items = [
            Items::item('closed', '2026-10-05', '2026-10-07', allDay: true, layer: EventLayer::Background),
        ];
        for ($i = 0; $i < 5; ++$i) {
            $items[] = Items::item("e$i", "2026-10-05 0$i:00", "2026-10-05 0$i:30");
        }

        $model = (new MonthView($this->formatter()))->build($this->context('2026-10-10', ['month' => ['dayMaxEvents' => 3]]), $items);
        $week = $model->weeks[1];

        self::assertCount(1, $week->backgrounds);
        self::assertCount(3, $week->segments);
        self::assertTrue($week->hasOverflow);
        self::assertSame(2, $week->days[0]->overflowCount);
        self::assertCount(5, $week->days[0]->items);
        self::assertSame([], $week->days[1]->items);
    }
}

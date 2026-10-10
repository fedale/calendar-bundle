<?php

namespace Fedale\CalendarBundle\Tests\View;

use Fedale\CalendarBundle\Model\EventLayer;
use Fedale\CalendarBundle\Tests\Support\Items;
use Fedale\CalendarBundle\View\ListView;
use Fedale\CalendarBundle\View\Model\ListDay;
use Fedale\CalendarBundle\View\Model\ListEntry;

final class ListViewTest extends ViewTestCase
{
    public function testMonthAndWeekRanges(): void
    {
        $view = new ListView($this->formatter());

        $month = $view->range($this->context('2026-10-10'));
        self::assertSame(['2026-10-01', '2026-11-01'], [$month->start->format('Y-m-d'), $month->end->format('Y-m-d')]);

        $week = $view->range($this->context('2026-10-10', ['list' => ['range' => 'week']]));
        self::assertSame(['2026-10-05', '2026-10-12'], [$week->start->format('Y-m-d'), $week->end->format('Y-m-d')]);
        self::assertSame('2026-10-17', $view->navigate($this->context('2026-10-10', ['list' => ['range' => 'week']]), 1)->format('Y-m-d'));
    }

    public function testWeekTitleShowsBothEnds(): void
    {
        $title = (new ListView($this->formatter()))->title($this->context('2026-10-10', ['list' => ['range' => 'week']], locale: 'en'));

        self::assertSame('Oct 5 – Oct 11, 2026', $title);
    }

    public function testListsMultiDayEventsOnEveryDayWithTheirKind(): void
    {
        $model = (new ListView($this->formatter()))->build($this->context('2026-10-10'), [
            Items::item('trip', '2026-10-05 18:00', '2026-10-07 09:00'),
            Items::item('meeting', '2026-10-06 10:00', '2026-10-06 11:00'),
            Items::item('holiday', '2026-10-08', '2026-10-09', allDay: true),
            Items::item('closed', '2026-10-08', '2026-10-09', allDay: true, layer: EventLayer::Background),
        ]);

        $kinds = [];
        foreach ($model->days as $day) {
            $kinds[$day->date->format('d')] = array_map(fn(ListEntry $entry) => $entry->item->event->id.':'.$entry->kind->value, $day->entries);
        }

        self::assertSame([
            '05' => ['trip:starts'],
            '06' => ['trip:continues', 'meeting:timed'],
            '07' => ['trip:ends'],
            '08' => ['holiday:all_day'],
        ], $kinds);
    }

    public function testSkipsHiddenDays(): void
    {
        $model = (new ListView($this->formatter()))->build($this->context('2026-10-10', ['hiddenDays' => [6]]), [
            Items::item('sat', '2026-10-10 10:00', '2026-10-10 11:00'),
        ]);

        self::assertSame([], array_map(fn(ListDay $day) => $day->date, $model->days));
    }
}

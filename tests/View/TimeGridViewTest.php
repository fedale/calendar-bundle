<?php

namespace Fedale\CalendarBundle\Tests\View;

use Fedale\CalendarBundle\Model\CalendarEvent;
use Fedale\CalendarBundle\Model\CalendarResource;
use Fedale\CalendarBundle\Options\ViewVariant;
use Fedale\CalendarBundle\Source\CalendarItem;
use Fedale\CalendarBundle\Tests\Support\Items;
use Fedale\CalendarBundle\View\TimeGrid\GridColumn;
use Fedale\CalendarBundle\View\TimeGridView;

final class TimeGridViewTest extends ViewTestCase
{
    /**
     * @param list<string> $resources
     */
    private function item(string $id, string $start, string $end, array $resources = [], bool $allDay = false): CalendarItem
    {
        return CalendarItem::fromEvent('test', new CalendarEvent($id, $id, Items::at($start), Items::at($end), allDay: $allDay, resourceIds: $resources), Items::tz());
    }

    public function testWeekColumnsSplitOvernightEvents(): void
    {
        $model = (new TimeGridView($this->formatter()))->build($this->context('2026-10-10', view: 'timeGridWeek'), [
            $this->item('night', '2026-10-09 22:00', '2026-10-10 06:00'),
        ]);

        self::assertCount(7, $model->columns);
        self::assertCount(48, $model->slots);
        $friday = $model->columns[4];
        $saturday = $model->columns[5];
        self::assertSame('2026-10-09', $friday->day->format('Y-m-d'));
        self::assertEqualsWithDelta(22 / 24, $friday->events[0]->top, 1e-12);
        self::assertTrue($friday->events[0]->continuesAfter);
        self::assertSame(0.0, $saturday->events[0]->top);
        self::assertEqualsWithDelta(6 / 24, $saturday->events[0]->height, 1e-12);
        self::assertTrue($saturday->events[0]->continuesBefore);
    }

    public function testAllDayEventsGoToTheAllDayRow(): void
    {
        $model = (new TimeGridView($this->formatter()))->build($this->context('2026-10-10', view: 'timeGridWeek'), [
            $this->item('trip', '2026-10-07', '2026-10-09', allDay: true),
        ]);

        self::assertSame([], array_merge(...array_map(fn(GridColumn $column) => $column->events, $model->columns)));
        self::assertNotNull($model->allDay);
        self::assertSame([2, 2], [$model->allDay->segments[0]->start, $model->allDay->segments[0]->span]);
    }

    public function testResourceColumnsAreResourcesThenDays(): void
    {
        $resources = [new CalendarResource('b', 'Room B'), new CalendarResource('a', 'Room A')];
        $model = (new TimeGridView($this->formatter()))->build($this->context('2026-10-10', view: 'resourceTimeGridWeek'), [
            $this->item('talk', '2026-10-06 09:00', '2026-10-06 10:00', ['b']),
            $this->item('away', '2026-10-06', '2026-10-08', ['b'], allDay: true),
        ], $resources);

        self::assertCount(14, $model->columns);
        self::assertSame(['Room A', 'Room B'], array_map(fn(array $header) => $header['resource']->title, $model->resourceHeaders));
        self::assertSame(7, $model->resourceHeaders[0]['span']);
        self::assertCount(0, $model->columns[1]->events);   // Room A, Tuesday
        self::assertCount(1, $model->columns[8]->events);   // Room B, Tuesday
        self::assertSame([8, 2], [$model->allDay?->segments[0]->start, $model->allDay?->segments[0]->span]);
    }

    public function testWindowBusinessHoursNowAndScroll(): void
    {
        $model = (new TimeGridView($this->formatter()))->build($this->context(
            '2026-10-10',
            ['businessHours' => [['days' => [6], 'start' => '09:00', 'end' => '13:00']], 'views' => ['variants' => ['timeGridDay' => ['dayStart' => '08:00', 'dayEnd' => '20:00']]]],
            view: 'timeGridDay',
            now: '2026-10-10 14:00',
        ), []);

        $column = $model->columns[0];
        self::assertCount(24, $model->slots);
        self::assertSame([[0.0, 1 / 12], [5 / 12, 7 / 12]], $column->closed);
        self::assertSame(0.5, $column->now);
        self::assertSame(0.5, $model->scrollTo);
    }

    public function testRejectsAMonthRange(): void
    {
        $this->expectExceptionMessage('takes a "range" of day or week');

        (new TimeGridView($this->formatter()))->validateVariant(new ViewVariant('timeGridMonth', 'timeGrid', ['range' => 'month']));
    }
}

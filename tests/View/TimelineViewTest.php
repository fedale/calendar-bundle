<?php

namespace Fedale\CalendarBundle\Tests\View;

use Fedale\CalendarBundle\Model\CalendarEvent;
use Fedale\CalendarBundle\Model\CalendarResource;
use Fedale\CalendarBundle\Model\EventLayer;
use Fedale\CalendarBundle\Options\ViewVariant;
use Fedale\CalendarBundle\Source\CalendarItem;
use Fedale\CalendarBundle\Tests\Support\Items;
use Fedale\CalendarBundle\View\Timeline\TimelineRow;
use Fedale\CalendarBundle\View\TimelineView;
use PHPUnit\Framework\Attributes\DataProvider;

final class TimelineViewTest extends ViewTestCase
{
    /** @var array<string, mixed> */
    private const DISPLAY = [
        'resources' => [
            'columns' => [['field' => 'title', 'label' => 'Room'], ['field' => 'seats', 'label' => 'Seats']],
            'groupBy' => 'building',
        ],
    ];

    /**
     * @return list<CalendarResource>
     */
    private function rooms(): array
    {
        return [
            new CalendarResource('b', 'Room B', data: ['building' => 'North', 'seats' => 20]),
            new CalendarResource('a', 'Room A', data: ['building' => 'North', 'seats' => 40]),
            new CalendarResource('a1', 'Room A1', parentId: 'a', data: ['building' => 'North', 'seats' => 10]),
            new CalendarResource('c', 'Room C', data: ['building' => 'East', 'seats' => 8]),
        ];
    }

    private function item(string $id, string $start, string $end, array $resources, EventLayer $layer = EventLayer::Foreground): CalendarItem
    {
        return CalendarItem::fromEvent('test', new CalendarEvent($id, $id, Items::at($start), Items::at($end), resourceIds: $resources, layer: $layer), Items::tz());
    }

    public function testRowsAreGroupedSortedAndNested(): void
    {
        $model = (new TimelineView($this->formatter()))->build($this->context('2026-10-10', self::DISPLAY, view: 'timelineDay'), [], $this->rooms());

        self::assertSame(['g:East', 'r:c', 'g:North', 'r:a', 'r:a1', 'r:b'], array_map(fn(TimelineRow $row) => $row->key, $model->rows));
        self::assertSame(['Room A1', '10'], $model->rows[4]->cells);
        self::assertSame(2, $model->rows[4]->depth);
        self::assertTrue($model->rows[3]->hasChildren);
    }

    public function testCollapsedRowsHideTheirDescendants(): void
    {
        $model = (new TimelineView($this->formatter()))->build($this->context('2026-10-10', self::DISPLAY, view: 'timelineDay', collapsed: ['g:East', 'r:a']), [], $this->rooms());

        self::assertSame(['g:East', 'g:North', 'r:a', 'r:b'], array_map(fn(TimelineRow $row) => $row->key, $model->rows));
        self::assertTrue($model->rows[0]->collapsed);
        self::assertTrue($model->rows[2]->collapsed);
    }

    public function testEventsLandOnTheirResourcesAndUnassignedBandsSpanEveryRow(): void
    {
        $model = (new TimelineView($this->formatter()))->build($this->context('2026-10-10', view: 'timelineDay'), [
            $this->item('talk', '2026-10-10 09:00', '2026-10-10 12:00', ['a', 'c']),
            $this->item('cleaning', '2026-10-10 06:00', '2026-10-10 07:00', ['a'], EventLayer::Background),
            $this->item('closed', '2026-10-10 20:00', '2026-10-11 00:00', [], EventLayer::Background),
            $this->item('nobody', '2026-10-10 10:00', '2026-10-10 11:00', []),
        ], $this->rooms());

        $byKey = [];
        foreach ($model->rows as $row) {
            $byKey[$row->key] = $row;
        }
        self::assertCount(1, $byKey['r:a']->layout->segments);
        self::assertCount(1, $byKey['r:c']->layout->segments);
        self::assertCount(0, $byKey['r:b']->layout->segments);
        self::assertEqualsWithDelta(9 / 24, $byKey['r:a']->layout->segments[0]->left, 1e-12);
        self::assertCount(1, $byKey['r:a']->bands);
        self::assertCount(1, $model->bands);
    }

    public function testNowLineAndScrollTarget(): void
    {
        $view = new TimelineView($this->formatter());

        $today = $view->build($this->context('2026-10-10', view: 'timelineDay', now: '2026-10-10 12:00'), [], []);
        self::assertSame(0.5, $today->now);
        self::assertSame(0.5, $today->scrollTo);

        $otherDay = $view->build($this->context('2026-10-12', view: 'timelineDay', now: '2026-10-10 12:00'), [], []);
        self::assertNull($otherDay->now);
        self::assertSame(8 / 24, $otherDay->scrollTo);
    }

    public function testMonthHeadersGroupDaysByWeek(): void
    {
        $model = (new TimelineView($this->formatter()))->build($this->context('2026-10-10', view: 'timelineMonth'), [], []);

        self::assertCount(31, $model->slots);
        self::assertTrue($model->daySlots);
        self::assertSame('40', $model->groups[0]->start->format('W'));
        self::assertSame('Ottobre 2026', ucfirst((new TimelineView($this->formatter()))->title($this->context('2026-10-10', view: 'timelineMonth'))));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidVariants(): iterable
    {
        yield 'unknown parameter' => [['zoom' => 2], 'takes only'];
        yield 'slot not dividing a day' => [['slotMinutes' => 7], 'divides a day'];
        yield 'window not made of slots' => [['slotMinutes' => 60, 'dayStart' => '08:30'], 'whole number'];
        yield 'end before start' => [['dayStart' => '18:00', 'dayEnd' => '08:00'], 'after'];
        yield 'bad range' => [['range' => 'year'], 'range'];
    }

    /**
     * @param array<string, mixed> $params
     */
    #[DataProvider('invalidVariants')]
    public function testRejectsInvalidVariants(array $params, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        (new TimelineView($this->formatter()))->validateVariant(new ViewVariant('custom', 'timeline', $params));
    }
}

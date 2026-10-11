<?php

namespace Fedale\CalendarBundle\Tests\View;

use Fedale\CalendarBundle\Tests\Support\Items;
use Fedale\CalendarBundle\View\Year\YearDay;
use Fedale\CalendarBundle\View\YearView;

final class YearViewTest extends ViewTestCase
{
    /**
     * @param array<string, mixed> $display
     */
    private function build(array $display = [], array $items = []): \Fedale\CalendarBundle\View\Year\YearViewModel
    {
        return (new YearView($this->formatter()))->build($this->context('2026-10-10', $display, view: 'year'), $items);
    }

    private function day(\Fedale\CalendarBundle\View\Year\YearViewModel $model, string $date): YearDay
    {
        foreach ($model->months as $month) {
            foreach ($month->weeks as $week) {
                foreach ($week as $day) {
                    if ($day->inMonth && $day->date->format('Y-m-d') === $date) {
                        return $day;
                    }
                }
            }
        }
        self::fail("No day $date.");
    }

    public function testRangeAndTitleAreTheYear(): void
    {
        $view = new YearView($this->formatter());
        $context = $this->context('2026-10-10', view: 'year');

        self::assertSame(['2026-01-01', '2027-01-01'], [$view->range($context)->start->format('Y-m-d'), $view->range($context)->end->format('Y-m-d')]);
        self::assertSame('2027-01-01', $view->navigate($context, 1)->format('Y-m-d'));
        self::assertSame('2026', $view->title($context));
    }

    public function testDensityIsRelativeToTheBusiestDay(): void
    {
        $items = [Items::item('trip', '2026-03-02', '2026-03-05', allDay: true)];
        for ($i = 0; $i < 4; ++$i) {
            $items[] = Items::item("busy$i", "2026-03-03 0$i:00", "2026-03-03 0$i:30");
        }

        $model = $this->build(items: $items);

        self::assertCount(12, $model->months);
        self::assertSame([1, 1], [$this->day($model, '2026-03-02')->count, $this->day($model, '2026-03-02')->level]);
        self::assertSame([5, 4], [$this->day($model, '2026-03-03')->count, $this->day($model, '2026-03-03')->level]);
        self::assertSame(0, $this->day($model, '2026-03-05')->level);
        self::assertTrue($this->day($model, '2026-10-10')->isToday);
    }

    public function testDaysOpenInTheMostDetailedEnabledDayView(): void
    {
        self::assertSame('month', $this->build(['views' => ['enabled' => ['year', 'month'], 'default' => 'year']])->dayView);
        self::assertSame('timeGridDay', $this->build(['views' => ['enabled' => ['year', 'listDay', 'timeGridDay'], 'default' => 'year']])->dayView);
        self::assertNull($this->build(['views' => ['enabled' => ['year', 'listMonth'], 'default' => 'year']])->dayView);
    }

    public function testHiddenDaysAndFirstDayShapeTheMiniMonths(): void
    {
        $model = $this->build(['firstDay' => 7, 'hiddenDays' => [6]]);

        self::assertSame(['7', '1', '2', '3', '4', '5'], array_map(fn(\DateTimeImmutable $day) => $day->format('N'), $model->weekdays));
    }
}

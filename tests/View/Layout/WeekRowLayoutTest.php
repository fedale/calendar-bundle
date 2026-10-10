<?php

namespace Fedale\CalendarBundle\Tests\View\Layout;

use Fedale\CalendarBundle\Source\CalendarItem;
use Fedale\CalendarBundle\Tests\Support\Items;
use Fedale\CalendarBundle\View\Layout\RowSegment;
use Fedale\CalendarBundle\View\Layout\WeekRowLayout;
use PHPUnit\Framework\TestCase;

final class WeekRowLayoutTest extends TestCase
{
    /**
     * Monday 5 to Sunday 11 October 2026, minus $hidden ISO days.
     *
     * @param list<int> $hidden
     *
     * @return list<\DateTimeImmutable>
     */
    private function week(array $hidden = []): array
    {
        $days = [];
        for ($i = 0; $i < 7; ++$i) {
            $day = Items::at('2026-10-05')->modify("+$i day");
            if (!\in_array((int) $day->format('N'), $hidden, true)) {
                $days[] = $day;
            }
        }

        return $days;
    }

    /**
     * @param list<RowSegment> $segments
     *
     * @return array<string, array{int, int, int}> event id => [start, span, lane]
     */
    private function placements(array $segments): array
    {
        $placements = [];
        foreach ($segments as $segment) {
            $placements[$segment->item->event->id] = [$segment->start, $segment->span, $segment->lane];
        }

        return $placements;
    }

    public function testMultiDayBarsKeepOneLaneAndOthersFillTheGaps(): void
    {
        $layout = WeekRowLayout::layout($this->week(), [
            Items::item('trip', '2026-10-06', '2026-10-09', allDay: true),   // Tue-Thu
            Items::item('mon', '2026-10-05 09:00', '2026-10-05 10:00'),
            Items::item('wed', '2026-10-07 09:00', '2026-10-07 10:00'),
            Items::item('fri', '2026-10-09 09:00', '2026-10-09 10:00'),
        ]);

        self::assertSame([
            'trip' => [1, 3, 0],
            'mon' => [0, 1, 0],
            'wed' => [2, 1, 1],
            'fri' => [4, 1, 0],
        ], $this->placements($layout->segments));
        self::assertSame(2, $layout->laneCount);
    }

    public function testBarsCrossingTheRowAreClippedAndFlagged(): void
    {
        $layout = WeekRowLayout::layout($this->week(), [
            Items::item('long', '2026-10-01', '2026-10-15', allDay: true),
        ]);

        $segment = $layout->segments[0];
        self::assertSame([0, 7], [$segment->start, $segment->span]);
        self::assertTrue($segment->continuesBefore);
        self::assertTrue($segment->continuesAfter);
    }

    public function testItemsBeyondTheLaneLimitOverflowOnEveryDayTheyCover(): void
    {
        $items = [
            Items::item('a', '2026-10-05 08:00', '2026-10-05 09:00'),
            Items::item('b', '2026-10-05 09:00', '2026-10-05 10:00'),
            Items::item('c', '2026-10-05', '2026-10-07', allDay: true),
        ];
        usort($items, CalendarItem::compare(...));

        $layout = WeekRowLayout::layout($this->week(), $items, maxLanes: 2);

        // c sorts first (all-day): it takes lane 0, a takes lane 1, b overflows.
        self::assertSame(['c', 'a'], array_keys($this->placements($layout->segments)));
        self::assertSame(1, $layout->overflowCount(0));
        self::assertSame(0, $layout->overflowCount(1));
    }

    public function testHiddenDaysAreSkippedAndItemsOnlyOnThemLeftOut(): void
    {
        $layout = WeekRowLayout::layout($this->week(hidden: [6, 7]), [
            Items::item('weekend', '2026-10-10 10:00', '2026-10-10 12:00'),
            Items::item('fri-sun', '2026-10-09', '2026-10-12', allDay: true),
        ]);

        self::assertSame(['fri-sun' => [4, 1, 0]], $this->placements($layout->segments));
        self::assertTrue($layout->segments[0]->continuesAfter);
    }
}

<?php

namespace Fedale\CalendarBundle\Tests\View\Timeline;

use Fedale\CalendarBundle\Tests\Support\Items;
use Fedale\CalendarBundle\View\Timeline\AxisSlot;
use Fedale\CalendarBundle\View\Timeline\TimeAxis;
use PHPUnit\Framework\TestCase;

final class TimeAxisTest extends TestCase
{
    public function testMapsInstantsInsideAWindowAndClampsOutside(): void
    {
        $axis = new TimeAxis([Items::at('2026-10-10')], 8 * 60, 20 * 60);

        self::assertSame(0.0, $axis->position(Items::at('2026-10-10 08:00')));
        self::assertSame(0.5, $axis->position(Items::at('2026-10-10 14:00')));
        self::assertSame(0.0, $axis->position(Items::at('2026-10-10 06:00')));
        self::assertSame(1.0, $axis->position(Items::at('2026-10-10 22:00')));
        self::assertNull($axis->span(Items::at('2026-10-10 21:00'), Items::at('2026-10-10 23:00')));
        self::assertNull($axis->exactPosition(Items::at('2026-10-10 21:00')));
    }

    public function testDaysSitSideBySideAndHiddenDaysCollapse(): void
    {
        // Friday and Monday: the weekend in between is not on the axis.
        $axis = new TimeAxis([Items::at('2026-10-09'), Items::at('2026-10-12')]);

        self::assertSame(0.5, $axis->position(Items::at('2026-10-10 12:00')));
        self::assertSame([0.25, 0.5], $axis->span(Items::at('2026-10-09 12:00'), Items::at('2026-10-12 12:00')));
    }

    public function testHourColumnsKeepTheirWidthAcrossADstChange(): void
    {
        // 25 October 2026: clocks go back in Rome, the day lasts 25 hours.
        $slots = (new TimeAxis([Items::at('2026-10-25')]))->slots(60);

        self::assertCount(24, $slots);
        self::assertSame(['00:00', '12:00', '23:00'], array_map(fn(AxisSlot $slot) => $slot->start->format('H:i'), [$slots[0], $slots[12], $slots[23]]));
        self::assertEqualsWithDelta(1 / 24, $slots[5]->width, 1e-12);
    }

    public function testGroupsByDayOrIsoWeek(): void
    {
        $days = [];
        for ($day = Items::at('2026-10-01'); $day < Items::at('2026-10-15'); $day = $day->modify('+1 day')) {
            $days[] = $day;
        }
        $axis = new TimeAxis($days);

        self::assertCount(14, $axis->groups(false));
        $weeks = $axis->groups(true);
        self::assertSame(['40', '41', '42'], array_map(fn($group) => $group->start->format('W'), $weeks));
        self::assertEqualsWithDelta(4 / 14, $weeks[0]->width, 1e-12); // Thu 1 - Sun 4
    }
}

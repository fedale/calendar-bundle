<?php

namespace Fedale\CalendarBundle\Tests\View\Timeline;

use Fedale\CalendarBundle\Tests\Support\Items;
use Fedale\CalendarBundle\View\Timeline\LaneLayout;
use Fedale\CalendarBundle\View\Timeline\TimelineSegment;
use PHPUnit\Framework\TestCase;

final class LaneLayoutTest extends TestCase
{
    /**
     * @param list<array{string, float, float}> $spans id, left, width
     *
     * @return list<array{\Fedale\CalendarBundle\Source\CalendarItem, float, float, bool, bool}>
     */
    private function placed(array $spans): array
    {
        return array_map(fn(array $span) => [Items::item($span[0], '2026-10-10 08:00', '2026-10-10 09:00'), $span[1], $span[2], false, false], $spans);
    }

    public function testOverlapsStackAndBackToBackItemsShareALane(): void
    {
        $layout = LaneLayout::layout($this->placed([['b', 0.2, 0.2], ['a', 0.0, 0.2], ['c', 0.1, 0.5]]), null);

        $lanes = [];
        foreach ($layout->segments as $segment) {
            $lanes[$segment->item->event->id] = $segment->lane;
        }
        self::assertSame(['a' => 0, 'c' => 1, 'b' => 0], $lanes);
        self::assertSame(2, $layout->laneCount);
    }

    public function testItemsBeyondTheLimitMergeIntoChipsWhereTheyOverlap(): void
    {
        $layout = LaneLayout::layout($this->placed([
            ['a', 0.0, 0.5], ['b', 0.1, 0.1], ['c', 0.15, 0.2], ['d', 0.6, 0.1], ['e', 0.6, 0.1],
        ]), 1);

        self::assertSame(['a', 'd'], array_map(fn(TimelineSegment $segment) => $segment->item->event->id, $layout->segments));
        self::assertCount(2, $layout->more);
        self::assertCount(2, $layout->more[0]->items); // b and c overlap
        self::assertEqualsWithDelta(0.1, $layout->more[0]->left, 1e-12);
        self::assertEqualsWithDelta(0.25, $layout->more[0]->width, 1e-12);
        self::assertCount(1, $layout->more[1]->items);
    }
}

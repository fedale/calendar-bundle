<?php

namespace Fedale\CalendarBundle\Tests\View\TimeGrid;

use Fedale\CalendarBundle\Tests\Support\Items;
use Fedale\CalendarBundle\View\TimeGrid\ColumnLayout;
use Fedale\CalendarBundle\View\TimeGrid\GridEvent;
use PHPUnit\Framework\TestCase;

final class ColumnLayoutTest extends TestCase
{
    /**
     * @param list<array{string, float, float}> $spans id, top, height
     *
     * @return array<string, array{float, float}> id => [left, width]
     */
    private function layout(array $spans): array
    {
        $placed = array_map(fn(array $span) => [Items::item($span[0], '2026-10-10 08:00', '2026-10-10 09:00'), $span[1], $span[2], false, false], $spans);
        $result = [];
        foreach (ColumnLayout::layout($placed) as $event) {
            self::assertInstanceOf(GridEvent::class, $event);
            $result[$event->item->event->id] = [round($event->left, 4), round($event->width, 4)];
        }

        return $result;
    }

    public function testEventsThatDoNotOverlapTakeTheWholeColumn(): void
    {
        self::assertSame(['a' => [0.0, 1.0], 'b' => [0.0, 1.0]], $this->layout([['a', 0.0, 0.25], ['b', 0.25, 0.25]]));
    }

    public function testOverlappingEventsSitSideBySide(): void
    {
        self::assertSame(['a' => [0.0, 0.5], 'b' => [0.5, 0.5]], $this->layout([['a', 0.0, 0.5], ['b', 0.25, 0.5]]));
    }

    public function testEventsGrowOverFreeSubColumns(): void
    {
        // a overlaps both b and c, which do not overlap each other: two
        // sub-columns. d starts after b, in a's cluster, and can use b's
        // sub-column; e sits alone later and takes the whole width.
        $layout = $this->layout([['a', 0.0, 0.6], ['b', 0.1, 0.2], ['c', 0.4, 0.1], ['e', 0.8, 0.1]]);

        self::assertSame([0.0, 0.5], $layout['a']);
        self::assertSame([0.5, 0.5], $layout['b']);
        self::assertSame([0.5, 0.5], $layout['c']);
        self::assertSame([0.0, 1.0], $layout['e']);
    }

    public function testThreeWayOverlapAndExpansion(): void
    {
        // a and b overlap at the top, c overlaps only a lower down: c takes
        // sub-column 1 (free once b ended) and cannot grow past the edge.
        $layout = $this->layout([['a', 0.0, 0.5], ['b', 0.0, 0.2], ['x', 0.0, 0.1], ['c', 0.3, 0.1]]);

        self::assertSame([0.0, 0.3333], $layout['a']);
        self::assertSame([0.3333, 0.3333], $layout['b']);
        self::assertSame([0.6667, 0.3333], $layout['x']);
        self::assertSame([0.3333, 0.6667], $layout['c']);
    }
}

<?php

namespace Fedale\CalendarBundle\View\Timeline;

use Fedale\CalendarBundle\Source\CalendarItem;

/**
 * Stacks the items of one timeline row into lanes: each item takes the
 * lowest lane that is free at its start, so overlapping items sit one above
 * the other and back-to-back items share a lane.
 */
final class LaneLayout
{
    private const EPSILON = 1e-9;

    /**
     * @param list<array{CalendarItem, float, float, bool, bool}> $placed   item, left, width, continuesBefore, continuesAfter
     * @param int|null                                            $maxLanes lanes available; the rest becomes "+N more". null = unlimited
     */
    public static function layout(array $placed, ?int $maxLanes): RowLayout
    {
        // Earlier first, then longer first, so long items keep the top lanes.
        usort($placed, fn(array $a, array $b) => [$a[1], -$a[2]] <=> [$b[1], -$b[2]]);

        /** @var list<float> $laneEnds lane => right edge of its last item */
        $laneEnds = [];
        $segments = [];
        $hidden = [];
        foreach ($placed as [$item, $left, $width, $before, $after]) {
            $lane = 0;
            while (isset($laneEnds[$lane]) && $laneEnds[$lane] > $left + self::EPSILON) {
                ++$lane;
            }
            if ($maxLanes !== null && $lane >= $maxLanes) {
                $hidden[] = [$item, $left, $left + $width];
                continue;
            }
            $laneEnds[$lane] = $left + $width;
            $segments[] = new TimelineSegment($item, $left, $width, $lane, $before, $after);
        }

        return new RowLayout($segments, self::chips($hidden), \count($laneEnds));
    }

    /**
     * @param list<array{CalendarItem, float, float}> $hidden item, left, right; sorted by left
     *
     * @return list<MoreChip>
     */
    private static function chips(array $hidden): array
    {
        $chips = [];
        $current = null;
        foreach ($hidden as [$item, $left, $right]) {
            if ($current !== null && $left < $current['right'] - self::EPSILON) {
                $current['right'] = max($current['right'], $right);
                $current['items'][] = $item;
                continue;
            }
            if ($current !== null) {
                $chips[] = new MoreChip($current['left'], $current['right'] - $current['left'], $current['items']);
            }
            $current = ['left' => $left, 'right' => $right, 'items' => [$item]];
        }
        if ($current !== null) {
            $chips[] = new MoreChip($current['left'], $current['right'] - $current['left'], $current['items']);
        }

        return $chips;
    }
}

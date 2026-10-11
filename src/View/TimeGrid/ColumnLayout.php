<?php

namespace Fedale\CalendarBundle\View\TimeGrid;

use Fedale\CalendarBundle\Source\CalendarItem;

/**
 * Lays out the timed events of one time-grid column side by side where they
 * overlap. Events that overlap, directly or through others, form a cluster;
 * inside it each event takes the leftmost free sub-column, every event is
 * as wide as one sub-column of the cluster, and then grows to the right over
 * the sub-columns that stay free during its time.
 */
final class ColumnLayout
{
    private const EPSILON = 1e-9;

    /**
     * @param list<array{CalendarItem, float, float, bool, bool}> $placed item, top, height, continuesBefore, continuesAfter
     *
     * @return list<GridEvent>
     */
    public static function layout(array $placed): array
    {
        // Earlier first, then longer first.
        usort($placed, fn(array $a, array $b) => [$a[1], -$a[2]] <=> [$b[1], -$b[2]]);

        $events = [];
        $cluster = [];
        $clusterBottom = -1.0;
        foreach ($placed as $entry) {
            if ($cluster !== [] && $entry[1] >= $clusterBottom - self::EPSILON) {
                array_push($events, ...self::cluster($cluster));
                $cluster = [];
            }
            $cluster[] = $entry;
            $clusterBottom = max($clusterBottom, $entry[1] + $entry[2]);
        }
        if ($cluster !== []) {
            array_push($events, ...self::cluster($cluster));
        }

        return $events;
    }

    /**
     * @param non-empty-list<array{CalendarItem, float, float, bool, bool}> $cluster
     *
     * @return list<GridEvent>
     */
    private static function cluster(array $cluster): array
    {
        /** @var list<float> $columnBottoms sub-column => bottom of its last event */
        $columnBottoms = [];
        $assigned = [];
        foreach ($cluster as $entry) {
            $column = 0;
            while (isset($columnBottoms[$column]) && $columnBottoms[$column] > $entry[1] + self::EPSILON) {
                ++$column;
            }
            $columnBottoms[$column] = $entry[1] + $entry[2];
            $assigned[] = [$entry, $column];
        }
        $count = \count($columnBottoms);

        $events = [];
        foreach ($assigned as [$entry, $column]) {
            [$item, $top, $height, $before, $after] = $entry;
            $span = 1;
            while ($column + $span < $count && !self::busy($assigned, $column + $span, $top, $top + $height)) {
                ++$span;
            }
            $events[] = new GridEvent($item, $top, $height, $column / $count, $span / $count, $before, $after);
        }

        return $events;
    }

    /**
     * @param list<array{array{CalendarItem, float, float, bool, bool}, int}> $assigned
     */
    private static function busy(array $assigned, int $column, float $top, float $bottom): bool
    {
        foreach ($assigned as [$entry, $other]) {
            if ($other === $column && $entry[1] < $bottom - self::EPSILON && $entry[1] + $entry[2] > $top + self::EPSILON) {
                return true;
            }
        }

        return false;
    }
}

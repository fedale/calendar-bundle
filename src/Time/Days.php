<?php

namespace Fedale\CalendarBundle\Time;

/**
 * Day arithmetic in the timezone the dates carry. Days are always stepped
 * with modify('+N day') and pinned back to midnight, never by adding 86400
 * seconds: across a DST change a day lasts 23 or 25 hours.
 */
final class Days
{
    public static function start(\DateTimeImmutable $at): \DateTimeImmutable
    {
        return $at->setTime(0, 0);
    }

    public static function add(\DateTimeImmutable $day, int $days): \DateTimeImmutable
    {
        return $day->modify(sprintf('%+d day', $days))->setTime(0, 0);
    }

    /** The first day of the week containing $day, weeks starting on ISO day $firstDay. */
    public static function startOfWeek(\DateTimeImmutable $day, int $firstDay): \DateTimeImmutable
    {
        $offset = ((int) $day->format('N') - $firstDay + 7) % 7;

        return self::add($day, -$offset);
    }

    public static function startOfMonth(\DateTimeImmutable $day): \DateTimeImmutable
    {
        return $day->setDate((int) $day->format('Y'), (int) $day->format('n'), 1)->setTime(0, 0);
    }

    public static function same(\DateTimeInterface $a, \DateTimeInterface $b): bool
    {
        return $a->format('Y-m-d') === $b->format('Y-m-d');
    }

    /** Whole days from $from to $to, both taken as calendar dates. */
    public static function between(\DateTimeInterface $from, \DateTimeInterface $to): int
    {
        $a = new \DateTimeImmutable($from->format('Y-m-d'), new \DateTimeZone('UTC'));
        $b = new \DateTimeImmutable($to->format('Y-m-d'), new \DateTimeZone('UTC'));

        return (int) $a->diff($b)->format('%r%a');
    }
}

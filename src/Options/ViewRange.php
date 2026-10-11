<?php

namespace Fedale\CalendarBundle\Options;

use Fedale\CalendarBundle\Model\DateRange;
use Fedale\CalendarBundle\Time\DateFormatter;
use Fedale\CalendarBundle\Time\Days;

/**
 * The period one page of a view covers, shared by the views that take a
 * `range` parameter (list, timeline): which days it spans, how it steps, and
 * how its heading reads.
 */
enum ViewRange: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    public static function fromVariant(ViewVariant $variant, self $default): self
    {
        $value = $variant->param('range', $default->value);
        $range = \is_string($value) ? self::tryFrom($value) : null;

        return $range ?? throw new \InvalidArgumentException(sprintf('Calendar view "%s" takes a "range" of day, week or month.', $variant->name));
    }

    public function range(\DateTimeImmutable $date, int $firstDay): DateRange
    {
        $start = match ($this) {
            self::Day => Days::start($date),
            self::Week => Days::startOfWeek($date, $firstDay),
            self::Month => Days::startOfMonth($date),
        };

        return new DateRange($start, match ($this) {
            self::Day => Days::add($start, 1),
            self::Week => Days::add($start, 7),
            self::Month => $start->modify('+1 month'),
        });
    }

    public function navigate(\DateTimeImmutable $date, int $step): \DateTimeImmutable
    {
        return match ($this) {
            self::Day => Days::add($date, $step),
            self::Week => Days::add($date, 7 * $step),
            self::Month => Days::startOfMonth($date)->modify(sprintf('%+d month', $step)),
        };
    }

    public function title(DateFormatter $formatter, \DateTimeImmutable $date, int $firstDay, string $locale): string
    {
        if ($this === self::Day) {
            return $formatter->format($date, 'EEEEdMMMMy', $locale);
        }
        if ($this === self::Month) {
            return $formatter->format($date, 'MMMMy', $locale);
        }
        $range = $this->range($date, $firstDay);

        return $formatter->format($range->start, 'dMMM', $locale)
            .' – '.$formatter->format(Days::add($range->end, -1), 'dMMMy', $locale);
    }
}

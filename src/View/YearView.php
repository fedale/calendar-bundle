<?php

namespace Fedale\CalendarBundle\View;

use Fedale\CalendarBundle\Attribute\AsCalendarView;
use Fedale\CalendarBundle\Contract\CalendarViewInterface;
use Fedale\CalendarBundle\Model\DateRange;
use Fedale\CalendarBundle\Options\ViewVariant;
use Fedale\CalendarBundle\Time\DateFormatter;
use Fedale\CalendarBundle\Time\Days;
use Fedale\CalendarBundle\View\Year\YearDay;
use Fedale\CalendarBundle\View\Year\YearMonth;
use Fedale\CalendarBundle\View\Year\YearViewModel;

/**
 * Twelve mini months with how busy each day is: a density from 0 (nothing)
 * to 4 (as busy as the busiest day of the year). A day opens in the
 * calendar's most detailed enabled view of a day, if any.
 */
#[AsCalendarView('year')]
final class YearView implements CalendarViewInterface
{
    /** Variants a day opens in, by preference. */
    private const DAY_TYPES = ['timeGrid', 'list', 'timeline'];

    public function __construct(private readonly DateFormatter $formatter)
    {
    }

    public function validateVariant(ViewVariant $variant): void
    {
        if ($variant->params !== []) {
            throw new \InvalidArgumentException(sprintf('Calendar view "%s" (year) takes no parameters, got "%s".', $variant->name, implode('", "', array_keys($variant->params))));
        }
    }

    public function usesResources(ViewVariant $variant): bool
    {
        return false;
    }

    public function range(ViewContext $context): DateRange
    {
        $start = $context->date->setDate((int) $context->date->format('Y'), 1, 1)->setTime(0, 0);

        return new DateRange($start, $start->modify('+1 year'));
    }

    public function navigate(ViewContext $context, int $step): \DateTimeImmutable
    {
        return $this->range($context)->start->modify(sprintf('%+d year', $step));
    }

    public function title(ViewContext $context): string
    {
        return $this->formatter->format($context->date, 'y', $context->locale);
    }

    public function build(ViewContext $context, array $items, array $resources = []): YearViewModel
    {
        $options = $context->options;

        // Events per day: each foreground item counts on every day it covers.
        $counts = [];
        foreach ($items as $item) {
            if ($item->isBackground()) {
                continue;
            }
            for ($day = $item->firstDay; $day <= $item->lastDay; $day = Days::add($day, 1)) {
                $key = $day->format('Y-m-d');
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }
        $max = $counts === [] ? 0 : max($counts);

        $months = [];
        $weekdays = [];
        for ($month = $this->range($context)->start, $i = 0; $i < 12; $month = $month->modify('+1 month'), ++$i) {
            $weeks = [];
            $last = Days::add($month->modify('+1 month'), -1);
            for ($weekStart = Days::startOfWeek($month, $options->firstDay); $weekStart <= $last; $weekStart = Days::add($weekStart, 7)) {
                $week = [];
                for ($d = 0; $d < 7; ++$d) {
                    $day = Days::add($weekStart, $d);
                    if ($options->isHidden($day)) {
                        continue;
                    }
                    $count = $counts[$day->format('Y-m-d')] ?? 0;
                    $week[] = new YearDay(
                        date: $day,
                        inMonth: $day->format('m') === $month->format('m'),
                        isToday: Days::same($day, $context->today),
                        count: $count,
                        level: $count === 0 ? 0 : (int) ceil($count / $max * 4),
                    );
                }
                $weeks[] = $week;
            }
            if ($weekdays === []) {
                $weekdays = array_map(fn(YearDay $day) => $day->date, $weeks[0]);
            }
            $months[] = new YearMonth($month, $weeks);
        }

        return new YearViewModel($weekdays, $months, $this->dayView($context));
    }

    public function template(): string
    {
        return '@FedaleCalendar/view/year.html.twig';
    }

    /** The enabled variant that shows one day, the most detailed type first. */
    private function dayView(ViewContext $context): ?string
    {
        $options = $context->options;
        foreach (self::DAY_TYPES as $type) {
            foreach ($options->enabledViews as $name) {
                $variant = $options->variant($name);
                if ($variant->type === $type && $variant->param('range') === 'day') {
                    return $name;
                }
            }
        }
        foreach ($options->enabledViews as $name) {
            if ($options->variant($name)->type === 'month') {
                return $name;
            }
        }

        return null;
    }
}

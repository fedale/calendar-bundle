<?php

namespace Fedale\CalendarBundle\View;

use Fedale\CalendarBundle\Attribute\AsCalendarView;
use Fedale\CalendarBundle\Contract\CalendarViewInterface;
use Fedale\CalendarBundle\Model\DateRange;
use Fedale\CalendarBundle\Options\ViewVariant;
use Fedale\CalendarBundle\Source\CalendarItem;
use Fedale\CalendarBundle\Time\DateFormatter;
use Fedale\CalendarBundle\Time\Days;
use Fedale\CalendarBundle\View\Layout\WeekRowLayout;
use Fedale\CalendarBundle\View\Model\MonthDay;
use Fedale\CalendarBundle\View\Model\MonthViewModel;
use Fedale\CalendarBundle\View\Model\MonthWeek;

/**
 * The month as whole weeks: from the week containing the 1st to the week
 * containing the last day, so the grid shows 4 to 6 rows.
 */
#[AsCalendarView('month')]
final class MonthView implements CalendarViewInterface
{
    public function __construct(private readonly DateFormatter $formatter)
    {
    }

    public function validateVariant(ViewVariant $variant): void
    {
        if ($variant->params !== []) {
            throw new \InvalidArgumentException(sprintf('Calendar view "%s" (month) takes no parameters, got "%s".', $variant->name, implode('", "', array_keys($variant->params))));
        }
    }

    public function usesResources(): bool
    {
        return false;
    }

    public function range(ViewContext $context): DateRange
    {
        $first = Days::startOfMonth($context->date);
        $lastDay = Days::add($first->modify('+1 month'), -1);

        return new DateRange(
            Days::startOfWeek($first, $context->options->firstDay),
            Days::add(Days::startOfWeek($lastDay, $context->options->firstDay), 7),
        );
    }

    public function navigate(ViewContext $context, int $step): \DateTimeImmutable
    {
        return Days::startOfMonth($context->date)->modify(sprintf('%+d month', $step));
    }

    public function title(ViewContext $context): string
    {
        return $this->formatter->format($context->date, 'MMMMy', $context->locale);
    }

    public function build(ViewContext $context, array $items, array $resources = []): MonthViewModel
    {
        $options = $context->options;
        $range = $this->range($context);
        $month = $context->date->format('Y-m');

        $foreground = array_values(array_filter($items, fn(CalendarItem $item) => !$item->isBackground()));
        $background = array_values(array_filter($items, fn(CalendarItem $item) => $item->isBackground()));

        $weeks = [];
        $weekdays = [];
        for ($weekStart = $range->start; $weekStart < $range->end; $weekStart = Days::add($weekStart, 7)) {
            $visible = [];
            for ($i = 0; $i < 7; ++$i) {
                $day = Days::add($weekStart, $i);
                if (!$options->isHidden($day)) {
                    $visible[] = $day;
                }
            }
            if ($weekdays === []) {
                $weekdays = $visible;
            }

            // Only the items touching this week are laid out and searched.
            $weekFirst = $weekStart->format('Y-m-d');
            $weekLast = Days::add($weekStart, 6)->format('Y-m-d');
            $touching = fn(CalendarItem $item) => $item->lastDate >= $weekFirst && $item->firstDate <= $weekLast;
            $weekForeground = array_values(array_filter($foreground, $touching));

            $layout = WeekRowLayout::layout($visible, $weekForeground, $options->dayMaxEvents);
            $bands = WeekRowLayout::layout($visible, array_values(array_filter($background, $touching)));

            $days = [];
            foreach ($visible as $column => $day) {
                $overflow = $layout->overflowCount($column);
                $days[] = new MonthDay(
                    date: $day,
                    column: $column,
                    inMonth: $day->format('Y-m') === $month,
                    isToday: Days::same($day, $context->today),
                    isWeekend: (int) $day->format('N') >= 6,
                    overflowCount: $overflow,
                    items: $overflow > 0
                        ? array_values(array_filter($weekForeground, fn(CalendarItem $item) => $item->coversDay($day)))
                        : [],
                );
            }

            $weeks[] = new MonthWeek(
                // The middle day of the row decides the ISO week, so a row
                // starting on Sunday is numbered like its Monday-to-Saturday.
                number: (int) Days::add($weekStart, 3)->format('W'),
                days: $days,
                segments: $layout->segments,
                backgrounds: $bands->segments,
                laneCount: $layout->laneCount,
                hasOverflow: $layout->overflow !== [],
            );
        }

        return new MonthViewModel($weekdays, $weeks, $options->weekNumbers);
    }

    public function template(): string
    {
        return '@FedaleCalendar/view/month.html.twig';
    }
}

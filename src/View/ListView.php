<?php

namespace Fedale\CalendarBundle\View;

use Fedale\CalendarBundle\Attribute\AsCalendarView;
use Fedale\CalendarBundle\Contract\CalendarViewInterface;
use Fedale\CalendarBundle\Model\DateRange;
use Fedale\CalendarBundle\Options\ListRange;
use Fedale\CalendarBundle\Source\CalendarItem;
use Fedale\CalendarBundle\Time\DateFormatter;
use Fedale\CalendarBundle\Time\Days;
use Fedale\CalendarBundle\View\Model\ListDay;
use Fedale\CalendarBundle\View\Model\ListEntry;
use Fedale\CalendarBundle\View\Model\ListEntryKind;
use Fedale\CalendarBundle\View\Model\ListViewModel;

/**
 * An agenda: the days of a week or a month that have events, each with its
 * events in order. A multi-day event is listed on every day it covers.
 */
#[AsCalendarView('list')]
final class ListView implements CalendarViewInterface
{
    public function __construct(private readonly DateFormatter $formatter)
    {
    }

    public function range(ViewContext $context): DateRange
    {
        if ($context->options->listRange === ListRange::Week) {
            $start = Days::startOfWeek($context->date, $context->options->firstDay);

            return new DateRange($start, Days::add($start, 7));
        }

        $start = Days::startOfMonth($context->date);

        return new DateRange($start, $start->modify('+1 month'));
    }

    public function navigate(ViewContext $context, int $step): \DateTimeImmutable
    {
        if ($context->options->listRange === ListRange::Week) {
            return Days::add($context->date, 7 * $step);
        }

        return Days::startOfMonth($context->date)->modify(sprintf('%+d month', $step));
    }

    public function title(ViewContext $context): string
    {
        if ($context->options->listRange === ListRange::Month) {
            return $this->formatter->format($context->date, 'MMMMy', $context->locale);
        }

        $range = $this->range($context);

        return $this->formatter->format($range->start, 'dMMM', $context->locale)
            .' – '.$this->formatter->format(Days::add($range->end, -1), 'dMMMy', $context->locale);
    }

    public function build(ViewContext $context, array $items): ListViewModel
    {
        $range = $this->range($context);
        $foreground = array_values(array_filter($items, fn(CalendarItem $item) => !$item->isBackground()));

        $days = [];
        for ($day = $range->start; $day < $range->end; $day = Days::add($day, 1)) {
            if ($context->options->isHidden($day)) {
                continue;
            }
            $entries = [];
            $date = $day->format('Y-m-d');
            foreach ($foreground as $item) {
                if ($item->coversDate($date)) {
                    $entries[] = new ListEntry($item, $this->kind($item, $day));
                }
            }
            if ($entries !== []) {
                $days[] = new ListDay($day, Days::same($day, $context->today), $entries);
            }
        }

        return new ListViewModel($days);
    }

    public function template(): string
    {
        return '@FedaleCalendar/view/list.html.twig';
    }

    private function kind(CalendarItem $item, \DateTimeImmutable $day): ListEntryKind
    {
        $starts = Days::same($item->firstDay, $day);
        $ends = Days::same($item->lastDay, $day);

        return match (true) {
            $item->isAllDay() => ListEntryKind::AllDay,
            $starts && $ends => ListEntryKind::Timed,
            $starts => ListEntryKind::Starts,
            $ends => ListEntryKind::Ends,
            default => ListEntryKind::Continues,
        };
    }
}

<?php

namespace Fedale\CalendarBundle\View;

use Fedale\CalendarBundle\Attribute\AsCalendarView;
use Fedale\CalendarBundle\Contract\CalendarViewInterface;
use Fedale\CalendarBundle\Model\DateRange;
use Fedale\CalendarBundle\Options\ViewRange;
use Fedale\CalendarBundle\Options\ViewVariant;
use Fedale\CalendarBundle\Source\CalendarItem;
use Fedale\CalendarBundle\Time\DateFormatter;
use Fedale\CalendarBundle\Time\Days;
use Fedale\CalendarBundle\View\Model\ListDay;
use Fedale\CalendarBundle\View\Model\ListEntry;
use Fedale\CalendarBundle\View\Model\ListEntryKind;
use Fedale\CalendarBundle\View\Model\ListViewModel;

/**
 * An agenda: the days of a day, week or month (the `range` parameter) that
 * have events, each with its events in order. A multi-day event is listed on
 * every day it covers.
 */
#[AsCalendarView('list')]
final class ListView implements CalendarViewInterface
{
    public function __construct(private readonly DateFormatter $formatter)
    {
    }

    public function validateVariant(ViewVariant $variant): void
    {
        $unknown = array_diff(array_keys($variant->params), ['range']);
        if ($unknown !== []) {
            throw new \InvalidArgumentException(sprintf('Calendar view "%s" (list) takes only "range", got "%s".', $variant->name, implode('", "', $unknown)));
        }
        ViewRange::fromVariant($variant, ViewRange::Month);
    }

    public function usesResources(ViewVariant $variant): bool
    {
        return false;
    }

    public function range(ViewContext $context): DateRange
    {
        return $this->viewRange($context)->range($context->date, $context->options->firstDay);
    }

    public function navigate(ViewContext $context, int $step): \DateTimeImmutable
    {
        return $this->viewRange($context)->navigate($context->date, $step);
    }

    public function title(ViewContext $context): string
    {
        return $this->viewRange($context)->title($this->formatter, $context->date, $context->options->firstDay, $context->locale);
    }

    public function build(ViewContext $context, array $items, array $resources = []): ListViewModel
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

    private function viewRange(ViewContext $context): ViewRange
    {
        return ViewRange::fromVariant($context->variant, ViewRange::Month);
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

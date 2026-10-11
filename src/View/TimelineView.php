<?php

namespace Fedale\CalendarBundle\View;

use Fedale\CalendarBundle\Attribute\AsCalendarView;
use Fedale\CalendarBundle\Contract\CalendarViewInterface;
use Fedale\CalendarBundle\Model\CalendarResource;
use Fedale\CalendarBundle\Model\DateRange;
use Fedale\CalendarBundle\Options\ResourceColumn;
use Fedale\CalendarBundle\Options\ViewRange;
use Fedale\CalendarBundle\Options\ViewVariant;
use Fedale\CalendarBundle\Time\DateFormatter;
use Fedale\CalendarBundle\Time\Days;
use Fedale\CalendarBundle\View\Timeline\LaneLayout;
use Fedale\CalendarBundle\View\Timeline\TimeAxis;
use Fedale\CalendarBundle\View\Timeline\TimelineRow;
use Fedale\CalendarBundle\View\Timeline\TimelineSegment;
use Fedale\CalendarBundle\View\Timeline\TimelineViewModel;

/**
 * Resources down the side, time across. Parameters:
 *
 * - `range`: day, week or month (default day);
 * - `slotMinutes`: width of a slot, a divisor of 1440 (default 60);
 * - `dayStart` / `dayEnd`: the window of each day shown, HH:MM (default
 *   00:00 / 24:00);
 * - `scrollTime`: HH:MM the view scrolls to when today is not on screen
 *   (default 08:00).
 *
 * Resources come from the calendar's sources ({@see \Fedale\CalendarBundle\Contract\ResourceSourceInterface}),
 * nested by `parentId` and optionally grouped by the `display.resources.groupBy`
 * field. Events show on the rows of their `resourceIds`; background events
 * without resources span every row.
 */
#[AsCalendarView('timeline')]
final class TimelineView implements CalendarViewInterface
{
    private const PARAMS = ['range', 'slotMinutes', 'dayStart', 'dayEnd', 'scrollTime'];

    public function __construct(private readonly DateFormatter $formatter)
    {
    }

    public function validateVariant(ViewVariant $variant): void
    {
        $unknown = array_diff(array_keys($variant->params), self::PARAMS);
        if ($unknown !== []) {
            throw new \InvalidArgumentException(sprintf('Calendar view "%s" (timeline) takes only %s, got "%s".', $variant->name, implode(', ', self::PARAMS), implode('", "', $unknown)));
        }
        ViewRange::fromVariant($variant, ViewRange::Day);
        [$slot, $start, $end] = $this->window($variant);
        if (($end - $start) % $slot !== 0) {
            throw new \InvalidArgumentException(sprintf('Calendar view "%s": the window %s-%s must be a whole number of %d-minute slots.', $variant->name, $variant->param('dayStart', '00:00'), $variant->param('dayEnd', '24:00'), $slot));
        }
        self::minutes($variant, 'scrollTime', '08:00');
    }

    public function usesResources(): bool
    {
        return true;
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

    public function build(ViewContext $context, array $items, array $resources = []): TimelineViewModel
    {
        $options = $context->options;
        $range = $this->range($context);
        [$slotMinutes, $dayStart, $dayEnd] = $this->window($context->variant);

        $days = [];
        for ($day = $range->start; $day < $range->end; $day = Days::add($day, 1)) {
            if (!$options->isHidden($day)) {
                $days[] = $day;
            }
        }
        $axis = new TimeAxis($days, $dayStart, $dayEnd);

        // Place every item once; rows then pick theirs by resource id.
        $foreground = [];
        $backgroundByResource = [];
        $bands = [];
        foreach ($items as $item) {
            $span = $axis->span($item->start, $item->end);
            if ($span === null) {
                continue;
            }
            $before = $item->start < $range->start;
            $after = $item->end > $range->end;
            if (!$item->isBackground()) {
                foreach ($item->event->resourceIds as $resourceId) {
                    $foreground[$resourceId][] = [$item, $span[0], $span[1], $before, $after];
                }
                continue;
            }
            $band = new TimelineSegment($item, $span[0], $span[1], 0, $before, $after);
            if ($item->event->resourceIds === []) {
                $bands[] = $band;
            }
            foreach ($item->event->resourceIds as $resourceId) {
                $backgroundByResource[$resourceId][] = $band;
            }
        }

        $rows = [];
        $collapsed = array_flip($context->collapsed);
        $addResource = function (CalendarResource $resource, int $depth, array $children) use (&$addResource, &$rows, $options, $foreground, $backgroundByResource, $collapsed): void {
            $key = 'r:'.$resource->id;
            $hasChildren = isset($children[$resource->id]);
            $isCollapsed = $hasChildren && isset($collapsed[$key]);
            $rows[] = new TimelineRow(
                key: $key,
                isGroup: false,
                label: $resource->title,
                resource: $resource,
                cells: array_map(fn(ResourceColumn $column) => $column->value($resource), $options->resourceColumns),
                depth: $depth,
                hasChildren: $hasChildren,
                collapsed: $isCollapsed,
                layout: LaneLayout::layout($foreground[$resource->id] ?? [], $options->rowMaxEvents),
                bands: $backgroundByResource[$resource->id] ?? [],
            );
            if (!$isCollapsed) {
                foreach ($children[$resource->id] ?? [] as $child) {
                    $addResource($child, $depth + 1, $children);
                }
            }
        };

        [$roots, $children] = $this->tree($resources);
        if ($options->resourceGroupBy === null) {
            foreach ($roots as $root) {
                $addResource($root, 0, $children);
            }
        } else {
            $groupColumn = new ResourceColumn($options->resourceGroupBy);
            $grouped = [];
            foreach ($roots as $root) {
                $grouped[$groupColumn->value($root)][] = $root;
            }
            uksort($grouped, fn($a, $b) => strnatcasecmp((string) $a, (string) $b));
            foreach ($grouped as $value => $members) {
                $key = 'g:'.$value;
                $rows[] = new TimelineRow($key, true, (string) $value, null, [], 0, true, isset($collapsed[$key]));
                if (!isset($collapsed[$key])) {
                    foreach ($members as $member) {
                        $addResource($member, 1, $children);
                    }
                }
            }
        }

        $now = $axis->exactPosition($context->now->setTimezone($context->timezone));
        $scrollTo = $now ?? ($slotMinutes < 1440 && $days !== []
            ? $axis->position($days[0]->setTime(...$this->hoursAndMinutes(self::minutes($context->variant, 'scrollTime', '08:00'))))
            : 0.0);

        return new TimelineViewModel(
            groups: $axis->groups($this->viewRange($context) === ViewRange::Month),
            slots: $axis->slots($slotMinutes),
            daySlots: $slotMinutes >= 1440,
            rows: $rows,
            bands: $bands,
            columns: $options->resourceColumns,
            areaLabel: $options->resourceLabel,
            areaWidth: $options->resourceAreaWidth,
            now: $now,
            windows: $axis->windows(),
            scrollTo: $scrollTo,
        );
    }

    public function template(): string
    {
        return '@FedaleCalendar/view/timeline.html.twig';
    }

    private function viewRange(ViewContext $context): ViewRange
    {
        return ViewRange::fromVariant($context->variant, ViewRange::Day);
    }

    /**
     * Roots (no parent, or a parent that is not among the resources) and
     * children by parent id, both sorted by order then title.
     *
     * @param list<CalendarResource> $resources
     *
     * @return array{list<CalendarResource>, array<string, list<CalendarResource>>}
     */
    private function tree(array $resources): array
    {
        usort($resources, fn(CalendarResource $a, CalendarResource $b) => [$a->order, $a->title] <=> [$b->order, $b->title]);
        $ids = array_flip(array_map(fn(CalendarResource $resource) => $resource->id, $resources));

        $roots = [];
        $children = [];
        foreach ($resources as $resource) {
            if ($resource->parentId !== null && $resource->parentId !== $resource->id && isset($ids[$resource->parentId])) {
                $children[$resource->parentId][] = $resource;
            } else {
                $roots[] = $resource;
            }
        }

        return [$roots, $children];
    }

    /**
     * @return array{int, int, int} slot minutes, window start, window end
     */
    private function window(ViewVariant $variant): array
    {
        $slot = $variant->param('slotMinutes', 60);
        if (!\is_int($slot) || $slot < 5 || 1440 % $slot !== 0) {
            throw new \InvalidArgumentException(sprintf('Calendar view "%s" takes a "slotMinutes" that divides a day (1440), e.g. 15, 30, 60, 360 or 1440.', $variant->name));
        }
        $start = self::minutes($variant, 'dayStart', '00:00');
        $end = self::minutes($variant, 'dayEnd', '24:00', allowEndOfDay: true);
        if ($end <= $start) {
            throw new \InvalidArgumentException(sprintf('Calendar view "%s": "dayEnd" must be after "dayStart".', $variant->name));
        }

        return [$slot, $start, $end];
    }

    private static function minutes(ViewVariant $variant, string $param, string $default, bool $allowEndOfDay = false): int
    {
        $value = $variant->param($param, $default);
        if ($allowEndOfDay && $value === '24:00') {
            return 1440;
        }
        if (!\is_string($value) || preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value, $match) !== 1) {
            throw new \InvalidArgumentException(sprintf('Calendar view "%s" takes "%s" as HH:MM.', $variant->name, $param));
        }

        return (int) $match[1] * 60 + (int) $match[2];
    }

    /**
     * @return array{int, int}
     */
    private function hoursAndMinutes(int $minutes): array
    {
        return [intdiv($minutes, 60), $minutes % 60];
    }
}

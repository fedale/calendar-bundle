<?php

namespace Fedale\CalendarBundle\Bridge\ApiPlatform\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Fedale\CalendarBundle\Bridge\ApiPlatform\Resource\ApiEvent;
use Fedale\CalendarBundle\Contract\EventFinderInterface;
use Fedale\CalendarBundle\Model\CalendarQuery;
use Fedale\CalendarBundle\Model\DateRange;
use Fedale\CalendarBundle\Model\EventVisibility;
use Fedale\CalendarBundle\Source\CalendarItem;
use Fedale\CalendarBundle\Source\EventCollector;
use Fedale\CalendarBundle\Source\SourceRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The events of a calendar in a range, through the same collector as the UI;
 * one event by id through its source's EventFinderInterface.
 *
 * @implements ProviderInterface<ApiEvent>
 */
final class EventProvider implements ProviderInterface
{
    /** Longest range served at once: a year and a few days around it. */
    private const MAX_DAYS = 400;

    public function __construct(
        private readonly CalendarApi $api,
        private readonly EventCollector $collector,
        private readonly SourceRegistry $sources,
    ) {
    }

    /**
     * @return ApiEvent|list<ApiEvent>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array
    {
        $calendar = $this->api->calendarId($uriVariables);
        $options = $this->api->options($calendar);
        $timezone = $this->api->timezone($options);

        if ($operation instanceof CollectionOperationInterface) {
            $request = $context['request'] ?? null;
            $query = $request instanceof Request ? $request->query : null;
            $start = $this->api->time($query?->getString('start'), 'start', $timezone);
            $end = $this->api->time($query?->getString('end'), 'end', $timezone);
            if ($end <= $start || $start->diff($end)->days > self::MAX_DAYS) {
                throw new BadRequestHttpException(sprintf('"end" must be after "start", at most %d days later.', self::MAX_DAYS));
            }
            $resources = array_values(array_filter(explode(',', $query?->getString('resources') ?? ''), fn(string $id) => $id !== ''));

            $items = $this->collector->collect($options, new CalendarQuery(new DateRange($start, $end), $timezone, $resources));
            if ($resources !== []) {
                // The query passes them on; sources may ignore them, the API does not.
                $items = array_values(array_filter($items, fn(CalendarItem $item) => array_intersect($item->event->resourceIds, $resources) !== []));
            }

            return array_map(fn(CalendarItem $item) => $this->api->event($calendar, $item), $items);
        }

        $sourceKey = (string) ($uriVariables['source'] ?? '');
        $source = \in_array($sourceKey, $options->sources, true) ? $this->sources->get($sourceKey) : null;
        $event = $source instanceof EventFinderInterface ? $source->findEvent((string) ($uriVariables['id'] ?? '')) : null;
        if ($event === null || $event->visibility === EventVisibility::Hidden) {
            throw new NotFoundHttpException('No such event, or its source cannot find events by id.');
        }

        return $this->api->event($calendar, CalendarItem::fromEvent($sourceKey, $event, $timezone, $options->nextDayThreshold));
    }
}

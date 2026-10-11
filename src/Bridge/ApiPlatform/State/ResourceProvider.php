<?php

namespace Fedale\CalendarBundle\Bridge\ApiPlatform\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Fedale\CalendarBundle\Bridge\ApiPlatform\Resource\ApiResourceRow;
use Fedale\CalendarBundle\Model\CalendarQuery;
use Fedale\CalendarBundle\Model\CalendarResource;
use Fedale\CalendarBundle\Model\DateRange;
use Fedale\CalendarBundle\Options\ResourceColumn;
use Fedale\CalendarBundle\Source\ResourceCollector;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The resources of a calendar, as of today. Only the fields its resource
 * columns and groupBy show are exposed, not the whole payload.
 *
 * @implements ProviderInterface<ApiResourceRow>
 */
final class ResourceProvider implements ProviderInterface
{
    public function __construct(
        private readonly CalendarApi $api,
        private readonly ResourceCollector $collector,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return ApiResourceRow|list<ApiResourceRow>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array
    {
        $calendar = $this->api->calendarId($uriVariables);
        $options = $this->api->options($calendar);
        $timezone = $this->api->timezone($options);
        $today = $this->clock->now()->setTimezone($timezone)->setTime(0, 0);

        $fields = array_unique([...array_map(fn(ResourceColumn $column) => $column->field, $options->resourceColumns), ...($options->resourceGroupBy !== null ? [$options->resourceGroupBy] : [])]);
        $rows = [];
        foreach ($this->collector->collect($options, new CalendarQuery(new DateRange($today, $today->modify('+1 day')), $timezone)) as $resource) {
            $rows[$resource->id] = $this->row($calendar, $resource, $fields);
        }

        if ($operation instanceof CollectionOperationInterface) {
            return array_values($rows);
        }

        return $rows[(string) ($uriVariables['id'] ?? '')] ?? throw new NotFoundHttpException('No such resource in this calendar.');
    }

    /**
     * @param list<string> $fields
     */
    private function row(string $calendar, CalendarResource $resource, array $fields): ApiResourceRow
    {
        $row = new ApiResourceRow();
        $row->calendar = $calendar;
        $row->id = $resource->id;
        $row->title = $resource->title;
        $row->parentId = $resource->parentId;
        $row->order = $resource->order;
        $row->color = $resource->style->color;
        foreach ($fields as $field) {
            if (!\in_array($field, ['id', 'title'], true)) {
                $row->fields[$field] = (new ResourceColumn($field))->value($resource);
            }
        }

        return $row;
    }
}

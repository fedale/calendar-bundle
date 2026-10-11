<?php

namespace Fedale\CalendarBundle\Source;

use Fedale\CalendarBundle\Contract\ResourceSourceInterface;
use Fedale\CalendarBundle\Model\CalendarQuery;
use Fedale\CalendarBundle\Model\CalendarResource;
use Fedale\CalendarBundle\Options\CalendarOptions;

/**
 * Gathers the resources of every source of a calendar that also implements
 * {@see ResourceSourceInterface}. Two sources sharing a resource id share the
 * row: the first one wins.
 */
class ResourceCollector
{
    public function __construct(private readonly SourceRegistry $sources)
    {
    }

    /**
     * @return list<CalendarResource>
     */
    public function collect(CalendarOptions $options, CalendarQuery $query): array
    {
        $resources = [];
        foreach ($options->sources as $key) {
            $source = $this->sources->get($key);
            if (!$source instanceof ResourceSourceInterface) {
                continue;
            }
            foreach ($source->getResources($query) as $resource) {
                if (!$resource instanceof CalendarResource) {
                    throw new \UnexpectedValueException(sprintf('Calendar source "%s" must yield %s objects from getResources(), got %s.', $key, CalendarResource::class, get_debug_type($resource)));
                }
                $resources[$resource->id] ??= $resource;
            }
        }

        return array_values($resources);
    }
}

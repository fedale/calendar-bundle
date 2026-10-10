<?php

namespace Fedale\CalendarBundle\Contract;

use Fedale\CalendarBundle\Model\CalendarQuery;
use Fedale\CalendarBundle\Model\CalendarResource;

/**
 * Optional companion of {@see EventSourceInterface}: a source that also
 * implements it provides the rows of the timeline and the columns of the
 * resource view.
 */
interface ResourceSourceInterface
{
    /**
     * @return iterable<CalendarResource>
     */
    public function getResources(CalendarQuery $query): iterable;
}

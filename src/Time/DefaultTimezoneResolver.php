<?php

namespace Fedale\CalendarBundle\Time;

use Fedale\CalendarBundle\Contract\TimezoneResolverInterface;

/**
 * PHP's default timezone (date.timezone / date_default_timezone_set()).
 */
final class DefaultTimezoneResolver implements TimezoneResolverInterface
{
    public function resolve(): \DateTimeZone
    {
        return new \DateTimeZone(date_default_timezone_get());
    }
}

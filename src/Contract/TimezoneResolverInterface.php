<?php

namespace Fedale\CalendarBundle\Contract;

/**
 * The timezone calendars are displayed in when their options do not set one.
 * Replace the default (PHP's default timezone) to follow the user's own.
 */
interface TimezoneResolverInterface
{
    public function resolve(): \DateTimeZone;
}

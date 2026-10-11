<?php

namespace Fedale\CalendarBundle\Tests\Options;

use Fedale\CalendarBundle\Options\BusinessHours;
use PHPUnit\Framework\TestCase;

final class BusinessHoursTest extends TestCase
{
    public function testClosedHoursAreTheGapsBetweenMergedRules(): void
    {
        $hours = BusinessHours::fromArray([
            ['days' => [1, 2, 3, 4, 5], 'start' => '09:00', 'end' => '13:00'],
            ['days' => [1, 2, 3, 4, 5], 'start' => '12:00', 'end' => '18:00'],
            ['days' => [6], 'start' => '10:00', 'end' => '12:00'],
        ], 'display.businessHours');

        self::assertSame([[0, 540], [1080, 1440]], $hours->closed(new \DateTimeImmutable('2026-10-12'))); // Monday
        self::assertSame([[480, 600], [720, 1200]], $hours->closed(new \DateTimeImmutable('2026-10-10'), 480, 1200)); // Saturday, 08-20 window
        self::assertSame([[0, 1440]], $hours->closed(new \DateTimeImmutable('2026-10-11'))); // Sunday: closed all day
    }

    public function testRejectsMalformedRules(): void
    {
        $this->expectExceptionMessage('"display.businessHours.0.end" must be after its start');

        BusinessHours::fromArray([['days' => [1], 'start' => '18:00', 'end' => '09:00']], 'display.businessHours');
    }
}

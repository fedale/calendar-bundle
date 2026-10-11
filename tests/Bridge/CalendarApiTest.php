<?php

namespace Fedale\CalendarBundle\Tests\Bridge;

use Fedale\CalendarBundle\Bridge\ApiPlatform\State\CalendarApi;
use Fedale\CalendarBundle\Contract\CalendarViewInterface;
use Fedale\CalendarBundle\Contract\TimezoneResolverInterface;
use Fedale\CalendarBundle\Definition\AbstractCalendar;
use Fedale\CalendarBundle\Definition\CalendarRegistry;
use Fedale\CalendarBundle\Options\CalendarOptionsResolver;
use Fedale\CalendarBundle\Source\SourceRegistry;
use Fedale\CalendarBundle\View\ViewRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class CalendarApiTest extends TestCase
{
    /**
     * @param list<string>                $exposed
     * @param array<string, list<string>> $grants  attribute => calendar ids granted
     */
    private function api(array $exposed = [], ?string $read = null, ?string $write = null, ?array $grants = null): CalendarApi
    {
        $calendar = new class extends AbstractCalendar {};
        $registry = new CalendarRegistry(new ServiceLocator(['public' => fn() => $calendar, 'staff' => fn() => $calendar]));
        $view = $this->createStub(CalendarViewInterface::class);
        $options = new CalendarOptionsResolver($registry, new ViewRegistry(new ServiceLocator(['month' => fn() => $view, 'list' => fn() => $view])), new SourceRegistry(new ServiceLocator([])));

        $authorization = null;
        if ($grants !== null) {
            $authorization = $this->createStub(AuthorizationCheckerInterface::class);
            $authorization->method('isGranted')->willReturnCallback(fn(string $attribute, string $id) => \in_array($id, $grants[$attribute] ?? [], true));
        }

        return new CalendarApi($registry, $options, $this->createStub(TimezoneResolverInterface::class), $exposed, $read, $write, $authorization);
    }

    public function testEveryCalendarWithoutRestrictions(): void
    {
        self::assertSame(['public', 'staff'], $this->api()->readableIds());
        self::assertSame('staff', $this->api()->calendarId(['calendar' => 'staff'], write: true));
    }

    public function testCalendarsLeftOutOfTheListDoNotExist(): void
    {
        $api = $this->api(exposed: ['public']);

        self::assertSame(['public'], $api->readableIds());
        $this->expectException(NotFoundHttpException::class);
        $api->calendarId(['calendar' => 'staff']);
    }

    public function testReadAndWriteAttributesAreCheckedPerCalendar(): void
    {
        $api = $this->api(read: 'CALENDAR_READ', write: 'CALENDAR_WRITE', grants: ['CALENDAR_READ' => ['public', 'staff'], 'CALENDAR_WRITE' => ['staff']]);

        self::assertSame(['public', 'staff'], $api->readableIds());
        self::assertSame('public', $api->calendarId(['calendar' => 'public']));
        self::assertSame('staff', $api->calendarId(['calendar' => 'staff'], write: true));
        $this->expectException(AccessDeniedHttpException::class);
        $api->calendarId(['calendar' => 'public'], write: true);
    }

    public function testAnAttributeWithoutSecurityDeniesInsteadOfOpeningUp(): void
    {
        $api = $this->api(read: 'CALENDAR_READ');

        self::assertSame([], $api->readableIds());
        $this->expectException(AccessDeniedHttpException::class);
        $api->calendarId(['calendar' => 'public']);
    }
}

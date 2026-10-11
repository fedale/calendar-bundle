<?php

namespace Fedale\CalendarBundle\Tests\Intent;

use Fedale\CalendarBundle\Contract\EventSourceInterface;
use Fedale\CalendarBundle\Contract\IntentHandlerInterface;
use Fedale\CalendarBundle\Intent\CalendarIntent;
use Fedale\CalendarBundle\Intent\DeleteEvent;
use Fedale\CalendarBundle\Intent\IntentDispatcher;
use Fedale\CalendarBundle\Intent\IntentResult;
use Fedale\CalendarBundle\Intent\MoveEvent;
use Fedale\CalendarBundle\Intent\Rejection;
use Fedale\CalendarBundle\Intent\ResizeEvent;
use Fedale\CalendarBundle\Intent\SelectRange;
use Fedale\CalendarBundle\Model\CalendarEvent;
use Fedale\CalendarBundle\Model\CalendarQuery;
use Fedale\CalendarBundle\Model\EventInteractions;
use Fedale\CalendarBundle\Options\CalendarOptions;
use Fedale\CalendarBundle\Options\OptionsMerger;
use Fedale\CalendarBundle\Source\CalendarItem;
use Fedale\CalendarBundle\Source\SourceRegistry;
use Fedale\CalendarBundle\Tests\Support\Items;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class IntentDispatcherTest extends TestCase
{
    /** @var list<CalendarIntent> */
    private array $handled = [];

    private function dispatcher(): IntentDispatcher
    {
        $handler = new class($this->handled) implements EventSourceInterface, IntentHandlerInterface {
            /** @param list<CalendarIntent> $handled */
            public function __construct(private array &$handled)
            {
            }

            public function getEvents(CalendarQuery $query): iterable
            {
                return [];
            }

            public function handle(CalendarIntent $intent): IntentResult
            {
                $this->handled[] = $intent;

                return IntentResult::accepted('done');
            }
        };
        $readOnly = $this->createStub(EventSourceInterface::class);

        return new IntentDispatcher(new SourceRegistry(new ServiceLocator(['rooms' => fn() => $handler, 'holidays' => fn() => $readOnly])));
    }

    /**
     * @param array<string, mixed> $behavior
     */
    private function options(array $behavior = []): CalendarOptions
    {
        return CalendarOptions::fromArray(OptionsMerger::merge(CalendarOptions::DEFAULTS, [
            'behavior' => $behavior,
            'integration' => ['sources' => ['holidays', 'rooms']],
        ]));
    }

    /**
     * @return list<CalendarItem>
     */
    private function items(): array
    {
        $editable = new EventInteractions(movable: true, resizable: true, deletable: true);

        return [
            CalendarItem::fromEvent('rooms', new CalendarEvent('talk', 'Talk', Items::at('2026-10-12 09:00'), Items::at('2026-10-12 10:00'), interactions: $editable, version: '3'), Items::tz()),
            CalendarItem::fromEvent('rooms', new CalendarEvent('fixed', 'Fixed', Items::at('2026-10-12 11:00'), Items::at('2026-10-12 12:00'), version: '1'), Items::tz()),
            CalendarItem::fromEvent('holidays', new CalendarEvent('xmas', 'Christmas', Items::at('2026-12-25'), Items::at('2026-12-26'), allDay: true, interactions: $editable), Items::tz()),
        ];
    }

    private function move(string $source, string $id, ?string $version, string $start = '2026-10-12 14:00', string $end = '2026-10-12 15:00'): MoveEvent
    {
        return new MoveEvent('rooms', $source, $id, $version, Items::at($start), Items::at($end));
    }

    public function testHandsAValidIntentToTheSourceHandler(): void
    {
        $result = $this->dispatcher()->dispatch($this->options(), $intent = $this->move('rooms', 'talk', '3'), $this->items());

        self::assertTrue($result->accepted);
        self::assertSame([$intent], $this->handled);
    }

    /**
     * @return iterable<string, array{\Closure(self): CalendarIntent, Rejection, string}>
     */
    public static function rejected(): iterable
    {
        yield 'event not on screen' => [fn(self $test) => $test->move('rooms', 'nope', '1'), Rejection::Conflict, 'intent.gone'];
        yield 'interaction not enabled' => [fn(self $test) => $test->move('rooms', 'fixed', '1'), Rejection::Forbidden, 'intent.not_allowed'];
        yield 'stale version' => [fn(self $test) => $test->move('rooms', 'talk', '2'), Rejection::Conflict, 'intent.stale'];
        yield 'end before start' => [fn(self $test) => new ResizeEvent('rooms', 'rooms', 'talk', '3', Items::at('2026-10-12 09:00'), Items::at('2026-10-12 08:00')), Rejection::Validation, 'intent.invalid'];
        yield 'read-only source' => [fn(self $test) => new DeleteEvent('rooms', 'holidays', 'xmas'), Rejection::Forbidden, 'intent.read_only'];
        yield 'selection not enabled' => [fn(self $test) => new SelectRange('rooms', Items::at('2026-10-12 09:00'), Items::at('2026-10-12 10:00')), Rejection::Forbidden, 'intent.not_allowed'];
    }

    /**
     * @param \Closure(self): CalendarIntent $intent
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('rejected')]
    public function testRejectsWhatTheCalendarCanAlreadyTell(\Closure $intent, Rejection $reason, string $message): void
    {
        $result = $this->dispatcher()->dispatch($this->options(), $intent($this), $this->items());

        self::assertFalse($result->accepted);
        self::assertSame($reason, $result->rejection);
        self::assertSame($message, $result->message);
        self::assertSame('CalendarBundle', $result->translationDomain);
        self::assertSame([], $this->handled, 'The handler is not called.');
    }

    public function testSelectionsGoToTheFirstSourceThatHandlesIntents(): void
    {
        $intent = new SelectRange('rooms', Items::at('2026-10-12 09:00'), Items::at('2026-10-12 10:00'), resourceId: 'a');

        $result = $this->dispatcher()->dispatch($this->options(['selectable' => true]), $intent, []);

        self::assertTrue($result->accepted);
        self::assertSame([$intent], $this->handled);
    }

    public function testASelectSourceWithoutHandlerMakesSelectionsReadOnly(): void
    {
        $intent = new SelectRange('rooms', Items::at('2026-10-12 09:00'), Items::at('2026-10-12 10:00'));

        $result = $this->dispatcher()->dispatch($this->options(['selectable' => true, 'selectSource' => 'holidays']), $intent, []);

        self::assertSame('intent.read_only', $result->message);
    }
}

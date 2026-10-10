<?php

namespace Fedale\CalendarBundle\Tests\Options;

use Fedale\CalendarBundle\Options\OptionsMerger;
use PHPUnit\Framework\TestCase;

final class OptionsMergerTest extends TestCase
{
    public function testMergesSubtreesKeepingSiblings(): void
    {
        $merged = OptionsMerger::merge(
            ['display' => ['firstDay' => 1, 'weekNumbers' => false]],
            ['display' => ['weekNumbers' => true]],
        );

        self::assertSame(['display' => ['firstDay' => 1, 'weekNumbers' => true]], $merged);
    }

    public function testReplacesListsAsAWhole(): void
    {
        $merged = OptionsMerger::merge(
            ['views' => ['enabled' => ['month', 'list']]],
            ['views' => ['enabled' => ['list']]],
        );

        self::assertSame(['views' => ['enabled' => ['list']]], $merged);
    }

    public function testAnEmptyArrayOverridesNothingInASubtreeButEmptiesAList(): void
    {
        $merged = OptionsMerger::merge(
            ['display' => ['firstDay' => 1, 'hiddenDays' => [6, 7]]],
            ['display' => []],
            ['display' => ['hiddenDays' => []]],
        );

        self::assertSame(['display' => ['firstDay' => 1, 'hiddenDays' => []]], $merged);
    }

    public function testLaterLayersWin(): void
    {
        self::assertSame(['a' => 3], OptionsMerger::merge(['a' => 1], ['a' => 2], ['a' => 3]));
    }
}

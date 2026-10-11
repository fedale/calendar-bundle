<?php

namespace Fedale\CalendarBundle\Tests\UiSettings;

use Fedale\CalendarBundle\Options\CalendarOptions;
use Fedale\CalendarBundle\Options\OptionsMerger;
use Fedale\CalendarBundle\UiSettings\CalendarDescriptor;
use Fedale\CalendarBundle\UiSettings\DefaultViewUiSetting;
use Fedale\CalendarBundle\UiSettings\SlotDurationUiSetting;
use Fedale\CalendarBundle\UiSettings\WeekendsUiSetting;
use PHPUnit\Framework\TestCase;

final class UiSettingsTest extends TestCase
{
    /**
     * @param array<string, mixed> $display
     *
     * @return array<string, mixed>
     */
    private function tree(array $display): array
    {
        return OptionsMerger::merge(CalendarOptions::DEFAULTS, ['display' => $display]);
    }

    public function testWeekendsEditHiddenDaysWithoutLosingTheOthers(): void
    {
        $setting = new WeekendsUiSetting();
        $tree = $this->tree(['hiddenDays' => [3, 7]]);

        self::assertSame([3, 6, 7], $setting->apply($tree, 'hide')['display']['hiddenDays']);
        self::assertSame([3], $setting->apply($tree, 'show')['display']['hiddenDays']);
        self::assertFalse($setting->isApplicable('hide', $this->tree(['hiddenDays' => [1, 2, 3, 4, 5]])));
    }

    public function testSlotLengthGoesToEveryTimeGridVariantThatCanTakeIt(): void
    {
        $setting = new SlotDurationUiSetting();
        $tree = $this->tree(['views' => ['variants' => ['timeGridWeek' => ['dayStart' => '08:15']]]]);

        self::assertFalse($setting->isApplicable(30, $tree), '08:15-24:00 is not a whole number of 30-minute slots.');
        self::assertTrue($setting->isApplicable(15, $tree));

        $applied = $setting->apply($tree, 15)['display']['views']['variants'];
        self::assertSame([15, 15, 15, 15], [$applied['timeGridDay']['slotMinutes'], $applied['timeGridWeek']['slotMinutes'], $applied['resourceTimeGridDay']['slotMinutes'], $applied['resourceTimeGridWeek']['slotMinutes']]);
        self::assertSame(60, $applied['timelineDay']['slotMinutes']);
    }

    public function testDefaultViewIsOfferedAndAppliedOnlyAmongTheEnabledViews(): void
    {
        $setting = new DefaultViewUiSetting();
        $calendar = new CalendarDescriptor('rooms', null, $this->tree(['views' => ['enabled' => ['timelineDay', 'timelineWeek'], 'default' => 'timelineDay']]));

        self::assertSame(['view.timelineDay' => 'timelineDay', 'view.timelineWeek' => 'timelineWeek'], $setting->choicesFor($calendar));
        self::assertTrue($setting->supportsCalendar($calendar));
        self::assertFalse($setting->isApplicable('month', $calendar->tree));
        self::assertContains('year', $setting->choicesFor(null));
    }
}

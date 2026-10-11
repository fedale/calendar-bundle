<?php

namespace Fedale\CalendarBundle\UiSettings;

/** Week numbers in the month view (`display.weekNumbers`). */
final class WeekNumbersUiSetting extends AbstractUiSetting
{
    public function key(): string
    {
        return 'week_numbers';
    }

    public function label(): string
    {
        return 'ui_settings.week_numbers.label';
    }

    protected function optionPath(): string
    {
        return 'display.weekNumbers';
    }

    public function supportsCalendar(CalendarDescriptor $calendar): bool
    {
        return $calendar->hasViewType('month');
    }

    public function choicesFor(?CalendarDescriptor $calendar): array
    {
        return ['ui_settings.week_numbers.show' => 'show', 'ui_settings.week_numbers.hide' => 'hide'];
    }

    public function apply(array $tree, mixed $value): array
    {
        return parent::apply($tree, $value === 'show');
    }
}

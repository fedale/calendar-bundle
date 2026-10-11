<?php

namespace Fedale\CalendarBundle\UiSettings;

/** The first day of the week (`display.firstDay`). */
final class FirstDayUiSetting extends AbstractUiSetting
{
    public function key(): string
    {
        return 'first_day';
    }

    public function label(): string
    {
        return 'ui_settings.first_day.label';
    }

    protected function optionPath(): string
    {
        return 'display.firstDay';
    }

    public function choicesFor(?CalendarDescriptor $calendar): array
    {
        return ['ui_settings.first_day.monday' => 1, 'ui_settings.first_day.saturday' => 6, 'ui_settings.first_day.sunday' => 7];
    }
}

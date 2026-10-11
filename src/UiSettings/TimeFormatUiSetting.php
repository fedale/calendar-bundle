<?php

namespace Fedale\CalendarBundle\UiSettings;

/** 24-hour or 12-hour clock (`display.timeFormat`). */
final class TimeFormatUiSetting extends AbstractUiSetting
{
    public function key(): string
    {
        return 'time_format';
    }

    public function label(): string
    {
        return 'ui_settings.time_format.label';
    }

    protected function optionPath(): string
    {
        return 'display.timeFormat';
    }

    public function choicesFor(?CalendarDescriptor $calendar): array
    {
        return ['ui_settings.time_format.24h' => '24h', 'ui_settings.time_format.12h' => '12h'];
    }
}

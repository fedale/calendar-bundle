<?php

namespace Fedale\CalendarBundle\UiSettings;

use Fedale\SettingBundle\Scoped\SettingDefinitionInterface;

/**
 * One calendar option the end user may set at runtime from the settings
 * dialog, for every calendar or for one calendar (which wins).
 *
 * This is the extension point of the configurator: a new option is one class
 * implementing this interface (or extending {@see AbstractUiSetting}). It is
 * a fedale/setting-bundle scoped setting of the `calendar.ui` namespace, at
 * the scope levels `global` and `calendar`: setting-bundle autoconfigures,
 * stores and resolves it (current tenant first, then calendar before global).
 *
 * The resolved value is applied to the calendar's option tree after
 * viewConfig() and before validation, and only when isApplicable() accepts it
 * for that calendar: its context is the calendar's option tree.
 */
interface UiSettingInterface extends SettingDefinitionInterface
{
    /**
     * Writes $value into the option tree and returns it. Most settings set
     * one leaf; a setting may also touch several (e.g. every time-grid
     * variant's slot length).
     *
     * @param array<string, mixed> $tree
     *
     * @return array<string, mixed>
     */
    public function apply(array $tree, mixed $value): array;

    /** Whether the setting is offered for every calendar at once. */
    public function supportsGlobal(): bool;

    /** Whether the setting is offered for this calendar. */
    public function supportsCalendar(CalendarDescriptor $calendar): bool;

    /**
     * The values offered in the dialog, as `translation key => value`; $calendar
     * is null for the global scope.
     *
     * @return array<string, scalar>
     */
    public function choicesFor(?CalendarDescriptor $calendar): array;
}

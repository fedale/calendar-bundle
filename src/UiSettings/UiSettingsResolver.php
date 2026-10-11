<?php

namespace Fedale\CalendarBundle\UiSettings;

use Fedale\SettingBundle\Scoped\Scope;
use Fedale\SettingBundle\Scoped\ScopeChain;
use Fedale\SettingBundle\Scoped\ScopedSettingsResolver;
use Fedale\SettingBundle\Scoped\ScopedSettingsWriter;
use Fedale\SettingBundle\Scoped\SettingDefinitionRegistry;

/**
 * Applies the stored UI settings to a calendar's option tree, and reads and
 * writes them for the settings dialog, on top of fedale/setting-bundle's
 * scoped settings.
 *
 * Precedence, lowest to highest: bundle defaults < YAML < viewConfig() <
 * global UI setting < per-calendar UI setting (the current tenant's values
 * before the platform's) < the view and date in the URL. A value a calendar
 * cannot honour ({@see UiSettingInterface::isApplicable()}) is skipped and the
 * next scope is tried.
 *
 * Only defined when setting-bundle and symfony/form are installed; the
 * feature is off until `fedale_setting.scoped.store` is configured.
 */
class UiSettingsResolver
{
    public const NAMESPACE = 'calendar.ui';

    /** Scope level of a single calendar; the scope id is the calendar id. */
    public const CALENDAR_LEVEL = 'calendar';

    /** Value of the dialog's `scope` parameter for every calendar at once. */
    public const GLOBAL_SCOPE = '_global';

    public function __construct(
        private readonly ScopedSettingsResolver $resolver,
        private readonly ScopedSettingsWriter $writer,
        private readonly SettingDefinitionRegistry $definitions,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->resolver->isEnabled();
    }

    /**
     * @param array<string, mixed> $tree the calendar's option tree as configured in code
     *
     * @return array<string, mixed>
     */
    public function apply(string $calendarId, array $tree): array
    {
        $settings = $this->settings();
        if ($settings === [] || !$this->isEnabled()) {
            return $tree;
        }

        $chain = new ScopeChain([self::scope($calendarId), Scope::global()]);
        foreach ($this->resolver->resolve(self::NAMESPACE, $chain, $tree) as $key => $resolved) {
            if (isset($settings[$key])) {
                $tree = $settings[$key]->apply($tree, $resolved->value);
            }
        }

        return $tree;
    }

    /**
     * The settings offered in a scope: the global ones when $calendar is null.
     *
     * @return array<string, UiSettingInterface>
     */
    public function settingsFor(?CalendarDescriptor $calendar): array
    {
        return array_filter(
            $this->settings(),
            static fn(UiSettingInterface $setting): bool => $calendar === null ? $setting->supportsGlobal() : $setting->supportsCalendar($calendar),
        );
    }

    /**
     * @return array<string, mixed> setting key => value stored for the scope (no inheritance)
     */
    public function values(string $scope): array
    {
        return $this->resolver->values(self::NAMESPACE, self::scope($scope));
    }

    /**
     * What each setting of a scope falls back to on "inherit".
     *
     * @return array<string, mixed> setting key => inherited value
     */
    public function inherited(string $scope, ?CalendarDescriptor $calendar = null): array
    {
        $own = self::scope($scope);
        $chain = new ScopeChain($own->isGlobal() ? [$own] : [$own, Scope::global()]);
        $values = [];
        foreach ($this->resolver->inherited(self::NAMESPACE, $chain, $calendar?->tree) as $key => $resolved) {
            $values[$key] = $resolved->value;
        }

        return $values;
    }

    /**
     * Writes the values of a scope; null and '' mean "inherit". Keys of
     * settings not offered in the scope are ignored.
     *
     * @param array<string, mixed> $values
     */
    public function save(string $scope, array $values, ?CalendarDescriptor $calendar = null): void
    {
        $this->writer->save(self::NAMESPACE, self::scope($scope), array_intersect_key($values, $this->settingsFor($calendar)));
    }

    /**
     * @return array<string, UiSettingInterface>
     */
    private function settings(): array
    {
        return array_filter(
            $this->definitions->all(self::NAMESPACE),
            static fn(object $definition): bool => $definition instanceof UiSettingInterface,
        );
    }

    private static function scope(string $scope): Scope
    {
        return $scope === self::GLOBAL_SCOPE ? Scope::global() : new Scope(self::CALENDAR_LEVEL, $scope);
    }
}

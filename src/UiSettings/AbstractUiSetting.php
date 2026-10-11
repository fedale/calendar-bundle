<?php

namespace Fedale\CalendarBundle\UiSettings;

use Fedale\SettingBundle\Scoped\AbstractSettingDefinition;
use Fedale\SettingBundle\Scoped\Scope;
use Fedale\SettingBundle\Scoped\SettingType;

/**
 * Convenience base for {@see UiSettingInterface}: a choice offered in both
 * scopes and written to one option path. A concrete setting declares its key,
 * label, path and choices.
 */
abstract class AbstractUiSetting extends AbstractSettingDefinition implements UiSettingInterface
{
    /** Dotted path of the option the value is written to, e.g. 'display.firstDay'. */
    abstract protected function optionPath(): string;

    public function namespace(): string
    {
        return UiSettingsResolver::NAMESPACE;
    }

    public function type(): SettingType
    {
        return SettingType::Enum;
    }

    public function supportsGlobal(): bool
    {
        return true;
    }

    public function supportsCalendar(CalendarDescriptor $calendar): bool
    {
        return true;
    }

    public function supportsScope(Scope $scope): bool
    {
        return $scope->isGlobal() ? $this->supportsGlobal() : $scope->level === UiSettingsResolver::CALENDAR_LEVEL;
    }

    /**
     * The global choices are enforced by setting-bundle on write and read; a
     * calendar scope answers null, its choices depending on the calendar,
     * which the dialog enforces on write and isApplicable() at render time.
     */
    public function choices(?Scope $scope): ?array
    {
        return $scope === null || $scope->isGlobal() ? $this->choicesFor(null) : null;
    }

    public function isApplicable(mixed $value, mixed $context): bool
    {
        return \in_array($value, $this->choicesFor(null), true);
    }

    public function apply(array $tree, mixed $value): array
    {
        $node = &$tree;
        foreach (explode('.', $this->optionPath()) as $segment) {
            if (!isset($node[$segment]) || !\is_array($node[$segment])) {
                $node[$segment] = [];
            }
            $node = &$node[$segment];
        }
        $node = $value;

        return $tree;
    }
}

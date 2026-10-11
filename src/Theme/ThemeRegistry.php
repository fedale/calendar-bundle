<?php

namespace Fedale\CalendarBundle\Theme;

/**
 * Framework themes: the CSS classes of the calendar's controls, by class key.
 * The `default` theme is the canonical key set and uses the bundle's own
 * `cal-*` styles; `bootstrap5` maps the same keys to Bootstrap's classes.
 * Applications declare more under `fedale_calendar.themes`, optionally
 * extending a built-in one and overriding some keys.
 *
 * Layout never depends on a theme: themes only dress the controls (toolbar
 * buttons and button groups).
 */
final class ThemeRegistry
{
    public const DEFAULT = [
        'button' => 'cal-btn',
        'button.primary' => 'cal-btn cal-btn--primary',
        'button.icon' => 'cal-btn cal-btn--icon',
        // Added to a view button when its view is on screen (aria-pressed is set too).
        'button.active' => '',
        'button.group' => 'cal-toolbar__group--joined',
    ];

    private const BUILT_IN = [
        'default' => self::DEFAULT,
        'bootstrap5' => [
            'button' => 'btn btn-outline-secondary',
            'button.primary' => 'btn btn-primary',
            'button.icon' => 'btn btn-outline-secondary',
            'button.active' => 'active',
            'button.group' => 'btn-group',
        ],
    ];

    /**
     * @param array<string, array{extends?: string|null, classes?: array<string, string>}> $themes application themes
     */
    public function __construct(private readonly array $themes = [])
    {
    }

    public function has(string $name): bool
    {
        return isset(self::BUILT_IN[$name]) || isset($this->themes[$name]);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_values(array_unique([...array_keys(self::BUILT_IN), ...array_keys($this->themes)]));
    }

    /**
     * Every class key of the theme, missing keys taken from what it extends
     * and then from the default theme.
     *
     * @return array<string, string>
     */
    public function classes(string $name): array
    {
        if (isset(self::BUILT_IN[$name])) {
            return self::BUILT_IN[$name] + self::DEFAULT;
        }
        $theme = $this->themes[$name] ?? throw new \InvalidArgumentException(sprintf('Unknown calendar theme "%s"; known themes: %s.', $name, implode(', ', $this->names())));
        $base = ($theme['extends'] ?? null) !== null ? $this->classes((string) $theme['extends']) : self::DEFAULT;

        return ($theme['classes'] ?? []) + $base;
    }
}

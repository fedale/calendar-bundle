<?php

namespace Fedale\CalendarBundle\Options;

/**
 * The resolved, validated options of one calendar. Built from the merged
 * option tree by {@see fromArray()}, which rejects unknown keys so a typo in a
 * viewConfig() fails loudly instead of being ignored.
 */
final readonly class CalendarOptions
{
    /** The canonical option tree: every accepted key, with its bundle default. */
    public const DEFAULTS = [
        'display' => [
            'views' => [
                'enabled' => ['month', 'list'],
                'default' => 'month',
            ],
            // ISO-8601 day numbers: 1 = Monday ... 7 = Sunday.
            'firstDay' => 1,
            'hiddenDays' => [],
            'weekNumbers' => false,
            'timeFormat' => '24h',
            // H:i. A timed event shows on the day it ends only if it ends at or
            // after this time: '09:00' keeps a 22:00-06:00 shift on one day.
            'nextDayThreshold' => '00:00',
            // null = the request locale.
            'locale' => null,
            // null = the TimezoneResolverInterface service.
            'timezone' => null,
            'month' => [
                'dayMaxEvents' => 3,
            ],
            'list' => [
                'range' => 'month',
            ],
        ],
        'integration' => [
            'sources' => [],
        ],
    ];

    /**
     * @param list<string>         $enabledViews
     * @param list<int>            $hiddenDays   ISO-8601 day numbers
     * @param list<string>         $sources
     * @param array<string, mixed> $tree         the merged tree, for custom views reading their own keys
     */
    public function __construct(
        public array $enabledViews,
        public string $defaultView,
        public int $firstDay,
        public array $hiddenDays,
        public bool $weekNumbers,
        public TimeFormat $timeFormat,
        public string $nextDayThreshold,
        public ?string $locale,
        public ?string $timezone,
        public int $dayMaxEvents,
        public ListRange $listRange,
        public array $sources,
        public array $tree,
    ) {
    }

    /**
     * @param array<string, mixed> $tree a tree merged over {@see DEFAULTS}
     */
    public static function fromArray(array $tree): self
    {
        self::assertKnownKeys($tree, self::DEFAULTS, '');

        $display = $tree['display'];

        $enabled = self::stringList($display['views']['enabled'], 'display.views.enabled');
        if ($enabled === []) {
            throw new \InvalidArgumentException('Calendar option "display.views.enabled" must list at least one view.');
        }
        $default = $display['views']['default'];
        if (!\is_string($default) || !\in_array($default, $enabled, true)) {
            throw new \InvalidArgumentException(sprintf('Calendar option "display.views.default" must be one of the enabled views (%s).', implode(', ', $enabled)));
        }

        $firstDay = self::isoDay($display['firstDay'], 'display.firstDay');
        $hiddenDays = [];
        foreach ((array) $display['hiddenDays'] as $day) {
            $hiddenDays[] = self::isoDay($day, 'display.hiddenDays');
        }
        $hiddenDays = array_values(array_unique($hiddenDays));
        if (\count($hiddenDays) >= 7) {
            throw new \InvalidArgumentException('Calendar option "display.hiddenDays" cannot hide every day of the week.');
        }

        $timezone = $display['timezone'];
        if ($timezone !== null) {
            try {
                new \DateTimeZone((string) $timezone);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException(sprintf('Calendar option "display.timezone" is not a valid timezone, got "%s".', $timezone), 0, $e);
            }
        }

        $threshold = $display['nextDayThreshold'];
        if (!\is_string($threshold) || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $threshold) !== 1) {
            throw new \InvalidArgumentException('Calendar option "display.nextDayThreshold" must be a time as HH:MM, e.g. "09:00".');
        }

        $dayMaxEvents = $display['month']['dayMaxEvents'];
        if (!\is_int($dayMaxEvents) || $dayMaxEvents < 1) {
            throw new \InvalidArgumentException('Calendar option "display.month.dayMaxEvents" must be a positive integer.');
        }

        return new self(
            enabledViews: $enabled,
            defaultView: $default,
            firstDay: $firstDay,
            hiddenDays: $hiddenDays,
            weekNumbers: (bool) $display['weekNumbers'],
            timeFormat: self::enum(TimeFormat::class, $display['timeFormat'], 'display.timeFormat'),
            nextDayThreshold: $threshold,
            locale: $display['locale'] !== null ? (string) $display['locale'] : null,
            timezone: $timezone !== null ? (string) $timezone : null,
            dayMaxEvents: $dayMaxEvents,
            listRange: self::enum(ListRange::class, $display['list']['range'], 'display.list.range'),
            sources: self::stringList($tree['integration']['sources'], 'integration.sources'),
            tree: $tree,
        );
    }

    public function isHidden(\DateTimeInterface $day): bool
    {
        return \in_array((int) $day->format('N'), $this->hiddenDays, true);
    }

    /**
     * @param array<string, mixed> $tree
     * @param array<string, mixed> $defaults
     */
    private static function assertKnownKeys(array $tree, array $defaults, string $path): void
    {
        foreach ($tree as $key => $value) {
            $keyPath = ltrim($path.'.'.$key, '.');
            if (!\array_key_exists($key, $defaults)) {
                throw new \InvalidArgumentException(sprintf('Unknown calendar option "%s".', $keyPath));
            }
            $default = $defaults[$key];
            // Only associative defaults are subtrees; a list default (e.g.
            // `hiddenDays`) is a leaf whose value is validated on its own.
            if (\is_array($default) && $default !== [] && !array_is_list($default)) {
                if (!\is_array($value)) {
                    throw new \InvalidArgumentException(sprintf('Calendar option "%s" must be an array.', $keyPath));
                }
                self::assertKnownKeys($value, $default, $keyPath);
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value, string $path): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException(sprintf('Calendar option "%s" must be a list of strings.', $path));
        }
        foreach ($value as $item) {
            if (!\is_string($item) || $item === '') {
                throw new \InvalidArgumentException(sprintf('Calendar option "%s" must be a list of strings.', $path));
            }
        }

        return array_values(array_unique($value));
    }

    private static function isoDay(mixed $value, string $path): int
    {
        if (!\is_int($value) || $value < 1 || $value > 7) {
            throw new \InvalidArgumentException(sprintf('Calendar option "%s" takes ISO-8601 day numbers, 1 (Monday) to 7 (Sunday).', $path));
        }

        return $value;
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     */
    private static function enum(string $enum, mixed $value, string $path): \BackedEnum
    {
        $case = \is_string($value) ? $enum::tryFrom($value) : null;
        if ($case === null) {
            throw new \InvalidArgumentException(sprintf('Calendar option "%s" must be one of: %s.', $path, implode(', ', array_map(fn(\BackedEnum $c) => $c->value, $enum::cases()))));
        }

        return $case;
    }
}

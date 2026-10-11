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
                // Variant names, as offered by the toolbar.
                'enabled' => ['month', 'listMonth'],
                'default' => 'month',
                // A variant is a view type plus its parameters: one type
                // (timeline) serves several durations (timelineDay, ...).
                // Open map: add your own names, or override a built-in's
                // parameters key by key.
                'variants' => [
                    'year' => ['type' => 'year'],
                    'month' => ['type' => 'month'],
                    'listDay' => ['type' => 'list', 'range' => 'day'],
                    'listWeek' => ['type' => 'list', 'range' => 'week'],
                    'listMonth' => ['type' => 'list', 'range' => 'month'],
                    'timelineDay' => ['type' => 'timeline', 'range' => 'day', 'slotMinutes' => 60],
                    'timelineWeek' => ['type' => 'timeline', 'range' => 'week', 'slotMinutes' => 360],
                    'timelineMonth' => ['type' => 'timeline', 'range' => 'month', 'slotMinutes' => 1440],
                    'timeGridDay' => ['type' => 'timeGrid', 'range' => 'day', 'slotMinutes' => 30],
                    'timeGridWeek' => ['type' => 'timeGrid', 'range' => 'week', 'slotMinutes' => 30],
                    'resourceTimeGridDay' => ['type' => 'timeGrid', 'range' => 'day', 'slotMinutes' => 30, 'resources' => true],
                    'resourceTimeGridWeek' => ['type' => 'timeGrid', 'range' => 'week', 'slotMinutes' => 30, 'resources' => true],
                ],
            ],
            // Zones of the toolbar. Space-separated groups of comma-separated
            // items: prev, next, today, title, views (one button per enabled
            // variant), settings (the UI settings dialog, when enabled), a
            // variant name, or a key of `buttons`.
            'toolbar' => [
                'start' => 'prev,today,next',
                'center' => 'title',
                'end' => 'views',
            ],
            // Open map of custom toolbar buttons: key => [label, and url (+ frame) or event].
            'buttons' => [],
            // Framework theme of the controls: default, bootstrap5, or one
            // declared under fedale_calendar.themes.
            'theme' => 'default',
            // ISO-8601 day numbers: 1 = Monday ... 7 = Sunday.
            'firstDay' => 1,
            'hiddenDays' => [],
            'weekNumbers' => false,
            'timeFormat' => '24h',
            // H:i. A timed event shows on the day it ends only if it ends at or
            // after this time: '09:00' keeps a 22:00-06:00 shift on one day.
            'nextDayThreshold' => '00:00',
            // When the business is open: a list of ['days' => ISO days,
            // 'start' => 'HH:MM', 'end' => 'HH:MM']. The rest of the day is
            // shaded in the time grid and the timeline. [] = no shading.
            'businessHours' => [],
            // null = the request locale.
            'locale' => null,
            // null = the TimezoneResolverInterface service.
            'timezone' => null,
            'month' => [
                'dayMaxEvents' => 3,
            ],
            // The resource area of the timeline.
            'resources' => [
                // Heading above the columns; null = the generic "Resources".
                'label' => null,
                // field: 'title', 'id' or a key of CalendarResource::$data.
                'columns' => [['field' => 'title', 'label' => null]],
                // A field to group the top-level resources by; null = no groups.
                'groupBy' => null,
                'width' => '16rem',
                // Lanes per resource row; the rest goes into "+N more". null = unlimited.
                'rowMaxEvents' => 3,
            ],
        ],
        'behavior' => [
            // Dragging over empty time selects a range and sends a SelectRange intent.
            'selectable' => false,
            // The source whose handler receives selections; null = the first
            // source of the calendar that implements IntentHandlerInterface.
            'selectSource' => null,
            // Step of drags and selections on a time axis, in minutes.
            'snapMinutes' => 15,
        ],
        'integration' => [
            'sources' => [],
        ],
    ];

    /** Option maps whose keys are chosen by the application, not by the bundle. */
    private const OPEN_MAPS = ['display.views.variants', 'display.buttons'];

    private const NAME = '/^[A-Za-z][A-Za-z0-9_]*$/';

    private const TOOLBAR_ITEMS = ['prev', 'next', 'today', 'title', 'views', 'settings'];

    /**
     * @param list<string>                      $enabledViews    variant names
     * @param array<string, ViewVariant>        $variants        every declared variant, by name
     * @param array<string, list<list<string>>> $toolbar         zone => groups => items
     * @param array<string, ToolbarButton>      $buttons
     * @param list<int>                         $hiddenDays      ISO-8601 day numbers
     * @param list<ResourceColumn>              $resourceColumns
     * @param list<string>                      $sources
     * @param array<string, mixed>              $tree            the merged tree, for custom views reading their own keys
     */
    public function __construct(
        public array $enabledViews,
        public string $defaultView,
        public array $variants,
        public array $toolbar,
        public array $buttons,
        public string $theme,
        public int $firstDay,
        public array $hiddenDays,
        public bool $weekNumbers,
        public TimeFormat $timeFormat,
        public string $nextDayThreshold,
        public BusinessHours $businessHours,
        public ?string $locale,
        public ?string $timezone,
        public int $dayMaxEvents,
        public ?string $resourceLabel,
        public array $resourceColumns,
        public ?string $resourceGroupBy,
        public string $resourceAreaWidth,
        public ?int $rowMaxEvents,
        public bool $selectable,
        public ?string $selectSource,
        public int $snapMinutes,
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

        $variants = self::variants($display['views']['variants']);
        $enabled = self::stringList($display['views']['enabled'], 'display.views.enabled');
        if ($enabled === []) {
            throw new \InvalidArgumentException('Calendar option "display.views.enabled" must list at least one view.');
        }
        foreach ($enabled as $name) {
            if (!isset($variants[$name])) {
                throw new \InvalidArgumentException(sprintf('Calendar option "display.views.enabled" names the undeclared view "%s"; declare it under "display.views.variants".', $name));
            }
        }
        $default = $display['views']['default'];
        if (!\is_string($default) || !\in_array($default, $enabled, true)) {
            throw new \InvalidArgumentException(sprintf('Calendar option "display.views.default" must be one of the enabled views (%s).', implode(', ', $enabled)));
        }

        $buttons = self::buttons($display['buttons']);
        $toolbar = self::toolbar($display['toolbar'], $enabled, $buttons);

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

        $resources = $display['resources'];
        $rowMaxEvents = $resources['rowMaxEvents'];
        if ($rowMaxEvents !== null && (!\is_int($rowMaxEvents) || $rowMaxEvents < 1)) {
            throw new \InvalidArgumentException('Calendar option "display.resources.rowMaxEvents" must be a positive integer or null.');
        }
        // Lands in a style attribute: only a plain CSS length is accepted.
        $width = $resources['width'];
        if (!\is_string($width) || preg_match('/^\d+(\.\d+)?(px|rem|em|ch|vw)$/', $width) !== 1) {
            throw new \InvalidArgumentException('Calendar option "display.resources.width" must be a CSS length such as "16rem" or "240px".');
        }
        $groupBy = $resources['groupBy'];
        if ($groupBy !== null && (!\is_string($groupBy) || $groupBy === '')) {
            throw new \InvalidArgumentException('Calendar option "display.resources.groupBy" must be a field name or null.');
        }

        $behavior = $tree['behavior'];
        $selectSource = $behavior['selectSource'];
        if ($selectSource !== null && (!\is_string($selectSource) || $selectSource === '')) {
            throw new \InvalidArgumentException('Calendar option "behavior.selectSource" must be a source key or null.');
        }
        $sources = self::stringList($tree['integration']['sources'], 'integration.sources');
        if ($selectSource !== null && !\in_array($selectSource, $sources, true)) {
            throw new \InvalidArgumentException(sprintf('Calendar option "behavior.selectSource" must be one of the calendar\'s sources (%s).', implode(', ', $sources)));
        }
        $snap = $behavior['snapMinutes'];
        if (!\is_int($snap) || $snap < 1 || 1440 % $snap !== 0) {
            throw new \InvalidArgumentException('Calendar option "behavior.snapMinutes" must divide a day (1440), e.g. 5, 15 or 30.');
        }

        return new self(
            enabledViews: $enabled,
            defaultView: $default,
            variants: $variants,
            toolbar: $toolbar,
            buttons: $buttons,
            theme: \is_string($display['theme']) && $display['theme'] !== '' ? $display['theme'] : throw new \InvalidArgumentException('Calendar option "display.theme" must be a theme name.'),
            firstDay: $firstDay,
            hiddenDays: $hiddenDays,
            weekNumbers: (bool) $display['weekNumbers'],
            timeFormat: self::enum(TimeFormat::class, $display['timeFormat'], 'display.timeFormat'),
            nextDayThreshold: $threshold,
            businessHours: BusinessHours::fromArray($display['businessHours'], 'display.businessHours'),
            locale: $display['locale'] !== null ? (string) $display['locale'] : null,
            timezone: $timezone !== null ? (string) $timezone : null,
            dayMaxEvents: $dayMaxEvents,
            resourceLabel: $resources['label'] !== null ? (string) $resources['label'] : null,
            resourceColumns: self::resourceColumns($resources['columns']),
            resourceGroupBy: $groupBy,
            resourceAreaWidth: $width,
            rowMaxEvents: $rowMaxEvents,
            selectable: (bool) $behavior['selectable'],
            selectSource: $selectSource,
            snapMinutes: $snap,
            sources: $sources,
            tree: $tree,
        );
    }

    public function isHidden(\DateTimeInterface $day): bool
    {
        return \in_array((int) $day->format('N'), $this->hiddenDays, true);
    }

    public function variant(string $name): ViewVariant
    {
        return $this->variants[$name] ?? throw new \InvalidArgumentException(sprintf('Unknown calendar view "%s".', $name));
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
            if (\in_array($keyPath, self::OPEN_MAPS, true)) {
                if (!\is_array($value)) {
                    throw new \InvalidArgumentException(sprintf('Calendar option "%s" must be an array.', $keyPath));
                }
                continue;
            }
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
     * @param array<mixed> $declared
     *
     * @return array<string, ViewVariant>
     */
    private static function variants(array $declared): array
    {
        $variants = [];
        foreach ($declared as $name => $config) {
            $path = 'display.views.variants.'.$name;
            if (!\is_string($name) || preg_match(self::NAME, $name) !== 1) {
                throw new \InvalidArgumentException(sprintf('Calendar option "%s" needs a name made of letters, digits and underscores, starting with a letter.', $path));
            }
            if (!\is_array($config) || !\is_string($config['type'] ?? null) || $config['type'] === '') {
                throw new \InvalidArgumentException(sprintf('Calendar option "%s" must be an array with a "type".', $path));
            }
            $label = $config['label'] ?? null;
            if ($label !== null && !\is_string($label)) {
                throw new \InvalidArgumentException(sprintf('Calendar option "%s.label" must be a string.', $path));
            }
            $params = $config;
            unset($params['type'], $params['label']);
            $variants[$name] = new ViewVariant($name, $config['type'], $params, $label);
        }

        return $variants;
    }

    /**
     * @param array<mixed> $declared
     *
     * @return array<string, ToolbarButton>
     */
    private static function buttons(array $declared): array
    {
        $buttons = [];
        foreach ($declared as $key => $config) {
            $path = 'display.buttons.'.$key;
            if (!\is_string($key) || preg_match(self::NAME, $key) !== 1 || \in_array($key, self::TOOLBAR_ITEMS, true)) {
                throw new \InvalidArgumentException(sprintf('Calendar option "%s" needs a name made of letters, digits and underscores, other than %s.', $path, implode(', ', self::TOOLBAR_ITEMS)));
            }
            if (!\is_array($config) || !\is_string($config['label'] ?? null) || $config['label'] === '') {
                throw new \InvalidArgumentException(sprintf('Calendar option "%s" must be an array with a "label".', $path));
            }
            $unknown = array_diff(array_keys($config), ['label', 'url', 'frame', 'event']);
            if ($unknown !== []) {
                throw new \InvalidArgumentException(sprintf('Unknown calendar option "%s.%s".', $path, reset($unknown)));
            }
            $url = $config['url'] ?? null;
            $event = $config['event'] ?? null;
            if (($url === null) === ($event === null) || ($url !== null && !\is_string($url)) || ($event !== null && !\is_string($event))) {
                throw new \InvalidArgumentException(sprintf('Calendar option "%s" needs either a "url" or an "event", not both.', $path));
            }
            $frame = $config['frame'] ?? null;
            if ($frame !== null && ($url === null || !\is_string($frame))) {
                throw new \InvalidArgumentException(sprintf('Calendar option "%s.frame" is a Turbo Frame id and goes with "url".', $path));
            }
            $buttons[$key] = new ToolbarButton($key, $config['label'], $url, $frame, $event);
        }

        return $buttons;
    }

    /**
     * @param array<string, mixed>         $zones
     * @param list<string>                 $enabled
     * @param array<string, ToolbarButton> $buttons
     *
     * @return array<string, list<list<string>>>
     */
    private static function toolbar(array $zones, array $enabled, array $buttons): array
    {
        $toolbar = [];
        foreach ($zones as $zone => $layout) {
            $path = 'display.toolbar.'.$zone;
            if (!\is_string($layout)) {
                throw new \InvalidArgumentException(sprintf('Calendar option "%s" must be a string such as "prev,today,next title".', $path));
            }
            $groups = [];
            foreach (preg_split('/\s+/', trim($layout), -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $group) {
                $items = explode(',', $group);
                foreach ($items as $item) {
                    if (!\in_array($item, self::TOOLBAR_ITEMS, true) && !\in_array($item, $enabled, true) && !isset($buttons[$item])) {
                        throw new \InvalidArgumentException(sprintf('Calendar option "%s" has the unknown item "%s"; use %s, an enabled view or a key of "display.buttons".', $path, $item, implode(', ', self::TOOLBAR_ITEMS)));
                    }
                }
                $groups[] = $items;
            }
            $toolbar[$zone] = $groups;
        }

        return $toolbar;
    }

    /**
     * @return list<ResourceColumn>
     */
    private static function resourceColumns(mixed $value): array
    {
        $path = 'display.resources.columns';
        if (!\is_array($value) || !array_is_list($value) || $value === []) {
            throw new \InvalidArgumentException(sprintf('Calendar option "%s" must be a non-empty list of columns.', $path));
        }
        $columns = [];
        foreach ($value as $i => $column) {
            if (!\is_array($column) || !\is_string($column['field'] ?? null) || $column['field'] === '') {
                throw new \InvalidArgumentException(sprintf('Calendar option "%s.%d" must be an array with a "field".', $path, $i));
            }
            $unknown = array_diff(array_keys($column), ['field', 'label']);
            if ($unknown !== []) {
                throw new \InvalidArgumentException(sprintf('Unknown calendar option "%s.%d.%s".', $path, $i, reset($unknown)));
            }
            $label = $column['label'] ?? null;
            $columns[] = new ResourceColumn($column['field'], $label !== null ? (string) $label : null);
        }

        return $columns;
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

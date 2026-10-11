# Options

> ← Back to the [documentation index](index.md) · previous: [Events and sources](02_events-and-sources.md) · next: [Views](04_views.md)

Every calendar has a tree of options. The bundle provides the defaults; YAML and the calendar's
`viewConfig()` override what they need, key by key.

## Precedence

From lowest to highest:

1. the bundle defaults (below);
2. `fedale_calendar.defaults` in YAML, for every calendar;
3. `fedale_calendar.calendars.<id>` in YAML, for one calendar;
4. the definition's `viewConfig()`;
5. the [end-user settings](07_ui-settings.md), global then per calendar, when enabled;
6. the view and date in the URL.

Subtrees merge key by key; lists (`views.enabled`, `hiddenDays`, `resources.columns`,
`businessHours`, `sources`) are replaced as a whole. So a calendar can change one parameter of a
built-in variant (`'variants' => ['timelineDay' => ['dayStart' => '08:00']]`) and keep the others.

```yaml
# config/packages/fedale_calendar.yaml
fedale_calendar:
    defaults:
        display:
            timezone: Europe/Rome
            weekNumbers: true
    calendars:
        shifts:
            display:
                timeFormat: 12h
```

An unknown key is an error that names its full path (`Unknown calendar option
"display.month.dayMaxEvent"`), so a typo fails loudly. The two maps whose keys you choose,
`views.variants` and `buttons`, are validated entry by entry instead.

## The full tree

```php
[
    'display' => [
        'views' => [
            'enabled' => ['month', 'listMonth'],
            'default' => 'month',
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
        'toolbar' => ['start' => 'prev,today,next', 'center' => 'title', 'end' => 'views'],
        'buttons' => [],
        'theme' => 'default',
        'firstDay' => 1,
        'hiddenDays' => [],
        'weekNumbers' => false,
        'timeFormat' => '24h',
        'nextDayThreshold' => '00:00',
        'businessHours' => [],
        'locale' => null,
        'timezone' => null,
        'month' => ['dayMaxEvents' => 3],
        'resources' => [
            'label' => null,
            'columns' => [['field' => 'title', 'label' => null]],
            'groupBy' => null,
            'width' => '16rem',
            'rowMaxEvents' => 3,
        ],
    ],
    'behavior' => [
        'selectable' => false,
        'selectSource' => null,
        'snapMinutes' => 15,
    ],
    'integration' => [
        'sources' => [],
    ],
]
```

## display

| Key | Default | Meaning |
|---|---|---|
| `views.enabled` | `['month', 'listMonth']` | Variant names offered in the toolbar, in order. See [Views](04_views.md) |
| `views.default` | `'month'` | The variant a calendar opens on; one of `enabled` |
| `views.variants` | built-ins | Named view types with their parameters; add your own or override built-ins key by key |
| `toolbar.start` / `center` / `end` | see above | The toolbar layout. See [Toolbar](05_toolbar.md) |
| `buttons` | `[]` | Custom toolbar buttons: `key => [label, url (+ frame) or event]`. See [Toolbar](05_toolbar.md#custom-buttons) |
| `theme` | `'default'` | Framework theme of the controls: `default`, `bootstrap5`, or one of yours. See [Styling](09_styling.md#themes) |
| `firstDay` | `1` | First day of the week, ISO-8601: 1 = Monday ... 7 = Sunday |
| `hiddenDays` | `[]` | ISO-8601 days not shown, e.g. `[6, 7]`; at least one day must remain |
| `weekNumbers` | `false` | ISO week numbers in the month view |
| `timeFormat` | `'24h'` | `'24h'` or `'12h'`; the locale decides separators and the AM/PM marker |
| `nextDayThreshold` | `'00:00'` | `HH:MM`. A timed event crossing midnight shows on the day it ends only if it ends at or after this time. See [Views](04_views.md#events-crossing-midnight) |
| `businessHours` | `[]` | Open hours, shaded outside: `[['days' => [1, 2, 3, 4, 5], 'start' => '09:00', 'end' => '18:00'], ...]`. See [Views](04_views.md#business-hours) |
| `locale` | `null` | Locale of labels and dates; `null` = the request locale |
| `timezone` | `null` | Display timezone; `null` = the `TimezoneResolverInterface` service |
| `month.dayMaxEvents` | `3` | Lanes per week in the month view; the rest goes into "+N more" |
| `resources.label` | `null` | Heading of the timeline's resource area; `null` = "Resources" |
| `resources.columns` | title only | Columns of the resource area: `field` is `title`, `id` or a key of the resource's `data`; `label` is the header |
| `resources.groupBy` | `null` | A field to group the top-level resources by (a building, a team) |
| `resources.width` | `'16rem'` | Width of the resource area, a CSS length (`px`, `rem`, `em`, `ch`, `vw`) |
| `resources.rowMaxEvents` | `3` | Lanes per timeline row; the rest goes into "+N more"; `null` = unlimited |

## behavior

| Key | Default | Meaning |
|---|---|---|
| `selectable` | `false` | Dragging over empty time selects a range and sends a `SelectRange` intent. See [Interaction](06_interaction.md) |
| `selectSource` | `null` | The source whose handler receives selections; `null` = the first source with a handler |
| `snapMinutes` | `15` | Step of drags and selections on a time axis, a divisor of 1440 |

## integration

| Key | Default | Meaning |
|---|---|---|
| `sources` | `[]` | Keys of the `#[AsCalendarSource]` services feeding the calendar |

## Calendar definitions

A definition implements `CalendarDefinitionInterface` (or extends `AbstractCalendar`) and is
registered with `#[AsCalendar('id')]`:

| Method | Meaning |
|---|---|
| `label()` | Heading of the calendar, a translation key or plain text; `null` for none |
| `viewConfig()` | Option overrides for this calendar, as a partial tree of the defaults |

The id is what the component takes: `<twig:Fedale:Calendar calendar="id" />`, and what URLs of the
[API](08_api.md) and scopes of the [end-user settings](07_ui-settings.md) use.

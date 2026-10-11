# fedale/calendar-bundle

> **A server-rendered calendar for Symfony UX that knows nothing about your domain.**

Calendar Bundle renders month, list, day and week (time grid), resource timeline and resource
column views of time-based data: work shifts, seat bookings, room schedules, lessons, anything with
a start and an end. It is a **presentation component**: your application keeps the domain, the
rules and the data, and hands the calendar a read-only view of them.

- **Server-rendered.** A Live Component lays out the grid in PHP and Twig. The browser never builds
  the calendar: navigation is a round trip, and the period lives in the URL.
- **Domain-agnostic.** Applications feed it through a small contract (`CalendarEvent`,
  `EventSourceInterface`). There is no universal `Appointment` entity to adopt.
- **CSS-framework agnostic.** Its own `cal-*` classes, styled through `--cal-*` custom properties.

> **Status:** early development (0.1). The views are in place; interaction (selecting, moving,
> resizing events) is next. The roadmap is in [`docs/plan.md`](docs/plan.md) (in Italian).

---

## Requirements

- PHP 8.2+ with `ext-intl`
- Symfony 6.4, 7.x or 8.x
- Symfony UX Live Component and Twig Component 2.20+, StimulusBundle

## Installation

```bash
composer require fedale/calendar-bundle
```

Register the bundle (Symfony Flex does it for you), next to the UX bundles it relies on:

```php
// config/bundles.php
return [
    // ...
    Symfony\UX\StimulusBundle\StimulusBundle::class => ['all' => true],
    Symfony\UX\TwigComponent\TwigComponentBundle::class => ['all' => true],
    Symfony\UX\LiveComponent\LiveComponentBundle::class => ['all' => true],
    Fedale\CalendarBundle\FedaleCalendarBundle::class => ['all' => true],
];
```

Live Components need their route. The Live Component recipe adds it; without Flex, import it:

```yaml
# config/routes/ux_live_component.yaml
live_component:
    resource: '@LiveComponentBundle/config/routes.php'
    prefix: '/_components'
```

Import the stylesheet. With AssetMapper, the bundle exposes its `assets/` directory as
`@fedale/calendar-bundle`. A bare import from JavaScript also needs an importmap entry:

```php
// importmap.php
return [
    // ...
    '@fedale/calendar-bundle/styles/calendar.css' => [
        'path' => '@fedale/calendar-bundle/styles/calendar.css',
        'type' => 'css',
    ],
];
```

```js
// assets/app.js
import '@fedale/calendar-bundle/styles/calendar.css';
```

Alternatively, link it from your layout:
`<link rel="stylesheet" href="{{ asset('@fedale/calendar-bundle/styles/calendar.css') }}">`.

The bundle ships two small Stimulus controllers: `cal-now` (scrolls the timeline and the time grid
to "now" and keeps the "now" line moving) and `cal-button` (custom toolbar buttons that dispatch a
DOM event).
Flex registers them in `assets/controllers.json`; without Flex, add them yourself:

```json
{
    "controllers": {
        "@fedale/calendar-bundle": {
            "now": { "enabled": true, "fetch": "lazy" },
            "button": { "enabled": true, "fetch": "lazy" }
        }
    }
}
```

The calendar works without them: the timeline and the time grid then open at their start, and the
"now" line stays where the server put it.

## Your first calendar

A calendar needs two things: a **source**, which turns your data into events, and a **definition**,
which gives the calendar an id and its options.

### 1. A source

```php
namespace App\Calendar;

use App\Repository\ShiftRepository;
use Fedale\CalendarBundle\Attribute\AsCalendarSource;
use Fedale\CalendarBundle\Contract\EventSourceInterface;
use Fedale\CalendarBundle\Model\CalendarEvent;
use Fedale\CalendarBundle\Model\CalendarQuery;
use Fedale\CalendarBundle\Model\EventStyle;

#[AsCalendarSource('shifts')]
final class ShiftSource implements EventSourceInterface
{
    public function __construct(private readonly ShiftRepository $shifts)
    {
    }

    public function getEvents(CalendarQuery $query): iterable
    {
        foreach ($this->shifts->findBetween($query->range->start, $query->range->end) as $shift) {
            yield new CalendarEvent(
                id: (string) $shift->getId(),
                title: $shift->getEmployee()->getName(),
                start: $shift->getStartsAt(),
                end: $shift->getEndsAt(),
                type: 'shift',
                style: new EventStyle(color: $shift->getTeam()->getColor()),
                url: '/shifts/'.$shift->getId(),
            );
        }
    }
}
```

The source is the adapter between your domain and the calendar. It decides what the current user
may see, and it expands recurring events within `$query->range`.

### 2. A definition

```php
namespace App\Calendar;

use Fedale\CalendarBundle\Attribute\AsCalendar;
use Fedale\CalendarBundle\Definition\AbstractCalendar;

#[AsCalendar('shifts')]
final class ShiftCalendar extends AbstractCalendar
{
    public function label(): string
    {
        return 'Work shifts';
    }

    public function viewConfig(): array
    {
        return [
            'display' => [
                'views' => ['enabled' => ['month', 'listMonth'], 'default' => 'month'],
                'hiddenDays' => [7],
            ],
            'integration' => ['sources' => ['shifts']],
        ];
    }
}
```

### 3. Render it

```twig
<twig:Fedale:Calendar calendar="shifts" />
```

The toolbar moves between periods and views. The view and the focused date are kept in the URL
(`?view=list&date=2026-10-05`), so every period has a link and the back button works.

---

## The event contract

`CalendarEvent` is what a source yields. It is deliberately detached from your entities:

| Property | Meaning |
|---|---|
| `id` | Unique within its source |
| `title` | Text shown on the event |
| `start`, `end` | `end` is exclusive. Any timezone: the calendar converts to the display timezone |
| `allDay` | The event is a date range, not an instant range (see below) |
| `resourceIds` | Resources the event is assigned to (timeline and resource views, on the roadmap) |
| `type`, `status` | Free-form strings for your own templates and styles |
| `layer` | `EventLayer::Foreground` (a regular event) or `EventLayer::Background` (a band behind them: availability, closures) |
| `visibility` | `EventVisibility::Full`, `BusyOnly` (time slot only, under a generic "Busy" label) or `Hidden` (dropped) |
| `interactions` | `EventInteractions`: which interactions the UI enables (move, resize, click, delete), on the roadmap |
| `version` | Concurrency token, sent back with write intents (roadmap) |
| `style` | `EventStyle`: `color` (any CSS color), `cssClass`, `icon` |
| `hints` | Display-only facts computed by the source, such as "3 seats left" |
| `url` | Makes the event a link |
| `data` | Opaque payload, never interpreted by the calendar |

### Timezones and all-day events

Timed events are **instants**: pass them in whatever timezone you store them in, and the calendar
shows them in the display timezone.

All-day events are **dates**: only the `Y-m-d` of `start` and `end` (exclusive) is read, so a
holiday stays on its day wherever the calendar is displayed. A one-day event on 25 December runs
from `2026-12-25` to `2026-12-26`.

The display timezone is the `display.timezone` option or, when it's `null`, the
`TimezoneResolverInterface` service. The default implementation returns PHP's default timezone.
Replace it to follow each user's own timezone:

```yaml
# config/services.yaml
services:
    Fedale\CalendarBundle\Contract\TimezoneResolverInterface: '@App\Calendar\UserTimezoneResolver'
```

### What the current user may see

The calendar never decides what a user may see. The source does:

- leave out events the user must not know about, or mark them `EventVisibility::Hidden`;
- mark events the user may know about but not read as `EventVisibility::BusyOnly`. Title, URL and
  payload are withheld from the rendered HTML, not only hidden with CSS.

---

## Options

Options form a tree. The bundle defaults, with every accepted key:

```php
[
    'display' => [
        'views' => [
            'enabled' => ['month', 'listMonth'],   // variant names, in toolbar order
            'default' => 'month',
            'variants' => [                        // see "Views"
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
        'toolbar' => [                  // see "Toolbar"
            'start' => 'prev,today,next',
            'center' => 'title',
            'end' => 'views',
        ],
        'buttons' => [],                // custom toolbar buttons, see "Toolbar"
        'firstDay' => 1,                // ISO-8601: 1 = Monday ... 7 = Sunday
        'hiddenDays' => [],             // ISO-8601 day numbers, e.g. [6, 7]
        'weekNumbers' => false,
        'timeFormat' => '24h',          // or '12h'
        'nextDayThreshold' => '00:00',  // HH:MM, see below
        'businessHours' => [],          // see "Business hours"
        'locale' => null,               // null = the request locale
        'timezone' => null,             // null = TimezoneResolverInterface
        'month' => [
            'dayMaxEvents' => 3,        // lanes per week; the rest goes into "+N more"
        ],
        'resources' => [                // the timeline's resource area, see "Timeline"
            'label' => null,            // heading; null = "Resources"
            'columns' => [['field' => 'title', 'label' => null]],
            'groupBy' => null,          // a field to group top-level resources by
            'width' => '16rem',
            'rowMaxEvents' => 3,        // lanes per row; the rest goes into "+N more"; null = unlimited
        ],
    ],
    'integration' => [
        'sources' => [],                // keys of #[AsCalendarSource] services
    ],
]
```

`nextDayThreshold` decides when a timed event that crosses midnight also shows on the day it ends:
only if it ends at or after that time. With the default, a 22:00-06:00 night shift is a bar over two
days; with `'09:00'` it shows on the day it starts only. Events ending exactly at midnight never
show on the next day, whatever the threshold.

An unknown key is an error that names its full path (`Unknown calendar option
"display.month.dayMaxEvent"`), so a typo fails loudly instead of being ignored. The two maps whose
keys you choose, `views.variants` and `buttons`, are validated entry by entry instead.

### Precedence

From lowest to highest:

1. the bundle defaults above;
2. `fedale_calendar.defaults` in YAML, for every calendar;
3. `fedale_calendar.calendars.<id>` in YAML, for one calendar;
4. the definition's `viewConfig()`;
5. the view and date in the URL.

Subtrees are merged key by key, while lists (`views.enabled`, `hiddenDays`, `resources.columns`,
`sources`) are replaced as a whole. So a calendar can change one parameter of a built-in variant
(`'variants' => ['timelineDay' => ['dayStart' => '08:00']]`) and keep the others.

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

---

## Views

A **view type** is a layout; a **variant** is a type with its parameters, under a name. The toolbar
and the URL use variant names, so one type serves several durations, as `timelineDay`,
`timelineWeek` and `timelineMonth` do. Declare your own variants next to the built-in ones:

```php
'views' => [
    'enabled' => ['timelineDay', 'timelineWorkWeek'],
    'default' => 'timelineDay',
    'variants' => [
        'timelineDay' => ['dayStart' => '07:00', 'dayEnd' => '21:00'],
        'timelineWorkWeek' => [
            'type' => 'timeline',
            'range' => 'week',
            'slotMinutes' => 120,
            'label' => 'Work week',     // toolbar label; default: the "view.<name>" translation
        ],
    ],
],
```

| Type | Parameters | What it shows |
|---|---|---|
| `month` | none | Whole weeks around the month. Multi-day events are continuous bars. A day with more events than `dayMaxEvents` shows "+N more", which opens the full list in a popover (native `popover`, no JavaScript) |
| `list` | `range`: `day`, `week` or `month` | An agenda of the days that have events. Multi-day events are listed on every day they cover |
| `timeline` | `range`: `day`, `week` or `month`; `slotMinutes`: a divisor of 1440; `dayStart` / `dayEnd`: the window of each day (HH:MM, `24:00` allowed as end); `scrollTime`: where the view opens when "now" is off screen (default `08:00`) | Resources down the side, time across (see "Timeline") |
| `timeGrid` | `range`: `day` or `week`; `slotMinutes`, `dayStart`, `dayEnd`, `scrollTime` as above; `resources`: one column per resource and day; `allDaySlot` (default `true`); `allDayMaxEvents` (default 3) | Days as columns, hours down the side, an all-day row on top (see "Time grid") |

Every view honors `firstDay`, `hiddenDays` and the display timezone, and is laid out in PHP: the
server sends positions as custom properties (`--cal-start`, `--cal-span`, `--cal-left`,
`--cal-width`, ...), the browser only paints them.

### Timeline

The timeline shows one row per resource. Resources come from the calendar's sources that also
implement `ResourceSourceInterface`:

```php
#[AsCalendarSource('rooms')]
final class RoomSource implements EventSourceInterface, ResourceSourceInterface
{
    public function getResources(CalendarQuery $query): iterable
    {
        yield new CalendarResource('aud-a', 'Auditorium A', data: ['building' => '460 Bryant', 'seats' => 40]);
        yield new CalendarResource('aud-d', 'Auditorium D', data: ['building' => '460 Bryant', 'seats' => 40]);
        // Nested under Auditorium D:
        yield new CalendarResource('aud-d1', 'Room D1', parentId: 'aud-d', data: ['building' => '460 Bryant', 'seats' => 10]);
    }

    public function getEvents(CalendarQuery $query): iterable
    {
        // ... CalendarEvent objects with resourceIds: ['aud-a'], ...
    }
}
```

- An event shows on the row of each of its `resourceIds`; an event booked in two rooms is a bar on
  both rows. Foreground events without resources are not shown on the timeline.
- A background event with resources is a band on those rows; without resources it spans every row
  (a public holiday, a building closure).
- `display.resources.columns` picks what the resource area shows: `title`, `id`, or any key of the
  resource's `data`. `groupBy` groups the top-level resources by one of those fields.
- Groups and parent resources collapse and expand. The state is kept by the component, so it
  survives navigation, and collapsed rows are not rendered at all.
- Rows stack overlapping events in lanes; beyond `rowMaxEvents`, the rest goes into "+N more" chips
  placed over the time they cover.
- The resource area and the header stay in place while the track scrolls, with CSS alone
  (`position: sticky`). A red line marks "now".

```php
'display' => [
    'views' => [
        'enabled' => ['timelineDay', 'timelineWeek', 'timelineMonth'],
        'default' => 'timelineDay',
    ],
    'resources' => [
        'label' => 'Rooms',
        'columns' => [
            ['field' => 'building', 'label' => 'Building'],
            ['field' => 'title', 'label' => 'Room'],
            ['field' => 'seats', 'label' => 'Seats'],
        ],
        'groupBy' => 'building',
        'width' => '26rem',
    ],
],
```

### Time grid

The `timeGrid` type shows days side by side with the hours running down, as in most agenda apps.

- Timed events sit at their hours. Events that overlap share the column side by side, and an event
  grows to the right over the space its neighbors leave free.
- A timed event crossing midnight is split over the days it covers, squared off where it continues.
- All-day events go to the all-day row on top, as bars over the days they cover; beyond
  `allDayMaxEvents` lanes, the rest goes into "+N more".
- With `resources: true` (`resourceTimeGridDay`, `resourceTimeGridWeek`), every resource gets its
  own columns, resources above and their days below, in the same order as the timeline (parents
  followed by their children). An event shows in the columns of its `resourceIds`. `groupBy` does
  not apply here.
- The header and the hour gutter stay in place while the grid scrolls, and a red line marks "now".

### Business hours

`display.businessHours` lists when the business is open; the time grid and the timeline (with slots
shorter than a day) shade the rest. It is a visual aid only: it never hides or rejects events.

```php
'businessHours' => [
    ['days' => [1, 2, 3, 4, 5], 'start' => '09:00', 'end' => '18:00'],
    ['days' => [6], 'start' => '09:00', 'end' => '13:00'],
],
```

### Toolbar

`display.toolbar` lays out three zones, `start`, `center` and `end`. Each zone is a string of groups
separated by spaces; items in a group are separated by commas and drawn as one joined control.

| Item | What it is |
|---|---|
| `prev`, `next`, `today` | Navigation |
| `title` | The period heading |
| `views` | One button per enabled variant (nothing when only one is enabled) |
| a variant name | That variant's button alone |
| a key of `display.buttons` | A custom button |

A custom button has a `label` and either a `url` (optionally with a Turbo `frame` to load it into,
such as your own booking form) or an `event`, a DOM event it dispatches for your JavaScript to
handle. The event bubbles, with `{calendar, key, view, date}` as its detail.

```php
'display' => [
    'toolbar' => ['start' => 'add prev,today,next', 'center' => 'title', 'end' => 'views'],
    'buttons' => [
        'add' => ['label' => 'Add event', 'event' => 'rooms:add'],
        'export' => ['label' => 'Export', 'url' => '/rooms/export'],
    ],
],
```

```js
document.addEventListener('rooms:add', (event) => {
    openBookingForm(event.detail.date);
});
```

### Adding a view

A view type implements `CalendarViewInterface` and is registered with `#[AsCalendarView('type')]`.
Reusing a built-in type replaces the built-in view.

```php
use Fedale\CalendarBundle\Attribute\AsCalendarView;
use Fedale\CalendarBundle\Contract\CalendarViewInterface;

#[AsCalendarView('fortnight')]
final class FortnightView implements CalendarViewInterface
{
    // validateVariant(): reject parameters this type does not understand
    // usesResources():   whether build() needs the resources
    // range():    the period to fetch around the focused date
    // navigate(): the focused date one step back or forward
    // title():    the localized heading
    // build():    a view model, from the context (with the variant), the sorted items and the resources
    // template(): the Twig template rendering that view model as `model`
}
```

Declare a variant of it and enable that:
`'views' => ['enabled' => ['month', 'fortnight'], 'variants' => ['fortnight' => ['type' => 'fortnight']]]`.
Give it a `label`, or add the `view.fortnight` key to the `CalendarBundle` translation domain.

---

## Styling

Every visual value is a custom property on `.cal`. Override the tokens rather than the rules:

```css
.cal {
    --cal-accent: #0f766e;
    --cal-radius: 8px;
    --cal-week-min-height: 8rem;
    --cal-slot-width: 5rem;       /* timeline slot width */
    --cal-tl-max-height: 60vh;    /* timeline and time grid height before they scroll */
    --cal-slot-height: 2rem;      /* time grid slot height */
    --cal-col-min-width: 9rem;    /* time grid column width before it scrolls sideways */
    --cal-nonbusiness: rgb(0 0 0 / .06);
    --cal-primary: #0f766e;       /* custom buttons */
    --cal-now: #dc2626;           /* the "now" line */
}
```

The stylesheet follows `prefers-color-scheme`; set `data-theme="light"` on the calendar to opt out
of the dark palette.

## Translations

System strings live in the `CalendarBundle` translation domain, in English and Italian. Dates are
formatted with ICU skeletons, so month and day names, their order and the time format follow the
locale.

## Development

```bash
composer test       # PHPUnit: unit tests and Live Component integration tests
composer phpstan    # level 6
composer cs         # php-cs-fixer, dry run
```

## License

MIT

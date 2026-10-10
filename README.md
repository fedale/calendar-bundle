# fedale/calendar-bundle

> **A server-rendered calendar for Symfony UX that knows nothing about your domain.**

Calendar Bundle renders month, list (and, on the roadmap, week, day, year, timeline and
resource) views of time-based data: work shifts, seat bookings, lessons, anything with a start and
an end. It is a **presentation component**: your application keeps the domain, the rules and the
data, and hands the calendar a read-only view of them.

- **Server-rendered.** A Live Component lays out the grid in PHP and Twig. The browser never builds
  the calendar: navigation is a round trip, and the period lives in the URL.
- **Domain-agnostic.** Applications feed it through a small contract (`CalendarEvent`,
  `EventSourceInterface`). There is no universal `Appointment` entity to adopt.
- **CSS-framework agnostic.** Its own `cal-*` classes, styled through `--cal-*` custom properties.

> **Status:** early development (0.1). The month and list views are in place; the rest of the
> roadmap is in [`docs/plan.md`](docs/plan.md) (in Italian).

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
                'views' => ['enabled' => ['month', 'list'], 'default' => 'month'],
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
            'enabled' => ['month', 'list'],
            'default' => 'month',
        ],
        'firstDay' => 1,            // ISO-8601: 1 = Monday ... 7 = Sunday
        'hiddenDays' => [],         // ISO-8601 day numbers, e.g. [6, 7]
        'weekNumbers' => false,
        'timeFormat' => '24h',      // or '12h'
        'nextDayThreshold' => '00:00', // HH:MM, see below
        'locale' => null,           // null = the request locale
        'timezone' => null,         // null = TimezoneResolverInterface
        'month' => [
            'dayMaxEvents' => 3,    // lanes per week; the rest goes into "+N more"
        ],
        'list' => [
            'range' => 'month',     // or 'week'
        ],
    ],
    'integration' => [
        'sources' => [],            // keys of #[AsCalendarSource] services
    ],
]
```

`nextDayThreshold` decides when a timed event that crosses midnight also shows on the day it ends:
only if it ends at or after that time. With the default, a 22:00-06:00 night shift is a bar over two
days; with `'09:00'` it shows on the day it starts only. Events ending exactly at midnight never
show on the next day, whatever the threshold.

An unknown key is an error that names its full path (`Unknown calendar option
"display.month.dayMaxEvent"`), so a typo fails loudly instead of being ignored.

### Precedence

From lowest to highest:

1. the bundle defaults above;
2. `fedale_calendar.defaults` in YAML, for every calendar;
3. `fedale_calendar.calendars.<id>` in YAML, for one calendar;
4. the definition's `viewConfig()`;
5. the view and date in the URL.

Subtrees are merged key by key, while lists (`views.enabled`, `hiddenDays`, `sources`) are replaced
as a whole.

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

| Name | What it shows |
|---|---|
| `month` | Whole weeks around the month. Multi-day events are continuous bars. A day with more events than `dayMaxEvents` shows "+N more", which opens the full list in a popover (native `popover`, no JavaScript) |
| `list` | An agenda of the days that have events, for a month or a week. Multi-day events are listed on every day they cover |

Both honor `firstDay`, `hiddenDays` and the display timezone, and both are laid out in PHP: the
server sends grid positions as custom properties (`--cal-start`, `--cal-span`, `--cal-row`).

### Adding a view

A view implements `CalendarViewInterface` and is registered with `#[AsCalendarView('name')]`.
Reusing a built-in name replaces the built-in view.

```php
use Fedale\CalendarBundle\Attribute\AsCalendarView;
use Fedale\CalendarBundle\Contract\CalendarViewInterface;

#[AsCalendarView('fortnight')]
final class FortnightView implements CalendarViewInterface
{
    // range():    the period to fetch around the focused date
    // navigate(): the focused date one step back or forward
    // title():    the localized heading
    // build():    a view model, from the context and the sorted items
    // template(): the Twig template rendering that view model as `model`
}
```

Enable it in a calendar with `'views' => ['enabled' => ['month', 'fortnight']]`, and add the
`view.fortnight` key to the `CalendarBundle` translation domain for the toolbar button.

---

## Styling

Every visual value is a custom property on `.cal`. Override the tokens rather than the rules:

```css
.cal {
    --cal-accent: #0f766e;
    --cal-radius: 8px;
    --cal-week-min-height: 8rem;
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

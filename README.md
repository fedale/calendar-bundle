# fedale/calendar-bundle

> **A server-rendered calendar for Symfony UX that knows nothing about your domain.**

Calendar Bundle renders year, month, list, day and week (time grid), resource timeline and resource
column views of time-based data: work shifts, seat bookings, room schedules, lessons, anything with
a start and an end. It is a **presentation component**: your application keeps the domain, the
rules and the data, and hands the calendar a read-only view of them.

- **Server-rendered.** A Live Component lays out the grid in PHP and Twig. The browser never builds
  the calendar: navigation is a round trip, and the period lives in the URL.
- **Domain-agnostic.** Applications feed it through a small contract (`CalendarEvent`,
  `EventSourceInterface`). There is no universal `Appointment` entity to adopt.
- **CSS-framework agnostic.** Its own `cal-*` classes, styled through `--cal-*` custom properties.

- **Your domain decides.** Drags, resizes, selections, clicks and deletes reach your code as
  intents; your handlers accept or reject them, and the calendar re-renders from your data.
- **Optional extras.** End-user settings on fedale/setting-bundle, a JSON API on API Platform,
  framework themes (Bootstrap 5 built in), keyboard access, five languages.

> **Status:** early development (0.1). The roadmap is in [`docs/plan.md`](docs/plan.md) (in Italian).

## Documentation

The full documentation is in [`docs/`](docs/index.md):

| Guide | |
|---|---|
| [Getting started](docs/01_getting-started.md) | Requirements, installation, your first calendar |
| [Events and sources](docs/02_events-and-sources.md) | The event and resource contract, timezones, visibility |
| [Options](docs/03_options.md) | Every option with its default, and precedence |
| [Views](docs/04_views.md) | Year, month, list, time grid, timeline; business hours |
| [Toolbar](docs/05_toolbar.md) | Layout and custom buttons |
| [Interaction](docs/06_interaction.md) | Intents, handlers, versions, keyboard |
| [End-user settings](docs/07_ui-settings.md) | The settings dialog on fedale/setting-bundle |
| [API](docs/08_api.md) | The API Platform bridge |
| [Styling](docs/09_styling.md) | Tokens, dark mode, themes, translations |
| [JavaScript](docs/10_javascript.md) | Stimulus controllers and DOM events |
| [Configuration reference](docs/11_configuration.md) | Every YAML key |
| [Extending](docs/12_extending.md) | Custom views, settings, timezone resolver, templates |

A demo application with rooms, people and bookings is at
[fedale/calendar-demo](https://github.com/fedale/calendar-demo).

## Using an AI coding assistant?

Point it at `vendor/fedale/calendar-bundle/docs/agents.md`: a compact map of what the bundle already
does and where your code goes, written to stop an assistant building a calendar grid, drag and drop
or permission checks by hand. Referencing that path from your project's own `AGENTS.md` /
`CLAUDE.md` is enough.

## Requirements

- PHP 8.2+ with `ext-intl`
- Symfony 6.4, 7.x or 8.x
- Symfony UX Live Component and Twig Component 2.20+, StimulusBundle

## Quick start

```bash
composer require fedale/calendar-bundle
```

Then import the stylesheet and enable the Stimulus controllers: see
[Installation](docs/01_getting-started.md#installation).

A calendar needs a **source**, which turns your data into events, and a **definition**, which gives
the calendar an id and its options.

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

## Development

```bash
composer test       # PHPUnit: unit tests and Live Component integration tests
composer phpstan    # level 6
composer cs         # php-cs-fixer, dry run
```

## License

MIT

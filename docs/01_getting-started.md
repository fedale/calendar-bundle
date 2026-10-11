# Getting started

> ← Back to the [documentation index](index.md) · next: [Events and sources](02_events-and-sources.md)

## Requirements

- PHP 8.2+ with `ext-intl`
- Symfony 6.4, 7.x or 8.x
- Symfony UX Live Component and Twig Component 2.20+, StimulusBundle
- Optional: fedale/setting-bundle 1.1+ and symfony/form for [end-user settings](07_ui-settings.md),
  API Platform 4 for the [API](08_api.md)

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

### Stylesheet

With AssetMapper, the bundle exposes its `assets/` directory as `@fedale/calendar-bundle`. A bare
import from JavaScript also needs an importmap entry:

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

Or link it from your layout:
`<link rel="stylesheet" href="{{ asset('@fedale/calendar-bundle/styles/calendar.css') }}">`.

### Stimulus controllers

Flex registers the bundle's controllers in `assets/controllers.json`; without Flex, add them
yourself:

```json
{
    "controllers": {
        "@fedale/calendar-bundle": {
            "interact": { "enabled": true, "fetch": "eager" },
            "ui-settings": { "enabled": true, "fetch": "lazy" },
            "now": { "enabled": true, "fetch": "lazy" },
            "button": { "enabled": true, "fetch": "lazy" }
        }
    }
}
```

Without them the calendar still renders and navigates; events just cannot be dragged. See
[JavaScript](10_javascript.md) for what each one does.

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

The source is the adapter between your domain and the calendar: it queries your data for the range
on screen, decides what the current user may see, and expands recurring events. See
[Events and sources](02_events-and-sources.md).

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
                'views' => ['enabled' => ['month', 'timeGridWeek', 'listMonth'], 'default' => 'month'],
                'hiddenDays' => [7],
            ],
            'integration' => ['sources' => ['shifts']],
        ];
    }
}
```

`viewConfig()` returns a partial tree of the [options](03_options.md): only what differs from the
defaults.

### 3. Render it

```twig
<twig:Fedale:Calendar calendar="shifts" />
```

The toolbar moves between periods and views. The view and the focused date are kept in the URL
(`?view=timeGridWeek&date=2026-10-05`), so every period has a link and the back button works.

## Where next

- Show resources (rooms, people) on a timeline: [Views](04_views.md#timeline).
- Let users drag events: [Interaction](06_interaction.md).
- Change how it looks: [Styling, themes and translations](09_styling.md).

A complete application using every feature, with a Doctrine database, is
[calendar-demo](https://github.com/fedale/calendar-demo).

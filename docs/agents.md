# Calendar for AI agents

**Read this file before writing any code that uses `fedale/calendar-bundle`.**

It is a map, not a tutorial: each row points at the guide that holds the detail. What it prevents is
the expensive failure mode: code that *looks* right, renders without an error, and quietly puts
business rules, permissions or a second calendar model in the wrong place.

Working on the bundle itself rather than using it? Read [`AGENTS.md`](../AGENTS.md) at the repository
root instead.

---

## The model in 60 seconds

Your application owns the data and the rules. The calendar owns the screen. It never reads your
entities, never writes them, and never decides who may do what.

A calendar is two classes and a tag:

```php
// 1. A source: your data as CalendarEvent objects (and, for timelines, CalendarResource objects).
#[AsCalendarSource('shifts')]
final class ShiftSource implements EventSourceInterface
{
    public function getEvents(CalendarQuery $query): iterable
    {
        foreach ($this->shifts->findBetween($query->range->start, $query->range->end) as $shift) {
            yield new CalendarEvent(
                id: (string) $shift->getId(),
                title: $shift->getEmployee()->getName(),
                start: $shift->getStartsAt(),
                end: $shift->getEndsAt(),          // exclusive
            );
        }
    }
}

// 2. A definition: an id and its options.
#[AsCalendar('shifts')]
final class ShiftCalendar extends AbstractCalendar
{
    public function viewConfig(): array
    {
        return [
            'display' => ['views' => ['enabled' => ['month', 'timeGridWeek', 'listMonth']]],
            'integration' => ['sources' => ['shifts']],
        ];
    }
}
```

```twig
{# 3. The component, anywhere in your pages #}
<twig:Fedale:Calendar calendar="shifts" />
```

Navigation, views, the toolbar, layout, the "now" line, keyboard access: all done. To let users
change things, the source also implements `IntentHandlerInterface`: the calendar sends it
**intents** (move, resize, select, click, delete) and re-renders from the source whatever the
answer.

---

## Do not hand-roll: the capability map

| When you need… | Use this | Guide |
|---|---|---|
| Rooms, people, machines as rows | `ResourceSourceInterface` + a `timeline*` variant | [04_views.md](04_views.md#timeline) |
| Resources as columns with hours down | `resourceTimeGridDay` / `resourceTimeGridWeek` | [04_views.md](04_views.md#time-grid) |
| Extra columns or grouping in the resource area | `display.resources.columns`, `groupBy` + the resource's `data` | [03_options.md](03_options.md#display) |
| A view with other durations or hours (work week, 7:00-21:00) | a variant in `display.views.variants`: `range`, `slotMinutes`, `dayStart`, `dayEnd` | [04_views.md](04_views.md#parameters) |
| Open hours shaded | `display.businessHours` | [04_views.md](04_views.md#business-hours) |
| Closures, holidays, availability bands | an event with `layer: EventLayer::Background` | [02_events-and-sources.md](02_events-and-sources.md#calendarevent) |
| Showing that a slot is taken without saying by whom | `visibility: EventVisibility::BusyOnly` | [02_events-and-sources.md](02_events-and-sources.md#what-each-user-may-see) |
| Drag to move or resize | `EventInteractions(movable: true, resizable: true)` on the event + a handler | [06_interaction.md](06_interaction.md) |
| Drag over empty time to create | `behavior.selectable: true` + `SelectRange` in the handler | [06_interaction.md](06_interaction.md#intents) |
| Your own form after a click or a selection | `IntentResult::openUrl($url, 'turbo-frame-id')` | [06_interaction.md](06_interaction.md#handling-them) |
| Refusing a change with a message | `IntentResult::rejected(Rejection::Validation, '...')` | [06_interaction.md](06_interaction.md#handling-them) |
| Not overwriting someone else's change | `version` on the event, checked in the handler | [06_interaction.md](06_interaction.md#versions-and-concurrency) |
| A confirmation before a change | listen to `cal:intent`, `preventDefault()` | [10_javascript.md](10_javascript.md#events) |
| An "Add" or "Export" button in the toolbar | `display.buttons` + its key in `display.toolbar` | [05_toolbar.md](05_toolbar.md#custom-buttons) |
| Colors per event type or status | `EventStyle(color: ...)` or `EventStyle(cssClass: ...)` | [09_styling.md](09_styling.md#styling-events) |
| Colors, sizes, dark mode | `--cal-*` custom properties | [09_styling.md](09_styling.md#tokens) |
| Bootstrap classes on the controls | `display.theme: bootstrap5`, or a theme in YAML | [09_styling.md](09_styling.md#themes) |
| Each user's timezone | a `TimezoneResolverInterface` service | [12_extending.md](12_extending.md#timezone-resolver) |
| End users choosing first day, weekends, 12/24h, default view | the settings dialog on fedale/setting-bundle | [07_ui-settings.md](07_ui-settings.md) |
| Mobile apps or services reading and changing the calendar | the API Platform bridge (`fedale_calendar.api`) | [08_api.md](08_api.md) |
| A layout the bundle lacks | a `CalendarViewInterface` with `#[AsCalendarView]` | [12_extending.md](12_extending.md#views) |

If you find yourself writing a calendar grid in Twig, positioning events with JavaScript, an AJAX
endpoint for drag and drop, or a permission check in a template, stop and check the table above.

---

## Ten traps

### 1. `end` is exclusive, and all-day events are dates

A one-hour meeting at 9:00 ends at 10:00. A one-day all-day event on 25 December ends on **26**
December. Only the date of an all-day event is read, never its time.

```php
// WRONG — throws: an all-day event must end on a later date (and 23:59:59 would be ignored)
new CalendarEvent(id: 'xmas', title: 'Closed', start: $dec25, end: $dec25, allDay: true);

// RIGHT
new CalendarEvent(id: 'xmas', title: 'Closed', start: $dec25, end: $dec25->modify('+1 day'), allDay: true);
```

### 2. The rules belong to the handler, not to the calendar

Booking windows, capacity, overlaps, who may move what: the source and its handler decide. The
calendar only reflects what the source says (`interactions`, `hints`). Do not add checks in Twig,
JavaScript or a `cal:intent` listener and call them enforced: anything in the browser can be skipped.

### 3. Hide with the source, not with CSS

An event the user must not know about is **left out** by the source. One the user may know about but
not read is `EventVisibility::BusyOnly`: its title, url and hints never reach the HTML or the API.
A CSS class that hides the title leaves it in the page.

### 4. Nothing is interactive by default

Dragging needs all of: the event's `EventInteractions` enabling it, a source implementing
`IntentHandlerInterface`, and the `cal-interact` controller enabled. Selections also need
`behavior.selectable: true`. Missing one is not an error: the event simply does not move.

### 5. Check the version in the handler

Give every event a `version` (Doctrine's `#[ORM\Version]`) and compare it in the handler before
writing, answering `Rejection::Conflict` on a mismatch; also catch `OptimisticLockException` on
flush. Without it, the second of two users silently overwrites the first.

### 6. Unknown option keys throw: copy paths, do not guess

Options are validated: a misspelled or misplaced key is an exception naming its path. Copy the shape
from [03_options.md](03_options.md#the-full-tree). Options live under `display`, `behavior` and
`integration`; a calendar's sources go in `integration.sources`.

### 7. Lists replace, maps merge

`views.enabled`, `hiddenDays`, `businessHours`, `resources.columns` and `sources` are replaced as a
whole: list every value you want, not just the new one. Maps merge key by key: to change one
parameter of a built-in variant, give only that key.

```php
'views' => [
    'enabled' => ['timelineDay', 'timelineWeek'],                 // the full list
    'variants' => ['timelineDay' => ['dayStart' => '07:00']],     // one key; the rest kept
],
```

### 8. The toolbar and the URL use variant names, not view types

`timelineWeek` is a variant of the type `timeline`. `views.enabled`, `views.default` and the toolbar
take variant names; a new duration is a new variant, not a new view class.

### 9. The calendar does not save forms: re-render it after yours

When your own form (opened by `openUrl()` or a custom button) saves a booking, the calendar does not
know. Redirect back to the page, or call `(await getComponent(element)).render()`. See
[10_javascript.md](10_javascript.md#re-rendering-after-your-own-changes).

### 10. Optional pieces need turning on, and protecting

- The **API** is off until `fedale_calendar.api.enabled: true`. List the exposed calendars and set
  `read_attribute` / `write_attribute` with a voter; put the routes behind your API firewall.
- The **settings dialog** needs setting-bundle with a scoped store, its route imported and `settings`
  in the toolbar. Its global scope changes every calendar for every user: protect the route.

---

## The guides

| File | What is in it |
|---|---|
| [01_getting-started.md](01_getting-started.md) | Requirements, installation, the first calendar |
| [02_events-and-sources.md](02_events-and-sources.md) | `CalendarEvent`, `CalendarResource`, timezones, visibility, finders |
| [03_options.md](03_options.md) | The full option tree with defaults, precedence |
| [04_views.md](04_views.md) | Types, variants and parameters; every view; business hours |
| [05_toolbar.md](05_toolbar.md) | Toolbar layout and custom buttons |
| [06_interaction.md](06_interaction.md) | Intents, handlers, answers, versions, keyboard |
| [07_ui-settings.md](07_ui-settings.md) | The end-user settings dialog |
| [08_api.md](08_api.md) | The API Platform bridge |
| [09_styling.md](09_styling.md) | Tokens, dark mode, event classes, themes, translations |
| [10_javascript.md](10_javascript.md) | Stimulus controllers and DOM events |
| [11_configuration.md](11_configuration.md) | Every YAML key |
| [12_extending.md](12_extending.md) | Timezone resolver, custom views, templates, dispatching intents |

---

## Before you call a calendar done

- [ ] Every source returns only the events overlapping `$query->range`, with exclusive ends.
- [ ] All-day events end on the day after their last day.
- [ ] What a user must not see is left out or `BusyOnly`, decided in the source.
- [ ] Interactions are enabled per event, only where the current user may act.
- [ ] The handler re-checks everything that matters, compares `version`, and answers every intent.
- [ ] Options were copied from the guides; lists list every value.
- [ ] No hardcoded framework classes, colors in templates, or user-facing strings outside `|trans`.
- [ ] The API, if enabled, lists its calendars and has read/write attributes with a voter.
- [ ] The settings route, if imported, is protected.

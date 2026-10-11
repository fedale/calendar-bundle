# Events and sources

> ← Back to the [documentation index](index.md) · previous: [Getting started](01_getting-started.md) · next: [Options](03_options.md)

The calendar never reads your entities. **Sources** read them and hand the calendar plain objects:
`CalendarEvent` for what happens, `CalendarResource` for what it happens to (a room, a person).
A source is the only place that knows both sides.

## Sources

A source implements `EventSourceInterface` and is registered with `#[AsCalendarSource('key')]`. A
calendar lists the keys of its sources in `integration.sources`; one calendar can overlay several.

```php
#[AsCalendarSource('rooms')]
final class RoomSource implements EventSourceInterface, ResourceSourceInterface
{
    public function getEvents(CalendarQuery $query): iterable
    {
        foreach ($this->bookings->findInRange($query->range->start, $query->range->end) as $booking) {
            yield new CalendarEvent(/* ... */);
        }
    }

    public function getResources(CalendarQuery $query): iterable
    {
        foreach ($this->rooms->findAll() as $room) {
            yield new CalendarResource((string) $room->getId(), $room->getName());
        }
    }
}
```

`CalendarQuery` carries:

| Property | Meaning |
|---|---|
| `range` | `DateRange` with `start` and `end` (exclusive): the period on screen. Return the events overlapping it; anything outside is dropped |
| `timezone` | The display timezone |
| `resourceIds` | Resources to narrow to, when the caller asks (the API's `resources` parameter); empty = all |
| `filters` | Opaque filters for the source; reserved for future use |

A source may implement more interfaces:

| Interface | What it adds |
|---|---|
| `ResourceSourceInterface` | Resources: the rows of the timeline, the columns of resource views |
| `IntentHandlerInterface` | Applies the user's changes; without it the source is read-only. See [Interaction](06_interaction.md) |
| `EventFinderInterface` | Finds one event by id. Used by the [API](08_api.md), so its writes get the same checks as the UI's |

Recurring events are your source's business: expand them within `$query->range`. The calendar
never sees a recurrence rule.

## CalendarEvent

| Property | Default | Meaning |
|---|---|---|
| `id` | required | Unique within its source |
| `title` | required | Text shown on the event |
| `start`, `end` | required | `end` is exclusive. Instants in any timezone, or dates for all-day events (see below) |
| `allDay` | `false` | The event is a date range, not an instant range |
| `resourceIds` | `[]` | Resources the event is assigned to; it shows on each of their rows or columns |
| `type`, `status` | `null` | Free-form strings, for your own styles and for API clients |
| `layer` | `Foreground` | `EventLayer::Background` draws a band behind the events (availability, closures) |
| `visibility` | `Full` | `EventVisibility::BusyOnly` or `Hidden`, see below |
| `interactions` | none | `EventInteractions(movable, resizable, clickable, deletable)`: what the UI offers on this event |
| `version` | `null` | Concurrency token, sent back with every change. See [Interaction](06_interaction.md#versions-and-concurrency) |
| `style` | none | `EventStyle(color, cssClass, icon)`: `color` is any CSS color |
| `hints` | `[]` | Display-only facts computed by the source ("3 seats left", the organizer's name) |
| `url` | `null` | Makes the event a link |
| `data` | `[]` | Opaque payload for your own code; the calendar never shows it, the API never exposes it |

An event that does not end after it starts (for all-day events, on a later date) throws when built.

## CalendarResource

| Property | Default | Meaning |
|---|---|---|
| `id` | required | Unique across the calendar's sources (two sources giving the same id share the row) |
| `title` | required | Name shown in the resource area |
| `parentId` | `null` | Nests the resource under another one (a room split into two halves); parents collapse |
| `order` | `0` | Sort order among siblings; then the title |
| `style` | none | `EventStyle`; `color` is exposed to API clients |
| `data` | `[]` | Fields the timeline can show in its columns and group by (`display.resources`) |

## Timezones and all-day events

Timed events are **instants**: pass them in whatever timezone you store them in (UTC is a good
choice), and the calendar shows them in the display timezone.

All-day events are **dates**: only the `Y-m-d` of `start` and `end` (exclusive) is read, so a
holiday stays on its day wherever the calendar is displayed. A one-day event on 25 December runs
from `2026-12-25` to `2026-12-26`.

The display timezone is the `display.timezone` option or, when it is `null`, the
`TimezoneResolverInterface` service, which returns PHP's default timezone. Replace it to follow
each user's own timezone (see [Extending](12_extending.md#timezone-resolver)).

Inside a day the calendar lays out wall-clock time, so hour columns keep their width on the 23- and
25-hour days of a DST change.

## What each user may see

The calendar never decides what a user may see. The source does, event by event:

- **Leave out** what the user must not know about, or mark it `EventVisibility::Hidden` (the
  calendar drops it defensively).
- Mark what the user may know about but not read as **`EventVisibility::BusyOnly`**: the time slot
  shows under a generic "Busy" label. Title, url, hints and payload are withheld from the HTML and
  the API, not just hidden with CSS.
- Enable **interactions** only where the user may act: an event without `movable` cannot be
  dragged, whatever the browser sends.

## Finding events by id

```php
interface EventFinderInterface
{
    public function findEvent(string $id): ?CalendarEvent;
}
```

The UI does not need it: an intent from the page concerns an event the calendar has just fetched.
API clients have no page; with a finder, their writes get the same checks as the UI's before your
handler (event visible, interaction enabled, version current), and `GET /calendars/{id}/events/
{source}/{eventId}` works. Return the event as the current user may see it, or `null`.

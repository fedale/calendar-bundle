# Interaction

> ← Back to the [documentation index](index.md) · previous: [Toolbar](05_toolbar.md) · next: [End-user settings](07_ui-settings.md)

Users drag events to move them (also to another resource row or column), drag their end to resize
them, drag over empty time to select a range, click clickable events and delete deletable ones —
with the mouse or the keyboard. The calendar does none of it itself: it turns each gesture into an
**intent** and hands it to the source, where your domain decides.

## Enabling it

1. The source enables, event by event, what the user may do: `CalendarEvent::$interactions`
   (`movable`, `resizable`, `clickable`, `deletable`). Nothing is enabled by default.
2. The source implements `IntentHandlerInterface`. A source without it is read-only.
3. For selections, set `behavior.selectable: true` (and `behavior.selectSource` if several sources
   could take them).
4. The `cal-interact` Stimulus controller is enabled (see [JavaScript](10_javascript.md)).

## Intents

| Intent | Sent when | Carries |
|---|---|---|
| `SelectRange` | dragging over empty time | `start`, `end` (exclusive), `allDay`, `resourceId` |
| `MoveEvent` | dropping a dragged event | `source`, `eventId`, `version`, new `start` and `end` (duration kept), `fromResourceId`, `toResourceId`; `changesResource()` |
| `ResizeEvent` | dropping a dragged event end | `source`, `eventId`, `version`, `start`, new `end` |
| `ClickEvent` | clicking (or Enter on) a clickable event without a url | `source`, `eventId`, `version` |
| `DeleteEvent` | the × button, or Delete on a focused event | `source`, `eventId`, `version` |

Every intent also carries the `calendar` id. Times are `DateTimeImmutable` in the display timezone,
snapped to `behavior.snapMinutes` on a time axis and to whole days elsewhere.

## Handling them

```php
#[AsCalendarSource('rooms')]
final class RoomSource implements EventSourceInterface, IntentHandlerInterface
{
    public function handle(CalendarIntent $intent): IntentResult
    {
        if ($intent instanceof MoveEvent) {
            $booking = $this->bookings->find((int) $intent->eventId);
            if ($booking === null) {
                return IntentResult::rejected(Rejection::Conflict, 'This booking no longer exists.');
            }
            if ((string) $booking->getVersion() !== $intent->version) {
                return IntentResult::rejected(Rejection::Conflict, 'This booking was changed meanwhile.');
            }
            if ($this->bookings->overlaps($intent->toResourceId, $intent->start, $intent->end, except: $booking)) {
                return IntentResult::rejected(Rejection::Validation, 'The room is already booked at that time.');
            }
            $booking->move($intent->toResourceId, $intent->start, $intent->end);
            $this->entityManager->flush();

            return IntentResult::accepted('Booking moved.');
        }
        if ($intent instanceof SelectRange) {
            // Let your own form create the booking, in a Turbo Frame (e.g. a modal).
            return IntentResult::openUrl('/bookings/new?'.http_build_query([
                'room' => $intent->resourceId,
                'start' => $intent->start->format(\DATE_ATOM),
                'end' => $intent->end->format(\DATE_ATOM),
            ]), 'booking-modal');
        }

        return IntentResult::rejected(Rejection::Forbidden, 'Not supported.');
    }
}
```

The handler answers with an `IntentResult`:

| Answer | Meaning |
|---|---|
| `IntentResult::accepted(?message, parameters, domain)` | Done |
| `IntentResult::rejected(Rejection, message, parameters, domain)` | Not done: `Conflict` (data changed meanwhile), `Validation` (the domain refuses the new state), `Forbidden` (not allowed) |
| `IntentResult::openUrl(url, ?frame)` | Accepted; the browser opens the URL, into the Turbo Frame `frame` when given |

Messages are translation keys or plain text, in `domain` (null = your default domain). They show
above the view, with `role="status"`, or `role="alert"` for rejections.

**Whatever the answer, the calendar re-renders from the sources.** An accepted move shows the event
at its new place, a rejected one back where it was; there is no client-side undo to get wrong.

## What the calendar checks first

Before calling your handler, the calendar rejects what it can tell on its own, against the events it
fetches again for the period on screen (not against what the browser claims):

- the event is not on screen any more → `Conflict`;
- the event does not enable that interaction, or is a background event → `Forbidden`;
- its version changed since the page was rendered → `Conflict`;
- the end is not after the start, or a time is malformed → `Validation`;
- the source belongs to another calendar, or has no handler → `Forbidden`.

These checks stop stale or forged requests early. They do not replace your domain's checks, which
are the authority: the handler must still verify everything that matters to it.

## Versions and concurrency

Give each event a `version` that changes whenever it changes (Doctrine's `#[ORM\Version]` column
does it for you). The calendar sends it back with every intent, so a handler can refuse a change
built on a state that no longer exists:

1. Two users open the same week. A booking is at 9:00, version 3.
2. One moves it to 14:00: it becomes version 4.
3. The other, still looking at 9:00, drags it to 11:00. The intent says "version 3".
4. The calendar sees version 4 on its fresh read and refuses with a conflict; the screen
   re-renders with the booking at 14:00.

Without a version, the second move would silently overwrite the first. With Doctrine, also catch
`OptimisticLockException` on flush and answer a conflict: it covers two requests racing between
your read and your write.

## Keyboard

On a focused event:

| Keys | Action |
|---|---|
| Arrows | Move it by one step: a slot (`snapMinutes`) on a time axis, a day on day columns (a week with up and down); in a timeline, up and down move it to the previous or next resource row |
| Shift + arrows | Move its end |
| Delete, Backspace | Delete it (after a confirmation) |
| Enter, Space | Activate a clickable event |

Focus stays on the event after the calendar re-renders. Interactive events describe these keys to
assistive technology with `aria-description`.

## In the browser

The `cal-interact` controller dispatches a cancelable `cal:intent` DOM event before each call, with
the action and its arguments as detail; `preventDefault()` stops it. An `openUrl()` answer arrives
as a `cal:open` event. See [JavaScript](10_javascript.md).

Events folded into a "+N more" popover cannot be dragged from there.

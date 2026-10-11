# API

> ← Back to the [documentation index](index.md) · previous: [End-user settings](07_ui-settings.md) · next: [Styling](09_styling.md)

With API Platform 4 installed and the bridge turned on, the bundle exposes its calendars to API
clients (mobile apps, other services). The API keeps the calendar's abstraction: clients read what
the calendar would show and write **intents**, the same ones the UI sends, which your handlers decide
on. It is not a CRUD over your entities; expose those yourself if you need to.

## Configuration

The bridge is **off by default**: installing API Platform exposes nothing until you decide what to
expose and to whom.

```yaml
# config/packages/fedale_calendar.yaml
fedale_calendar:
    api:
        enabled: true
        calendars: [rooms, team]
        read_attribute: CALENDAR_API_READ
        write_attribute: CALENDAR_API_WRITE
```

| Key | Default | Meaning |
|---|---|---|
| `enabled` | `false` | Registers the API resources. Off, the bundle adds nothing to API Platform |
| `calendars` | `[]` | Ids of the calendars the API exposes; empty = every calendar. Any other id answers 404, as if it did not exist |
| `read_attribute` | `null` | Security attribute checked with `isGranted(attribute, calendarId)` before any read; `null` = no check |
| `write_attribute` | `null` | The same, before any write (move, resize, delete, selection) |

If symfony/security is missing, a configured attribute denies everything rather than open up.

## Who may call it

The bundle does not authenticate anyone. The routes take API Platform's prefix (usually `/api`)
and should sit behind your API firewall. Then three layers decide:

1. the attributes, per user and per calendar, through a voter;
2. your sources, per event: what each user may see (see
   [Events and sources](02_events-and-sources.md#what-each-user-may-see));
3. your handlers, per change: what each user may do.

```php
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, string> */
final class CalendarApiVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, ['CALENDAR_API_READ', 'CALENDAR_API_WRITE'], true) && \is_string($subject);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if ($user === null) {
            return false;
        }

        return match ($attribute) {
            'CALENDAR_API_READ' => true,
            'CALENDAR_API_WRITE' => $subject === 'rooms' && \in_array('ROLE_BOOKER', $user->getRoles(), true),
        };
    }
}
```

## Endpoints

| Method and path | What it does |
|---|---|
| `GET /calendars` | The exposed calendars |
| `GET /calendars/{calendar}` | One calendar: label, views, timezone, whether ranges can be selected |
| `GET /calendars/{calendar}/events?start=…&end=…[&resources=a,b]` | Events of a range |
| `GET /calendars/{calendar}/events/{source}/{id}` | One event, when its source implements `EventFinderInterface` |
| `GET /calendars/{calendar}/resources` | The resources, with the fields the resource columns show |
| `GET /calendars/{calendar}/resources/{id}` | One resource |
| `POST /calendars/{calendar}/events/{source}/{id}/move` | `MoveEvent` |
| `POST /calendars/{calendar}/events/{source}/{id}/resize` | `ResizeEvent` |
| `DELETE /calendars/{calendar}/events/{source}/{id}` | `DeleteEvent`; answers 204 |
| `POST /calendars/{calendar}/selections` | `SelectRange` |

Responses come in API Platform's configured formats (JSON-LD, plain JSON, ...).

### Reading events

`start` and `end` are ISO 8601 with an offset (`2026-10-12T00:00:00+02:00`), at most 400 days apart;
`end` is exclusive. `resources` narrows to some resource ids. Events go through the same sources and
visibility rules as the UI: hidden events are left out, busy ones come without title, url or hints,
and the `data` payload is never exposed.

```http
GET /api/calendars/rooms/events?start=2026-10-12T00:00:00%2B02:00&end=2026-10-19T00:00:00%2B02:00
```

### Writing

| Endpoint | Body |
|---|---|
| move | `{"start", "end", "resource"?, "fromResource"?, "version"?}`: `resource` moves the event to another resource, `fromResource` says which of its resources (when it has several) |
| resize | `{"end", "start"?, "version"?}` |
| selections | `{"start", "end", "allDay"?, "resource"?}` |
| delete | no body |

```http
POST /api/calendars/rooms/events/rooms/42/move
Content-Type: application/json
If-Match: "7"

{"start": "2026-10-13T14:00:00+02:00", "end": "2026-10-13T15:30:00+02:00", "resource": "12"}
```

Pass the event's `version` in `If-Match` or in the body. A write may only reach the calendar's own
sources. When the source implements `EventFinderInterface`, the bridge runs the same checks as the
UI before your handler (event visible, interaction enabled, version current); without it, only your
handler decides.

### Answers

| Handler answer | Status | Body |
|---|---|---|
| `accepted()` | 200 | `{"accepted": true, "message": …, "url": null}` |
| `openUrl()` | 200 | `{"accepted": true, "message": null, "url": …}` |
| `rejected(Conflict)` | 409 | error, with the message as `detail` |
| `rejected(Validation)` | 422 | idem |
| `rejected(Forbidden)` | 403 | idem |
| (delete accepted) | 204 | none |

Malformed times answer 400; an unknown calendar, source or event 404.

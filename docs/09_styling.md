# Styling

> ← Back to the [documentation index](index.md) · previous: [API](08_api.md) · next: [JavaScript](10_javascript.md)

The calendar has its own stylesheet, `@fedale/calendar-bundle/styles/calendar.css`, and needs no CSS
framework. Two levers change its look: **tokens** for the calendar itself, **themes** for its
controls.

## Tokens

Every visual value is a custom property on `.cal`. Override the tokens rather than the rules, for
every calendar or for one (`#my-calendar .cal`, a class of your own):

```css
.cal {
    --cal-accent: #0f766e;
    --cal-radius: 8px;
    --cal-slot-height: 2rem;
}
```

| Token | Default | What it sets |
|---|---|---|
| `--cal-font` | `inherit` | Font family |
| `--cal-bg` | `#fff` | Background |
| `--cal-fg` | `#1f2328` | Text |
| `--cal-muted` | `#6b7280` | Secondary text (times, headers) |
| `--cal-border` | `#e3e5e8` | Grid lines |
| `--cal-outside-bg` | `#f7f8f9` | Days outside the month |
| `--cal-weekend-bg` | `#fbfbfc` | Weekends |
| `--cal-today-bg` | `#fff8e1` | Today |
| `--cal-accent` | `#2563eb` | Default event color, focus, year density |
| `--cal-primary` | `#7c3aed` | Custom toolbar buttons |
| `--cal-now` | `#ef4444` | The "now" line |
| `--cal-event-color` | `var(--cal-accent)` | Event background; set per event by `EventStyle::$color` |
| `--cal-event-fg` | `#fff` | Event text |
| `--cal-radius` | `4px` | Corner radius |
| `--cal-gap` | `2px` | Space between events |
| `--cal-lane-height` | `1.45rem` | Height of an event bar (month, all-day row, timeline) |
| `--cal-week-min-height` | `6.5rem` | Minimum height of a month week |
| `--cal-slot-width` | `4rem` | Width of a timeline slot |
| `--cal-tl-header-height` | `2.1rem` | Height of a timeline header row |
| `--cal-tl-max-height` | `75vh` | Height of the timeline and the time grid before they scroll |
| `--cal-slot-height` | `1.5rem` | Height of a time grid slot |
| `--cal-col-min-width` | `7rem` | Time grid column width before it scrolls sideways |
| `--cal-gutter-width` | `4rem` | Time grid hour gutter |
| `--cal-nonbusiness` | `rgb(0 0 0 / .045)` | Shade outside business hours |
| `--cal-density-1` … `4` | mixes of the accent | Year view shades, from quiet to busiest |

## Dark mode

The stylesheet follows `prefers-color-scheme` and switches to a dark palette (background, text,
borders, accent). Set `data-theme="light"` on the calendar's element, or on the tokens of your own
dark theme, to opt out.

## Styling events

Each event carries classes you can target:

| Class | When |
|---|---|
| `cal-event--timed`, `cal-event--all-day` | Timed or all-day |
| `cal-event--busy` | Shown as busy only |
| `cal-event--movable`, `cal-event--clickable` | Interactions enabled |
| `cal-event--continues-before`, `cal-event--continues-after` | Cut at the edge of the view or the day |
| `cal-band` | A background event |

plus the `cssClass` of its `EventStyle`. A source can give each type or status of event its own
class, and your stylesheet decides:

```php
style: new EventStyle(cssClass: 'booking--'.$booking->getStatus()),
```

```css
.booking--tentative {
    --cal-event-color: transparent;
    --cal-event-fg: var(--cal-accent);
    outline: 1px dashed var(--cal-accent);
}
```

The bundle's own event rules are wrapped in `:where()`, so a single class of yours wins over them.

## Themes

The toolbar's controls take their classes from a **theme**. `default` uses the bundle's own
`cal-btn` styles; `bootstrap5` maps them to Bootstrap. Pick one with `display.theme`, for every
calendar or for one, or declare your own:

```yaml
fedale_calendar:
    defaults:
        display:
            theme: brand
    themes:
        brand:
            extends: bootstrap5
            classes:
                button.primary: 'btn btn-success'
```

| Class key | `default` | `bootstrap5` |
|---|---|---|
| `button` | `cal-btn` | `btn btn-outline-secondary` |
| `button.primary` | `cal-btn cal-btn--primary` | `btn btn-primary` |
| `button.icon` | `cal-btn cal-btn--icon` | `btn btn-outline-secondary` |
| `button.active` | (none) | `active` |
| `button.group` | `cal-toolbar__group--joined` | `btn-group` |

`button.active` is added to the button of the view on screen; `button.group` to joined groups. A
theme with `extends` starts from that theme's classes; without it, from `default`. Themes only dress
the controls: the calendar's layout always comes from its own stylesheet.

## Translations

System strings live in the `CalendarBundle` translation domain, in English, Italian, French, German
and Spanish. Override any key in your own `translations/CalendarBundle.<locale>.yaml`. Dates are
formatted with ICU skeletons, so month and day names, their order and the time format follow the
locale.

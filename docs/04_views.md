# Views

> ← Back to the [documentation index](index.md) · previous: [Options](03_options.md) · next: [Toolbar](05_toolbar.md)

## Types and variants

A **view type** is a layout; a **variant** is a type with its parameters, under a name. The toolbar
and the URL use variant names, so one type serves several durations, as `timelineDay`,
`timelineWeek` and `timelineMonth` do.

| Built-in variant | Type | Parameters |
|---|---|---|
| `year` | `year` | |
| `month` | `month` | |
| `listDay`, `listWeek`, `listMonth` | `list` | `range` |
| `timeGridDay`, `timeGridWeek` | `timeGrid` | `range`, `slotMinutes: 30` |
| `resourceTimeGridDay`, `resourceTimeGridWeek` | `timeGrid` | `range`, `slotMinutes: 30`, `resources: true` |
| `timelineDay` | `timeline` | `range: day`, `slotMinutes: 60` |
| `timelineWeek` | `timeline` | `range: week`, `slotMinutes: 360` |
| `timelineMonth` | `timeline` | `range: month`, `slotMinutes: 1440` |

Declare your own next to the built-in ones, or change one key of a built-in:

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
            'label' => 'Work week',   // toolbar label; default: the "view.<name>" translation
        ],
    ],
],
```

Each type validates its own parameters when the calendar is resolved: an unknown or invalid one is
an error naming the variant.

Every view honors `firstDay`, `hiddenDays` and the display timezone, and is laid out in PHP: the
server sends positions as CSS custom properties (`--cal-start`, `--cal-span`, `--cal-left`,
`--cal-width`, `--cal-top`, `--cal-height`), the browser only paints them.

## Parameters

| Parameter | Types | Default | Meaning |
|---|---|---|---|
| `range` | list, timeGrid, timeline | month (list), week (timeGrid), day (timeline) | `day`, `week` or `month` (not `month` for timeGrid) |
| `slotMinutes` | timeGrid, timeline | 30 (timeGrid), 60 (timeline) | Length of a slot, a divisor of 1440 |
| `dayStart`, `dayEnd` | timeGrid, timeline | `00:00`, `24:00` | The window of each day shown; a whole number of slots |
| `scrollTime` | timeGrid, timeline | `08:00` | Where the view opens when "now" is off screen |
| `resources` | timeGrid | `false` | One column per resource and day |
| `allDaySlot` | timeGrid | `true` | Show the all-day row |
| `allDayMaxEvents` | timeGrid | `3` | Lanes of the all-day row; the rest goes into "+N more" |
| `label` | any | `null` | Toolbar label (translation key or text) |

## Year

Twelve mini months. Each day is shaded by how busy it is, from 0 (nothing) to 4 (as busy as the
busiest day of the year). A day opens in the calendar's most detailed enabled view of a day — a
time grid, list or timeline with `range: day` — else in the month.

## Month

Whole weeks around the month, 4 to 6 rows. Multi-day events are continuous bars; background events
are bands behind the days. A day with more events than `month.dayMaxEvents` lanes shows "+N more",
which opens the full list in a popover (the native `popover` attribute, no JavaScript).
`weekNumbers` adds ISO week numbers.

## List

An agenda of the days that have events, for a day, a week or a month. A multi-day event is listed
on every day it covers ("from 22:00", "until 06:00", "all day").

## Time grid

Days side by side, hours running down, an all-day row on top.

- Timed events sit at their hours. Events that overlap share the column side by side, and an event
  grows to the right over the space its neighbors leave free.
- A timed event crossing midnight is split over the days it covers, squared off where it continues.
- All-day events go to the all-day row, as bars over the days they cover.
- With `resources: true`, every resource gets its own columns: resources above, their days below,
  in the timeline's order (parents followed by their children). An event shows in the columns of
  its `resourceIds`; `groupBy` does not apply here.
- The header and the hour gutter stay in place while the grid scrolls; a red line marks "now".

## Timeline

One row per resource, time across. Resources come from the calendar's sources that implement
`ResourceSourceInterface`.

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

- An event shows on the row of each of its `resourceIds`: an event booked in two rooms is a bar on
  both rows. Foreground events without resources are not shown on the timeline.
- A background event with resources is a band on those rows; without resources it spans every row
  (a public holiday, a closure).
- `resources.columns` picks what the resource area shows; `groupBy` groups the top-level resources
  by one of those fields.
- Groups and parent resources collapse and expand. The component keeps the state, so it survives
  navigation, and collapsed rows are not rendered at all.
- Rows stack overlapping events in lanes; beyond `rowMaxEvents`, the rest goes into "+N more" chips
  over the time they cover.
- The resource area and the header stay in place while the track scrolls (CSS `position: sticky`);
  a red line marks "now". The header shows days over hours, or ISO weeks over days for a month.

## Business hours

`display.businessHours` lists when the business is open; the time grid and the timeline (with
slots shorter than a day) shade the rest. It is a visual aid only: it never hides or rejects events.

```php
'businessHours' => [
    ['days' => [1, 2, 3, 4, 5], 'start' => '09:00', 'end' => '18:00'],
    ['days' => [6], 'start' => '09:00', 'end' => '13:00'],
],
```

Overlapping rules merge; a day no rule mentions is closed all day.

## Events crossing midnight

In the month, list and timeline-by-day views, a timed event crossing midnight shows on every day it
covers. `display.nextDayThreshold` decides when it also shows on the day it ends: only if it ends at
or after that time. With the default `00:00`, a 22:00-06:00 night shift is a bar over two days; with
`'09:00'` it shows on the day it starts only. Events ending exactly at midnight never show on the
next day. The time grid always splits such events over their days.

## Performance

Views render on the server, so their cost is HTML. Measured on the
[demo](https://github.com/fedale/calendar-demo)'s database (PHP 8.4 with opcache): a day or a week
renders in 20-60 ms; a month timeline of 54 rooms (~3,800 bookings) in 180 ms for 2.2 MB of HTML.
For more resources than that, paginate them in your source or split them over several calendars.

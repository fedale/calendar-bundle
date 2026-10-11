# Toolbar

> ← Back to the [documentation index](index.md) · previous: [Views](04_views.md) · next: [Interaction](06_interaction.md)

`display.toolbar` lays out three zones, `start`, `center` and `end`. Each zone is a string of
**groups** separated by spaces; items in a group are separated by commas and drawn as one joined
control.

```php
'toolbar' => [
    'start' => 'add prev,today,next',   // two groups: "add" alone, then the three navigation buttons joined
    'center' => 'title',
    'end' => 'views settings',
],
```

## Items

| Item | What it is |
|---|---|
| `prev`, `next`, `today` | Navigation; `today` is disabled when today is on screen |
| `title` | The period heading |
| `views` | One button per enabled variant (nothing when only one is enabled) |
| a variant name | That variant's button alone |
| `settings` | A gear opening the [end-user settings](07_ui-settings.md) dialog, shown only when they are enabled |
| a key of `display.buttons` | A custom button |

An unknown item is an error naming the zone.

## Custom buttons

A custom button has a `label` (translation key or text) and either:

- a `url`, opened as a link, optionally into a Turbo Frame with `frame` (your own booking form in
  a modal, an export);
- or an `event`, the name of a DOM event it dispatches for your JavaScript to handle. The event
  bubbles, with `{calendar, key, view, date}` as its detail.

```php
'buttons' => [
    'add' => ['label' => 'Add event', 'event' => 'rooms:add'],
    'export' => ['label' => 'Export', 'url' => '/rooms/export'],
    'book' => ['label' => 'Book', 'url' => '/bookings/new', 'frame' => 'booking-modal'],
],
```

```js
document.addEventListener('rooms:add', (event) => {
    openBookingForm(event.detail.calendar, event.detail.date);
});
```

Button keys are names made of letters, digits and underscores, other than the built-in items.
Their look comes from the theme's `button.primary` class (see [Styling](09_styling.md#themes)).

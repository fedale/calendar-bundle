# JavaScript

> ← Back to the [documentation index](index.md) · previous: [Styling](09_styling.md) · next: [Configuration reference](11_configuration.md)

The calendar is a Live Component: it renders on the server and navigates through Live actions.
JavaScript only handles what a server cannot: pointer and keyboard gestures, scrolling, a clock.
There is nothing to initialize; the bundle's Stimulus controllers attach themselves.

## Controllers

Declared in the bundle's `package.json`, enabled in your `assets/controllers.json` (see
[Getting started](01_getting-started.md#installation)):

| Controller | Loaded | What it does |
|---|---|---|
| `cal-interact` | eager | Drag to move, resize and select; keyboard editing; deletes; sends intents as Live actions |
| `cal-now` | lazy | Scrolls the time grid and the timeline to now (or `scrollTime`) and moves the "now" line every minute |
| `cal-button` | lazy | Custom toolbar buttons with an `event`: dispatches that DOM event |
| `cal-ui-settings` | lazy | The [end-user settings](07_ui-settings.md) dialog |

Disable `cal-interact` to make every calendar read-only in the browser (the server still checks
everything on its own).

## Events

All events bubble, so you can listen on `document` or on a container.

| Event | Cancelable | Detail | When |
|---|---|---|---|
| `cal:intent` | yes | `{action, ...arguments}`: `action` is the Live action (`moveEvent`, `resizeEvent`, `select`, `clickEvent`, `deleteEvent`) | Before an intent is sent. `preventDefault()` stops it and puts the event back |
| `cal:open` | no | `{url, frame}` | A handler answered `openUrl()` |
| a custom button's `event` | no | `{calendar, key, view, date}` | The button was clicked |

Ask for confirmation before moving an event to another resource:

```js
document.addEventListener('cal:intent', (event) => {
    const { action, toResource, fromResource } = event.detail;
    if (action === 'moveEvent' && toResource && toResource !== fromResource && !confirm('Move to another room?')) {
        event.preventDefault();
    }
});
```

`cal:open` is handled by `cal-interact` itself: the URL is loaded into the Turbo Frame with the id
`frame` when it exists on the page, else visited with Turbo, else with a plain navigation. Listen
to it to do something else as well, such as opening the modal around the frame:

```js
document.addEventListener('cal:open', (event) => {
    if (event.detail.frame === 'booking-modal') {
        document.getElementById('booking-dialog').showModal();
    }
});
```

## Re-rendering after your own changes

After your own form saves a booking, re-render the calendar so it shows it:

```js
import { getComponent } from '@symfony/ux-live-component';

const calendar = document.querySelector('.cal[data-controller~="live"]');
(await getComponent(calendar)).render();
```

With Turbo, a form answering with a redirect to the page re-renders it as well.

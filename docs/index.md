# fedale/calendar-bundle — Documentation

A server-rendered calendar for Symfony UX that knows nothing about your domain. Your application
keeps the data and the rules; it hands the calendar a read-only view of them through **sources**,
and the calendar hands back the user's gestures as **intents** for your domain to decide on.

New here? Start with **[Getting started](01_getting-started.md)**, then reach for the guide you
need. Every page is self-contained.

Working with an AI coding assistant? Point it at **[agents.md](agents.md)** first: a compact
capability map and the traps that keep it from putting rules or permissions in the wrong place.

## Guides

### Core

- **[Getting started](01_getting-started.md)** — requirements, installation, your first calendar
  (a source, a definition, the component).
- **[Events and sources](02_events-and-sources.md)** — the `CalendarEvent` and `CalendarResource`
  contract, timezones and all-day events, what each user may see, finding events by id.
- **[Options](03_options.md)** — every option of a calendar, with its default, and how YAML,
  `viewConfig()`, end-user settings and the URL combine.
- **[Views](04_views.md)** — view types and variants: year, month, list, time grid, timeline;
  resources, business hours, `nextDayThreshold`.
- **[Toolbar](05_toolbar.md)** — zones, groups, built-in items and custom buttons.

### Writing back

- **[Interaction](06_interaction.md)** — dragging, resizing, selecting, clicking and deleting,
  from the mouse and the keyboard: intents, handlers, answers, versions and concurrency.
- **[End-user settings](07_ui-settings.md)** — the settings dialog on fedale/setting-bundle.
- **[API](08_api.md)** — the optional API Platform bridge: endpoints, configuration, access
  control.

### Presentation and extension

- **[Styling, themes and translations](09_styling.md)** — design tokens, dark mode, framework
  themes, translations and date formats.
- **[JavaScript](10_javascript.md)** — the Stimulus controllers and the DOM events you can listen
  to.
- **[Extending](12_extending.md)** — custom views, custom settings, the timezone resolver,
  overriding templates.

### Reference

- **[Configuration reference](11_configuration.md)** — every key of `fedale_calendar` in YAML.

## Design notes

The architecture and its decisions, phase by phase, are in [plan.md](plan.md) (in Italian). The
short version:

- **The calendar is a presentation component, not a domain.** No entity to adopt, no business
  rule inside: sources say what to show, handlers decide what may change.
- **The server lays out the calendar.** Views compute positions in PHP; the browser paints them
  and turns gestures into intents. Navigation and changes are round trips that re-render from the
  sources, so the screen always shows the real state.
- **Optional pieces stay optional.** End-user settings need fedale/setting-bundle, the API needs
  API Platform; without them the calendar works as configured in code.

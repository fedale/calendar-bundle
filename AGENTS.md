# Contributing to fedale/calendar-bundle — guidelines for AI assistants

> `CLAUDE.md` is a symlink to this file: one set of guidelines, served under both names. Edit this
> file, never the link.

## What this repository is

`fedale/calendar-bundle` is a **reusable Symfony bundle**: a server-rendered calendar component
(Symfony UX Live Component) that a host application drops into its own pages. It is a library, not
an application.

Two principles shape every change:

- **The calendar is a presentation component, not a domain.** It shows time-based data and turns
  user gestures into intents. It never holds business rules (booking windows, capacity, shift
  coverage): those live in the host application's domain modules, which reach the calendar through
  the contract in `src/Model/` and `src/Contract/`.
- **The server lays out the calendar.** Views compute positions in PHP and pass them to CSS as
  custom properties. JavaScript (Stimulus) only handles interaction; it never builds or measures the
  grid.

It is framework-agnostic on the CSS side: `cal-*` classes, `--cal-*` tokens, no Bootstrap or
Tailwind anywhere in `src/` or `templates/`.

The roadmap and the reasoning behind the design are in [`docs/plan.md`](docs/plan.md), in Italian.
Read it before adding a view, an option or anything that touches the contract.

**Requirements:** PHP 8.2+ with `ext-intl`, Symfony 6.4 / 7.x / 8.x, Symfony UX 2.20+.

## Verifying a change

```bash
composer cs         # php-cs-fixer, dry run with a diff
composer cs-fix     # apply the fixes
composer test       # phpunit (suite = tests/)
composer phpstan    # level 6 over src/
```

Run the linter, PHPStan and the tests before reporting a change as done. There is no PHPStan
baseline: keep it that way, and do not add `@phpstan-ignore` to silence your own change.
`treatPhpDocTypesAsCertain` is off on purpose: the bundle checks at runtime what application code
hands it.

## Repository map

| Path | What lives there |
|---|---|
| `src/Model/` | The read contract: `CalendarEvent`, `CalendarResource`, `CalendarQuery`, `DateRange`, and the `EventLayer` / `EventVisibility` / `EventInteractions` / `EventStyle` value objects |
| `src/Contract/` | Every public interface: sources, definitions, views, timezone resolver |
| `src/Attribute/` | `#[AsCalendar]`, `#[AsCalendarSource]`, `#[AsCalendarView]`: they carry the id/key/name the registries index by |
| `src/Definition/` | `AbstractCalendar`, `CalendarRegistry` |
| `src/Options/` | `CalendarOptions` (**the** list of accepted keys, with defaults and validation), `OptionsMerger`, `CalendarOptionsResolver` (precedence), and the value objects it builds: `ViewVariant`, `ViewRange`, `ToolbarButton`, `ResourceColumn` |
| `src/Source/` | `SourceRegistry`, `EventCollector` (fetch, filter, convert, sort), `ResourceCollector`, `CalendarItem` (an event as the views see it) |
| `src/View/` | One class per view type (`MonthView`, `ListView`, `TimelineView`), `ViewContext`, `ViewRegistry` |
| `src/View/Layout/` | The month's row layout (`WeekRowLayout`) |
| `src/View/Timeline/` | The timeline's engines and models: `TimeAxis` (instants to fractions of the axis), `LaneLayout` (stacking and "+N more"), rows and segments |
| `src/View/Model/` | The view models the templates render |
| `src/Time/` | `Days` (DST-safe day arithmetic), `DateFormatter` (ICU skeletons), the default timezone resolver |
| `src/Twig/` | The `Fedale:Calendar` Live Component and the Twig extension (`cal_format`, `cal_color`) |
| `templates/components/` | The component shell (toolbar + active view) |
| `templates/view/` | One template per view, plus `_event.html.twig` |
| `assets/styles/calendar.css` | The stylesheet, exposed to AssetMapper as `@fedale/calendar-bundle` |
| `assets/controllers/` | Stimulus controllers (`cal-timeline`, `cal-button`), declared in `assets/package.json` |
| `translations/` | `CalendarBundle.{en,it}.yaml`, the system domain |
| `config/services.yaml` | Service wiring |
| `tests/Integration/` | A minimal kernel (`TestKernel`) and fixtures to render the real component |
| `docs/plan.md` | Architecture plan and roadmap (Italian) |

## Invariants

- **Templates read events through `CalendarItem`, never `CalendarEvent` directly**, for anything
  visibility-sensitive (`item.title`, `item.url`). That is what keeps a `BusyOnly` event's title and
  link out of the HTML.
- **Every option key exists in `CalendarOptions::DEFAULTS`.** Unknown keys are rejected, so a new
  option is added there first, then validated in `fromArray()`, then exposed as a typed property.
- **Lists in the option tree are replaced, subtrees are merged** (`OptionsMerger`). Keep list-valued
  options as lists and grouped options as associative arrays.
- **Day arithmetic goes through `Days`.** Never add 86400 seconds: across a DST change a day lasts 23
  or 25 hours. Compare calendar days by their `Y-m-d`.
- **All-day events are dates, timed events are instants.** `CalendarItem::fromEvent()` is the single
  place that converts them; views only read `firstDay`, `lastDay`, `start`, `end`.
- **Views are pure.** A view gets a `ViewContext` (with its variant, today and now), sorted items
  and, when it asks for them, resources, and returns a view model. It does not fetch, read the
  request or the clock; the component supplies all of that.
- **Views are types, variants are names.** `#[AsCalendarView]` registers a type (`timeline`); the
  toolbar, the URL and `views.enabled` use variant names (`timelineWeek`). A type validates its own
  parameters in `validateVariant()`.
- **The component state is `calendar`, `view`, `date` and `collapsed`.** Anything that must survive
  a re-render is a `LiveProp`; a bad value from the URL falls back to a default instead of throwing.
- **Timeline positions are fractions of the axis**, rendered as percentages with `cal_pct()`.
  Inside a day the axis uses wall-clock minutes, so hour columns keep their width on DST days.
- **Source-provided values that land in a `style` attribute go through `cal_color()`** (or an
  equivalent whitelist). HTML escaping does not stop CSS injection there.

## PHP style

`.php-cs-fixer.dist.php` is the source of truth (the same rules as gridview-bundle):

- `@Symfony` + `@Symfony:risky`, with **non-Yoda** comparisons, unqualified global calls
  (`count()`), and `fn(...)` / `function(...)` with no extra space.
- **No `declare(strict_types=1);`**.
- PHP 8.2 is the floor: `readonly` classes are welcome for value objects and view models.
- Enums for fixed sets of values, not constants.
- Exception messages: capital letter, final period, `sprintf()`, no backticks. Option errors name
  the full option path.
- Services are autowired; registries receive tagged locators (see `config/services.yaml`).

### Comments

Comments carry the *why*, in **English**: the invariant a line protects, the edge case it handles
(DST, exclusive ends, hidden days). Skip restating what the code says. When you change behavior a
comment describes, update the comment in the same edit.

## Tests

`tests/` mirrors `src/`. PHPUnit 11.

- Unit tests for layout, views, options and items: they need no container. Build items with
  `tests/Support/Items.php` (Europe/Rome).
- `tests/Integration/` boots `TestKernel` and drives the real Live Component with
  `InteractsWithLiveComponents`; the clock is frozen at Saturday 10 October 2026, 12:00 Europe/Rome.
  Use it for anything that crosses the template boundary.
- A bug fix comes with the test that fails before it.
- Date logic is tested at its edges: midnight ends, multi-day spans, hidden days, DST changes,
  `firstDay` other than Monday.

## Templates and assets

- Twig: `|trans` for every user-facing string (domain `CalendarBundle`, with the calendar's
  `locale`), `cal_format(date, skeleton, locale)` for every date, never a hard-coded pattern.
- Twig gotcha: `false|default(true)` is `true`. Use `?? true` for boolean parameters.
- Structural hooks are `cal-*` classes (BEM-like: `cal-month__cell--today`); positions are custom
  properties set by the server.
- CSS: every visual value reads a `--cal-*` token; 4-space indent. Prefer native platform features
  over JavaScript (the "+N more" list is a native `popover`).
- Stimulus controllers go under `assets/controllers/`, one per file, registered in
  `assets/package.json`, and must survive Live re-renders. They only do what the server cannot
  (scrolling, a moving clock, dispatching DOM events): the calendar must render correctly without
  them.
- Translations: when you add a key, add it to **every** locale file.

## Documentation

When you change behavior, update `README.md`. Config keys in the docs must match
`CalendarOptions::DEFAULTS` exactly. Writing style: American English, second person,
gender-neutral, realistic examples (shifts, bookings, lessons), no `foo`/`bar`.

## Git

- Commit subjects in **English**, imperative mood, ~50 characters, no trailing period.
- Do not commit, push, or open a pull request unless you were asked to.
- Never edit `vendor/` or `composer.lock` by hand.

## Anti-patterns

- Putting a business rule in the bundle (booking windows, capacity, who may move what). The source
  computes `EventInteractions` and `hints`; the calendar only reflects them.
- Building or measuring the grid in JavaScript.
- Reading `item.event.title` or `item.event.url` in a template.
- Adding an option outside `CalendarOptions::DEFAULTS`, or validating it anywhere but `fromArray()`.
- Calling the clock or the request from a view.
- Hardcoding Bootstrap or Tailwind classes, user-facing text, or date patterns.

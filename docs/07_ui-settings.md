# End-user settings

> ← Back to the [documentation index](index.md) · previous: [Interaction](06_interaction.md) · next: [API](08_api.md)

With [fedale/setting-bundle](https://github.com/fedale/setting-bundle) 1.1+ and `symfony/form`, end
users can change some options at runtime, for every calendar or for one calendar (which wins), from
a dialog opened by the `settings` toolbar item.

## The settings

| Setting (key) | What it changes | Offered for |
|---|---|---|
| Default view (`default_view`) | `display.views.default` | calendars with more than one view |
| First day of the week (`first_day`) | `display.firstDay` | every calendar |
| Weekends (`weekends`) | Saturday and Sunday in `display.hiddenDays`, other hidden days kept | every calendar |
| Time format (`time_format`) | `display.timeFormat` | every calendar |
| Week numbers (`week_numbers`) | `display.weekNumbers` | calendars with a month view |
| Time slots (`slot_minutes`) | `slotMinutes` of every time-grid variant | calendars with a time grid |

They apply after `viewConfig()` and before the URL (see [Options](03_options.md#precedence)), and
only where they fit: a default view a calendar does not enable, or a slot length its day window
cannot hold, is skipped for that calendar.

Values are stored by setting-bundle under the `calendar.ui` namespace, at two scopes: `global` and
`calendar:<id>`, current tenant first.

## Enabling them

Configure a scoped store in setting-bundle, import the dialog's route, and add the item to the
toolbar:

```yaml
# config/packages/fedale_setting.yaml
fedale_setting:
    scoped:
        store: doctrine     # or the id of your ScopedStoreInterface service
```

```yaml
# config/routes/fedale_calendar.yaml
fedale_calendar_ui_settings:
    resource: '@FedaleCalendarBundle/src/Controller/UiSettingsController.php'
    type: attribute
    prefix: /calendar/_settings
```

```yaml
# config/packages/fedale_calendar.yaml
fedale_calendar:
    defaults:
        display:
            toolbar:
                end: 'views settings'
```

Without setting-bundle, its store, or the route, calendars render as configured in code and the
gear is not shown.

## Who may change them

The settings are shared: a global value changes every calendar for every user (of the tenant).
Protect the route like any other administrative page if not every user should change them:

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/calendar/_settings, roles: ROLE_ADMIN }
```

## The dialog

The gear opens a native `<dialog>` whose body is loaded from the route: a scope selector (every
calendar, or this one) and the form of that scope. Saving re-renders every calendar on the page,
since a global value may change them all. The dialog is ignored by Live re-renders, so it stays
open while the calendar behind it refreshes.

## Adding a setting

A setting is one class: implement `UiSettingInterface`, or extend `AbstractUiSetting`, which offers a
choice in both scopes and writes it to one option path. setting-bundle picks it up as a service.

```php
use Fedale\CalendarBundle\UiSettings\AbstractUiSetting;
use Fedale\CalendarBundle\UiSettings\CalendarDescriptor;

final class DayMaxEventsUiSetting extends AbstractUiSetting
{
    public function key(): string
    {
        return 'day_max_events';
    }

    public function label(): string
    {
        return 'Events per day';
    }

    protected function optionPath(): string
    {
        return 'display.month.dayMaxEvents';
    }

    public function supportsCalendar(CalendarDescriptor $calendar): bool
    {
        return $calendar->hasViewType('month');
    }

    public function choicesFor(?CalendarDescriptor $calendar): array
    {
        return ['2' => 2, '3' => 3, '5' => 5];
    }
}
```

| Method | Meaning |
|---|---|
| `key()` | Setting key, snake_case, unique in `calendar.ui` |
| `label()` | Label in the dialog, a translation key or text |
| `choicesFor(?calendar)` | Offered values, `label => value`; `$calendar` is `null` for the global scope |
| `supportsGlobal()`, `supportsCalendar()` | Where the setting is offered |
| `apply(tree, value)` | Writes the value into the option tree; override it to touch several options |

Override `apply()` when the stored value is not the option's value, as the built-in week numbers do
(`'show'` → `true`).

# Configuration reference

> ← Back to the [documentation index](index.md) · previous: [JavaScript](10_javascript.md) · next: [Extending](12_extending.md)

Everything under `fedale_calendar` is optional: calendars defined in code work with no YAML at all.

```yaml
# config/packages/fedale_calendar.yaml
fedale_calendar:

    # Option overrides for every calendar: the same tree as viewConfig().
    defaults:
        display:
            timezone: Europe/Rome
            theme: bootstrap5
            toolbar:
                end: 'views settings'

    # Option overrides per calendar id, above the defaults and below viewConfig().
    calendars:
        rooms:
            display:
                businessHours:
                    - { days: [1, 2, 3, 4, 5], start: '08:00', end: '19:00' }

    # Framework themes of the controls.
    themes:
        brand:
            extends: bootstrap5        # optional: default, bootstrap5 or another theme of yours
            classes:
                button.primary: 'btn btn-success'

    # The API Platform bridge.
    api:
        enabled: false
        calendars: []                  # empty = every calendar
        read_attribute: ~              # isGranted(attribute, calendar id) before reads
        write_attribute: ~             # ... and before writes
```

| Key | Default | Meaning | See |
|---|---|---|---|
| `defaults` | `{}` | Options for every calendar | [Options](03_options.md) |
| `calendars.<id>` | | Options for one calendar | [Options](03_options.md#precedence) |
| `themes.<name>.extends` | `null` | Theme whose classes this one starts from; `null` = `default` | [Styling](09_styling.md#themes) |
| `themes.<name>.classes` | `{}` | Classes by class key: `button`, `button.primary`, `button.icon`, `button.active`, `button.group` | [Styling](09_styling.md#themes) |
| `api.enabled` | `false` | Turns the API Platform bridge on | [API](08_api.md) |
| `api.calendars` | `[]` | Calendars the API exposes; empty = all | [API](08_api.md#configuration) |
| `api.read_attribute` | `null` | Security attribute for reads; `null` = no check | [API](08_api.md#who-may-call-it) |
| `api.write_attribute` | `null` | Security attribute for writes; `null` = no check | [API](08_api.md#who-may-call-it) |

Options under `defaults` and `calendars` are validated when a calendar is resolved, with errors
naming the full path of a wrong key; the YAML tree itself accepts any map.

## Routes

| Route | Needed for | Import |
|---|---|---|
| Live Component actions | every calendar | `@LiveComponentBundle/config/routes.php`, prefix `/_components` |
| Settings dialog | [end-user settings](07_ui-settings.md) | `@FedaleCalendarBundle/src/Controller/UiSettingsController.php`, type `attribute` |
| API | the [API](08_api.md) | registered by API Platform when `api.enabled` is true |

## Services you can replace

| Service id | Default | Why replace it |
|---|---|---|
| `Fedale\CalendarBundle\Contract\TimezoneResolverInterface` | PHP's default timezone | Show each user's timezone. See [Extending](12_extending.md#timezone-resolver) |

## Related bundles

| Bundle | Configuration used |
|---|---|
| fedale/setting-bundle | `fedale_setting.scoped.store`: where end-user settings are kept |
| api-platform/core | its own prefix, formats and firewall apply to the calendar's endpoints |

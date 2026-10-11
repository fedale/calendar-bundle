# Extending

> ← Back to the [documentation index](index.md) · previous: [Configuration reference](11_configuration.md)

Most needs are met by sources, handlers and options. When they are not, these are the extension
points.

## Timezone resolver

The display timezone of a calendar without `display.timezone` comes from
`TimezoneResolverInterface`, which by default returns PHP's default timezone. Follow each user's
own instead:

```php
namespace App\Calendar;

use Fedale\CalendarBundle\Contract\TimezoneResolverInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class UserTimezoneResolver implements TimezoneResolverInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function resolve(): \DateTimeZone
    {
        $user = $this->security->getUser();

        return new \DateTimeZone($user instanceof User ? $user->getTimezone() : date_default_timezone_get());
    }
}
```

```yaml
# config/services.yaml
services:
    Fedale\CalendarBundle\Contract\TimezoneResolverInterface: '@App\Calendar\UserTimezoneResolver'
```

## Views

A view type implements `CalendarViewInterface` and is registered with `#[AsCalendarView('type')]`.
Reusing a built-in type name replaces the built-in view.

```php
use Fedale\CalendarBundle\Attribute\AsCalendarView;
use Fedale\CalendarBundle\Contract\CalendarViewInterface;

#[AsCalendarView('fortnight')]
final class FortnightView implements CalendarViewInterface
{
    // ...
}
```

| Method | What it does |
|---|---|
| `validateVariant(ViewVariant)` | Rejects parameters this type does not understand, or invalid values |
| `usesResources(ViewVariant)` | Whether `build()` needs the resources (they are fetched only then) |
| `range(ViewContext)` | The period to fetch around the focused date |
| `navigate(ViewContext, int $step)` | The focused date one step back (`-1`) or forward (`1`) |
| `title(ViewContext)` | The localized heading |
| `build(ViewContext, items, resources)` | A view model: positions and labels, from the sorted items |
| `template()` | The Twig template rendering that view model as `model` |

The built-in views are the best starting point: `MonthView` with `WeekRowLayout` for day columns,
`TimelineView` with `LaneLayout` for rows, `TimeGridView` with `ColumnLayout` and `TimeAxis` for
hours. These layouts are plain classes you can reuse.

Declare a variant of the new type and enable it:

```php
'views' => [
    'enabled' => ['month', 'fortnight'],
    'variants' => ['fortnight' => ['type' => 'fortnight']],
],
```

Give the variant a `label`, or add the `view.fortnight` key to the `CalendarBundle` translation
domain.

## End-user settings

A new setting is one class implementing `UiSettingInterface` or extending `AbstractUiSetting`. See
[End-user settings](07_ui-settings.md#adding-a-setting).

## Templates

The component and its views are Twig templates under `@FedaleCalendar`:

| Template | What it renders |
|---|---|
| `components/Calendar.html.twig` | The component: toolbar, messages, the view, the settings dialog |
| `view/month.html.twig`, `year.html.twig`, `list.html.twig`, `time_grid.html.twig`, `timeline.html.twig` | One view each |
| `view/_event.html.twig` | One event, shared by the views |
| `ui_settings/_form.html.twig` | The settings dialog's body |

Override one with Symfony's usual mechanism, a copy under
`templates/bundles/FedaleCalendarBundle/`. Keep the `data-cal-*` attributes and the custom
properties: the Stimulus controllers and the stylesheet rely on them. Prefer a token, an event
`cssClass` or a theme when they suffice: overridden templates do not follow the bundle's updates.

## Calling intents from your own code

`IntentDispatcher` is the service the component and the API use to run an intent through the
calendar's checks and the source's handler. Use `dispatchById()` to send an intent from your own
code (a console command, a message handler) with the same guarantees:

```php
$options = $this->optionsResolver->resolve('rooms');   // CalendarOptionsResolver
$intent = new DeleteEvent(calendar: 'rooms', source: 'rooms', eventId: '42', version: '7');
$result = $this->dispatcher->dispatchById($options, $intent, new \DateTimeZone('Europe/Rome'));
```

The calendar's checks need the source to implement `EventFinderInterface`; without a finder, only
the handler decides.

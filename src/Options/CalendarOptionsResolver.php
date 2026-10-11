<?php

namespace Fedale\CalendarBundle\Options;

use Fedale\CalendarBundle\Definition\CalendarRegistry;
use Fedale\CalendarBundle\Source\SourceRegistry;
use Fedale\CalendarBundle\View\ViewRegistry;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Resolves the options of a calendar. Precedence, lowest to highest:
 *
 *  1. bundle defaults ({@see CalendarOptions::DEFAULTS})
 *  2. YAML `fedale_calendar.defaults`
 *  3. YAML `fedale_calendar.calendars.<id>`
 *  4. the definition's viewConfig()
 *
 * The end-user UI settings will slot in above 4; the view and date in the URL
 * are applied by the component, on top of everything.
 */
class CalendarOptionsResolver implements ResetInterface
{
    /** @var array<string, CalendarOptions> */
    private array $resolved = [];

    /**
     * @param array<string, mixed>                $defaults        YAML `defaults`
     * @param array<string, array<string, mixed>> $calendarConfigs YAML `calendars`, by calendar id
     */
    public function __construct(
        private readonly CalendarRegistry $calendars,
        private readonly ViewRegistry $views,
        private readonly SourceRegistry $sources,
        private readonly array $defaults = [],
        private readonly array $calendarConfigs = [],
    ) {
    }

    public function resolve(string $id): CalendarOptions
    {
        if (isset($this->resolved[$id])) {
            return $this->resolved[$id];
        }

        $tree = OptionsMerger::merge(
            CalendarOptions::DEFAULTS,
            $this->defaults,
            $this->calendarConfigs[$id] ?? [],
            $this->calendars->get($id)->viewConfig(),
        );

        try {
            $options = CalendarOptions::fromArray($tree);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException(sprintf('Invalid options for calendar "%s": %s', $id, $e->getMessage()), 0, $e);
        }

        foreach ($options->enabledViews as $name) {
            $variant = $options->variant($name);
            if (!$this->views->has($variant->type)) {
                throw new \InvalidArgumentException(sprintf('Calendar "%s" enables the view "%s" of unknown type "%s"; known types: %s.', $id, $name, $variant->type, implode(', ', $this->views->names())));
            }
            try {
                $this->views->get($variant->type)->validateVariant($variant);
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException(sprintf('Invalid options for calendar "%s": %s', $id, $e->getMessage()), 0, $e);
            }
        }
        foreach ($options->sources as $source) {
            if (!$this->sources->has($source)) {
                throw new \InvalidArgumentException(sprintf('Calendar "%s" uses the unknown source "%s"; register it with #[AsCalendarSource].', $id, $source));
            }
        }

        return $this->resolved[$id] = $options;
    }

    public function reset(): void
    {
        $this->resolved = [];
    }
}

<?php

namespace Fedale\CalendarBundle\Definition;

use Fedale\CalendarBundle\Contract\CalendarDefinitionInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Every calendar of the application, by id. The definitions reach it as a
 * lazy locator, so a definition is only instantiated when its calendar renders.
 */
class CalendarRegistry
{
    /**
     * @param ServiceProviderInterface<CalendarDefinitionInterface> $definitions
     */
    public function __construct(private readonly ServiceProviderInterface $definitions)
    {
    }

    public function has(string $id): bool
    {
        return $this->definitions->has($id);
    }

    public function get(string $id): CalendarDefinitionInterface
    {
        if (!$this->definitions->has($id)) {
            throw new \InvalidArgumentException(sprintf('Unknown calendar "%s"; register a CalendarDefinitionInterface with #[AsCalendar(\'%s\')].', $id, $id));
        }

        return $this->definitions->get($id);
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_keys($this->definitions->getProvidedServices());
    }
}

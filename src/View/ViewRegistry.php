<?php

namespace Fedale\CalendarBundle\View;

use Fedale\CalendarBundle\Contract\CalendarViewInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Every calendar view, built-in or from the application, by name (lazy locator).
 */
class ViewRegistry
{
    /**
     * @param ServiceProviderInterface<CalendarViewInterface> $views
     */
    public function __construct(private readonly ServiceProviderInterface $views)
    {
    }

    public function has(string $name): bool
    {
        return $this->views->has($name);
    }

    public function get(string $name): CalendarViewInterface
    {
        if (!$this->views->has($name)) {
            throw new \InvalidArgumentException(sprintf('Unknown calendar view "%s".', $name));
        }

        return $this->views->get($name);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->views->getProvidedServices());
    }
}

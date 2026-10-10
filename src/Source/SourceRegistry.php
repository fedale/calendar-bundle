<?php

namespace Fedale\CalendarBundle\Source;

use Fedale\CalendarBundle\Contract\EventSourceInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Every event source of the application, by key (lazy locator).
 */
class SourceRegistry
{
    /**
     * @param ServiceProviderInterface<EventSourceInterface> $sources
     */
    public function __construct(private readonly ServiceProviderInterface $sources)
    {
    }

    public function has(string $key): bool
    {
        return $this->sources->has($key);
    }

    public function get(string $key): EventSourceInterface
    {
        if (!$this->sources->has($key)) {
            throw new \InvalidArgumentException(sprintf('Unknown calendar source "%s"; register an EventSourceInterface with #[AsCalendarSource(\'%s\')].', $key, $key));
        }

        return $this->sources->get($key);
    }
}

<?php

namespace Fedale\CalendarBundle\Intent;

use Fedale\CalendarBundle\Contract\EventFinderInterface;
use Fedale\CalendarBundle\Contract\IntentHandlerInterface;
use Fedale\CalendarBundle\Model\EventVisibility;
use Fedale\CalendarBundle\Options\CalendarOptions;
use Fedale\CalendarBundle\Source\CalendarItem;
use Fedale\CalendarBundle\Source\SourceRegistry;

/**
 * Routes an intent to the handler of the source it concerns, after the
 * checks the calendar can make on its own: the event is on screen, it
 * enables that interaction, its version has not changed since the page was
 * rendered, its source can handle intents. These reject a forged or stale
 * request early; the handler still has the last word.
 */
class IntentDispatcher
{
    private const DOMAIN = 'CalendarBundle';

    public function __construct(private readonly SourceRegistry $sources)
    {
    }

    /**
     * @param list<CalendarItem> $items the items of the period on screen, freshly fetched
     */
    public function dispatch(CalendarOptions $options, CalendarIntent $intent, array $items): IntentResult
    {
        if ($intent instanceof SelectRange) {
            if (!$options->selectable) {
                return IntentResult::rejected(Rejection::Forbidden, 'intent.not_allowed', translationDomain: self::DOMAIN);
            }
            $handler = $this->selectHandler($options);
            if ($handler === null) {
                return IntentResult::rejected(Rejection::Forbidden, 'intent.read_only', translationDomain: self::DOMAIN);
            }

            return $handler->handle($intent);
        }

        if (!$intent instanceof EventIntent) {
            throw new \InvalidArgumentException(sprintf('Unsupported calendar intent %s.', get_debug_type($intent)));
        }
        if (!\in_array($intent->source, $options->sources, true)) {
            // Only the calendar's own sources: a request cannot reach others.
            return IntentResult::rejected(Rejection::Forbidden, 'intent.not_allowed', translationDomain: self::DOMAIN);
        }

        $item = null;
        foreach ($items as $candidate) {
            if ($candidate->source === $intent->source && $candidate->event->id === $intent->eventId) {
                $item = $candidate;
                break;
            }
        }
        if ($item === null) {
            // Gone since the page was rendered, or never shown to this user.
            return IntentResult::rejected(Rejection::Conflict, 'intent.gone', translationDomain: self::DOMAIN);
        }

        $interactions = $item->event->interactions;
        $allowed = match (true) {
            $intent instanceof MoveEvent => $interactions->movable,
            $intent instanceof ResizeEvent => $interactions->resizable,
            $intent instanceof ClickEvent => $interactions->clickable,
            $intent instanceof DeleteEvent => $interactions->deletable,
            default => false,
        };
        if (!$allowed || $item->isBackground()) {
            return IntentResult::rejected(Rejection::Forbidden, 'intent.not_allowed', translationDomain: self::DOMAIN);
        }
        if ($intent->version !== $item->event->version) {
            return IntentResult::rejected(Rejection::Conflict, 'intent.stale', translationDomain: self::DOMAIN);
        }
        if (($intent instanceof MoveEvent || $intent instanceof ResizeEvent) && $intent->end <= $intent->start) {
            return IntentResult::rejected(Rejection::Validation, 'intent.invalid', translationDomain: self::DOMAIN);
        }

        $source = $this->sources->get($intent->source);
        if (!$source instanceof IntentHandlerInterface) {
            return IntentResult::rejected(Rejection::Forbidden, 'intent.read_only', translationDomain: self::DOMAIN);
        }

        return $source->handle($intent);
    }

    /**
     * For clients without a page on screen (an API): the event is looked up
     * by id through the source's {@see EventFinderInterface}, then checked as
     * from the page. A source without a finder leaves every check but the
     * obvious ones to its handler.
     */
    public function dispatchById(CalendarOptions $options, CalendarIntent $intent, \DateTimeZone $timezone): IntentResult
    {
        if (!$intent instanceof EventIntent || !\in_array($intent->source, $options->sources, true)) {
            return $this->dispatch($options, $intent, []);
        }

        $source = $this->sources->get($intent->source);
        if ($source instanceof EventFinderInterface) {
            $event = $source->findEvent($intent->eventId);
            $items = $event !== null && $event->visibility !== EventVisibility::Hidden ? [CalendarItem::fromEvent($intent->source, $event, $timezone)] : [];

            return $this->dispatch($options, $intent, $items);
        }

        if (($intent instanceof MoveEvent || $intent instanceof ResizeEvent) && $intent->end <= $intent->start) {
            return IntentResult::rejected(Rejection::Validation, 'intent.invalid', translationDomain: self::DOMAIN);
        }

        return $source instanceof IntentHandlerInterface
            ? $source->handle($intent)
            : IntentResult::rejected(Rejection::Forbidden, 'intent.read_only', translationDomain: self::DOMAIN);
    }

    private function selectHandler(CalendarOptions $options): ?IntentHandlerInterface
    {
        $keys = $options->selectSource !== null ? [$options->selectSource] : $options->sources;
        foreach ($keys as $key) {
            $source = $this->sources->get($key);
            if ($source instanceof IntentHandlerInterface) {
                return $source;
            }
        }

        return null;
    }
}

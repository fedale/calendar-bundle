<?php

namespace Fedale\CalendarBundle\Bridge\ApiPlatform\State;

use Fedale\CalendarBundle\Bridge\ApiPlatform\Resource\ApiEvent;
use Fedale\CalendarBundle\Contract\TimezoneResolverInterface;
use Fedale\CalendarBundle\Definition\CalendarRegistry;
use Fedale\CalendarBundle\Options\CalendarOptions;
use Fedale\CalendarBundle\Options\CalendarOptionsResolver;
use Fedale\CalendarBundle\Source\CalendarItem;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * What the providers and the processor share: access to the calendar of the
 * URI, its options and timezone, times from the request, and events in their
 * API shape.
 *
 * Access is the first thing checked on every operation: a calendar the API
 * does not expose answers 404 (its existence is not disclosed), one the
 * user may not read or write answers 403, through the configured security
 * attributes (`isGranted(attribute, calendar id)`).
 */
class CalendarApi
{
    /**
     * @param list<string> $exposed ids of the calendars the API exposes; empty = all
     */
    public function __construct(
        private readonly CalendarRegistry $calendars,
        private readonly CalendarOptionsResolver $options,
        private readonly TimezoneResolverInterface $timezoneResolver,
        private readonly array $exposed = [],
        private readonly ?string $readAttribute = null,
        private readonly ?string $writeAttribute = null,
        private readonly ?AuthorizationCheckerInterface $authorization = null,
    ) {
    }

    /**
     * The calendar id of the URI, once the user may access it.
     *
     * @param array<string, mixed> $uriVariables
     */
    public function calendarId(array $uriVariables, string $name = 'calendar', bool $write = false): string
    {
        $id = (string) ($uriVariables[$name] ?? '');
        if (!$this->isExposed($id)) {
            throw new NotFoundHttpException(sprintf('No calendar "%s".', $id));
        }
        if (!$this->isGranted($this->readAttribute, $id) || ($write && !$this->isGranted($this->writeAttribute, $id))) {
            throw new AccessDeniedHttpException('Access to this calendar is denied.');
        }

        return $id;
    }

    /**
     * The calendars the API exposes and the user may read.
     *
     * @return list<string>
     */
    public function readableIds(): array
    {
        $ids = array_values(array_filter($this->calendars->ids(), fn(string $id) => $this->isExposed($id) && $this->isGranted($this->readAttribute, $id)));
        sort($ids);

        return $ids;
    }

    private function isExposed(string $id): bool
    {
        return $this->calendars->has($id) && ($this->exposed === [] || \in_array($id, $this->exposed, true));
    }

    private function isGranted(?string $attribute, string $calendar): bool
    {
        if ($attribute === null) {
            return true;
        }
        if ($this->authorization === null) {
            // Configured but unenforceable: refuse rather than open up.
            return false;
        }

        return $this->authorization->isGranted($attribute, $calendar);
    }

    public function options(string $calendar): CalendarOptions
    {
        return $this->options->resolve($calendar);
    }

    public function timezone(CalendarOptions $options): \DateTimeZone
    {
        return $options->timezone !== null ? new \DateTimeZone($options->timezone) : $this->timezoneResolver->resolve();
    }

    /**
     * An ISO 8601 instant, converted to the display timezone; with $date, a
     * Y-m-d date at midnight in it. Anything else is a 400 or a 422 for the
     * client, never a guess.
     */
    public function time(?string $value, string $field, \DateTimeZone $timezone, bool $date = false): \DateTimeImmutable
    {
        if ($value === null || $value === '') {
            throw new BadRequestHttpException(sprintf('"%s" is required.', $field));
        }
        if ($date) {
            $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
            if ($day === false || $day->format('Y-m-d') !== $value) {
                throw new BadRequestHttpException(sprintf('"%s" must be a date (Y-m-d).', $field));
            }

            return $day;
        }
        // An offset is required: without one, the instant would depend on the server.
        if (preg_match('/(Z|[+-]\d{2}:?\d{2})$/', $value) !== 1) {
            throw new BadRequestHttpException(sprintf('"%s" must be an ISO 8601 date-time with an offset, e.g. 2026-10-12T09:00:00+02:00.', $field));
        }
        try {
            return (new \DateTimeImmutable($value))->setTimezone($timezone);
        } catch (\Exception $e) {
            throw new BadRequestHttpException(sprintf('"%s" is not a valid date-time.', $field), $e);
        }
    }

    /**
     * The API shape of an event: what the calendar would render, nothing more.
     */
    public function event(string $calendar, CalendarItem $item): ApiEvent
    {
        $event = $item->event;
        $api = new ApiEvent();
        $api->calendar = $calendar;
        $api->source = $item->source;
        $api->id = $event->id;
        $api->title = $item->title();
        $api->busy = $item->isBusy();
        $api->start = $item->start;
        $api->end = $item->end;
        $api->allDay = $event->allDay;
        $api->resourceIds = $event->resourceIds;
        $api->type = $event->type;
        $api->status = $event->status;
        $api->layer = $event->layer->value;
        $api->interactions = [
            'movable' => $event->interactions->movable,
            'resizable' => $event->interactions->resizable,
            'clickable' => $event->interactions->clickable,
            'deletable' => $event->interactions->deletable,
        ];
        $api->version = $event->version;
        $api->color = $event->style->color;
        $api->cssClass = $event->style->cssClass;
        // Hints are display facts the source chose to show; a busy event shows none.
        $api->hints = $item->isBusy() ? [] : $event->hints;
        $api->url = $item->url();

        return $api;
    }
}

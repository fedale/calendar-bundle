<?php

namespace Fedale\CalendarBundle\Bridge\ApiPlatform\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Fedale\CalendarBundle\Bridge\ApiPlatform\Dto\IntentOutput;
use Fedale\CalendarBundle\Bridge\ApiPlatform\Dto\MoveInput;
use Fedale\CalendarBundle\Bridge\ApiPlatform\Dto\ResizeInput;
use Fedale\CalendarBundle\Bridge\ApiPlatform\Dto\SelectionInput;
use Fedale\CalendarBundle\Contract\EventFinderInterface;
use Fedale\CalendarBundle\Intent\CalendarIntent;
use Fedale\CalendarBundle\Intent\DeleteEvent;
use Fedale\CalendarBundle\Intent\IntentDispatcher;
use Fedale\CalendarBundle\Intent\MoveEvent;
use Fedale\CalendarBundle\Intent\Rejection;
use Fedale\CalendarBundle\Intent\ResizeEvent;
use Fedale\CalendarBundle\Intent\SelectRange;
use Fedale\CalendarBundle\Model\CalendarEvent;
use Fedale\CalendarBundle\Options\CalendarOptions;
use Fedale\CalendarBundle\Source\CalendarItem;
use Fedale\CalendarBundle\Source\SourceRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Turns the API's writes into the same intents the UI sends, through the
 * same dispatcher and handlers. The answer maps onto HTTP: accepted 200,
 * Conflict 409, Validation 422, Forbidden 403.
 *
 * @implements ProcessorInterface<MoveInput|ResizeInput|SelectionInput|null, IntentOutput>
 */
final class IntentProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CalendarApi $api,
        private readonly IntentDispatcher $dispatcher,
        private readonly SourceRegistry $sources,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): IntentOutput
    {
        $calendar = $this->api->calendarId($uriVariables, write: true);
        $options = $this->api->options($calendar);
        $timezone = $this->api->timezone($options);
        $request = $context['request'] ?? null;
        $request = $request instanceof Request ? $request : null;

        $intent = $this->intent($calendar, $data, $operation, $uriVariables, $timezone, $request);
        $result = $this->dispatcher->dispatchById($options, $intent, $timezone);

        $message = $result->message !== null
            ? $this->translator->trans($result->message, $result->parameters, $result->translationDomain, $request?->getLocale())
            : null;
        if (!$result->accepted) {
            throw new HttpException(match ($result->rejection) {
                Rejection::Conflict => Response::HTTP_CONFLICT,
                Rejection::Validation => Response::HTTP_UNPROCESSABLE_ENTITY,
                default => Response::HTTP_FORBIDDEN,
            }, $message ?? 'Rejected.');
        }

        $output = new IntentOutput();
        $output->message = $message;
        $output->url = $result->url;

        return $output;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function intent(string $calendar, mixed $data, Operation $operation, array $uriVariables, \DateTimeZone $timezone, ?Request $request): CalendarIntent
    {
        if ($data instanceof SelectionInput) {
            return new SelectRange(
                $calendar,
                $this->api->time($data->start, 'start', $timezone, $data->allDay),
                $this->api->time($data->end, 'end', $timezone, $data->allDay),
                $data->allDay,
                $data->resource !== '' ? $data->resource : null,
            );
        }

        $source = (string) ($uriVariables['source'] ?? '');
        $id = (string) ($uriVariables['id'] ?? '');
        $version = $this->ifMatch($request) ?? ($data instanceof MoveInput || $data instanceof ResizeInput ? $data->version : null);

        if ($operation instanceof DeleteOperationInterface) {
            return new DeleteEvent($calendar, $source, $id, $version);
        }

        $current = $this->current($this->api->options($calendar), $source, $id);
        $allDay = $current !== null && $current->allDay;

        if ($data instanceof MoveInput) {
            $from = $data->fromResource ?? ($current !== null && \count($current->resourceIds) === 1 ? $current->resourceIds[0] : null);

            return new MoveEvent(
                $calendar,
                $source,
                $id,
                $version,
                $this->api->time($data->start, 'start', $timezone, $allDay),
                $this->api->time($data->end, 'end', $timezone, $allDay),
                $from,
                $data->resource ?? $from,
            );
        }
        if ($data instanceof ResizeInput) {
            // The event's own start, read as the calendar reads it (dates stay dates).
            $start = $current !== null && $data->start === null
                ? CalendarItem::fromEvent($source, $current, $timezone)->start
                : $this->api->time($data->start, 'start', $timezone, $allDay);

            return new ResizeEvent($calendar, $source, $id, $version, $start, $this->api->time($data->end, 'end', $timezone, $allDay));
        }

        throw new BadRequestHttpException('Unsupported calendar write.');
    }

    /** The event as its source has it now, when the source can tell. */
    private function current(CalendarOptions $options, string $source, string $id): ?CalendarEvent
    {
        if (!\in_array($source, $options->sources, true)) {
            return null;
        }
        $finder = $this->sources->get($source);

        return $finder instanceof EventFinderInterface ? $finder->findEvent($id) : null;
    }

    /** The version from `If-Match: "7"` (or W/"7"); null without the header. */
    private function ifMatch(?Request $request): ?string
    {
        $header = $request?->headers->get('If-Match');
        if ($header === null || $header === '' || $header === '*') {
            return null;
        }

        return trim(preg_replace('/^W\//', '', trim($header)) ?? '', '"');
    }
}

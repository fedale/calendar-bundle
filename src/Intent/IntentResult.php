<?php

namespace Fedale\CalendarBundle\Intent;

/**
 * What a handler answers. Whatever the answer, the calendar re-renders from
 * the sources, so the screen always shows the real state: an accepted move
 * shows the event at its new place, a rejected one at its old place.
 *
 * `message` is shown to the user: a translation key or plain text, in
 * `translationDomain` (null = the application's default domain).
 */
final readonly class IntentResult
{
    /**
     * @param array<string, scalar> $parameters translation parameters
     */
    private function __construct(
        public bool $accepted,
        public ?Rejection $rejection,
        public ?string $message,
        public array $parameters = [],
        public ?string $translationDomain = null,
        public ?string $url = null,
        public ?string $frame = null,
    ) {
    }

    /**
     * @param array<string, scalar> $parameters
     */
    public static function accepted(?string $message = null, array $parameters = [], ?string $translationDomain = null): self
    {
        return new self(true, null, $message, $parameters, $translationDomain);
    }

    /**
     * @param array<string, scalar> $parameters
     */
    public static function rejected(Rejection $reason, string $message, array $parameters = [], ?string $translationDomain = null): self
    {
        return new self(false, $reason, $message, $parameters, $translationDomain);
    }

    /**
     * Accepted, and the browser should open $url: in the Turbo Frame $frame
     * when given (e.g. a modal with the application's own form), else as a
     * page visit. Typical answer to a {@see SelectRange}.
     */
    public static function openUrl(string $url, ?string $frame = null): self
    {
        return new self(true, null, null, url: $url, frame: $frame);
    }
}

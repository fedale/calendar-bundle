<?php

namespace Fedale\CalendarBundle\Bridge\ApiPlatform\Dto;

/**
 * The answer to an accepted intent. Rejections are HTTP errors instead:
 * 409 (conflict), 422 (validation), 403 (forbidden), with the message as
 * their detail.
 */
final class IntentOutput
{
    public bool $accepted = true;

    public ?string $message = null;

    /** Where the handler asks the client to go next (e.g. a creation form). */
    public ?string $url = null;
}

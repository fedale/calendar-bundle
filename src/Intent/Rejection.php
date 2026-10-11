<?php

namespace Fedale\CalendarBundle\Intent;

/** Why an intent was not applied. */
enum Rejection: string
{
    /** The data changed since the page was rendered (another user, another tab). */
    case Conflict = 'conflict';
    /** The domain refused the new state (overlap, capacity, closed hours, ...). */
    case Validation = 'validation';
    /** The user may not do this, or the event does not accept it. */
    case Forbidden = 'forbidden';
}

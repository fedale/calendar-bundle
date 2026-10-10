<?php

namespace Fedale\CalendarBundle\Options;

enum TimeFormat: string
{
    case H24 = '24h';
    case H12 = '12h';

    /** ICU skeleton for hours and minutes; the locale picks separators and the AM/PM marker. */
    public function skeleton(): string
    {
        return match ($this) {
            self::H24 => 'Hmm',
            self::H12 => 'hmma',
        };
    }
}

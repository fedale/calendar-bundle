<?php

namespace Fedale\CalendarBundle\Time;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Locale-aware date formatting from ICU skeletons ("MMMMy", "EEE", "Hmm"):
 * the skeleton says which fields to show, the locale decides their order and
 * separators, so templates never hard-code a date pattern.
 */
class DateFormatter
{
    /** @var array<string, string> locale|skeleton => pattern */
    private array $patterns = [];

    /**
     * Building an IntlDateFormatter costs far more than using one, and a month
     * formats hundreds of dates with a handful of patterns: keep them.
     *
     * @var array<string, \IntlDateFormatter> locale|pattern|timezone => formatter
     */
    private array $formatters = [];

    public function __construct(private readonly ?RequestStack $requestStack = null)
    {
    }

    /** The locale used when none is given: the current request's, else the ICU default. */
    public function defaultLocale(): string
    {
        return $this->requestStack?->getCurrentRequest()?->getLocale() ?? \Locale::getDefault();
    }

    /** Formats $date in its own timezone. */
    public function format(\DateTimeInterface $date, string $skeleton, ?string $locale = null): string
    {
        $locale ??= $this->defaultLocale();
        $pattern = $this->patterns[$locale.'|'.$skeleton] ??= (new \IntlDatePatternGenerator($locale))->getBestPattern($skeleton)
            ?: throw new \InvalidArgumentException(sprintf('Cannot build a date pattern for skeleton "%s" and locale "%s".', $skeleton, $locale));

        $timezone = $date->getTimezone();
        $formatter = $this->formatters[$locale.'|'.$pattern.'|'.$timezone->getName()]
            ??= new \IntlDateFormatter($locale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $timezone, \IntlDateFormatter::GREGORIAN, $pattern);
        $formatted = $formatter->format($date);
        if ($formatted === false) {
            throw new \RuntimeException(sprintf('Cannot format a date with pattern "%s": %s.', $pattern, $formatter->getErrorMessage()));
        }

        return $formatted;
    }
}

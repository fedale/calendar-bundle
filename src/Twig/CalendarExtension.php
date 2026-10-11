<?php

namespace Fedale\CalendarBundle\Twig;

use Fedale\CalendarBundle\Time\DateFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CalendarExtension extends AbstractExtension
{
    // Colors come from the sources and end up in a style attribute: HTML
    // escaping does not stop a "red; background: url(...)" there, so only
    // characters a CSS color can contain are let through.
    private const SAFE_COLOR = '/^[#a-zA-Z0-9(),.%\s+-]+$/';

    public function __construct(private readonly DateFormatter $formatter)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cal_format', $this->formatter->format(...)),
            new TwigFunction('cal_color', $this->color(...)),
            new TwigFunction('cal_pct', $this->percent(...)),
        ];
    }

    /** A fraction (0..1) as a CSS percentage, dot-decimal whatever the PHP locale. */
    public function percent(float $fraction): string
    {
        return rtrim(rtrim(number_format($fraction * 100, 4, '.', ''), '0'), '.').'%';
    }

    public function color(?string $color): ?string
    {
        return $color !== null && preg_match(self::SAFE_COLOR, $color) === 1 ? $color : null;
    }
}

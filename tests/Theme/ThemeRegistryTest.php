<?php

namespace Fedale\CalendarBundle\Tests\Theme;

use Fedale\CalendarBundle\Theme\ThemeRegistry;
use PHPUnit\Framework\TestCase;

final class ThemeRegistryTest extends TestCase
{
    public function testCustomThemesExtendABuiltInAndFallBackToTheDefaultKeys(): void
    {
        $registry = new ThemeRegistry(['brand' => ['extends' => 'bootstrap5', 'classes' => ['button.primary' => 'btn btn-brand']]]);

        $classes = $registry->classes('brand');

        self::assertSame('btn btn-brand', $classes['button.primary']);
        self::assertSame('btn btn-outline-secondary', $classes['button']);
        self::assertEqualsCanonicalizing(array_keys(ThemeRegistry::DEFAULT), array_keys($classes));
        self::assertSame(['default', 'bootstrap5', 'brand'], $registry->names());
    }

    public function testRejectsUnknownThemes(): void
    {
        $this->expectExceptionMessage('Unknown calendar theme "tailwind"');

        (new ThemeRegistry())->classes('tailwind');
    }
}

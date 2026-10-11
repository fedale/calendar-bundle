<?php

namespace Fedale\CalendarBundle\Tests\Integration;

use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use Fedale\CalendarBundle\FedaleCalendarBundle;
use Fedale\CalendarBundle\Tests\Integration\Fixtures\MemoryScopedStore;
use Fedale\CalendarBundle\Tests\Integration\Fixtures\NoSettingStorage;
use Fedale\SettingBundle\FedaleSettingBundle;
use Psr\Clock\ClockInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\LiveComponent\LiveComponentBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;

/**
 * Smallest app able to render the calendar: Framework, Twig, Twig/Live
 * components, setting-bundle (UI settings, stored in memory), API Platform
 * (the bridge, under /api) and the bundle,
 * with demo calendars and a frozen clock (Saturday 10 October 2026,
 * Europe/Rome).
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new StimulusBundle();
        yield new TwigComponentBundle();
        yield new LiveComponentBundle();
        yield new ApiPlatformBundle();
        yield new FedaleSettingBundle();
        yield new FedaleCalendarBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/fedale-calendar-bundle/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/fedale-calendar-bundle/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'default_locale' => 'it',
            'translator' => ['fallbacks' => ['en']],
            'router' => ['utf8' => true],
            'php_errors' => ['log' => true],
            'form' => ['enabled' => true, 'csrf_protection' => false],
            'serializer' => ['enabled' => true],
            'property_info' => ['enabled' => true],
        ]);
        $container->extension('api_platform', [
            'formats' => ['jsonld' => ['application/ld+json'], 'json' => ['application/json']],
            'docs_formats' => ['jsonopenapi' => ['application/vnd.openapi+json']],
        ]);
        // UI settings in memory; the free-form settings are not used.
        $container->extension('fedale_setting', [
            'provider' => NoSettingStorage::class,
            'cache' => ['enabled' => false],
            'scoped' => ['store' => MemoryScopedStore::class],
        ]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/']);
        $container->extension('fedale_calendar', [
            'defaults' => ['display' => ['timezone' => 'Europe/Rome']],
            'api' => ['enabled' => true],
        ]);

        // TwigBundle wires its form integration only when symfony/form is a
        // production dependency; for this bundle it is an optional one.
        $container->import(\dirname(__DIR__, 2).'/vendor/symfony/twig-bundle/Resources/config/form.php');

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->load('Fedale\\CalendarBundle\\Tests\\Integration\\Fixtures\\', __DIR__.'/Fixtures/');
        $services->set('logger', NullLogger::class);
        $services->set(ClockInterface::class, MockClock::class)
            ->args(['2026-10-10 12:00', 'Europe/Rome']);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@LiveComponentBundle/config/routes.php')->prefix('/_components');
        $routes->import(\dirname(__DIR__, 2).'/src/Controller/UiSettingsController.php', 'attribute')->prefix('/calendar/_settings');
        $routes->import('.', 'api_platform')->prefix('/api');
    }
}

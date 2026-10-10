<?php

namespace Fedale\CalendarBundle\Tests\Integration;

use Fedale\CalendarBundle\FedaleCalendarBundle;
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
 * components and the bundle, with a demo calendar and a frozen clock
 * (Saturday 10 October 2026, Europe/Rome).
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
        ]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/']);
        $container->extension('fedale_calendar', [
            'defaults' => ['display' => ['timezone' => 'Europe/Rome']],
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->load('Fedale\\CalendarBundle\\Tests\\Integration\\Fixtures\\', __DIR__.'/Fixtures/');
        $services->set('logger', NullLogger::class);
        $services->set(ClockInterface::class, MockClock::class)
            ->args(['2026-10-10 12:00', 'Europe/Rome']);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@LiveComponentBundle/config/routes.php');
    }
}

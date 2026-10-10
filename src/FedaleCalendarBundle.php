<?php

namespace Fedale\CalendarBundle;

use Fedale\CalendarBundle\Attribute\AsCalendar;
use Fedale\CalendarBundle\Attribute\AsCalendarSource;
use Fedale\CalendarBundle\Attribute\AsCalendarView;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class FedaleCalendarBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        // Option trees are validated at render time by CalendarOptions, the
        // single place that knows every key: YAML, viewConfig() and (later) UI
        // settings all go through it, so the schema is not duplicated here.
        $definition->rootNode()
            ->children()
                ->variableNode('defaults')
                    ->info('Option overrides for every calendar (same tree as viewConfig()).')
                    ->defaultValue([])
                ->end()
                ->arrayNode('calendars')
                    ->info('Option overrides per calendar id.')
                    ->useAttributeAsKey('id')
                    ->variablePrototype()->end()
                ->end()
            ->end();
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.yaml');

        $container->parameters()
            ->set('fedale_calendar.defaults', $config['defaults'] ?? [])
            ->set('fedale_calendar.calendars', $config['calendars'] ?? []);

        // The attributes carry the id/key/name the registries index by, so an
        // application registers a calendar, a source or a view with the
        // attribute alone.
        $builder->registerAttributeForAutoconfiguration(AsCalendar::class, static function (ChildDefinition $definition, AsCalendar $attribute): void {
            $definition->addTag('fedale_calendar.calendar', ['id' => $attribute->id]);
        });
        $builder->registerAttributeForAutoconfiguration(AsCalendarSource::class, static function (ChildDefinition $definition, AsCalendarSource $attribute): void {
            $definition->addTag('fedale_calendar.source', ['key' => $attribute->key]);
        });
        $builder->registerAttributeForAutoconfiguration(AsCalendarView::class, static function (ChildDefinition $definition, AsCalendarView $attribute): void {
            $definition->addTag('fedale_calendar.view', ['name' => $attribute->name]);
        });
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Expose assets/ to AssetMapper as @fedale/calendar-bundle, so the app
        // imports the stylesheet without copying it.
        if (interface_exists(AssetMapperInterface::class)) {
            $builder->prependExtensionConfig('framework', [
                'asset_mapper' => [
                    'paths' => [__DIR__.'/../assets' => '@fedale/calendar-bundle'],
                ],
            ]);
        }
    }
}

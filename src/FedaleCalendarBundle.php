<?php

namespace Fedale\CalendarBundle;

use Fedale\CalendarBundle\Attribute\AsCalendar;
use Fedale\CalendarBundle\Attribute\AsCalendarSource;
use Fedale\CalendarBundle\Attribute\AsCalendarView;
use Fedale\CalendarBundle\Controller\UiSettingsController;
use Fedale\CalendarBundle\Options\CalendarOptionsResolver;
use Fedale\CalendarBundle\UiSettings\DefaultViewUiSetting;
use Fedale\CalendarBundle\UiSettings\FirstDayUiSetting;
use Fedale\CalendarBundle\UiSettings\SlotDurationUiSetting;
use Fedale\CalendarBundle\UiSettings\TimeFormatUiSetting;
use Fedale\CalendarBundle\UiSettings\UiSettingsResolver;
use Fedale\CalendarBundle\UiSettings\WeekendsUiSetting;
use Fedale\CalendarBundle\UiSettings\WeekNumbersUiSetting;
use Fedale\SettingBundle\FedaleSettingBundle;
use Fedale\SettingBundle\Scoped\ScopedSettingsResolver;
use Fedale\SettingBundle\Scoped\ScopedSettingsWriter;
use Fedale\SettingBundle\Scoped\SettingDefinitionInterface;
use Fedale\SettingBundle\Scoped\SettingDefinitionRegistry;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\FormFactoryInterface;
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
                ->arrayNode('themes')
                    ->info('Framework themes: CSS classes of the controls by class key, optionally extending a built-in theme (default, bootstrap5).')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('extends')->defaultNull()->end()
                            ->arrayNode('classes')
                                ->normalizeKeys(false)
                                ->useAttributeAsKey('key')
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
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
            ->set('fedale_calendar.calendars', $config['calendars'] ?? [])
            ->set('fedale_calendar.themes', $config['themes'] ?? []);

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

        $this->loadUiSettings($builder);
    }

    /**
     * The UI settings are fedale/setting-bundle scoped settings, an optional
     * dependency: without it (and symfony/form, for the dialog) nothing is
     * defined, calendars render as configured in code and the dialog's route
     * answers 404. Application UiSettingInterface services need no tag of
     * ours: setting-bundle autoconfigures every SettingDefinitionInterface.
     */
    private function loadUiSettings(ContainerBuilder $builder): void
    {
        $enabled = interface_exists(SettingDefinitionInterface::class)
            && \in_array(FedaleSettingBundle::class, (array) ($builder->hasParameter('kernel.bundles') ? $builder->getParameter('kernel.bundles') : []), true);

        if ($enabled) {
            $builder->register('fedale_calendar.ui_settings.resolver', UiSettingsResolver::class)
                ->setArguments([
                    new Reference(ScopedSettingsResolver::class),
                    new Reference(ScopedSettingsWriter::class),
                    new Reference(SettingDefinitionRegistry::class),
                ]);
            $builder->setAlias(UiSettingsResolver::class, 'fedale_calendar.ui_settings.resolver');
            $builder->getDefinition(CalendarOptionsResolver::class)
                ->setArgument('$uiSettings', new Reference('fedale_calendar.ui_settings.resolver'));

            foreach ([DefaultViewUiSetting::class, FirstDayUiSetting::class, WeekendsUiSetting::class, TimeFormatUiSetting::class, WeekNumbersUiSetting::class, SlotDurationUiSetting::class] as $setting) {
                $builder->register($setting, $setting)->addTag('fedale_setting.definition');
            }
        }

        if (interface_exists(FormFactoryInterface::class)) {
            $builder->register(UiSettingsController::class, UiSettingsController::class)
                ->setPublic(true)
                ->setArguments([
                    new Reference('fedale_calendar.ui_settings.resolver', ContainerBuilder::NULL_ON_INVALID_REFERENCE),
                    new Reference(CalendarOptionsResolver::class),
                    new Reference('form.factory', ContainerBuilder::NULL_ON_INVALID_REFERENCE),
                    new Reference('twig'),
                    new Reference('translator'),
                    new Reference('router'),
                ])
                ->addTag('controller.service_arguments');
        }
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

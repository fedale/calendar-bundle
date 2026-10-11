<?php

namespace Fedale\CalendarBundle\Controller;

use Fedale\CalendarBundle\Options\CalendarOptionsResolver;
use Fedale\CalendarBundle\UiSettings\CalendarDescriptor;
use Fedale\CalendarBundle\UiSettings\UiSettingInterface;
use Fedale\CalendarBundle\UiSettings\UiSettingsResolver;
use Fedale\SettingBundle\Exception\SettingValidationException;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Body of the UI settings dialog, loaded and submitted by the cal-ui-settings
 * Stimulus controller: GET renders the form of one scope (`?scope=_global` or
 * `?scope=<calendar id>`), POST saves it and renders it again with a "saved"
 * marker (an invalid submission comes back with a 422 and its errors).
 *
 * Answers 404 while the feature is off: its store not configured in
 * fedale/setting-bundle.
 *
 * The application imports the route with its own prefix:
 * `resource: '@FedaleCalendarBundle/src/Controller/UiSettingsController.php'`,
 * `type: attribute`, `prefix: /calendar/_settings`.
 */
final class UiSettingsController
{
    private const DOMAIN = 'CalendarBundle';

    /**
     * @param UiSettingsResolver|null   $resolver    null when fedale/setting-bundle is not installed and enabled
     * @param FormFactoryInterface|null $formFactory null when the framework's form component is off
     */
    public function __construct(
        private readonly ?UiSettingsResolver $resolver,
        private readonly CalendarOptionsResolver $calendars,
        private readonly ?FormFactoryInterface $formFactory,
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('', name: 'fedale_calendar_ui_settings', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if ($this->resolver === null || $this->formFactory === null || !$this->resolver->isEnabled()) {
            throw new NotFoundHttpException('Calendar UI settings are disabled: they need fedale/setting-bundle with fedale_setting.scoped.store set, and symfony/form.');
        }

        $scope = (string) $request->query->get('scope', UiSettingsResolver::GLOBAL_SCOPE);
        $calendars = $this->calendars->describeAll();
        $calendar = null;
        if ($scope !== UiSettingsResolver::GLOBAL_SCOPE) {
            $calendar = $calendars[$scope] ?? throw new NotFoundHttpException(sprintf('Unknown calendar "%s".', $scope));
        }

        $settings = $this->resolver->settingsFor($calendar);
        $form = $this->form($settings, $calendar, $scope);
        $form->handleRequest($request);
        $saved = $form->isSubmitted() && $form->isValid() && $this->save($form, $scope, $calendar);
        if ($saved) {
            // Rebuild from the store, so "inherit" placeholders reflect the save.
            $form = $this->form($settings, $calendar, $scope);
        }

        $html = $this->twig->render('@FedaleCalendar/ui_settings/_form.html.twig', [
            'form' => $form->createView(),
            'scope' => $scope,
            'scopes' => $this->scopes($calendars),
            'calendar' => $calendar,
            'has_settings' => $settings !== [],
            'saved' => $saved,
        ]);

        return new Response($html, $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }

    /**
     * A value setting-bundle rejects (e.g. a constraint) becomes a form error.
     */
    private function save(FormInterface $form, string $scope, ?CalendarDescriptor $calendar): bool
    {
        try {
            $this->resolver?->save($scope, (array) $form->getData(), $calendar);
        } catch (SettingValidationException $e) {
            $field = $form->has($e->getKey()) ? $form->get($e->getKey()) : $form;
            foreach ($e->getViolationMessages() as $message) {
                $field->addError(new FormError($message));
            }

            return false;
        }

        return true;
    }

    /**
     * One optional choice per setting; the empty choice is "inherit" and names
     * what is inherited.
     *
     * @param array<string, UiSettingInterface> $settings
     */
    private function form(array $settings, ?CalendarDescriptor $calendar, string $scope): FormInterface
    {
        $builder = $this->formFactory?->createNamedBuilder('cal_ui_settings', FormType::class, $this->resolver?->values($scope), [
            'action' => $this->urlGenerator->generate('fedale_calendar_ui_settings', ['scope' => $scope]),
            'translation_domain' => false,
        ]);

        $inherited = $this->resolver?->inherited($scope, $calendar) ?? [];
        foreach ($settings as $key => $setting) {
            $choices = [];
            foreach ($setting->choicesFor($calendar) as $labelKey => $value) {
                $choices[$this->trans($labelKey)] = $value;
            }
            $builder->add($key, ChoiceType::class, [
                'label' => $this->trans($setting->label()),
                'help' => $setting->help() !== null ? $this->trans($setting->help()) : null,
                'choices' => $choices,
                'required' => false,
                'placeholder' => $this->placeholder($setting, $calendar, $inherited),
                'choice_translation_domain' => false,
            ]);
        }

        return $builder->getForm();
    }

    /**
     * @param array<string, mixed> $inherited
     */
    private function placeholder(UiSettingInterface $setting, ?CalendarDescriptor $calendar, array $inherited): string
    {
        $value = $inherited[$setting->key()] ?? null;
        $label = $value === null ? false : array_search($value, $setting->choicesFor($calendar), true);
        if ($label === false) {
            return $this->trans('ui_settings.inherit_code');
        }

        return $this->translator->trans('ui_settings.inherit_value', ['%value%' => $this->trans((string) $label)], self::DOMAIN);
    }

    /**
     * @param array<string, CalendarDescriptor> $calendars
     *
     * @return array<string, string> scope => label, every calendar first
     */
    private function scopes(array $calendars): array
    {
        $scopes = [UiSettingsResolver::GLOBAL_SCOPE => $this->trans('ui_settings.scope_global')];
        foreach ($calendars as $id => $calendar) {
            $scopes[$id] = $calendar->label !== null ? $this->translator->trans($calendar->label) : $id;
        }

        return $scopes;
    }

    private function trans(string $key): string
    {
        return $this->translator->trans($key, [], self::DOMAIN);
    }
}

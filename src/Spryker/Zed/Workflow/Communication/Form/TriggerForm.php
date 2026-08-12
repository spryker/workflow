<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TriggerForm extends AbstractType
{
    /**
     * @var string
     */
    public const FIELD_ID_STATE_MACHINE_PROCESS = 'idStateMachineProcess';

    /**
     * @var string
     */
    public const FIELD_EVENT_NAMES = 'eventNames';

    /**
     * @var string
     */
    public const FIELD_EVENT_NAMES_TO_BE_ADDED = 'eventNamesToBeAdded';

    /**
     * @var string
     */
    public const FIELD_EVENT_NAMES_TO_BE_REMOVED = 'eventNamesToBeRemoved';

    /**
     * @var string
     */
    public const OPTION_AVAILABLE_EVENTS = 'availableEvents';

    /**
     * @var string
     */
    public const TRIGGER_EVENT_NAME = 'eventName';

    /**
     * @var string
     */
    public const TRIGGER_EVENT_LABEL = 'label';

    /**
     * @var string
     */
    public const TRIGGER_EVENT_DESCRIPTION = 'description';

    /**
     * @param array<string, mixed> $options
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(static::FIELD_ID_STATE_MACHINE_PROCESS, HiddenType::class)
            ->add(static::FIELD_EVENT_NAMES, ChoiceType::class, [
                'label' => 'Trigger events',
                'required' => false,
                'multiple' => true,
                'expanded' => false,
                'choices' => $this->buildChoices($options[static::OPTION_AVAILABLE_EVENTS]),
                'choice_attr' => $this->createTooltipChoiceAttributeCallback($options[static::OPTION_AVAILABLE_EVENTS]),
                'attr' => [
                    'class' => 'spryker-form-select2combobox',
                    'data-qa' => 'trigger-events-select',
                ],
                'placeholder' => 'Select trigger events',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            static::OPTION_AVAILABLE_EVENTS => [],
            'allow_extra_fields' => true,
        ]);
    }

    /**
     * The dropdown shows the friendly name (falling back to the technical event name when a plugin omits
     * it); the submitted value stays the technical event name.
     *
     * @param array<string, array<string, string>> $availableEvents Map of event name to its name/label/description.
     *
     * @return array<string, string> Map of choice label to submitted event name value.
     */
    protected function buildChoices(array $availableEvents): array
    {
        $choices = [];
        foreach ($availableEvents as $eventName => $triggerEvent) {
            $label = $triggerEvent[static::TRIGGER_EVENT_LABEL] !== '' ? $triggerEvent[static::TRIGGER_EVENT_LABEL] : $eventName;
            $choices[$label] = $eventName;
        }

        return $choices;
    }

    /**
     * Returns a callback that attaches the technical event name (and description, when present) as a hover
     * tooltip on each option. Symfony invokes it per choice with the submitted value ($choiceValue), which
     * is the technical event name and therefore the key into the available-events map.
     *
     * @param array<string, array<string, string>> $availableEvents Map of event name to its name/label/description.
     *
     * @return callable(string, string, string): array<string, string>
     */
    protected function createTooltipChoiceAttributeCallback(array $availableEvents): callable
    {
        return function ($choiceValue) use ($availableEvents): array {
            if (!isset($availableEvents[$choiceValue])) {
                return [];
            }

            $description = $availableEvents[$choiceValue][static::TRIGGER_EVENT_DESCRIPTION];
            $title = $description !== '' ? sprintf('%s — %s', $choiceValue, $description) : $choiceValue;

            return ['title' => $title];
        };
    }
}

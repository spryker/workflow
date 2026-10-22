<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Length;

/**
 * The event choices are limited to the subject type of the workflow, which cannot change after creation.
 */
class ProcessEditForm extends AbstractType
{
    public const string FIELD_DESCRIPTION = ProcessForm::FIELD_DESCRIPTION;

    public const string FIELD_TRIGGERS = ProcessForm::FIELD_TRIGGERS;

    public const string OPTION_EVENT_CHOICES = ProcessForm::OPTION_EVENT_CHOICES;

    public const string OPTION_EVENT_CHOICE_ATTRIBUTES = ProcessForm::OPTION_EVENT_CHOICE_ATTRIBUTES;

    /**
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(static::FIELD_DESCRIPTION, TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'help' => ProcessForm::HELP_DESCRIPTION,
                'help_translation_parameters' => [ProcessForm::HELP_DESCRIPTION_PARAMETER_MAX => ProcessForm::DESCRIPTION_MAX_LENGTH],
                'attr' => [
                    'class' => 'js-workflow-process-edit-description',
                    'maxlength' => ProcessForm::DESCRIPTION_MAX_LENGTH,
                    'data-qa' => 'workflow-process-edit-description-input',
                ],
                'constraints' => [new Length(max: ProcessForm::DESCRIPTION_MAX_LENGTH)],
            ])
            ->add(static::FIELD_TRIGGERS, ChoiceType::class, [
                'label' => 'Triggers',
                'multiple' => true,
                'expanded' => false,
                'choices' => $options[static::OPTION_EVENT_CHOICES],
                'choice_attr' => $options[static::OPTION_EVENT_CHOICE_ATTRIBUTES],
                'attr' => [
                    'class' => 'spryker-form-select2combobox js-workflow-process-edit-trigger-events',
                    'style' => 'width: 100%',
                    'data-qa' => 'workflow-process-edit-trigger-events-select',
                ],
                'placeholder' => 'Select trigger events',
                'constraints' => [
                    new Count(min: 1, minMessage: ProcessForm::MESSAGE_TRIGGERS_REQUIRED),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            static::OPTION_EVENT_CHOICES => [],
            static::OPTION_EVENT_CHOICE_ATTRIBUTES => [],
        ]);
    }
}

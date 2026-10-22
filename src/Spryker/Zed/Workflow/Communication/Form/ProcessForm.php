<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ProcessForm extends AbstractType
{
    public const string FIELD_NAME = 'name';

    protected const string PATTERN_NAME = '/^[A-Za-z0-9 _-]+$/';

    protected const string MESSAGE_NAME_PATTERN = 'The name may only contain letters, numbers, spaces, hyphens and underscores.';

    public const string FIELD_SUBJECT_TYPE = 'subjectType';

    public const string FIELD_DESCRIPTION = 'description';

    public const string FIELD_TRIGGERS = 'triggers';

    /**
     * @var positive-int
     */
    public const int DESCRIPTION_MAX_LENGTH = 255;

    public const string HELP_DESCRIPTION = 'Up to %max% characters.';

    public const string HELP_DESCRIPTION_PARAMETER_MAX = '%max%';

    public const string FIELD_STATE_MACHINE_DEFINITION = 'stateMachineDefinition';

    public const string OPTION_SUBJECT_TYPES = 'subjectTypes';

    public const string OPTION_EVENT_CHOICES = 'eventChoices';

    public const string OPTION_EVENT_CHOICE_ATTRIBUTES = 'eventChoiceAttributes';

    public const string OPTION_EVENT_SUBJECT_TYPES = 'eventSubjectTypes';

    public const string MESSAGE_TRIGGERS_REQUIRED = 'Select at least one trigger.';

    protected const string MESSAGE_EVENT_SUBJECT_TYPE_MISMATCH = 'Select only triggers of the selected subject type.';

    /**
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addProcessFields($builder, $options);
        $this->addTriggersField($builder, $options);
        $this->addStateMachineDefinitionField($builder);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            static::OPTION_SUBJECT_TYPES => [],
            static::OPTION_EVENT_CHOICES => [],
            static::OPTION_EVENT_CHOICE_ATTRIBUTES => [],
            static::OPTION_EVENT_SUBJECT_TYPES => [],
        ]);
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function addProcessFields(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(static::FIELD_NAME, TextType::class, [
                'label' => 'Name',
                'attr' => [
                    'class' => 'js-workflow-process-name',
                    'data-qa' => 'workflow-process-name-input',
                ],
                'constraints' => [
                    new NotBlank(),
                    new Regex([
                        'pattern' => static::PATTERN_NAME,
                        'message' => static::MESSAGE_NAME_PATTERN,
                    ]),
                ],
            ])
            ->add(static::FIELD_SUBJECT_TYPE, ChoiceType::class, [
                'label' => 'Subject type',
                'choices' => $options[static::OPTION_SUBJECT_TYPES],
                'placeholder' => 'Select subject type',
                'attr' => [
                    'class' => 'spryker-form-select2combobox js-workflow-process-subject-type',
                    'data-qa' => 'workflow-process-subject-type-input',
                ],
                'constraints' => [new NotBlank()],
            ])
            ->add(static::FIELD_DESCRIPTION, TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'help' => static::HELP_DESCRIPTION,
                'help_translation_parameters' => [static::HELP_DESCRIPTION_PARAMETER_MAX => static::DESCRIPTION_MAX_LENGTH],
                'attr' => [
                    'class' => 'js-workflow-process-description',
                    'maxlength' => static::DESCRIPTION_MAX_LENGTH,
                ],
                'constraints' => [new Length(max: static::DESCRIPTION_MAX_LENGTH)],
            ]);
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function addTriggersField(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(static::FIELD_TRIGGERS, ChoiceType::class, [
            'label' => 'Triggers',
            'multiple' => true,
            'expanded' => false,
            'choices' => $options[static::OPTION_EVENT_CHOICES],
            'choice_attr' => $options[static::OPTION_EVENT_CHOICE_ATTRIBUTES],
            'attr' => [
                'class' => 'spryker-form-select2combobox js-workflow-process-trigger-events',
                'data-qa' => 'workflow-process-trigger-events-select',
            ],
            'placeholder' => 'Select trigger events',
            'constraints' => [
                new Count([
                    'min' => 1,
                    'minMessage' => static::MESSAGE_TRIGGERS_REQUIRED,
                ]),
                $this->createEventSubjectTypeConstraint($options[static::OPTION_EVENT_SUBJECT_TYPES]),
            ],
            'help' => 'Please select a Subject Type before choosing a Trigger.',
        ]);
    }

    /**
     * @param array<string, string> $eventSubjectTypes
     */
    protected function createEventSubjectTypeConstraint(array $eventSubjectTypes): Callback
    {
        return new Callback(function (array $eventNames, ExecutionContextInterface $context) use ($eventSubjectTypes): void {
            $subjectType = $context->getRoot()->get(static::FIELD_SUBJECT_TYPE)->getData();

            if ($subjectType === null || $this->hasEventsOfSubjectTypeOnly($eventNames, $subjectType, $eventSubjectTypes)) {
                return;
            }

            $context->addViolation(static::MESSAGE_EVENT_SUBJECT_TYPE_MISMATCH);
        });
    }

    /**
     * @param array<string> $eventNames
     * @param array<string, string> $eventSubjectTypes
     */
    protected function hasEventsOfSubjectTypeOnly(array $eventNames, string $subjectType, array $eventSubjectTypes): bool
    {
        foreach ($eventNames as $eventName) {
            if (($eventSubjectTypes[$eventName] ?? null) !== $subjectType) {
                return false;
            }
        }

        return true;
    }

    protected function addStateMachineDefinitionField(FormBuilderInterface $builder): void
    {
        $builder->add(static::FIELD_STATE_MACHINE_DEFINITION, StateMachineDefinitionForm::class, [
            'label' => false,
            'error_bubbling' => true,
        ]);
    }
}

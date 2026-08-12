<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class VersionForm extends AbstractType
{
    /**
     * @var string
     */
    public const FORM_NAME = 'version_form';

    /**
     * @var string
     */
    public const FIELD_ID_STATE_MACHINE_PROCESS = 'idStateMachineProcess';

    /**
     * @var string
     */
    public const FIELD_INITIAL_STATE = 'initialState';

    /**
     * @var string
     */
    public const FIELD_DEFINITION = 'definition';

    /**
     * @param array<string, mixed> $options
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(static::FIELD_ID_STATE_MACHINE_PROCESS, HiddenType::class)
            ->add(static::FIELD_INITIAL_STATE, TextType::class, [
                'label' => 'Initial state',
                'attr' => ['data-qa' => 'workflow-version-initial-state-input'],
                'constraints' => [new NotBlank()],
            ])
            ->add(static::FIELD_DEFINITION, TextareaType::class, [
                'label' => 'Definition (state-machine-01 XML)',
                'constraints' => [new NotBlank()],
                'attr' => ['rows' => 20, 'data-qa' => 'workflow-version-definition-input'],
            ]);
    }

    public function getBlockPrefix(): string
    {
        return static::FORM_NAME;
    }
}

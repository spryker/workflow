<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

class VersionForm extends AbstractType
{
    public const string FORM_NAME = 'version_form';

    public const string FIELD_ID_STATE_MACHINE_PROCESS = 'idStateMachineProcess';

    public const string FIELD_STATE_MACHINE_DEFINITION = 'stateMachineDefinition';

    /**
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(static::FIELD_ID_STATE_MACHINE_PROCESS, HiddenType::class)
            ->add(static::FIELD_STATE_MACHINE_DEFINITION, StateMachineDefinitionForm::class, [
                'label' => false,
                'error_bubbling' => true,
            ]);
    }

    public function getBlockPrefix(): string
    {
        return static::FORM_NAME;
    }
}

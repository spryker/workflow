<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class StateMachineDefinitionForm extends AbstractType
{
    public const string FIELD_INITIAL_STATE = 'initialState';

    public const string FIELD_DEFINITION = 'definition';

    protected const string MESSAGE_INITIAL_STATE_REQUIRED = 'The initial state is not specified. Mark one state as the initial state.';

    protected const string MESSAGE_DEFINITION_REQUIRED = 'Add at least one state.';

    /**
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(static::FIELD_INITIAL_STATE, HiddenType::class, [
                'constraints' => [new NotBlank(['message' => static::MESSAGE_INITIAL_STATE_REQUIRED])],
            ])
            ->add(static::FIELD_DEFINITION, HiddenType::class, [
                'constraints' => [new NotBlank(['message' => static::MESSAGE_DEFINITION_REQUIRED])],
            ]);
    }
}

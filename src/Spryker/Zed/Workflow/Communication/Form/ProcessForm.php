<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ProcessForm extends AbstractType
{
    /**
     * A workflow is one process (process === state machine), so a single name identifies both.
     *
     * @var string
     */
    public const FIELD_NAME = 'name';

    /**
     * Allows letters, digits, spaces, hyphens and underscores only.
     *
     * @var string
     */
    protected const PATTERN_NAME = '/^[A-Za-z0-9 _-]+$/';

    /**
     * @var string
     */
    protected const MESSAGE_NAME_PATTERN = 'The name may only contain letters, numbers, spaces, hyphens and underscores.';

    /**
     * @var string
     */
    public const FIELD_SUBJECT_TYPE = 'subjectType';

    /**
     * @var string
     */
    public const FIELD_DESCRIPTION = 'description';

    /**
     * @param array<string, mixed> $options
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(static::FIELD_NAME, TextType::class, [
                'label' => 'Name',
                'attr' => ['data-qa' => 'workflow-process-name-input'],
                'constraints' => [
                    new NotBlank(),
                    new Regex([
                        'pattern' => static::PATTERN_NAME,
                        'message' => static::MESSAGE_NAME_PATTERN,
                    ]),
                ],
            ])
            ->add(static::FIELD_SUBJECT_TYPE, TextType::class, [
                'label' => 'Subject type',
                'attr' => ['data-qa' => 'workflow-process-subject-type-input'],
                'constraints' => [new NotBlank()],
            ])
            ->add(static::FIELD_DESCRIPTION, TextareaType::class, [
                'label' => 'Description',
                'required' => false,
            ]);
    }
}

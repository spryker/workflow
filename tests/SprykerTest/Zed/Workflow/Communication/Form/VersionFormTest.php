<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Communication\Form;

use Codeception\Test\Unit;
use Spryker\Zed\Workflow\Communication\Form\StateMachineDefinitionForm;
use Spryker\Zed\Workflow\Communication\Form\VersionForm;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group Workflow
 * @group Communication
 * @group Form
 * @group VersionFormTest
 * Add your own group annotations below this line
 */
class VersionFormTest extends Unit
{
    protected FormFactoryInterface $formFactory;

    protected function _before(): void
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $this->formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension($validator))
            ->getFormFactory();
    }

    public function testVersionFormIsValidWhenTheStateMachineDefinitionIsProvided(): void
    {
        // Arrange
        $form = $this->formFactory->create(VersionForm::class);

        // Act
        $form->submit($this->createVersionFormData('new', '<statemachine/>'));

        // Assert
        $this->assertTrue($form->isValid());
    }

    public function testVersionFormIsInvalidWhenInitialStateIsEmpty(): void
    {
        // Arrange
        $form = $this->formFactory->create(VersionForm::class);

        // Act
        $form->submit($this->createVersionFormData('', '<statemachine/>'));

        // Assert
        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->getErrors());
    }

    public function testVersionFormIsInvalidWhenDefinitionIsEmpty(): void
    {
        // Arrange
        $form = $this->formFactory->create(VersionForm::class);

        // Act
        $form->submit($this->createVersionFormData('new', ''));

        // Assert
        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->getErrors());
    }

    /**
     * @return array<string, mixed>
     */
    protected function createVersionFormData(string $initialState, string $definition): array
    {
        return [
            VersionForm::FIELD_ID_STATE_MACHINE_PROCESS => '1',
            VersionForm::FIELD_STATE_MACHINE_DEFINITION => [
                StateMachineDefinitionForm::FIELD_INITIAL_STATE => $initialState,
                StateMachineDefinitionForm::FIELD_DEFINITION => $definition,
            ],
        ];
    }
}

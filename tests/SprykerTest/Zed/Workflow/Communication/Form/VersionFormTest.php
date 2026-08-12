<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Communication\Form;

use Codeception\Test\Unit;
use Spryker\Zed\Workflow\Communication\Form\VersionForm;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

/**
 * Verifies that the Back Office version create form rejects empty required fields (Initial state and the
 * Definition XML). Uses a self-contained Symfony form factory with the validator extension so the NotBlank
 * constraints are exercised without the full Zed form application.
 *
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

    public function testVersionFormIsInvalidWhenInitialStateIsEmpty(): void
    {
        // Arrange
        $form = $this->formFactory->create(VersionForm::class);

        // Act
        $form->submit([
            VersionForm::FIELD_ID_STATE_MACHINE_PROCESS => '1',
            VersionForm::FIELD_INITIAL_STATE => '',
            VersionForm::FIELD_DEFINITION => '<statemachine/>',
        ]);

        // Assert
        $this->assertFalse($form->isValid());
        $this->assertGreaterThan(0, $form->get(VersionForm::FIELD_INITIAL_STATE)->getErrors()->count());
    }

    public function testVersionFormIsInvalidWhenDefinitionIsEmpty(): void
    {
        // Arrange
        $form = $this->formFactory->create(VersionForm::class);

        // Act
        $form->submit([
            VersionForm::FIELD_ID_STATE_MACHINE_PROCESS => '1',
            VersionForm::FIELD_INITIAL_STATE => 'new',
            VersionForm::FIELD_DEFINITION => '',
        ]);

        // Assert
        $this->assertFalse($form->isValid());
        $this->assertGreaterThan(0, $form->get(VersionForm::FIELD_DEFINITION)->getErrors()->count());
    }
}

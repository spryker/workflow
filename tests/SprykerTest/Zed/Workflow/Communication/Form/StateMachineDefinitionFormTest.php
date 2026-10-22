<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Communication\Form;

use Codeception\Test\Unit;
use Spryker\Zed\Workflow\Communication\Form\StateMachineDefinitionForm;
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
 * @group StateMachineDefinitionFormTest
 * Add your own group annotations below this line
 */
class StateMachineDefinitionFormTest extends Unit
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

    public function testStateMachineDefinitionFormReportsBothMessagesWhenNothingIsSubmitted(): void
    {
        // Arrange
        $form = $this->formFactory->create(StateMachineDefinitionForm::class);

        // Act
        $form->submit([]);

        // Assert
        $this->assertFalse($form->isValid());
        $this->assertSame(
            ['The initial state is not specified. Mark one state as the initial state.', 'Add at least one state.'],
            array_map(static fn ($formError): string => $formError->getMessage(), iterator_to_array($form->getErrors(true))),
        );
    }
}

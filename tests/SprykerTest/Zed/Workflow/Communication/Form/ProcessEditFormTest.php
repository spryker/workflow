<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Communication\Form;

use Codeception\Test\Unit;
use Spryker\Zed\Workflow\Communication\Form\ProcessEditForm;
use Spryker\Zed\Workflow\Communication\Form\ProcessForm;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
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
 * @group ProcessEditFormTest
 * Add your own group annotations below this line
 */
class ProcessEditFormTest extends Unit
{
    protected const string EVENT_NAME = 'Entity.spy_customer.create';

    protected const string SECOND_EVENT_NAME = 'Entity.spy_customer.update';

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

    public function testProcessEditFormIsValidWhenAllFieldsAreProvided(): void
    {
        // Arrange
        $form = $this->createProcessEditForm();

        // Act
        $form->submit($this->createValidFormData());

        // Assert
        $this->assertTrue($form->isValid());
        $this->assertSame([static::EVENT_NAME, static::SECOND_EVENT_NAME], $form->get(ProcessEditForm::FIELD_TRIGGERS)->getData());
    }

    public function testProcessEditFormIsValidWhenDescriptionIsEmpty(): void
    {
        // Arrange
        $form = $this->createProcessEditForm();

        // Act
        $form->submit($this->createValidFormData([ProcessEditForm::FIELD_DESCRIPTION => '']));

        // Assert
        $this->assertTrue($form->isValid());
    }

    public function testProcessEditFormIsInvalidWhenDescriptionIsTooLong(): void
    {
        // Arrange
        $form = $this->createProcessEditForm();

        // Act
        $form->submit($this->createValidFormData([ProcessEditForm::FIELD_DESCRIPTION => str_repeat('a', ProcessForm::DESCRIPTION_MAX_LENGTH + 1)]));

        // Assert
        $this->assertFalse($form->isValid());
        $this->assertGreaterThan(0, $form->get(ProcessEditForm::FIELD_DESCRIPTION)->getErrors()->count());
    }

    public function testProcessEditFormIsInvalidWhenNoTriggerIsSelected(): void
    {
        // Arrange
        $form = $this->createProcessEditForm();

        // Act
        $form->submit($this->createValidFormData([ProcessEditForm::FIELD_TRIGGERS => []]));

        // Assert
        $this->assertFalse($form->isValid());
        $this->assertGreaterThan(0, $form->get(ProcessEditForm::FIELD_TRIGGERS)->getErrors()->count());
    }

    public function testProcessEditFormIsInvalidWhenTriggerIsNotAvailable(): void
    {
        // Arrange
        $form = $this->createProcessEditForm();

        // Act
        $form->submit($this->createValidFormData([ProcessEditForm::FIELD_TRIGGERS => ['Entity.spy_company.create']]));

        // Assert
        $this->assertFalse($form->isValid());
        $this->assertGreaterThan(0, $form->get(ProcessEditForm::FIELD_TRIGGERS)->getErrors()->count());
    }

    protected function createProcessEditForm(): FormInterface
    {
        return $this->formFactory->create(ProcessEditForm::class, null, [
            ProcessEditForm::OPTION_EVENT_CHOICES => [
                'Customer created' => static::EVENT_NAME,
                'Customer updated' => static::SECOND_EVENT_NAME,
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    protected function createValidFormData(array $overrides = []): array
    {
        return $overrides + [
            ProcessEditForm::FIELD_DESCRIPTION => 'Onboards new customers.',
            ProcessEditForm::FIELD_TRIGGERS => [static::EVENT_NAME, static::SECOND_EVENT_NAME],
        ];
    }
}

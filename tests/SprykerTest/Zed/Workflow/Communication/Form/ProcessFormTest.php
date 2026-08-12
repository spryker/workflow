<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Communication\Form;

use Codeception\Test\Unit;
use Spryker\Zed\Workflow\Communication\Form\ProcessForm;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

/**
 * Verifies that the Back Office process create form rejects empty required fields (Name and Subject type).
 * Uses a self-contained Symfony form factory with the validator extension so the NotBlank constraints are
 * exercised without the full Zed form application.
 *
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group Workflow
 * @group Communication
 * @group Form
 * @group ProcessFormTest
 * Add your own group annotations below this line
 */
class ProcessFormTest extends Unit
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

    public function testProcessFormIsInvalidWhenNameIsEmpty(): void
    {
        // Arrange
        $form = $this->formFactory->create(ProcessForm::class);

        // Act
        $form->submit([
            ProcessForm::FIELD_NAME => '',
            ProcessForm::FIELD_SUBJECT_TYPE => 'spy_customer',
        ]);

        // Assert
        $this->assertFalse($form->isValid());
        $this->assertGreaterThan(0, $form->get(ProcessForm::FIELD_NAME)->getErrors()->count());
    }

    public function testProcessFormIsInvalidWhenSubjectTypeIsEmpty(): void
    {
        // Arrange
        $form = $this->formFactory->create(ProcessForm::class);

        // Act
        $form->submit([
            ProcessForm::FIELD_NAME => 'my-workflow',
            ProcessForm::FIELD_SUBJECT_TYPE => '',
        ]);

        // Assert
        $this->assertFalse($form->isValid());
        $this->assertGreaterThan(0, $form->get(ProcessForm::FIELD_SUBJECT_TYPE)->getErrors()->count());
    }

    /**
     * @dataProvider validNameProvider
     */
    public function testProcessFormAcceptsAllowedNameCharacters(string $name): void
    {
        // Arrange
        $form = $this->formFactory->create(ProcessForm::class);

        // Act
        $form->submit([
            ProcessForm::FIELD_NAME => $name,
            ProcessForm::FIELD_SUBJECT_TYPE => 'spy_customer',
        ]);

        // Assert
        $this->assertTrue($form->isValid());
        $this->assertCount(0, $form->get(ProcessForm::FIELD_NAME)->getErrors());
    }

    /**
     * @dataProvider invalidNameProvider
     */
    public function testProcessFormRejectsDisallowedNameCharacters(string $name): void
    {
        // Arrange
        $form = $this->formFactory->create(ProcessForm::class);

        // Act
        $form->submit([
            ProcessForm::FIELD_NAME => $name,
            ProcessForm::FIELD_SUBJECT_TYPE => 'spy_customer',
        ]);

        // Assert
        $this->assertFalse($form->isValid());
        $this->assertGreaterThan(0, $form->get(ProcessForm::FIELD_NAME)->getErrors()->count());
    }

    /**
     * @return array<string, array<string>>
     */
    public function validNameProvider(): array
    {
        return [
            'alphanumeric' => ['CompanyOnboarding123'],
            'with spaces' => ['Customer onboarding demo'],
            'with hyphen' => ['customer-onboarding'],
            'with underscore' => ['customer_onboarding'],
            'mixed allowed' => ['B2B Onboarding_v2 - demo'],
        ];
    }

    /**
     * @return array<string, array<string>>
     */
    public function invalidNameProvider(): array
    {
        return [
            'slash' => ['customer/onboarding'],
            'at sign' => ['customer@onboarding'],
            'percent' => ['customer%onboarding'],
            'dot' => ['customer.onboarding'],
            'parenthesis' => ['Onboarding (demo)'],
            'quote' => ['Onboarding"demo'],
        ];
    }
}

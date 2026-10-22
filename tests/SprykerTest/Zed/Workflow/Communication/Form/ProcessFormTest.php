<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Communication\Form;

use Codeception\Test\Unit;
use Spryker\Zed\Workflow\Communication\Form\ProcessForm;
use Spryker\Zed\Workflow\Communication\Form\StateMachineDefinitionForm;
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
 * @group ProcessFormTest
 * Add your own group annotations below this line
 */
class ProcessFormTest extends Unit
{
    protected const string SUBJECT_TYPE = 'spy_customer';

    protected const string EVENT_NAME = 'Entity.spy_customer.create';

    protected const string OTHER_SUBJECT_TYPE = 'spy_company';

    protected const string OTHER_SUBJECT_TYPE_EVENT_NAME = 'Entity.spy_company.create';

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

    public function testProcessFormIsValidWhenAllFieldsAreProvided(): void
    {
        // Arrange
        $form = $this->createProcessForm();

        // Act
        $form->submit($this->createValidFormData());

        // Assert
        $this->assertTrue($form->isValid());
    }

    public function testProcessFormIsInvalidWhenNameIsEmpty(): void
    {
        // Arrange
        $form = $this->createProcessForm();

        // Act
        $form->submit($this->createValidFormData([ProcessForm::FIELD_NAME => '']));

        // Assert
        $this->assertFalse($form->isValid());
        $this->assertGreaterThan(0, $form->get(ProcessForm::FIELD_NAME)->getErrors()->count());
    }

    public function testProcessFormIsInvalidWhenSubjectTypeIsEmpty(): void
    {
        // Arrange
        $form = $this->createProcessForm();

        // Act
        $form->submit($this->createValidFormData([ProcessForm::FIELD_SUBJECT_TYPE => '']));

        // Assert
        $this->assertFalse($form->isValid());
        $this->assertGreaterThan(0, $form->get(ProcessForm::FIELD_SUBJECT_TYPE)->getErrors()->count());
    }

    public function testProcessFormIsInvalidWhenSubjectTypeIsNotRegistered(): void
    {
        // Arrange
        $form = $this->createProcessForm();

        // Act
        $form->submit($this->createValidFormData([ProcessForm::FIELD_SUBJECT_TYPE => 'unregistered_subject_type']));

        // Assert
        $this->assertFalse($form->isValid());
        $this->assertGreaterThan(0, $form->get(ProcessForm::FIELD_SUBJECT_TYPE)->getErrors()->count());
    }

    public function testProcessFormIsInvalidWhenNoTriggerIsSelected(): void
    {
        // Arrange
        $form = $this->createProcessForm();

        // Act
        $form->submit($this->createValidFormData([ProcessForm::FIELD_TRIGGERS => []]));

        // Assert
        $this->assertFalse($form->isValid());
        $this->assertGreaterThan(0, $form->get(ProcessForm::FIELD_TRIGGERS)->getErrors()->count());
    }

    public function testProcessFormIsInvalidWhenTriggerIsNotRegistered(): void
    {
        // Arrange
        $form = $this->createProcessForm();

        // Act
        $form->submit($this->createValidFormData([ProcessForm::FIELD_TRIGGERS => ['Entity.unregistered.create']]));

        // Assert
        $this->assertFalse($form->isValid());
        $this->assertGreaterThan(0, $form->get(ProcessForm::FIELD_TRIGGERS)->getErrors()->count());
    }

    public function testProcessFormIsInvalidWhenTriggerBelongsToAnotherSubjectType(): void
    {
        // Arrange
        $form = $this->createProcessForm();

        // Act
        $form->submit($this->createValidFormData([ProcessForm::FIELD_TRIGGERS => [static::EVENT_NAME, static::OTHER_SUBJECT_TYPE_EVENT_NAME]]));

        // Assert
        $this->assertFalse($form->isValid());
        $this->assertGreaterThan(0, $form->get(ProcessForm::FIELD_TRIGGERS)->getErrors()->count());
    }

    public function testProcessFormIsInvalidWhenDescriptionIsTooLong(): void
    {
        // Arrange
        $form = $this->createProcessForm();

        // Act
        $form->submit($this->createValidFormData([ProcessForm::FIELD_DESCRIPTION => str_repeat('a', ProcessForm::DESCRIPTION_MAX_LENGTH + 1)]));

        // Assert
        $this->assertFalse($form->isValid());
        $this->assertGreaterThan(0, $form->get(ProcessForm::FIELD_DESCRIPTION)->getErrors()->count());
    }

    public function testProcessFormIsValidWhenDescriptionHasMaximumLength(): void
    {
        // Arrange
        $form = $this->createProcessForm();

        // Act
        $form->submit($this->createValidFormData([ProcessForm::FIELD_DESCRIPTION => str_repeat('a', ProcessForm::DESCRIPTION_MAX_LENGTH)]));

        // Assert
        $this->assertTrue($form->isValid());
    }

    public function testProcessFormIsInvalidWhenInitialStateIsEmpty(): void
    {
        // Arrange
        $form = $this->createProcessForm();

        // Act
        $form->submit($this->createValidFormData([ProcessForm::FIELD_STATE_MACHINE_DEFINITION => $this->createStateMachineDefinitionFormData('', '<statemachine/>')]));

        // Assert
        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->getErrors());
    }

    public function testProcessFormIsInvalidWhenDefinitionIsEmpty(): void
    {
        // Arrange
        $form = $this->createProcessForm();

        // Act
        $form->submit($this->createValidFormData([ProcessForm::FIELD_STATE_MACHINE_DEFINITION => $this->createStateMachineDefinitionFormData('new', '')]));

        // Assert
        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->getErrors());
    }

    /**
     * @dataProvider validNameProvider
     */
    public function testProcessFormAcceptsAllowedNameCharacters(string $name): void
    {
        // Arrange
        $form = $this->createProcessForm();

        // Act
        $form->submit($this->createValidFormData([ProcessForm::FIELD_NAME => $name]));

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
        $form = $this->createProcessForm();

        // Act
        $form->submit($this->createValidFormData([ProcessForm::FIELD_NAME => $name]));

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

    protected function createProcessForm(): FormInterface
    {
        return $this->formFactory->create(ProcessForm::class, null, [
            ProcessForm::OPTION_SUBJECT_TYPES => [
                static::SUBJECT_TYPE => static::SUBJECT_TYPE,
                static::OTHER_SUBJECT_TYPE => static::OTHER_SUBJECT_TYPE,
            ],
            ProcessForm::OPTION_EVENT_CHOICES => [
                'Customer created' => static::EVENT_NAME,
                'Company created' => static::OTHER_SUBJECT_TYPE_EVENT_NAME,
            ],
            ProcessForm::OPTION_EVENT_SUBJECT_TYPES => [
                static::EVENT_NAME => static::SUBJECT_TYPE,
                static::OTHER_SUBJECT_TYPE_EVENT_NAME => static::OTHER_SUBJECT_TYPE,
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
            ProcessForm::FIELD_NAME => 'my-workflow',
            ProcessForm::FIELD_SUBJECT_TYPE => static::SUBJECT_TYPE,
            ProcessForm::FIELD_TRIGGERS => [static::EVENT_NAME],
            ProcessForm::FIELD_STATE_MACHINE_DEFINITION => $this->createStateMachineDefinitionFormData('new', '<statemachine/>'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function createStateMachineDefinitionFormData(string $initialState, string $definition): array
    {
        return [
            StateMachineDefinitionForm::FIELD_INITIAL_STATE => $initialState,
            StateMachineDefinitionForm::FIELD_DEFINITION => $definition,
        ];
    }
}

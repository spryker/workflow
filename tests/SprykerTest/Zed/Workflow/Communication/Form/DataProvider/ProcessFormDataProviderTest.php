<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Communication\Form\DataProvider;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\StateMachineTriggerEventCollectionTransfer;
use Generated\Shared\Transfer\StateMachineTriggerEventTransfer;
use Spryker\Zed\Workflow\Business\WorkflowFacadeInterface;
use Spryker\Zed\Workflow\Communication\Form\DataProvider\ProcessFormDataProvider;
use Spryker\Zed\Workflow\Communication\Form\ProcessForm;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group Workflow
 * @group Communication
 * @group Form
 * @group DataProvider
 * @group ProcessFormDataProviderTest
 * Add your own group annotations below this line
 */
class ProcessFormDataProviderTest extends Unit
{
    protected const string SUBJECT_TYPE = 'spy_merchant';

    protected const string EVENT_NAME = 'Entity.spy_merchant.create';

    protected const string EVENT_LABEL = 'Merchant created';

    protected const string EVENT_DESCRIPTION = 'Starts when a merchant is created.';

    protected const string OTHER_SUBJECT_TYPE = 'spy_company';

    protected const string UNLABELED_EVENT_NAME = 'Entity.spy_company.create';

    public function testGetOptionsUsesTheNameAsChoiceKey(): void
    {
        // Arrange
        $processFormDataProvider = $this->createProcessFormDataProvider();

        // Act
        $options = $processFormDataProvider->getOptions();

        // Assert
        $this->assertSame([
            static::EVENT_LABEL => static::EVENT_NAME,
            static::UNLABELED_EVENT_NAME => static::UNLABELED_EVENT_NAME,
        ], $options[ProcessForm::OPTION_EVENT_CHOICES]);
    }

    public function testGetOptionsAddsTheTooltipAndTheSubjectTypeToEveryEventChoice(): void
    {
        // Arrange
        $processFormDataProvider = $this->createProcessFormDataProvider();

        // Act
        $options = $processFormDataProvider->getOptions();

        // Assert
        $this->assertSame([
            static::EVENT_LABEL => [
                'title' => static::EVENT_NAME . ' — ' . static::EVENT_DESCRIPTION,
                'data-subject-type' => static::SUBJECT_TYPE,
            ],
            static::UNLABELED_EVENT_NAME => [
                'title' => static::UNLABELED_EVENT_NAME,
                'data-subject-type' => static::OTHER_SUBJECT_TYPE,
            ],
        ], $options[ProcessForm::OPTION_EVENT_CHOICE_ATTRIBUTES]);
    }

    public function testGetOptionsReturnsTheSubjectTypesOfTheAvailableEvents(): void
    {
        // Arrange
        $processFormDataProvider = $this->createProcessFormDataProvider();

        // Act
        $options = $processFormDataProvider->getOptions();

        // Assert
        $this->assertSame([
            static::SUBJECT_TYPE => static::SUBJECT_TYPE,
            static::OTHER_SUBJECT_TYPE => static::OTHER_SUBJECT_TYPE,
        ], $options[ProcessForm::OPTION_SUBJECT_TYPES]);
        $this->assertSame([
            static::EVENT_NAME => static::SUBJECT_TYPE,
            static::UNLABELED_EVENT_NAME => static::OTHER_SUBJECT_TYPE,
        ], $options[ProcessForm::OPTION_EVENT_SUBJECT_TYPES]);
    }

    protected function createProcessFormDataProvider(): ProcessFormDataProvider
    {
        $stateMachineTriggerEventCollectionTransfer = (new StateMachineTriggerEventCollectionTransfer())
            ->addStateMachineTriggerEvent(
                (new StateMachineTriggerEventTransfer())
                    ->setEventName(static::EVENT_NAME)
                    ->setName(static::EVENT_LABEL)
                    ->setDescription(static::EVENT_DESCRIPTION)
                    ->setSubjectType(static::SUBJECT_TYPE),
            )
            ->addStateMachineTriggerEvent(
                (new StateMachineTriggerEventTransfer())
                    ->setEventName(static::UNLABELED_EVENT_NAME)
                    ->setName(static::UNLABELED_EVENT_NAME)
                    ->setSubjectType(static::OTHER_SUBJECT_TYPE),
            );

        $workflowFacadeMock = $this->createMock(WorkflowFacadeInterface::class);
        $workflowFacadeMock->method('getStateMachineTriggerEventCollection')
            ->willReturn($stateMachineTriggerEventCollectionTransfer);

        return new ProcessFormDataProvider($workflowFacadeMock);
    }
}

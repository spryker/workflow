<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Business;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\StateMachineItemTransfer;
use Generated\Shared\Transfer\StateMachineTransitionHandlerConditionsTransfer;
use Generated\Shared\Transfer\StateMachineTransitionHandlerCriteriaTransfer;
use Spryker\Zed\Workflow\Dependency\Plugin\WorkflowCommandPluginInterface;
use Spryker\Zed\Workflow\Dependency\Plugin\WorkflowConditionPluginInterface;
use Spryker\Zed\Workflow\WorkflowDependencyProvider;
use SprykerTest\Zed\Workflow\Helper\WorkflowHelper;
use SprykerTest\Zed\Workflow\WorkflowBusinessTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group Workflow
 * @group Business
 * @group StateMachineTransitionHandlerCollectionTest
 * Add your own group annotations below this line
 */
class StateMachineTransitionHandlerCollectionTest extends Unit
{
    /**
     * @var string
     */
    public const COMMAND_NAME = 'Send confirmation email';

    /**
     * @var string
     */
    public const CONDITION_NAME = 'Is customer verified';

    protected WorkflowBusinessTester $tester;

    public function testGetStateMachineTransitionHandlerCollectionReturnsPluginNamesForSubjectType(): void
    {
        // Arrange
        $this->tester->setDependency(WorkflowDependencyProvider::PLUGINS_COMMAND, [$this->createCommandPlugin()]);
        $this->tester->setDependency(WorkflowDependencyProvider::PLUGINS_CONDITION, [$this->createConditionPlugin()]);

        // Act
        $stateMachineTransitionHandlerCollectionTransfer = $this->tester->getFacade()->getStateMachineTransitionHandlerCollection(
            (new StateMachineTransitionHandlerCriteriaTransfer())
                ->setStateMachineTransitionHandlerConditions(
                    (new StateMachineTransitionHandlerConditionsTransfer())->setSubjectType(WorkflowHelper::SUBJECT_TYPE),
                ),
        );

        // Assert
        $this->assertSame([static::COMMAND_NAME], $stateMachineTransitionHandlerCollectionTransfer->getCommandNames());
        $this->assertSame([static::CONDITION_NAME], $stateMachineTransitionHandlerCollectionTransfer->getConditionNames());
    }

    public function testGetStateMachineTransitionHandlerCollectionSkipsPluginsOfOtherSubjectTypes(): void
    {
        // Arrange
        $this->tester->setDependency(WorkflowDependencyProvider::PLUGINS_COMMAND, [$this->createCommandPlugin('some_other_subject_type')]);
        $this->tester->setDependency(WorkflowDependencyProvider::PLUGINS_CONDITION, [$this->createConditionPlugin('some_other_subject_type')]);

        // Act
        $stateMachineTransitionHandlerCollectionTransfer = $this->tester->getFacade()->getStateMachineTransitionHandlerCollection(
            (new StateMachineTransitionHandlerCriteriaTransfer())
                ->setStateMachineTransitionHandlerConditions(
                    (new StateMachineTransitionHandlerConditionsTransfer())->setSubjectType(WorkflowHelper::SUBJECT_TYPE),
                ),
        );

        // Assert
        $this->assertCount(0, $stateMachineTransitionHandlerCollectionTransfer->getCommandNames());
        $this->assertCount(0, $stateMachineTransitionHandlerCollectionTransfer->getConditionNames());
    }

    protected function createCommandPlugin(string $subjectType = WorkflowHelper::SUBJECT_TYPE): WorkflowCommandPluginInterface
    {
        return new class ($subjectType) implements WorkflowCommandPluginInterface {
            public function __construct(protected string $subjectType)
            {
            }

            public function getName(): string
            {
                return StateMachineTransitionHandlerCollectionTest::COMMAND_NAME;
            }

            public function getSubjectType(): string
            {
                return $this->subjectType;
            }

            /**
             * @param \Generated\Shared\Transfer\StateMachineItemTransfer $stateMachineItemTransfer
             *
             * @return void
             */
            public function run(StateMachineItemTransfer $stateMachineItemTransfer)
            {
            }
        };
    }

    protected function createConditionPlugin(string $subjectType = WorkflowHelper::SUBJECT_TYPE): WorkflowConditionPluginInterface
    {
        return new class ($subjectType) implements WorkflowConditionPluginInterface {
            public function __construct(protected string $subjectType)
            {
            }

            public function getName(): string
            {
                return StateMachineTransitionHandlerCollectionTest::CONDITION_NAME;
            }

            public function getSubjectType(): string
            {
                return $this->subjectType;
            }

            /**
             * @param \Generated\Shared\Transfer\StateMachineItemTransfer $stateMachineItemTransfer
             *
             * @return bool
             */
            public function check(StateMachineItemTransfer $stateMachineItemTransfer)
            {
                return true;
            }
        };
    }
}

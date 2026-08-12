<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Business;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\StateMachineProcessCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Generated\Shared\Transfer\StateMachineTriggerEventConditionsTransfer;
use Generated\Shared\Transfer\StateMachineTriggerEventCriteriaTransfer;
use Spryker\Zed\Workflow\Dependency\Plugin\StateMachineProcessTriggerPluginInterface;
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
 * @group TriggerEventCollectionTest
 * Add your own group annotations below this line
 */
class TriggerEventCollectionTest extends Unit
{
    /**
     * @var string
     */
    public const EVENT_NAME = 'Entity.spy_customer.create';

    /**
     * @var string
     */
    public const EVENT_LABEL = 'Customer created';

    /**
     * @var string
     */
    public const EVENT_DESCRIPTION = 'Fired when a new customer is created.';

    protected WorkflowBusinessTester $tester;

    public function testGetStateMachineTriggerEventCollectionExposesPluginEventWithFriendlyName(): void
    {
        // Arrange
        $this->tester->setDependency(WorkflowDependencyProvider::PLUGINS_TRIGGER, [$this->createTriggerPlugin()]);
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();

        // Act
        $stateMachineTriggerEventCollectionTransfer = $this->tester->getFacade()->getStateMachineTriggerEventCollection(
            (new StateMachineTriggerEventCriteriaTransfer())
                ->setStateMachineTriggerEventConditions(
                    (new StateMachineTriggerEventConditionsTransfer())
                        ->addIdStateMachineProcess($stateMachineProcessTransfer->getIdStateMachineProcessOrFail()),
                ),
        );

        // Assert: the plugin's event is offered with its technical name, friendly name and description.
        $this->assertCount(1, $stateMachineTriggerEventCollectionTransfer->getStateMachineTriggerEvents());
        $stateMachineTriggerEventTransfer = $stateMachineTriggerEventCollectionTransfer->getStateMachineTriggerEvents()->getIterator()->current();
        $this->assertSame(static::EVENT_NAME, $stateMachineTriggerEventTransfer->getEventName());
        $this->assertSame(static::EVENT_LABEL, $stateMachineTriggerEventTransfer->getName());
        $this->assertSame(static::EVENT_DESCRIPTION, $stateMachineTriggerEventTransfer->getDescription());
        $this->assertSame(WorkflowHelper::SUBJECT_TYPE, $stateMachineTriggerEventTransfer->getSubjectType());
    }

    public function testGetStateMachineTriggerEventCollectionSkipsPluginsOfOtherSubjectTypes(): void
    {
        // Arrange: the only registered plugin targets a different subject type than the process.
        $this->tester->setDependency(
            WorkflowDependencyProvider::PLUGINS_TRIGGER,
            [$this->createTriggerPlugin('some_other_subject_type')],
        );
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();

        // Act
        $stateMachineTriggerEventCollectionTransfer = $this->tester->getFacade()->getStateMachineTriggerEventCollection(
            (new StateMachineTriggerEventCriteriaTransfer())
                ->setStateMachineTriggerEventConditions(
                    (new StateMachineTriggerEventConditionsTransfer())
                        ->addIdStateMachineProcess($stateMachineProcessTransfer->getIdStateMachineProcessOrFail()),
                ),
        );

        // Assert
        $this->assertCount(0, $stateMachineTriggerEventCollectionTransfer->getStateMachineTriggerEvents());
    }

    public function testGetStateMachineTriggerEventCollectionSkipsProcessWithoutSubjectType(): void
    {
        // Arrange: a process created WITHOUT a subject type resolves to no trigger events.
        $this->tester->setDependency(WorkflowDependencyProvider::PLUGINS_TRIGGER, [$this->createTriggerPlugin()]);
        $stateMachineProcessTransfer = $this->createProcessWithoutSubjectType();

        // Act
        $stateMachineTriggerEventCollectionTransfer = $this->tester->getFacade()->getStateMachineTriggerEventCollection(
            (new StateMachineTriggerEventCriteriaTransfer())
                ->setStateMachineTriggerEventConditions(
                    (new StateMachineTriggerEventConditionsTransfer())
                        ->addIdStateMachineProcess($stateMachineProcessTransfer->getIdStateMachineProcessOrFail()),
                ),
        );

        // Assert
        $this->assertCount(0, $stateMachineTriggerEventCollectionTransfer->getStateMachineTriggerEvents());
    }

    protected function createProcessWithoutSubjectType(): StateMachineProcessTransfer
    {
        $name = uniqid('process_', true);

        return $this->tester->getFacade()->createStateMachineProcessCollection(
            (new StateMachineProcessCollectionRequestTransfer())
                ->addStateMachineProcess(
                    (new StateMachineProcessTransfer())
                        ->setStateMachineName($name)
                        ->setProcessName($name),
                ),
        )->getStateMachineProcesses()->getIterator()->current();
    }

    protected function createTriggerPlugin(string $subjectType = WorkflowHelper::SUBJECT_TYPE): StateMachineProcessTriggerPluginInterface
    {
        return new class ($subjectType) implements StateMachineProcessTriggerPluginInterface {
            public function __construct(protected string $subjectType)
            {
            }

            public function getEventName(): string
            {
                return TriggerEventCollectionTest::EVENT_NAME;
            }

            public function getName(): string
            {
                return TriggerEventCollectionTest::EVENT_LABEL;
            }

            public function getSubjectType(): string
            {
                return $this->subjectType;
            }

            public function getDescription(): string
            {
                return TriggerEventCollectionTest::EVENT_DESCRIPTION;
            }
        };
    }
}

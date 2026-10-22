<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Communication\Reader;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Generated\Shared\Transfer\StateMachineTriggerEventCollectionTransfer;
use Generated\Shared\Transfer\StateMachineTriggerEventTransfer;
use PHPUnit\Framework\MockObject\MockObject;
use Spryker\Zed\Workflow\Business\WorkflowFacadeInterface;
use Spryker\Zed\Workflow\Communication\Reader\TriggerLabelReader;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group Workflow
 * @group Communication
 * @group Reader
 * @group TriggerLabelReaderTest
 * Add your own group annotations below this line
 */
class TriggerLabelReaderTest extends Unit
{
    protected const int ID_STATE_MACHINE_PROCESS = 7;

    protected const int ID_OTHER_STATE_MACHINE_PROCESS = 8;

    protected const int ID_STATE_MACHINE_PROCESS_DEFINITION_TRIGGER = 11;

    protected const string EVENT_NAME_WITH_FRIENDLY_NAME = 'merchant.created';

    protected const string EVENT_NAME_WITHOUT_FRIENDLY_NAME = 'merchant.updated';

    protected const string FRIENDLY_NAME = 'Merchant created';

    public function testGetTriggerLabelsGroupedByIdStateMachineProcessSkipsEventCatalogLookupWhenNoTriggerIsAttached(): void
    {
        // Arrange
        $workflowFacadeMock = $this->createMock(WorkflowFacadeInterface::class);
        $workflowFacadeMock->method('getStateMachineDefinitionTriggerCollection')
            ->willReturn(new StateMachineDefinitionTriggerCollectionTransfer());
        $workflowFacadeMock->expects($this->never())->method('getStateMachineTriggerEventCollection');

        // Act
        $triggerLabelsGroupedByIdStateMachineProcess = (new TriggerLabelReader($workflowFacadeMock))
            ->getTriggerLabelsGroupedByIdStateMachineProcess([static::ID_STATE_MACHINE_PROCESS]);

        // Assert
        $this->assertSame([], $triggerLabelsGroupedByIdStateMachineProcess);
    }

    public function testGetTriggerLabelsGroupedByIdStateMachineProcessGroupsLabelsOfEveryRequestedProcess(): void
    {
        // Arrange
        $workflowFacadeMock = $this->createWorkflowFacadeMock([
            static::ID_STATE_MACHINE_PROCESS => [static::EVENT_NAME_WITH_FRIENDLY_NAME],
            static::ID_OTHER_STATE_MACHINE_PROCESS => [static::EVENT_NAME_WITHOUT_FRIENDLY_NAME],
        ]);

        // Act
        $triggerLabelsGroupedByIdStateMachineProcess = (new TriggerLabelReader($workflowFacadeMock))
            ->getTriggerLabelsGroupedByIdStateMachineProcess([static::ID_STATE_MACHINE_PROCESS, static::ID_OTHER_STATE_MACHINE_PROCESS]);

        // Assert
        $this->assertSame([
            static::ID_STATE_MACHINE_PROCESS => [static::EVENT_NAME_WITH_FRIENDLY_NAME => static::FRIENDLY_NAME],
            static::ID_OTHER_STATE_MACHINE_PROCESS => [static::EVENT_NAME_WITHOUT_FRIENDLY_NAME => static::EVENT_NAME_WITHOUT_FRIENDLY_NAME],
        ], $triggerLabelsGroupedByIdStateMachineProcess);
    }

    public function testGetTriggerLabelsGroupedByIdStateMachineProcessSkipsFacadeWhenNoProcessIsRequested(): void
    {
        // Arrange
        $workflowFacadeMock = $this->createMock(WorkflowFacadeInterface::class);
        $workflowFacadeMock->expects($this->never())->method('getStateMachineDefinitionTriggerCollection');

        // Act
        $triggerLabelsGroupedByIdStateMachineProcess = (new TriggerLabelReader($workflowFacadeMock))
            ->getTriggerLabelsGroupedByIdStateMachineProcess([]);

        // Assert
        $this->assertSame([], $triggerLabelsGroupedByIdStateMachineProcess);
    }

    public function testGetStateMachineDefinitionTriggersIndexedByEventNameReturnsTheStoredTriggersOfTheProcess(): void
    {
        // Arrange
        $triggerLabelReader = new TriggerLabelReader($this->createWorkflowFacadeMock([
            static::ID_STATE_MACHINE_PROCESS => [static::EVENT_NAME_WITH_FRIENDLY_NAME, static::EVENT_NAME_WITHOUT_FRIENDLY_NAME],
        ]));

        // Act
        $stateMachineDefinitionTriggerTransfers = $triggerLabelReader->getStateMachineDefinitionTriggersIndexedByEventName(static::ID_STATE_MACHINE_PROCESS);

        // Assert
        $this->assertSame([static::EVENT_NAME_WITH_FRIENDLY_NAME, static::EVENT_NAME_WITHOUT_FRIENDLY_NAME], array_keys($stateMachineDefinitionTriggerTransfers));
        $this->assertSame(
            static::ID_STATE_MACHINE_PROCESS_DEFINITION_TRIGGER,
            $stateMachineDefinitionTriggerTransfers[static::EVENT_NAME_WITH_FRIENDLY_NAME]->getIdStateMachineProcessDefinitionTrigger(),
        );
    }

    /**
     * @param array<int, array<string>> $attachedEventNamesGroupedByIdStateMachineProcess
     */
    protected function createWorkflowFacadeMock(array $attachedEventNamesGroupedByIdStateMachineProcess): WorkflowFacadeInterface&MockObject
    {
        $stateMachineDefinitionTriggerCollectionTransfer = new StateMachineDefinitionTriggerCollectionTransfer();
        foreach ($attachedEventNamesGroupedByIdStateMachineProcess as $idStateMachineProcess => $eventNames) {
            foreach ($eventNames as $eventName) {
                $stateMachineDefinitionTriggerCollectionTransfer->addStateMachineDefinitionTrigger(
                    (new StateMachineDefinitionTriggerTransfer())
                        ->setIdStateMachineProcessDefinitionTrigger(static::ID_STATE_MACHINE_PROCESS_DEFINITION_TRIGGER)
                        ->setEventName($eventName)
                        ->setStateMachineProcess((new StateMachineProcessTransfer())->setIdStateMachineProcess($idStateMachineProcess)),
                );
            }
        }

        $workflowFacadeMock = $this->createMock(WorkflowFacadeInterface::class);
        $workflowFacadeMock->method('getStateMachineDefinitionTriggerCollection')
            ->willReturn($stateMachineDefinitionTriggerCollectionTransfer);
        $workflowFacadeMock->method('getStateMachineTriggerEventCollection')
            ->willReturn(
                (new StateMachineTriggerEventCollectionTransfer())
                    ->addStateMachineTriggerEvent(
                        (new StateMachineTriggerEventTransfer())
                            ->setEventName(static::EVENT_NAME_WITH_FRIENDLY_NAME)
                            ->setName(static::FRIENDLY_NAME),
                    ),
            );

        return $workflowFacadeMock;
    }
}

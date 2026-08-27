<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Business\Reader;

use ArrayObject;
use Codeception\Test\Unit;
use Generated\Shared\Transfer\StateMachineProcessCollectionTransfer;
use Generated\Shared\Transfer\StateMachineProcessCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Spryker\Zed\Workflow\Business\Reader\StateMachineProcessExistenceReader;
use Spryker\Zed\Workflow\Persistence\WorkflowRepositoryInterface;

/**
 * Pins the two branches the collection writers depend on but never exercise directly: the requested
 * identifiers are de-duplicated into a single repository lookup, and an empty request short-circuits
 * without touching the repository at all.
 *
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group Workflow
 * @group Business
 * @group Reader
 * @group StateMachineProcessExistenceReaderTest
 * Add your own group annotations below this line
 */
class StateMachineProcessExistenceReaderTest extends Unit
{
    protected const int ID_STATE_MACHINE_PROCESS = 21;

    protected const int ID_STATE_MACHINE_PROCESS_OTHER = 22;

    protected const string STATE_MACHINE_NAME = 'TestStateMachine';

    protected const string STATE_MACHINE_NAME_OTHER = 'OtherTestStateMachine';

    public function testGetExistingStateMachineProcessIdsDeduplicatesRequestedIdsIntoASingleLookup(): void
    {
        // Arrange
        $stateMachineProcessTransfers = new ArrayObject([
            (new StateMachineProcessTransfer())->setIdStateMachineProcess(static::ID_STATE_MACHINE_PROCESS),
            (new StateMachineProcessTransfer())->setIdStateMachineProcess(static::ID_STATE_MACHINE_PROCESS),
            (new StateMachineProcessTransfer())->setIdStateMachineProcess(static::ID_STATE_MACHINE_PROCESS_OTHER),
        ]);

        $workflowRepositoryMock = $this->createMock(WorkflowRepositoryInterface::class);
        $workflowRepositoryMock->expects($this->once())
            ->method('getStateMachineProcessCollection')
            ->with($this->callback(function (StateMachineProcessCriteriaTransfer $criteriaTransfer): bool {
                $this->assertSame(
                    [static::ID_STATE_MACHINE_PROCESS, static::ID_STATE_MACHINE_PROCESS_OTHER],
                    $criteriaTransfer->getStateMachineProcessConditionsOrFail()->getStateMachineProcessIds(),
                );

                return true;
            }))
            ->willReturn($this->createStateMachineProcessCollectionTransfer([
                (new StateMachineProcessTransfer())->setIdStateMachineProcess(static::ID_STATE_MACHINE_PROCESS),
            ]));

        // Act
        $existingStateMachineProcessIds = (new StateMachineProcessExistenceReader($workflowRepositoryMock))
            ->getExistingStateMachineProcessIds($stateMachineProcessTransfers);

        // Assert
        $this->assertSame([static::ID_STATE_MACHINE_PROCESS => true], $existingStateMachineProcessIds);
    }

    public function testGetExistingStateMachineProcessIdsReturnsEmptyArrayWithoutQueryingForEmptyInput(): void
    {
        // Arrange
        $workflowRepositoryMock = $this->createMock(WorkflowRepositoryInterface::class);
        $workflowRepositoryMock->expects($this->never())
            ->method('getStateMachineProcessCollection');

        // Act
        $existingStateMachineProcessIds = (new StateMachineProcessExistenceReader($workflowRepositoryMock))
            ->getExistingStateMachineProcessIds(new ArrayObject());

        // Assert
        $this->assertSame([], $existingStateMachineProcessIds);
    }

    public function testGetTakenStateMachineNamesDeduplicatesRequestedNamesIntoASingleLookup(): void
    {
        // Arrange
        $stateMachineProcessTransfers = new ArrayObject([
            (new StateMachineProcessTransfer())->setStateMachineName(static::STATE_MACHINE_NAME),
            (new StateMachineProcessTransfer())->setStateMachineName(static::STATE_MACHINE_NAME),
            (new StateMachineProcessTransfer())->setStateMachineName(static::STATE_MACHINE_NAME_OTHER),
        ]);

        $workflowRepositoryMock = $this->createMock(WorkflowRepositoryInterface::class);
        $workflowRepositoryMock->expects($this->once())
            ->method('getStateMachineProcessCollection')
            ->with($this->callback(function (StateMachineProcessCriteriaTransfer $criteriaTransfer): bool {
                $this->assertSame(
                    [static::STATE_MACHINE_NAME, static::STATE_MACHINE_NAME_OTHER],
                    $criteriaTransfer->getStateMachineProcessConditionsOrFail()->getStateMachineNames(),
                );

                return true;
            }))
            ->willReturn($this->createStateMachineProcessCollectionTransfer([
                (new StateMachineProcessTransfer())->setStateMachineName(static::STATE_MACHINE_NAME),
            ]));

        // Act
        $takenStateMachineNames = (new StateMachineProcessExistenceReader($workflowRepositoryMock))
            ->getTakenStateMachineNames($stateMachineProcessTransfers);

        // Assert
        $this->assertSame([static::STATE_MACHINE_NAME => true], $takenStateMachineNames);
    }

    public function testGetTakenStateMachineNamesReturnsEmptyArrayWithoutQueryingForEmptyInput(): void
    {
        // Arrange
        $workflowRepositoryMock = $this->createMock(WorkflowRepositoryInterface::class);
        $workflowRepositoryMock->expects($this->never())
            ->method('getStateMachineProcessCollection');

        // Act
        $takenStateMachineNames = (new StateMachineProcessExistenceReader($workflowRepositoryMock))
            ->getTakenStateMachineNames(new ArrayObject());

        // Assert
        $this->assertSame([], $takenStateMachineNames);
    }

    /**
     * @param array<\Generated\Shared\Transfer\StateMachineProcessTransfer> $stateMachineProcessTransfers
     */
    protected function createStateMachineProcessCollectionTransfer(
        array $stateMachineProcessTransfers
    ): StateMachineProcessCollectionTransfer {
        return (new StateMachineProcessCollectionTransfer())
            ->setStateMachineProcesses(new ArrayObject($stateMachineProcessTransfers));
    }
}

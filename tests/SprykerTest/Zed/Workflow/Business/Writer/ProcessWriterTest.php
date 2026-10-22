<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Business\Writer;

use Closure;
use Codeception\Test\Unit;
use Generated\Shared\Transfer\StateMachineProcessCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Spryker\Zed\Kernel\Persistence\EntityManager\TransactionHandlerInterface;
use Spryker\Zed\Workflow\Business\Reader\StateMachineProcessExistenceReaderInterface;
use Spryker\Zed\Workflow\Business\Validator\DefinitionValidatorInterface;
use Spryker\Zed\Workflow\Business\Writer\DefinitionWriterInterface;
use Spryker\Zed\Workflow\Business\Writer\ProcessWriter;
use Spryker\Zed\Workflow\Business\Writer\TriggerWriterInterface;
use Spryker\Zed\Workflow\Persistence\WorkflowEntityManagerInterface;
use Spryker\Zed\Workflow\WorkflowConfig;

/**
 * Pins the lookup shape of the collection writes: the existence reader is consulted ONCE for the whole
 * batch, never once per requested item.
 *
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group Workflow
 * @group Business
 * @group Writer
 * @group ProcessWriterTest
 * Add your own group annotations below this line
 */
class ProcessWriterTest extends Unit
{
    /**
     * @var string
     */
    protected const ERROR_PROCESS_NOT_FOUND = 'The workflow was not found.';

    /**
     * @var string
     */
    protected const ERROR_STATE_MACHINE_NAME_TAKEN = 'A state machine with this name already exists.';

    public function testUpdateStateMachineProcessCollectionConsultsTheExistenceReaderOncePerBatch(): void
    {
        // Arrange
        $existingIds = [11, 12, 13];
        $missingIds = [14, 15];

        $stateMachineProcessExistenceReaderMock = $this->createMock(StateMachineProcessExistenceReaderInterface::class);
        $stateMachineProcessExistenceReaderMock->expects($this->once())
            ->method('getExistingStateMachineProcessIds')
            ->willReturn(array_fill_keys($existingIds, true));

        $workflowEntityManagerMock = $this->createMock(WorkflowEntityManagerInterface::class);
        $workflowEntityManagerMock->expects($this->exactly(count($existingIds)))
            ->method('updateStateMachineProcess')
            ->willReturnArgument(0);

        $stateMachineProcessCollectionRequestTransfer = new StateMachineProcessCollectionRequestTransfer();
        foreach (array_merge($existingIds, $missingIds) as $idStateMachineProcess) {
            $stateMachineProcessCollectionRequestTransfer->addStateMachineProcess(
                (new StateMachineProcessTransfer())
                    ->setIdStateMachineProcess($idStateMachineProcess)
                    ->setStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE),
            );
        }

        // Act
        $stateMachineProcessCollectionResponseTransfer = $this
            ->createProcessWriter($workflowEntityManagerMock, $stateMachineProcessExistenceReaderMock)
            ->updateStateMachineProcessCollection($stateMachineProcessCollectionRequestTransfer);

        // Assert
        $this->assertCount(count($existingIds), $stateMachineProcessCollectionResponseTransfer->getStateMachineProcesses());
        $this->assertCount(count($missingIds), $stateMachineProcessCollectionResponseTransfer->getErrors());

        foreach ($stateMachineProcessCollectionResponseTransfer->getErrors() as $errorTransfer) {
            $this->assertSame(static::ERROR_PROCESS_NOT_FOUND, $errorTransfer->getMessage());
            $this->assertContains((int)$errorTransfer->getEntityIdentifier(), $missingIds);
        }
    }

    public function testCreateStateMachineProcessCollectionConsultsTheExistenceReaderOncePerBatch(): void
    {
        // Arrange
        $stateMachineProcessExistenceReaderMock = $this->createMock(StateMachineProcessExistenceReaderInterface::class);
        $stateMachineProcessExistenceReaderMock->expects($this->once())
            ->method('getTakenStateMachineNames')
            ->willReturn([]);

        $workflowEntityManagerMock = $this->createMock(WorkflowEntityManagerInterface::class);
        $workflowEntityManagerMock->expects($this->exactly(3))
            ->method('createStateMachineProcess')
            ->willReturnArgument(0);

        $stateMachineProcessCollectionRequestTransfer = new StateMachineProcessCollectionRequestTransfer();
        foreach (['alpha', 'beta', 'gamma'] as $stateMachineName) {
            $stateMachineProcessCollectionRequestTransfer->addStateMachineProcess(
                (new StateMachineProcessTransfer())->setStateMachineName($stateMachineName),
            );
        }

        // Act
        $stateMachineProcessCollectionResponseTransfer = $this
            ->createProcessWriter($workflowEntityManagerMock, $stateMachineProcessExistenceReaderMock)
            ->createStateMachineProcessCollection($stateMachineProcessCollectionRequestTransfer);

        // Assert
        $this->assertCount(3, $stateMachineProcessCollectionResponseTransfer->getStateMachineProcesses());
        $this->assertCount(0, $stateMachineProcessCollectionResponseTransfer->getErrors());
    }

    public function testCreateStateMachineProcessCollectionRejectsANameRepeatedWithinTheSameBatch(): void
    {
        // Arrange
        $stateMachineProcessExistenceReaderMock = $this->createMock(StateMachineProcessExistenceReaderInterface::class);
        $stateMachineProcessExistenceReaderMock->expects($this->once())
            ->method('getTakenStateMachineNames')
            ->willReturn([]);

        $workflowEntityManagerMock = $this->createMock(WorkflowEntityManagerInterface::class);
        $workflowEntityManagerMock->expects($this->once())
            ->method('createStateMachineProcess')
            ->willReturnArgument(0);

        $stateMachineProcessCollectionRequestTransfer = new StateMachineProcessCollectionRequestTransfer();
        foreach (['duplicate', 'duplicate'] as $stateMachineName) {
            $stateMachineProcessCollectionRequestTransfer->addStateMachineProcess(
                (new StateMachineProcessTransfer())->setStateMachineName($stateMachineName),
            );
        }

        // Act
        $stateMachineProcessCollectionResponseTransfer = $this
            ->createProcessWriter($workflowEntityManagerMock, $stateMachineProcessExistenceReaderMock)
            ->createStateMachineProcessCollection($stateMachineProcessCollectionRequestTransfer);

        // Assert
        $this->assertCount(1, $stateMachineProcessCollectionResponseTransfer->getStateMachineProcesses());
        $this->assertCount(1, $stateMachineProcessCollectionResponseTransfer->getErrors());
        $this->assertSame(
            static::ERROR_STATE_MACHINE_NAME_TAKEN,
            $stateMachineProcessCollectionResponseTransfer->getErrors()->getIterator()->current()->getMessage(),
        );
    }

    /**
     * The transaction handler is stubbed so the writer logic runs without a database connection.
     *
     * @return \Spryker\Zed\Workflow\Business\Writer\ProcessWriter|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function createProcessWriter(
        WorkflowEntityManagerInterface $workflowEntityManagerMock,
        StateMachineProcessExistenceReaderInterface $stateMachineProcessExistenceReaderMock
    ): ProcessWriter {
        $transactionHandlerMock = $this->createMock(TransactionHandlerInterface::class);
        $transactionHandlerMock->method('handleTransaction')->willReturnCallback(
            static fn (Closure $callback): mixed => $callback(),
        );

        $processWriterMock = $this->getMockBuilder(ProcessWriter::class)
            ->setConstructorArgs([
                $workflowEntityManagerMock,
                $stateMachineProcessExistenceReaderMock,
                $this->createMock(DefinitionWriterInterface::class),
                $this->createMock(TriggerWriterInterface::class),
                $this->createMock(DefinitionValidatorInterface::class),
            ])
            ->onlyMethods(['getTransactionHandler'])
            ->getMock();

        $processWriterMock->method('getTransactionHandler')->willReturn($transactionHandlerMock);

        return $processWriterMock;
    }
}

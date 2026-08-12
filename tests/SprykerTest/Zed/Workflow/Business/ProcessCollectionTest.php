<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Business;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\StateMachineProcessCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Spryker\Zed\Workflow\WorkflowConfig;
use SprykerTest\Zed\Workflow\Helper\WorkflowHelper;
use SprykerTest\Zed\Workflow\WorkflowBusinessTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group Workflow
 * @group Business
 * @group ProcessCollectionTest
 * Add your own group annotations below this line
 */
class ProcessCollectionTest extends Unit
{
    /**
     * @var string
     */
    protected const DUPLICATE_NAME_ERROR_MESSAGE = 'A state machine with this name already exists.';

    /**
     * @var string
     */
    protected const INTERNAL_STATE_MACHINE_NAME = 'SprykerTestInternalStateMachine';

    protected WorkflowBusinessTester $tester;

    public function testCreateStateMachineProcessCollectionPersistsProcessAsInactiveDatabaseType(): void
    {
        // Arrange
        $name = $this->tester->generateUniqueName('process');
        $stateMachineProcessCollectionRequestTransfer = (new StateMachineProcessCollectionRequestTransfer())
            ->addStateMachineProcess(
                (new StateMachineProcessTransfer())
                    ->setStateMachineName($name)
                    ->setProcessName($name)
                    ->setSubjectType(WorkflowHelper::SUBJECT_TYPE),
            );

        // Act
        $stateMachineProcessCollectionResponseTransfer = $this->tester->getFacade()
            ->createStateMachineProcessCollection($stateMachineProcessCollectionRequestTransfer);

        // Assert
        $stateMachineProcessTransfer = $stateMachineProcessCollectionResponseTransfer->getStateMachineProcesses()->getIterator()->current();
        $this->assertCount(0, $stateMachineProcessCollectionResponseTransfer->getErrors());
        $this->assertNotNull($stateMachineProcessTransfer->getIdStateMachineProcess());
        $this->assertSame(
            WorkflowConfig::PROCESS_DEFINITION_STATUS_INACTIVE,
            $stateMachineProcessTransfer->getStatus(),
        );
    }

    public function testCreateStateMachineProcessCollectionRejectsDuplicateNameWithErrorMessage(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $stateMachineProcessCollectionRequestTransfer = (new StateMachineProcessCollectionRequestTransfer())
            ->addStateMachineProcess(
                (new StateMachineProcessTransfer())
                    ->setStateMachineName($stateMachineProcessTransfer->getStateMachineNameOrFail())
                    ->setProcessName($stateMachineProcessTransfer->getProcessNameOrFail())
                    ->setSubjectType(WorkflowHelper::SUBJECT_TYPE),
            );

        // Act
        $stateMachineProcessCollectionResponseTransfer = $this->tester->getFacade()
            ->createStateMachineProcessCollection($stateMachineProcessCollectionRequestTransfer);

        // Assert
        $this->assertCount(0, $stateMachineProcessCollectionResponseTransfer->getStateMachineProcesses());
        $this->assertSame(1, $stateMachineProcessCollectionResponseTransfer->getErrors()->count());
        $this->assertSame(
            static::DUPLICATE_NAME_ERROR_MESSAGE,
            $stateMachineProcessCollectionResponseTransfer->getErrors()->getIterator()->current()->getMessage(),
        );
    }

    public function testUpdateStateMachineProcessCollectionChangesStatus(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $activeStatus = WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE;

        // Act
        $this->tester->getFacade()->updateStateMachineProcessCollection(
            (new StateMachineProcessCollectionRequestTransfer())
                ->addStateMachineProcess(
                    (new StateMachineProcessTransfer())
                        ->setIdStateMachineProcess($stateMachineProcessTransfer->getIdStateMachineProcessOrFail())
                        ->setStatus($activeStatus),
                ),
        );

        // Assert
        $this->assertSame($activeStatus, $this->findProcessStatusById($stateMachineProcessTransfer->getIdStateMachineProcessOrFail()));
    }

    public function testGetStateMachineProcessCollectionFiltersByStateMachineName(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();

        // Act
        $stateMachineProcessCollectionTransfer = $this->tester->getFacade()->getStateMachineProcessCollection(
            (new StateMachineProcessCriteriaTransfer())
                ->setStateMachineProcessConditions(
                    (new StateMachineProcessConditionsTransfer())
                        ->addStateMachineName($stateMachineProcessTransfer->getStateMachineNameOrFail()),
                ),
        );

        // Assert
        $this->assertCount(1, $stateMachineProcessCollectionTransfer->getStateMachineProcesses());
        $this->assertSame(
            $stateMachineProcessTransfer->getIdStateMachineProcess(),
            $stateMachineProcessCollectionTransfer->getStateMachineProcesses()->getIterator()->current()->getIdStateMachineProcess(),
        );
    }

    protected function findProcessStatusById(int $idStateMachineProcess): ?string
    {
        $stateMachineProcessTransfer = $this->tester->getFacade()->getStateMachineProcessCollection(
            (new StateMachineProcessCriteriaTransfer())
                ->setStateMachineProcessConditions(
                    (new StateMachineProcessConditionsTransfer())
                        ->addIdStateMachineProcess($idStateMachineProcess),
                ),
        )->getStateMachineProcesses()->getIterator()->current();

        return $stateMachineProcessTransfer !== null ? $stateMachineProcessTransfer->getStatus() : null;
    }
}

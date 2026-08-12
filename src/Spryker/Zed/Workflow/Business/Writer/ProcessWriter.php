<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Writer;

use Generated\Shared\Transfer\ErrorTransfer;
use Generated\Shared\Transfer\StateMachineProcessCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessCollectionResponseTransfer;
use Generated\Shared\Transfer\StateMachineProcessConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessCriteriaTransfer;
use Spryker\Zed\Kernel\Persistence\EntityManager\TransactionTrait;
use Spryker\Zed\Workflow\Persistence\WorkflowEntityManagerInterface;
use Spryker\Zed\Workflow\Persistence\WorkflowRepositoryInterface;
use Spryker\Zed\Workflow\WorkflowConfig;

class ProcessWriter implements ProcessWriterInterface
{
    use TransactionTrait;

    public function __construct(
        protected WorkflowEntityManagerInterface $workflowEntityManager,
        protected WorkflowRepositoryInterface $workflowRepository
    ) {
    }

    public function createStateMachineProcessCollection(
        StateMachineProcessCollectionRequestTransfer $stateMachineProcessCollectionRequestTransfer
    ): StateMachineProcessCollectionResponseTransfer {
        return $this->getTransactionHandler()->handleTransaction(function () use ($stateMachineProcessCollectionRequestTransfer): StateMachineProcessCollectionResponseTransfer {
            return $this->executeCreateStateMachineProcessCollectionTransaction($stateMachineProcessCollectionRequestTransfer);
        });
    }

    protected function executeCreateStateMachineProcessCollectionTransaction(
        StateMachineProcessCollectionRequestTransfer $stateMachineProcessCollectionRequestTransfer
    ): StateMachineProcessCollectionResponseTransfer {
        $stateMachineProcessCollectionResponseTransfer = new StateMachineProcessCollectionResponseTransfer();

        foreach ($stateMachineProcessCollectionRequestTransfer->getStateMachineProcesses() as $stateMachineProcessTransfer) {
            if ($this->hasStateMachineProcessWithName($stateMachineProcessTransfer->getStateMachineNameOrFail())) {
                $stateMachineProcessCollectionResponseTransfer->addError(
                    (new ErrorTransfer())
                        ->setMessage('A state machine with this name already exists.')
                        ->setEntityIdentifier($stateMachineProcessTransfer->getStateMachineNameOrFail()),
                );

                continue;
            }

            if ($stateMachineProcessTransfer->getStatus() === null) {
                $stateMachineProcessTransfer->setStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_INACTIVE);
            }

            $stateMachineProcessCollectionResponseTransfer->addStateMachineProcess(
                $this->workflowEntityManager->createStateMachineProcess($stateMachineProcessTransfer),
            );
        }

        return $stateMachineProcessCollectionResponseTransfer;
    }

    protected function hasStateMachineProcessWithName(string $stateMachineName): bool
    {
        return $this->workflowRepository
            ->getStateMachineProcessCollection(
                (new StateMachineProcessCriteriaTransfer())
                    ->setStateMachineProcessConditions(
                        (new StateMachineProcessConditionsTransfer())->addStateMachineName($stateMachineName),
                    ),
            )
            ->getStateMachineProcesses()
            ->count() > 0;
    }

    public function updateStateMachineProcessCollection(
        StateMachineProcessCollectionRequestTransfer $stateMachineProcessCollectionRequestTransfer
    ): StateMachineProcessCollectionResponseTransfer {
        return $this->getTransactionHandler()->handleTransaction(function () use ($stateMachineProcessCollectionRequestTransfer): StateMachineProcessCollectionResponseTransfer {
            return $this->executeUpdateStateMachineProcessCollectionTransaction($stateMachineProcessCollectionRequestTransfer);
        });
    }

    protected function executeUpdateStateMachineProcessCollectionTransaction(
        StateMachineProcessCollectionRequestTransfer $stateMachineProcessCollectionRequestTransfer
    ): StateMachineProcessCollectionResponseTransfer {
        $stateMachineProcessCollectionResponseTransfer = new StateMachineProcessCollectionResponseTransfer();

        foreach ($stateMachineProcessCollectionRequestTransfer->getStateMachineProcesses() as $stateMachineProcessTransfer) {
            $stateMachineProcessCollectionResponseTransfer->addStateMachineProcess(
                $this->workflowEntityManager->updateStateMachineProcess($stateMachineProcessTransfer),
            );
        }

        return $stateMachineProcessCollectionResponseTransfer;
    }
}

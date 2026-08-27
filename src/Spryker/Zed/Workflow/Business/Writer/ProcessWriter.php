<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Writer;

use Generated\Shared\Transfer\ErrorTransfer;
use Generated\Shared\Transfer\StateMachineProcessCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessCollectionResponseTransfer;
use Spryker\Zed\Kernel\Persistence\EntityManager\TransactionTrait;
use Spryker\Zed\Workflow\Business\Reader\StateMachineProcessExistenceReaderInterface;
use Spryker\Zed\Workflow\Persistence\WorkflowEntityManagerInterface;
use Spryker\Zed\Workflow\WorkflowConfig;

class ProcessWriter implements ProcessWriterInterface
{
    use TransactionTrait;

    /**
     * @var string
     */
    protected const ERROR_PROCESS_NOT_FOUND = 'The workflow was not found.';

    /**
     * @var string
     */
    protected const ERROR_STATE_MACHINE_NAME_TAKEN = 'A state machine with this name already exists.';

    public function __construct(
        protected WorkflowEntityManagerInterface $workflowEntityManager,
        protected StateMachineProcessExistenceReaderInterface $stateMachineProcessExistenceReader
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
        $stateMachineProcessTransfers = $stateMachineProcessCollectionRequestTransfer->getStateMachineProcesses();

        $takenStateMachineNames = $this->stateMachineProcessExistenceReader->getTakenStateMachineNames($stateMachineProcessTransfers);

        foreach ($stateMachineProcessTransfers as $stateMachineProcessTransfer) {
            $stateMachineName = $stateMachineProcessTransfer->getStateMachineNameOrFail();

            if (isset($takenStateMachineNames[$stateMachineName])) {
                $stateMachineProcessCollectionResponseTransfer->addError(
                    (new ErrorTransfer())
                        ->setMessage(static::ERROR_STATE_MACHINE_NAME_TAKEN)
                        ->setEntityIdentifier($stateMachineName),
                );

                continue;
            }

            if ($stateMachineProcessTransfer->getStatus() === null) {
                $stateMachineProcessTransfer->setStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_INACTIVE);
            }

            $takenStateMachineNames[$stateMachineName] = true;

            $stateMachineProcessCollectionResponseTransfer->addStateMachineProcess(
                $this->workflowEntityManager->createStateMachineProcess($stateMachineProcessTransfer),
            );
        }

        return $stateMachineProcessCollectionResponseTransfer;
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
        $stateMachineProcessTransfers = $stateMachineProcessCollectionRequestTransfer->getStateMachineProcesses();

        $existingStateMachineProcessIds = $this->stateMachineProcessExistenceReader->getExistingStateMachineProcessIds($stateMachineProcessTransfers);

        foreach ($stateMachineProcessTransfers as $stateMachineProcessTransfer) {
            $idStateMachineProcess = $stateMachineProcessTransfer->getIdStateMachineProcessOrFail();

            if (!isset($existingStateMachineProcessIds[$idStateMachineProcess])) {
                $stateMachineProcessCollectionResponseTransfer->addError(
                    (new ErrorTransfer())
                        ->setMessage(static::ERROR_PROCESS_NOT_FOUND)
                        ->setEntityIdentifier((string)$idStateMachineProcess),
                );

                continue;
            }

            $stateMachineProcessCollectionResponseTransfer->addStateMachineProcess(
                $this->workflowEntityManager->updateStateMachineProcess($stateMachineProcessTransfer),
            );
        }

        return $stateMachineProcessCollectionResponseTransfer;
    }
}

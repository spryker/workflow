<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Writer;

use Generated\Shared\Transfer\ErrorTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessCollectionResponseTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Spryker\Zed\Kernel\Persistence\EntityManager\TransactionTrait;
use Spryker\Zed\Workflow\Business\Reader\StateMachineProcessExistenceReaderInterface;
use Spryker\Zed\Workflow\Business\Validator\DefinitionValidatorInterface;
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
        protected StateMachineProcessExistenceReaderInterface $stateMachineProcessExistenceReader,
        protected DefinitionWriterInterface $definitionWriter,
        protected TriggerWriterInterface $triggerWriter,
        protected DefinitionValidatorInterface $definitionCreationValidator
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

            $definitionValidationErrorTransfers = $this->validateStateMachineProcessDefinitions($stateMachineProcessTransfer);

            if ($definitionValidationErrorTransfers !== []) {
                $this->addErrors($stateMachineProcessCollectionResponseTransfer, $definitionValidationErrorTransfers);

                continue;
            }

            if ($stateMachineProcessTransfer->getStatus() === null) {
                $stateMachineProcessTransfer->setStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_INACTIVE);
            }

            $takenStateMachineNames[$stateMachineName] = true;

            $stateMachineProcessTransfer = $this->workflowEntityManager->createStateMachineProcess($stateMachineProcessTransfer);
            $this->createStateMachineDefinitionTriggers($stateMachineProcessTransfer);
            $this->createStateMachineProcessDefinitions($stateMachineProcessTransfer, $stateMachineProcessCollectionResponseTransfer);

            $stateMachineProcessCollectionResponseTransfer->addStateMachineProcess($stateMachineProcessTransfer);
        }

        return $stateMachineProcessCollectionResponseTransfer;
    }

    /**
     * @return list<\Generated\Shared\Transfer\ErrorTransfer>
     */
    protected function validateStateMachineProcessDefinitions(StateMachineProcessTransfer $stateMachineProcessTransfer): array
    {
        foreach ($stateMachineProcessTransfer->getStateMachineProcessDefinitions() as $stateMachineProcessDefinitionTransfer) {
            $stateMachineProcessDefinitionTransfer->setStateMachineProcess(
                (new StateMachineProcessTransfer())
                    ->setProcessName($stateMachineProcessTransfer->getProcessName())
                    ->setSubjectType($stateMachineProcessTransfer->getSubjectType()),
            );
        }

        $stateMachineDefinitionValidationResponseTransfer = $this->definitionCreationValidator
            ->validateCollection($stateMachineProcessTransfer->getStateMachineProcessDefinitions());

        $errorTransfers = [];
        foreach ($stateMachineDefinitionValidationResponseTransfer->getErrors() as $stateMachineDefinitionValidationErrorTransfer) {
            $errorTransfers[] = (new ErrorTransfer())
                ->setMessage($stateMachineDefinitionValidationErrorTransfer->getMessageOrFail())
                ->setEntityIdentifier($stateMachineProcessTransfer->getStateMachineNameOrFail());
        }

        return $errorTransfers;
    }

    protected function createStateMachineDefinitionTriggers(StateMachineProcessTransfer $stateMachineProcessTransfer): void
    {
        $stateMachineDefinitionTriggerCollectionRequestTransfer = new StateMachineDefinitionTriggerCollectionRequestTransfer();

        foreach ($stateMachineProcessTransfer->getStateMachineDefinitionTriggers() as $stateMachineDefinitionTriggerTransfer) {
            $stateMachineDefinitionTriggerCollectionRequestTransfer->addStateMachineDefinitionTriggerToAdd(
                $stateMachineDefinitionTriggerTransfer->setStateMachineProcess(
                    (new StateMachineProcessTransfer())->setIdStateMachineProcess($stateMachineProcessTransfer->getIdStateMachineProcessOrFail()),
                ),
            );
        }

        $this->triggerWriter->updateStateMachineDefinitionTriggerCollection($stateMachineDefinitionTriggerCollectionRequestTransfer);
    }

    protected function createStateMachineProcessDefinitions(
        StateMachineProcessTransfer $stateMachineProcessTransfer,
        StateMachineProcessCollectionResponseTransfer $stateMachineProcessCollectionResponseTransfer
    ): void {
        if ($stateMachineProcessTransfer->getStateMachineProcessDefinitions()->count() === 0) {
            return;
        }

        $stateMachineProcessDefinitionCollectionRequestTransfer = new StateMachineProcessDefinitionCollectionRequestTransfer();

        foreach ($stateMachineProcessTransfer->getStateMachineProcessDefinitions() as $stateMachineProcessDefinitionTransfer) {
            $stateMachineProcessDefinitionCollectionRequestTransfer->addStateMachineProcessDefinition(
                $stateMachineProcessDefinitionTransfer->setStateMachineProcess((new StateMachineProcessTransfer())->setIdStateMachineProcess($stateMachineProcessTransfer->getIdStateMachineProcessOrFail())),
            );
        }

        $stateMachineProcessDefinitionCollectionResponseTransfer = $this->definitionWriter
            ->createStateMachineProcessDefinitionCollection($stateMachineProcessDefinitionCollectionRequestTransfer);

        $stateMachineProcessTransfer->setStateMachineProcessDefinitions(
            $stateMachineProcessDefinitionCollectionResponseTransfer->getStateMachineProcessDefinitions(),
        );

        $this->addErrors($stateMachineProcessCollectionResponseTransfer, $stateMachineProcessDefinitionCollectionResponseTransfer->getErrors());
    }

    /**
     * @param iterable<\Generated\Shared\Transfer\ErrorTransfer> $errorTransfers
     */
    protected function addErrors(
        StateMachineProcessCollectionResponseTransfer $stateMachineProcessCollectionResponseTransfer,
        iterable $errorTransfers
    ): void {
        foreach ($errorTransfers as $errorTransfer) {
            $stateMachineProcessCollectionResponseTransfer->addError($errorTransfer);
        }
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

        if ($stateMachineProcessCollectionResponseTransfer->getErrors()->count() > 0) {
            return $stateMachineProcessCollectionResponseTransfer;
        }

        $stateMachineDefinitionTriggerCollectionResponseTransfer = $this->triggerWriter->updateStateMachineDefinitionTriggerCollection(
            (new StateMachineDefinitionTriggerCollectionRequestTransfer())
                ->setStateMachineDefinitionTriggersToAdd($stateMachineProcessCollectionRequestTransfer->getStateMachineDefinitionTriggersToAdd())
                ->setStateMachineDefinitionTriggersToRemove($stateMachineProcessCollectionRequestTransfer->getStateMachineDefinitionTriggersToRemove()),
        );
        $this->addErrors($stateMachineProcessCollectionResponseTransfer, $stateMachineDefinitionTriggerCollectionResponseTransfer->getErrors());

        return $stateMachineProcessCollectionResponseTransfer;
    }
}

<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Writer;

use Generated\Shared\Transfer\ErrorTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionValidationResponseTransfer;
use Generated\Shared\Transfer\StateMachineProcessConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionResponseTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Spryker\Zed\Kernel\Persistence\EntityManager\TransactionTrait;
use Spryker\Zed\Workflow\Business\Expander\TransitionFlagExpanderInterface;
use Spryker\Zed\Workflow\Business\Validator\DefinitionValidatorInterface;
use Spryker\Zed\Workflow\Persistence\WorkflowEntityManagerInterface;
use Spryker\Zed\Workflow\Persistence\WorkflowRepositoryInterface;
use Spryker\Zed\Workflow\WorkflowConfig;

class DefinitionWriter implements DefinitionWriterInterface
{
    use TransactionTrait;

    /**
     * @var string
     */
    protected const ERROR_DEFINITION_NOT_FOUND = 'The version was not found.';

    /**
     * @var string
     */
    protected const ERROR_DEFINITION_ACTIVE = 'The active version cannot be deleted. Deactivate it first.';

    /**
     * @var string
     */
    protected const ERROR_DEFINITION_HAS_INSTANCES = 'The version cannot be deleted because it has running instances.';

    public function __construct(
        protected WorkflowEntityManagerInterface $workflowEntityManager,
        protected WorkflowRepositoryInterface $workflowRepository,
        protected DefinitionValidatorInterface $definitionValidator,
        protected TransitionFlagExpanderInterface $transitionFlagExpander
    ) {
    }

    public function createStateMachineProcessDefinitionCollection(
        StateMachineProcessDefinitionCollectionRequestTransfer $stateMachineProcessDefinitionCollectionRequestTransfer
    ): StateMachineProcessDefinitionCollectionResponseTransfer {
        return $this->getTransactionHandler()->handleTransaction(function () use ($stateMachineProcessDefinitionCollectionRequestTransfer): StateMachineProcessDefinitionCollectionResponseTransfer {
            return $this->executeCreateStateMachineProcessDefinitionCollectionTransaction($stateMachineProcessDefinitionCollectionRequestTransfer);
        });
    }

    protected function executeCreateStateMachineProcessDefinitionCollectionTransaction(
        StateMachineProcessDefinitionCollectionRequestTransfer $stateMachineProcessDefinitionCollectionRequestTransfer
    ): StateMachineProcessDefinitionCollectionResponseTransfer {
        $stateMachineProcessDefinitionCollectionResponseTransfer = new StateMachineProcessDefinitionCollectionResponseTransfer();

        foreach ($stateMachineProcessDefinitionCollectionRequestTransfer->getStateMachineProcessDefinitions() as $stateMachineProcessDefinitionTransfer) {
            $persistedStateMachineProcessDefinitionTransfer = $this->findStateMachineProcessDefinitionByVersion($stateMachineProcessDefinitionTransfer);

            if ($persistedStateMachineProcessDefinitionTransfer !== null) {
                $stateMachineProcessDefinitionTransfer->setIdStateMachineProcessDefinition(
                    $persistedStateMachineProcessDefinitionTransfer->getIdStateMachineProcessDefinitionOrFail(),
                );
            }

            if ($stateMachineProcessDefinitionTransfer->getVersion() === null) {
                $maximumExistingVersion = $this->workflowRepository->findMaxVersionStateMachineProcessDefinition(
                    (new StateMachineProcessCriteriaTransfer())
                        ->setStateMachineProcessConditions(
                            (new StateMachineProcessConditionsTransfer())
                                ->addIdStateMachineProcess($stateMachineProcessDefinitionTransfer->getStateMachineProcessOrFail()->getIdStateMachineProcessOrFail()),
                        ),
                );

                $stateMachineProcessDefinitionTransfer->setVersion(($maximumExistingVersion ?? 0) + 1);
            }

            $stateMachineDefinitionValidationResponseTransfer = $this->definitionValidator->validate($stateMachineProcessDefinitionTransfer);

            if (!$stateMachineDefinitionValidationResponseTransfer->getIsValid()) {
                $this->addValidationErrors($stateMachineDefinitionValidationResponseTransfer, $stateMachineProcessDefinitionCollectionResponseTransfer);

                continue;
            }

            $stateMachineProcessDefinitionTransfer = $this->transitionFlagExpander
                ->expandWithTransitionFlags($stateMachineProcessDefinitionTransfer)
                ->setStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_INACTIVE);

            $stateMachineProcessDefinitionTransfer = $this->workflowEntityManager->saveStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);

            $stateMachineProcessDefinitionCollectionResponseTransfer->addStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);
        }

        return $stateMachineProcessDefinitionCollectionResponseTransfer;
    }

    protected function findStateMachineProcessDefinitionByVersion(
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
    ): ?StateMachineProcessDefinitionTransfer {
        $version = $stateMachineProcessDefinitionTransfer->getVersion();

        if ($version === null) {
            return null;
        }

        return $this->workflowRepository
            ->getStateMachineProcessDefinitionCollection(
                (new StateMachineProcessDefinitionCriteriaTransfer())
                    ->setStateMachineProcessDefinitionConditions(
                        (new StateMachineProcessDefinitionConditionsTransfer())
                            ->addIdStateMachineProcess($stateMachineProcessDefinitionTransfer->getStateMachineProcessOrFail()->getIdStateMachineProcessOrFail())
                            ->addVersion($version),
                    ),
            )
            ->getStateMachineProcessDefinitions()
            ->getIterator()
            ->current() ?: null;
    }

    public function updateStateMachineProcessDefinitionCollection(
        StateMachineProcessDefinitionCollectionRequestTransfer $stateMachineProcessDefinitionCollectionRequestTransfer
    ): StateMachineProcessDefinitionCollectionResponseTransfer {
        return $this->getTransactionHandler()->handleTransaction(function () use ($stateMachineProcessDefinitionCollectionRequestTransfer): StateMachineProcessDefinitionCollectionResponseTransfer {
            return $this->executeUpdateStateMachineProcessDefinitionCollectionTransaction($stateMachineProcessDefinitionCollectionRequestTransfer);
        });
    }

    protected function executeUpdateStateMachineProcessDefinitionCollectionTransaction(
        StateMachineProcessDefinitionCollectionRequestTransfer $stateMachineProcessDefinitionCollectionRequestTransfer
    ): StateMachineProcessDefinitionCollectionResponseTransfer {
        $stateMachineProcessDefinitionCollectionResponseTransfer = new StateMachineProcessDefinitionCollectionResponseTransfer();

        foreach ($stateMachineProcessDefinitionCollectionRequestTransfer->getStateMachineProcessDefinitions() as $stateMachineProcessDefinitionTransfer) {
            $idStateMachineProcessDefinition = $stateMachineProcessDefinitionTransfer->getIdStateMachineProcessDefinitionOrFail();
            $stateMachineProcessDefinitionCollectionTransfer = $this->workflowRepository
            ->getStateMachineProcessDefinitionCollection(
                (new StateMachineProcessDefinitionCriteriaTransfer())
                    ->setStateMachineProcessDefinitionConditions(
                        (new StateMachineProcessDefinitionConditionsTransfer())
                            ->addIdStateMachineProcessDefinition($idStateMachineProcessDefinition),
                    ),
            );

            $persistedStateMachineProcessDefinitionTransfer = $stateMachineProcessDefinitionCollectionTransfer->getStateMachineProcessDefinitions()->getIterator()->current() ?: null;

            if ($persistedStateMachineProcessDefinitionTransfer === null) {
                $stateMachineProcessDefinitionCollectionResponseTransfer->addError(
                    (new ErrorTransfer())
                        ->setMessage(static::ERROR_DEFINITION_NOT_FOUND)
                        ->setEntityIdentifier((string)$idStateMachineProcessDefinition),
                );

                continue;
            }

            if ($stateMachineProcessDefinitionTransfer->getStatus() !== WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE) {
                $persistedStateMachineProcessDefinitionTransfer->setStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_INACTIVE);
                $this->workflowEntityManager->updateStateMachineProcessDefinition($persistedStateMachineProcessDefinitionTransfer);
                $stateMachineProcessDefinitionCollectionResponseTransfer->addStateMachineProcessDefinition($persistedStateMachineProcessDefinitionTransfer);

                continue;
            }

            $this->activateDefinition($persistedStateMachineProcessDefinitionTransfer, $stateMachineProcessDefinitionCollectionResponseTransfer);
        }

        return $stateMachineProcessDefinitionCollectionResponseTransfer;
    }

    public function deleteStateMachineProcessDefinitionCollection(
        StateMachineProcessDefinitionCollectionRequestTransfer $stateMachineProcessDefinitionCollectionRequestTransfer
    ): StateMachineProcessDefinitionCollectionResponseTransfer {
        return $this->getTransactionHandler()->handleTransaction(function () use ($stateMachineProcessDefinitionCollectionRequestTransfer): StateMachineProcessDefinitionCollectionResponseTransfer {
            return $this->executeDeleteStateMachineProcessDefinitionCollectionTransaction($stateMachineProcessDefinitionCollectionRequestTransfer);
        });
    }

    protected function executeDeleteStateMachineProcessDefinitionCollectionTransaction(
        StateMachineProcessDefinitionCollectionRequestTransfer $stateMachineProcessDefinitionCollectionRequestTransfer
    ): StateMachineProcessDefinitionCollectionResponseTransfer {
        $stateMachineProcessDefinitionCollectionResponseTransfer = new StateMachineProcessDefinitionCollectionResponseTransfer();

        foreach ($stateMachineProcessDefinitionCollectionRequestTransfer->getStateMachineProcessDefinitions() as $stateMachineProcessDefinitionTransfer) {
            $this->deleteDefinition($stateMachineProcessDefinitionTransfer, $stateMachineProcessDefinitionCollectionResponseTransfer);
        }

        return $stateMachineProcessDefinitionCollectionResponseTransfer;
    }

    protected function deleteDefinition(
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer,
        StateMachineProcessDefinitionCollectionResponseTransfer $stateMachineProcessDefinitionCollectionResponseTransfer
    ): void {
        $idStateMachineProcessDefinition = $stateMachineProcessDefinitionTransfer->getIdStateMachineProcessDefinitionOrFail();

        $persistedStateMachineProcessDefinitionTransfer = $this->workflowRepository
            ->getStateMachineProcessDefinitionCollection(
                (new StateMachineProcessDefinitionCriteriaTransfer())
                    ->setStateMachineProcessDefinitionConditions(
                        (new StateMachineProcessDefinitionConditionsTransfer())
                            ->addIdStateMachineProcessDefinition($idStateMachineProcessDefinition),
                    ),
            )
            ->getStateMachineProcessDefinitions()
            ->getIterator()
            ->current() ?: null;

        if ($persistedStateMachineProcessDefinitionTransfer === null) {
            $this->addDeleteError($stateMachineProcessDefinitionCollectionResponseTransfer, static::ERROR_DEFINITION_NOT_FOUND, $idStateMachineProcessDefinition);

            return;
        }

        if ($persistedStateMachineProcessDefinitionTransfer->getStatus() === WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE) {
            $this->addDeleteError($stateMachineProcessDefinitionCollectionResponseTransfer, static::ERROR_DEFINITION_ACTIVE, $idStateMachineProcessDefinition);

            return;
        }

        if ($this->workflowRepository->hasInstancesForProcessDefinition($idStateMachineProcessDefinition)) {
            $this->addDeleteError($stateMachineProcessDefinitionCollectionResponseTransfer, static::ERROR_DEFINITION_HAS_INSTANCES, $idStateMachineProcessDefinition);

            return;
        }

        $this->workflowEntityManager->deleteStateMachineProcessDefinition($persistedStateMachineProcessDefinitionTransfer);
        $stateMachineProcessDefinitionCollectionResponseTransfer->addStateMachineProcessDefinition($persistedStateMachineProcessDefinitionTransfer);
    }

    protected function addDeleteError(
        StateMachineProcessDefinitionCollectionResponseTransfer $stateMachineProcessDefinitionCollectionResponseTransfer,
        string $message,
        int $idStateMachineProcessDefinition
    ): void {
        $stateMachineProcessDefinitionCollectionResponseTransfer->addError(
            (new ErrorTransfer())
                ->setMessage($message)
                ->setEntityIdentifier((string)$idStateMachineProcessDefinition),
        );
    }

    /**
     * Activation is exclusive (single-active).
     *
     * @return void
     */
    protected function activateDefinition(
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer,
        StateMachineProcessDefinitionCollectionResponseTransfer $stateMachineProcessDefinitionCollectionResponseTransfer
    ): void {
        $stateMachineDefinitionValidationResponseTransfer = $this->definitionValidator->validate($stateMachineProcessDefinitionTransfer);

        if (!$stateMachineDefinitionValidationResponseTransfer->getIsValid()) {
            $this->addValidationErrors($stateMachineDefinitionValidationResponseTransfer, $stateMachineProcessDefinitionCollectionResponseTransfer);
            $stateMachineProcessDefinitionCollectionResponseTransfer->addStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);

            return;
        }

        $this->workflowEntityManager->deactivateStateMachineProcessDefinitions(
            (new StateMachineProcessDefinitionConditionsTransfer())
                ->addIdStateMachineProcess($stateMachineProcessDefinitionTransfer->getStateMachineProcessOrFail()->getIdStateMachineProcessOrFail()),
        );

        $stateMachineProcessDefinitionTransfer->setStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE);
        $this->workflowEntityManager->updateStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);
        $stateMachineProcessDefinitionCollectionResponseTransfer->addStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);
    }

    protected function addValidationErrors(
        StateMachineDefinitionValidationResponseTransfer $stateMachineDefinitionValidationResponseTransfer,
        StateMachineProcessDefinitionCollectionResponseTransfer $stateMachineProcessDefinitionCollectionResponseTransfer
    ): void {
        foreach ($stateMachineDefinitionValidationResponseTransfer->getErrors() as $stateMachineDefinitionValidationErrorTransfer) {
            $stateMachineProcessDefinitionCollectionResponseTransfer->addError(
                (new ErrorTransfer())->setMessage($stateMachineDefinitionValidationErrorTransfer->getMessageOrFail()),
            );
        }
    }
}

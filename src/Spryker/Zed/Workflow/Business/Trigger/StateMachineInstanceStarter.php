<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Trigger;

use Generated\Shared\Transfer\ErrorTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerConditionsTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerTransfer;
use Generated\Shared\Transfer\StateMachineEventTriggerRequestTransfer;
use Generated\Shared\Transfer\StateMachineEventTriggerResponseTransfer;
use Generated\Shared\Transfer\StateMachineItemStateTransfer;
use Generated\Shared\Transfer\StateMachineProcessConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Spryker\Zed\Kernel\Persistence\EntityManager\TransactionTrait;
use Spryker\Zed\StateMachine\Business\StateMachineFacadeInterface;
use Spryker\Zed\Workflow\Persistence\WorkflowEntityManagerInterface;
use Spryker\Zed\Workflow\Persistence\WorkflowRepositoryInterface;
use Spryker\Zed\Workflow\WorkflowConfig;
use Throwable;

class StateMachineInstanceStarter implements StateMachineInstanceStarterInterface
{
    use TransactionTrait;

    public function __construct(
        protected WorkflowRepositoryInterface $workflowRepository,
        protected WorkflowEntityManagerInterface $workflowEntityManager,
        protected StateMachineFacadeInterface $stateMachineFacade
    ) {
    }

    public function startStateMachineInstance(
        StateMachineEventTriggerRequestTransfer $stateMachineEventTriggerRequestTransfer
    ): StateMachineEventTriggerResponseTransfer {
        $stateMachineEventTriggerResponseTransfer = (new StateMachineEventTriggerResponseTransfer())->setAffectedItemCount(0);

        $stateMachineDefinitionTriggerCollectionTransfer = $this->workflowRepository
            ->getStateMachineDefinitionTriggerCollection(
                (new StateMachineDefinitionTriggerCriteriaTransfer())
                    ->setStateMachineDefinitionTriggerConditions(
                        (new StateMachineDefinitionTriggerConditionsTransfer())
                            ->addEventName($stateMachineEventTriggerRequestTransfer->getEventNameOrFail()),
                    ),
            );

        foreach ($stateMachineDefinitionTriggerCollectionTransfer->getStateMachineDefinitionTriggers() as $stateMachineDefinitionTriggerTransfer) {
            $this->startInstanceForTrigger($stateMachineDefinitionTriggerTransfer, $stateMachineEventTriggerRequestTransfer->getIdentifierOrFail(), $stateMachineEventTriggerResponseTransfer);
        }

        return $stateMachineEventTriggerResponseTransfer;
    }

    protected function startInstanceForTrigger(
        StateMachineDefinitionTriggerTransfer $stateMachineDefinitionTriggerTransfer,
        int $identifier,
        StateMachineEventTriggerResponseTransfer $stateMachineEventTriggerResponseTransfer
    ): void {
        $idStateMachineProcess = $stateMachineDefinitionTriggerTransfer->getStateMachineProcessOrFail()->getIdStateMachineProcessOrFail();
        $stateMachineProcessTransfer = $this->getActiveStateMachineProcessTransfer($idStateMachineProcess, $stateMachineEventTriggerResponseTransfer);

        if ($stateMachineProcessTransfer === null) {
            return;
        }

        $stateMachineProcessDefinitionTransfer = $this->getActiveStateMachineProcessDefinition($idStateMachineProcess, $stateMachineEventTriggerResponseTransfer);

        if ($stateMachineProcessDefinitionTransfer === null) {
            return;
        }

        if ($this->workflowRepository->hasRunningInstanceForProcessAndIdentifier($idStateMachineProcess, $identifier)) {
            return;
        }

        try {
            $affectedCount = $this->getTransactionHandler()->handleTransaction(
                fn (): int => $this->startNewInstance(
                    $stateMachineProcessTransfer,
                    $stateMachineProcessDefinitionTransfer,
                    $identifier,
                ),
            );

            $stateMachineEventTriggerResponseTransfer->setAffectedItemCount(
                $stateMachineEventTriggerResponseTransfer->getAffectedItemCount() + $affectedCount,
            );
        } catch (Throwable $throwable) {
            $stateMachineEventTriggerResponseTransfer->addError(
                (new ErrorTransfer())
                    ->setMessage($throwable->getMessage())
                    ->setEntityIdentifier((string)$identifier),
            );
        }
    }

    protected function getActiveStateMachineProcessTransfer(
        int $idStateMachineProcess,
        StateMachineEventTriggerResponseTransfer $stateMachineEventTriggerResponseTransfer
    ): ?StateMachineProcessTransfer {
        $stateMachineProcessTransfer = $this->workflowRepository
            ->getStateMachineProcessCollection(
                (new StateMachineProcessCriteriaTransfer())
                    ->setStateMachineProcessConditions(
                        (new StateMachineProcessConditionsTransfer())
                            ->addIdStateMachineProcess($idStateMachineProcess),
                    ),
            )
            ->getStateMachineProcesses()->getIterator()->current() ?: null;

        if ($stateMachineProcessTransfer === null) {
            $stateMachineEventTriggerResponseTransfer->addError(
                (new ErrorTransfer())->setMessage('Could not resolve the matched process.'),
            );

            return null;
        }

        if ($stateMachineProcessTransfer->getStatus() !== WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE) {
            return null;
        }

        return $stateMachineProcessTransfer;
    }

    protected function getActiveStateMachineProcessDefinition(
        int $idStateMachineProcess,
        StateMachineEventTriggerResponseTransfer $stateMachineEventTriggerResponseTransfer
    ): ?StateMachineProcessDefinitionTransfer {
        $stateMachineProcessDefinitionTransfer = $this->workflowRepository
            ->getStateMachineProcessDefinitionCollection(
                (new StateMachineProcessDefinitionCriteriaTransfer())
                    ->setStateMachineProcessDefinitionConditions(
                        (new StateMachineProcessDefinitionConditionsTransfer())
                            ->addIdStateMachineProcess($idStateMachineProcess)
                            ->addStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE),
                    ),
            )
            ->getStateMachineProcessDefinitions()
            ->getIterator()
            ->current();

        if ($stateMachineProcessDefinitionTransfer === null) {
            $stateMachineEventTriggerResponseTransfer->addError(
                (new ErrorTransfer())
                    ->setMessage('No active version to start for the matched process.')
                    ->setEntityIdentifier((string)$idStateMachineProcess),
            );

            return null;
        }

        return $stateMachineProcessDefinitionTransfer;
    }

    protected function startNewInstance(
        StateMachineProcessTransfer $stateMachineProcessTransfer,
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer,
        int $identifier
    ): int {
        $this->workflowEntityManager->saveStateMachineProcessDefinitionInstance(
            (new StateMachineProcessDefinitionInstanceTransfer())
                ->setStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer)
                ->setIdentifier($identifier),
        );

        $affectedCount = $this->stateMachineFacade->triggerForNewStateMachineItem(
            (new StateMachineProcessTransfer())
                ->setStateMachineName($stateMachineProcessTransfer->getStateMachineNameOrFail())
                ->setProcessName($stateMachineProcessTransfer->getProcessNameOrFail())
                ->setVersion($stateMachineProcessDefinitionTransfer->getVersionOrFail()),
            $identifier,
        );

        $this->setInitialStateWhenNotAdvancedByOnEnter($stateMachineProcessTransfer, $stateMachineProcessDefinitionTransfer, $identifier);

        return $affectedCount;
    }

    /**
     * The engine's on-enter chain already persisted the instance's current state via the handler's
     * itemStateUpdated(), overwriting it here would clobber that progress. Only set the initial state
     * when the engine left the instance unset (initial state has no on-enter event).
     *
     * @param \Generated\Shared\Transfer\StateMachineProcessTransfer $stateMachineProcessTransfer
     * @param \Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
     * @param int $identifier
     */
    protected function setInitialStateWhenNotAdvancedByOnEnter(
        StateMachineProcessTransfer $stateMachineProcessTransfer,
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer,
        int $identifier
    ): void {
        if ($this->isInstanceStateSetByEngine($stateMachineProcessDefinitionTransfer, $identifier)) {
            return;
        }

        $initialStateName = $stateMachineProcessDefinitionTransfer->getInitialStateOrFail();
        $idStateMachineItemState = $this->workflowRepository
            ->findIdStateMachineItemStateByProcessAndStateName(
                $stateMachineProcessTransfer->getIdStateMachineProcessOrFail(),
                $initialStateName,
            );

        if ($idStateMachineItemState === null) {
            return;
        }

        $this->workflowEntityManager->updateStateMachineProcessDefinitionInstance(
            (new StateMachineProcessDefinitionInstanceTransfer())
                ->setIdentifier($identifier)
                ->setStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer)
                ->setStateMachineItemState((new StateMachineItemStateTransfer())->setIdItemState($idStateMachineItemState)),
        );
    }

    protected function isInstanceStateSetByEngine(
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer,
        int $identifier
    ): bool {
        $stateMachineProcessDefinitionInstanceTransfer = $this->workflowRepository
            ->getStateMachineProcessDefinitionInstanceCollection(
                (new StateMachineProcessDefinitionInstanceCriteriaTransfer())
                    ->setStateMachineProcessDefinitionInstanceConditions(
                        (new StateMachineProcessDefinitionInstanceConditionsTransfer())
                            ->addIdStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer->getIdStateMachineProcessDefinitionOrFail())
                            ->addIdentifier($identifier)
                            ->setWithStateMachineItemState(true),
                    ),
            )
            ->getStateMachineProcessDefinitionInstances()
            ->getIterator()
            ->current();

        $stateMachineItemStateTransfer = $stateMachineProcessDefinitionInstanceTransfer?->getStateMachineItemState();

        return $stateMachineItemStateTransfer !== null && $stateMachineItemStateTransfer->getIdItemState() !== null;
    }
}

<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Trigger;

use Generated\Shared\Transfer\ErrorTransfer;
use Generated\Shared\Transfer\StateMachineEventTriggerRequestTransfer;
use Generated\Shared\Transfer\StateMachineEventTriggerResponseTransfer;
use Generated\Shared\Transfer\StateMachineItemTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceCriteriaTransfer;
use Spryker\Zed\StateMachine\Business\StateMachineFacadeInterface;
use Spryker\Zed\Workflow\Persistence\WorkflowRepositoryInterface;
use Throwable;

class StateMachineInstanceEventTrigger implements StateMachineInstanceEventTriggerInterface
{
    public function __construct(
        protected WorkflowRepositoryInterface $workflowRepository,
        protected StateMachineFacadeInterface $stateMachineFacade
    ) {
    }

    public function triggerTransitionEventForRunningInstance(
        StateMachineEventTriggerRequestTransfer $stateMachineEventTriggerRequestTransfer
    ): StateMachineEventTriggerResponseTransfer {
        $stateMachineEventTriggerResponseTransfer = (new StateMachineEventTriggerResponseTransfer())->setAffectedItemCount(0);

        $identifier = $stateMachineEventTriggerRequestTransfer->getIdentifierOrFail();
        $stateMachineProcessDefinitionInstanceCollectionTransfer = $this->workflowRepository->getStateMachineProcessDefinitionInstanceCollection(
            (new StateMachineProcessDefinitionInstanceCriteriaTransfer())
                ->setStateMachineProcessDefinitionInstanceConditions(
                    (new StateMachineProcessDefinitionInstanceConditionsTransfer())
                        ->addIdStateMachineProcessDefinition(
                            $stateMachineEventTriggerRequestTransfer->getStateMachineProcessDefinitionOrFail()->getIdStateMachineProcessDefinitionOrFail(),
                        )
                        ->addIdentifier($identifier)
                        ->setWithStateMachineItemState(true)
                        ->setWithStateMachineProcessDefinition(true),
                ),
        );

        $stateMachineProcessDefinitionInstanceTransfer = $stateMachineProcessDefinitionInstanceCollectionTransfer->getStateMachineProcessDefinitionInstances()->getIterator()->current();
        $stateMachineItemStateTransfer = $stateMachineProcessDefinitionInstanceTransfer?->getStateMachineItemState();

        if ($stateMachineItemStateTransfer === null || $stateMachineItemStateTransfer->getIdItemState() === null) {
            return $stateMachineEventTriggerResponseTransfer->addError(
                (new ErrorTransfer())
                    ->setMessage('No running instance with a current state found for this version.')
                    ->setEntityIdentifier((string)$identifier),
            );
        }

        return $this->triggerStateMachineEvent(
            (new StateMachineItemTransfer())
                ->setIdentifier($identifier)
                ->setEventName($stateMachineEventTriggerRequestTransfer->getEventNameOrFail())
                ->setIdItemState($stateMachineItemStateTransfer->getIdItemStateOrFail())
                ->setVersion($stateMachineProcessDefinitionInstanceTransfer->getStateMachineProcessDefinitionOrFail()->getVersion()),
            $stateMachineEventTriggerResponseTransfer,
        );
    }

    protected function triggerStateMachineEvent(
        StateMachineItemTransfer $stateMachineItemTransfer,
        StateMachineEventTriggerResponseTransfer $stateMachineEventTriggerResponseTransfer
    ): StateMachineEventTriggerResponseTransfer {
        try {
            $affectedCount = $this->stateMachineFacade->triggerEvent(
                $stateMachineItemTransfer->getEventNameOrFail(),
                $stateMachineItemTransfer,
            );

            return $stateMachineEventTriggerResponseTransfer->setAffectedItemCount($affectedCount);
        } catch (Throwable $throwable) {
            return $stateMachineEventTriggerResponseTransfer->addError(
                (new ErrorTransfer())
                    ->setMessage($throwable->getMessage())
                    ->setEntityIdentifier((string)$stateMachineItemTransfer->getIdentifierOrFail()),
            );
        }
    }
}

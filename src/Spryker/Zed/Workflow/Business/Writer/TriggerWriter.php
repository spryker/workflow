<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Writer;

use Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionResponseTransfer;
use Spryker\Zed\Kernel\Persistence\EntityManager\TransactionTrait;
use Spryker\Zed\Workflow\Persistence\WorkflowEntityManagerInterface;

class TriggerWriter implements TriggerWriterInterface
{
    use TransactionTrait;

    public function __construct(
        protected WorkflowEntityManagerInterface $workflowEntityManager
    ) {
    }

    public function updateStateMachineDefinitionTriggerCollection(
        StateMachineDefinitionTriggerCollectionRequestTransfer $stateMachineDefinitionTriggerCollectionRequestTransfer
    ): StateMachineDefinitionTriggerCollectionResponseTransfer {
        return $this->getTransactionHandler()->handleTransaction(function () use ($stateMachineDefinitionTriggerCollectionRequestTransfer): StateMachineDefinitionTriggerCollectionResponseTransfer {
            return $this->executeUpdateStateMachineDefinitionTriggerCollectionTransaction($stateMachineDefinitionTriggerCollectionRequestTransfer);
        });
    }

    protected function executeUpdateStateMachineDefinitionTriggerCollectionTransaction(
        StateMachineDefinitionTriggerCollectionRequestTransfer $stateMachineDefinitionTriggerCollectionRequestTransfer
    ): StateMachineDefinitionTriggerCollectionResponseTransfer {
        $stateMachineDefinitionTriggerCollectionResponseTransfer = new StateMachineDefinitionTriggerCollectionResponseTransfer();

        foreach ($stateMachineDefinitionTriggerCollectionRequestTransfer->getStateMachineDefinitionTriggersToRemove() as $stateMachineDefinitionTriggerTransfer) {
            $this->workflowEntityManager->deleteStateMachineDefinitionTrigger($stateMachineDefinitionTriggerTransfer);
        }

        foreach ($stateMachineDefinitionTriggerCollectionRequestTransfer->getStateMachineDefinitionTriggersToAdd() as $stateMachineDefinitionTriggerTransfer) {
            $stateMachineDefinitionTriggerCollectionResponseTransfer->addStateMachineDefinitionTrigger(
                $this->workflowEntityManager->saveStateMachineDefinitionTrigger($stateMachineDefinitionTriggerTransfer),
            );
        }

        return $stateMachineDefinitionTriggerCollectionResponseTransfer;
    }
}

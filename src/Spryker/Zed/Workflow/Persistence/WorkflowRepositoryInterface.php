<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Persistence;

use Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessCollectionTransfer;
use Generated\Shared\Transfer\StateMachineProcessCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceCollectionTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceCriteriaTransfer;

interface WorkflowRepositoryInterface
{
    public function getStateMachineProcessCollection(
        StateMachineProcessCriteriaTransfer $stateMachineProcessCriteriaTransfer
    ): StateMachineProcessCollectionTransfer;

    public function getStateMachineProcessDefinitionCollection(
        StateMachineProcessDefinitionCriteriaTransfer $stateMachineProcessDefinitionCriteriaTransfer
    ): StateMachineProcessDefinitionCollectionTransfer;

    public function getStateMachineProcessDefinitionInstanceCollection(
        StateMachineProcessDefinitionInstanceCriteriaTransfer $stateMachineProcessDefinitionInstanceCriteriaTransfer
    ): StateMachineProcessDefinitionInstanceCollectionTransfer;

    public function getStateMachineDefinitionTriggerCollection(
        StateMachineDefinitionTriggerCriteriaTransfer $stateMachineDefinitionTriggerCriteriaTransfer
    ): StateMachineDefinitionTriggerCollectionTransfer;

    /**
     * @return array<string>
     */
    public function getDatabaseStateMachineNames(): array;

    public function findMaxVersionStateMachineProcessDefinition(
        StateMachineProcessCriteriaTransfer $stateMachineProcessCriteriaTransfer
    ): ?int;

    public function hasRunningInstanceForProcessAndIdentifier(int $idStateMachineProcess, int $identifier): bool;

    public function hasInstancesForProcessDefinition(int $idStateMachineProcessDefinition): bool;

    public function findIdStateMachineItemStateByProcessAndStateName(int $idStateMachineProcess, string $stateName): ?int;
}

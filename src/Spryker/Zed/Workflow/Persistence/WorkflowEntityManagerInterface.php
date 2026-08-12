<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Persistence;

use Generated\Shared\Transfer\StateMachineDefinitionTriggerTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;

interface WorkflowEntityManagerInterface
{
    public function createStateMachineProcess(
        StateMachineProcessTransfer $stateMachineProcessTransfer
    ): StateMachineProcessTransfer;

    public function updateStateMachineProcess(
        StateMachineProcessTransfer $stateMachineProcessTransfer
    ): StateMachineProcessTransfer;

    public function saveStateMachineProcessDefinition(
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
    ): StateMachineProcessDefinitionTransfer;

    public function updateStateMachineProcessDefinition(
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
    ): void;

    public function deactivateStateMachineProcessDefinitions(
        StateMachineProcessDefinitionConditionsTransfer $stateMachineProcessDefinitionConditionsTransfer
    ): void;

    public function saveStateMachineProcessDefinitionInstance(
        StateMachineProcessDefinitionInstanceTransfer $stateMachineProcessDefinitionInstanceTransfer
    ): StateMachineProcessDefinitionInstanceTransfer;

    public function updateStateMachineProcessDefinitionInstance(
        StateMachineProcessDefinitionInstanceTransfer $stateMachineProcessDefinitionInstanceTransfer
    ): void;

    public function saveStateMachineDefinitionTrigger(
        StateMachineDefinitionTriggerTransfer $stateMachineDefinitionTriggerTransfer
    ): StateMachineDefinitionTriggerTransfer;

    public function deleteStateMachineDefinitionTrigger(
        StateMachineDefinitionTriggerTransfer $stateMachineDefinitionTriggerTransfer
    ): void;

    public function deleteStateMachineProcessDefinition(
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
    ): void;
}

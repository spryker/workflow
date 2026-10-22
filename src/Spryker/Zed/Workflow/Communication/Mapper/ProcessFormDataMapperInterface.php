<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Mapper;

use Generated\Shared\Transfer\StateMachineProcessCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;

interface ProcessFormDataMapperInterface
{
    /**
     * @param array<string, mixed> $processFormData
     */
    public function mapProcessFormDataToStateMachineProcessTransfer(
        array $processFormData,
        StateMachineProcessTransfer $stateMachineProcessTransfer,
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
    ): StateMachineProcessTransfer;

    /**
     * @param array<string, mixed> $processEditFormData
     * @param array<string, \Generated\Shared\Transfer\StateMachineDefinitionTriggerTransfer> $attachedStateMachineDefinitionTriggerTransfers
     */
    public function mapProcessEditFormDataToStateMachineProcessCollectionRequestTransfer(
        array $processEditFormData,
        array $attachedStateMachineDefinitionTriggerTransfers,
        StateMachineProcessTransfer $stateMachineProcessTransfer,
        StateMachineProcessCollectionRequestTransfer $stateMachineProcessCollectionRequestTransfer
    ): StateMachineProcessCollectionRequestTransfer;

    /**
     * @param array<string, string|null> $stateMachineDefinitionFormData
     */
    public function mapStateMachineDefinitionFormDataToStateMachineProcessDefinitionTransfer(
        array $stateMachineDefinitionFormData,
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
    ): StateMachineProcessDefinitionTransfer;
}

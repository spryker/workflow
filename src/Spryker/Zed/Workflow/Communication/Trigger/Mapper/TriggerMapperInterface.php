<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Trigger\Mapper;

use Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionTransfer;

interface TriggerMapperInterface
{
    /**
     * @param array<string, mixed> $formData
     * @param int $idStateMachineProcess
     * @param \Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionTransfer $persistedStateMachineDefinitionTriggerCollectionTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionRequestTransfer
     */
    public function mapFormDataToTriggerCollectionRequestTransfer(
        array $formData,
        int $idStateMachineProcess,
        StateMachineDefinitionTriggerCollectionTransfer $persistedStateMachineDefinitionTriggerCollectionTransfer
    ): StateMachineDefinitionTriggerCollectionRequestTransfer;
}

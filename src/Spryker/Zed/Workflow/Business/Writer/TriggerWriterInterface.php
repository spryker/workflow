<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Writer;

use Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionResponseTransfer;

interface TriggerWriterInterface
{
    public function updateStateMachineDefinitionTriggerCollection(
        StateMachineDefinitionTriggerCollectionRequestTransfer $stateMachineDefinitionTriggerCollectionRequestTransfer
    ): StateMachineDefinitionTriggerCollectionResponseTransfer;
}

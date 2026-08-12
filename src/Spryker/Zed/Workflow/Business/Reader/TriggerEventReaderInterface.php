<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Reader;

use Generated\Shared\Transfer\StateMachineTriggerEventCollectionTransfer;
use Generated\Shared\Transfer\StateMachineTriggerEventCriteriaTransfer;

interface TriggerEventReaderInterface
{
    public function getStateMachineTriggerEventCollection(
        StateMachineTriggerEventCriteriaTransfer $stateMachineTriggerEventCriteriaTransfer
    ): StateMachineTriggerEventCollectionTransfer;
}

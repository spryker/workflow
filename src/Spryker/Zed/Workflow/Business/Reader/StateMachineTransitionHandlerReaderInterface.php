<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Reader;

use Generated\Shared\Transfer\StateMachineTransitionHandlerCollectionTransfer;
use Generated\Shared\Transfer\StateMachineTransitionHandlerCriteriaTransfer;

interface StateMachineTransitionHandlerReaderInterface
{
    public function getStateMachineTransitionHandlerCollection(
        StateMachineTransitionHandlerCriteriaTransfer $stateMachineTransitionHandlerCriteriaTransfer
    ): StateMachineTransitionHandlerCollectionTransfer;
}

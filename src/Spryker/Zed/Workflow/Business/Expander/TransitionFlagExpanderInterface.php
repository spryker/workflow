<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Expander;

use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;

interface TransitionFlagExpanderInterface
{
    public function expandWithTransitionFlags(
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
    ): StateMachineProcessDefinitionTransfer;
}

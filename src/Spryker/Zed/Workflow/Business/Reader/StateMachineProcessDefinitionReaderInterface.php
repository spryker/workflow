<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Reader;

use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;

interface StateMachineProcessDefinitionReaderInterface
{
    /**
     * Final (end) state names of a single definition: states that are not the source of any transition.
     *
     * @return array<string>
     */
    public function getStateMachineProcessDefinitionFinalStateNames(
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
    ): array;
}

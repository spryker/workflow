<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Reader;

use Generated\Shared\Transfer\StateMachineProcessConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Spryker\Zed\Workflow\Business\WorkflowFacadeInterface;

class StateMachineProcessReader implements StateMachineProcessReaderInterface
{
    public function __construct(protected WorkflowFacadeInterface $workflowFacade)
    {
    }

    public function findStateMachineProcess(int $idStateMachineProcess): ?StateMachineProcessTransfer
    {
        return $this->workflowFacade->getStateMachineProcessCollection(
            (new StateMachineProcessCriteriaTransfer())->setStateMachineProcessConditions(
                (new StateMachineProcessConditionsTransfer())->addIdStateMachineProcess($idStateMachineProcess),
            ),
        )->getStateMachineProcesses()->getIterator()->current() ?: null;
    }
}

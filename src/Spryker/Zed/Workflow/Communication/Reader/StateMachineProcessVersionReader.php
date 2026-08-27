<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Reader;

use Generated\Shared\Transfer\StateMachineProcessDefinitionConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Spryker\Zed\Workflow\Business\WorkflowFacadeInterface;

class StateMachineProcessVersionReader implements StateMachineProcessVersionReaderInterface
{
    public function __construct(protected WorkflowFacadeInterface $workflowFacade)
    {
    }

    public function findStateMachineProcessDefinition(int $idStateMachineProcessDefinition): ?StateMachineProcessDefinitionTransfer
    {
        return $this->workflowFacade->getStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCriteriaTransfer())->setStateMachineProcessDefinitionConditions(
                (new StateMachineProcessDefinitionConditionsTransfer())->addIdStateMachineProcessDefinition($idStateMachineProcessDefinition),
            ),
        )->getStateMachineProcessDefinitions()->getIterator()->current() ?: null;
    }
}

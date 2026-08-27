<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Reader;

use ArrayObject;
use Generated\Shared\Transfer\StateMachineProcessConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessCriteriaTransfer;
use Spryker\Zed\Workflow\Persistence\WorkflowRepositoryInterface;

class StateMachineProcessExistenceReader implements StateMachineProcessExistenceReaderInterface
{
    public function __construct(protected WorkflowRepositoryInterface $workflowRepository)
    {
    }

    /**
     * {@inheritDoc}
     *
     * @param \ArrayObject<int, \Generated\Shared\Transfer\StateMachineProcessTransfer> $stateMachineProcessTransfers
     *
     * @return array<int, true>
     */
    public function getExistingStateMachineProcessIds(ArrayObject $stateMachineProcessTransfers): array
    {
        $stateMachineProcessIds = [];
        foreach ($stateMachineProcessTransfers as $stateMachineProcessTransfer) {
            $stateMachineProcessIds[] = $stateMachineProcessTransfer->getIdStateMachineProcessOrFail();
        }

        if ($stateMachineProcessIds === []) {
            return [];
        }

        $stateMachineProcessConditionsTransfer = (new StateMachineProcessConditionsTransfer())
            ->setStateMachineProcessIds(array_values(array_unique($stateMachineProcessIds)));

        $existingStateMachineProcessIds = [];
        foreach ($this->getStateMachineProcesses($stateMachineProcessConditionsTransfer) as $stateMachineProcessTransfer) {
            $existingStateMachineProcessIds[$stateMachineProcessTransfer->getIdStateMachineProcessOrFail()] = true;
        }

        return $existingStateMachineProcessIds;
    }

    /**
     * {@inheritDoc}
     *
     * @param \ArrayObject<int, \Generated\Shared\Transfer\StateMachineProcessTransfer> $stateMachineProcessTransfers
     *
     * @return array<string, true>
     */
    public function getTakenStateMachineNames(ArrayObject $stateMachineProcessTransfers): array
    {
        $stateMachineNames = [];
        foreach ($stateMachineProcessTransfers as $stateMachineProcessTransfer) {
            $stateMachineNames[] = $stateMachineProcessTransfer->getStateMachineNameOrFail();
        }

        if ($stateMachineNames === []) {
            return [];
        }

        $stateMachineProcessConditionsTransfer = (new StateMachineProcessConditionsTransfer())
            ->setStateMachineNames(array_values(array_unique($stateMachineNames)));

        $takenStateMachineNames = [];
        foreach ($this->getStateMachineProcesses($stateMachineProcessConditionsTransfer) as $stateMachineProcessTransfer) {
            $takenStateMachineNames[$stateMachineProcessTransfer->getStateMachineNameOrFail()] = true;
        }

        return $takenStateMachineNames;
    }

    /**
     * @return \ArrayObject<int, \Generated\Shared\Transfer\StateMachineProcessTransfer>
     */
    protected function getStateMachineProcesses(
        StateMachineProcessConditionsTransfer $stateMachineProcessConditionsTransfer
    ): ArrayObject {
        return $this->workflowRepository
            ->getStateMachineProcessCollection(
                (new StateMachineProcessCriteriaTransfer())
                    ->setStateMachineProcessConditions($stateMachineProcessConditionsTransfer),
            )
            ->getStateMachineProcesses();
    }
}

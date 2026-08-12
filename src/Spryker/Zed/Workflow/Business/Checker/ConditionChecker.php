<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Checker;

use Spryker\Shared\Log\LoggerTrait;
use Spryker\Zed\StateMachine\Business\StateMachineFacadeInterface;
use Spryker\Zed\Workflow\Persistence\WorkflowRepositoryInterface;
use Throwable;

class ConditionChecker implements TransitionCheckerInterface
{
    use LoggerTrait;

    public function __construct(
        protected WorkflowRepositoryInterface $workflowRepository,
        protected StateMachineFacadeInterface $stateMachineFacade
    ) {
    }

    public function check(): int
    {
        $affectedItems = 0;
        foreach ($this->workflowRepository->getDatabaseStateMachineNames() as $stateMachineName) {
            $affectedItems += $this->checkStateMachineConditions($stateMachineName);
        }

        return $affectedItems;
    }

    /**
     * Isolates each state machine: a broken one (e.g. an instance pinned to a missing definition version)
     * is logged and skipped so it cannot abort the whole cron run for the healthy state machines.
     */
    protected function checkStateMachineConditions(string $stateMachineName): int
    {
        try {
            return $this->stateMachineFacade->checkConditions($stateMachineName);
        } catch (Throwable $throwable) {
            $this->getLogger()->error(
                sprintf('Condition check skipped for state machine "%s": %s', $stateMachineName, $throwable->getMessage()),
                ['exception' => $throwable],
            );

            return 0;
        }
    }
}

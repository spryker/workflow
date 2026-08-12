<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Plugin\StateMachine;

use Generated\Shared\Transfer\StateMachineProcessConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessCriteriaTransfer;
use Spryker\Zed\Kernel\Communication\AbstractPlugin;
use Spryker\Zed\StateMachine\Dependency\Plugin\StateMachineHandlerInterface;
use Spryker\Zed\StateMachine\Dependency\Plugin\StateMachineHandlerResolverPluginInterface;

/**
 * @method \Spryker\Zed\Workflow\Business\WorkflowFacadeInterface getFacade()
 * @method \Spryker\Zed\Workflow\Communication\WorkflowCommunicationFactory getFactory()
 * @method \Spryker\Zed\Workflow\Business\WorkflowBusinessFactory getBusinessFactory()
 */
class WorkflowHandlerResolverPlugin extends AbstractPlugin implements StateMachineHandlerResolverPluginInterface
{
    /**
     * {@inheritDoc}
     * - Resolves the active spy_state_machine_process by the provided state machine name and, when
     *   found, returns one PersistentStateMachineHandler bound to that process.
     * - Returns null for any name not owned by a (DB-authored) process, so the core
     *   resolver can keep trying other plugins.
     *
     * @api
     *
     * @param string $stateMachineName
     *
     * @return \Spryker\Zed\StateMachine\Dependency\Plugin\StateMachineHandlerInterface|null
     */
    public function resolveStateMachineHandler(string $stateMachineName): ?StateMachineHandlerInterface
    {
        $stateMachineProcessTransfer = $this->getFacade()
            ->getStateMachineProcessCollection(
                (new StateMachineProcessCriteriaTransfer())
                    ->setStateMachineProcessConditions(
                        (new StateMachineProcessConditionsTransfer())->addStateMachineName($stateMachineName),
                    ),
            )
            ->getStateMachineProcesses()
            ->getIterator()
            ->current();

        if ($stateMachineProcessTransfer === null) {
            return null;
        }

        return new PersistentStateMachineHandlerPlugin(
            $stateMachineProcessTransfer,
            $this->getBusinessFactory()->createPersistentStateMachineHandler(),
        );
    }
}

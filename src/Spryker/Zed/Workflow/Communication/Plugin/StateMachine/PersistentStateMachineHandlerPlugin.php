<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Plugin\StateMachine;

use Generated\Shared\Transfer\StateMachineItemTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Spryker\Zed\Kernel\Communication\AbstractPlugin;
use Spryker\Zed\StateMachine\Business\Exception\StateMachineException;
use Spryker\Zed\StateMachine\Dependency\Plugin\PersistentStateMachineHandlerInterface;
use Spryker\Zed\Workflow\Business\StateMachine\PersistentStateMachineHandler;

/**
 * @method \Spryker\Zed\Workflow\Business\WorkflowFacadeInterface getFacade()
 * @method \Spryker\Zed\Workflow\Communication\WorkflowCommunicationFactory getFactory()
 * @method \Spryker\Zed\Workflow\Business\WorkflowBusinessFactory getBusinessFactory()
 */
class PersistentStateMachineHandlerPlugin extends AbstractPlugin implements PersistentStateMachineHandlerInterface
{
    public function __construct(
        protected StateMachineProcessTransfer $stateMachineProcessTransfer,
        protected PersistentStateMachineHandler $persistentStateMachineHandler
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return array<\Spryker\Zed\StateMachine\Dependency\Plugin\CommandPluginInterface>
     */
    public function getCommandPlugins()
    {
        return $this->persistentStateMachineHandler->getCommandPlugins($this->stateMachineProcessTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return array<\Spryker\Zed\StateMachine\Dependency\Plugin\ConditionPluginInterface>
     */
    public function getConditionPlugins()
    {
        return $this->persistentStateMachineHandler->getConditionPlugins($this->stateMachineProcessTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function getStateMachineName()
    {
        return $this->stateMachineProcessTransfer->getStateMachineNameOrFail();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return array<string>
     */
    public function getActiveProcesses()
    {
        return [$this->stateMachineProcessTransfer->getProcessNameOrFail()];
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return array<\Generated\Shared\Transfer\StateMachineProcessTransfer>
     */
    public function getProcessesForConditionCheck(): array
    {
        return $this->persistentStateMachineHandler->getProcessesForConditionCheck($this->stateMachineProcessTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @throws \Spryker\Zed\StateMachine\Business\Exception\StateMachineException
     */
    public function getInitialStateForProcess($processName)
    {
        throw new StateMachineException(
            sprintf(
                'Use %s::getInitialStateForPersistentProcess() for PersistentStateMachineHandlerInterface-authored processes; the version-less '
                    . '%s::getInitialStateForProcess() cannot resolve the correct version.',
                static::class,
                static::class,
            ),
        );
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function getInitialStateForPersistentProcess(StateMachineProcessTransfer $stateMachineProcessTransfer): string
    {
        $stateMachineProcessTransfer->setIdStateMachineProcess($this->stateMachineProcessTransfer->getIdStateMachineProcessOrFail());

        return $this->persistentStateMachineHandler->getInitialStateForPersistentProcess($stateMachineProcessTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function getDefinitionXmlForPersistentProcess(StateMachineProcessTransfer $stateMachineProcessTransfer): string
    {
        $stateMachineProcessTransfer->setIdStateMachineProcess($this->stateMachineProcessTransfer->getIdStateMachineProcessOrFail());

        return $this->persistentStateMachineHandler->getDefinitionXmlForPersistentProcess($stateMachineProcessTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function itemStateUpdated(StateMachineItemTransfer $stateMachineItemTransfer)
    {
        return $this->persistentStateMachineHandler->itemStateUpdated($this->stateMachineProcessTransfer, $stateMachineItemTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param array<int> $stateIds
     *
     * @throws \Spryker\Zed\StateMachine\Business\Exception\StateMachineException
     *
     * @return array<\Generated\Shared\Transfer\StateMachineItemTransfer>
     */
    public function getStateMachineItemsByStateIds(array $stateIds = [])
    {
        throw new StateMachineException(
            sprintf(
                'Use %s::getStateMachineItemsForPersistentProcess() for PersistentStateMachineHandlerInterface-authored processes; the version-less '
                    . '%s::getStateMachineItemsByStateIds() cannot resolve the correct version.',
                static::class,
                static::class,
            ),
        );
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineProcessTransfer $stateMachineProcessTransfer
     * @param array<int> $stateIds
     *
     * @return array<\Generated\Shared\Transfer\StateMachineItemTransfer>
     */
    public function getStateMachineItemsForPersistentProcessByStateIds(
        StateMachineProcessTransfer $stateMachineProcessTransfer,
        array $stateIds = []
    ): array {
        $stateMachineProcessTransfer->setIdStateMachineProcess($this->stateMachineProcessTransfer->getIdStateMachineProcessOrFail());

        return $this->persistentStateMachineHandler->getStateMachineItemsForPersistentProcessByStateIds($stateMachineProcessTransfer, $stateIds);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param array<\Generated\Shared\Transfer\StateMachineItemTransfer> $stateMachineItemTransfers
     *
     * @return array<\Generated\Shared\Transfer\StateMachineItemTransfer>
     */
    public function expandTimeoutItemsWithVersion(array $stateMachineItemTransfers): array
    {
        return $this->persistentStateMachineHandler->expandTimeoutItemsWithVersion($stateMachineItemTransfers);
    }
}

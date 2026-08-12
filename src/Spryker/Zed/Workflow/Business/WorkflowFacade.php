<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business;

use Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionResponseTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionValidationResponseTransfer;
use Generated\Shared\Transfer\StateMachineEventTriggerRequestTransfer;
use Generated\Shared\Transfer\StateMachineEventTriggerResponseTransfer;
use Generated\Shared\Transfer\StateMachineProcessCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessCollectionResponseTransfer;
use Generated\Shared\Transfer\StateMachineProcessCollectionTransfer;
use Generated\Shared\Transfer\StateMachineProcessCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionResponseTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceCollectionTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Generated\Shared\Transfer\StateMachineTriggerEventCollectionTransfer;
use Generated\Shared\Transfer\StateMachineTriggerEventCriteriaTransfer;
use Spryker\Zed\Kernel\Business\AbstractFacade;

/**
 * @method \Spryker\Zed\Workflow\Business\WorkflowBusinessFactory getFactory()
 * @method \Spryker\Zed\Workflow\Persistence\WorkflowRepositoryInterface getRepository()
 * @method \Spryker\Zed\Workflow\Persistence\WorkflowEntityManagerInterface getEntityManager()
 */
class WorkflowFacade extends AbstractFacade implements WorkflowFacadeInterface
{
    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineProcessCriteriaTransfer $stateMachineProcessCriteriaTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineProcessCollectionTransfer
     */
    public function getStateMachineProcessCollection(
        StateMachineProcessCriteriaTransfer $stateMachineProcessCriteriaTransfer
    ): StateMachineProcessCollectionTransfer {
        return $this->getRepository()->getStateMachineProcessCollection($stateMachineProcessCriteriaTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineProcessCollectionRequestTransfer $stateMachineProcessCollectionRequestTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineProcessCollectionResponseTransfer
     */
    public function createStateMachineProcessCollection(
        StateMachineProcessCollectionRequestTransfer $stateMachineProcessCollectionRequestTransfer
    ): StateMachineProcessCollectionResponseTransfer {
        return $this->getFactory()
            ->createProcessWriter()
            ->createStateMachineProcessCollection($stateMachineProcessCollectionRequestTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineProcessCollectionRequestTransfer $stateMachineProcessCollectionRequestTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineProcessCollectionResponseTransfer
     */
    public function updateStateMachineProcessCollection(
        StateMachineProcessCollectionRequestTransfer $stateMachineProcessCollectionRequestTransfer
    ): StateMachineProcessCollectionResponseTransfer {
        return $this->getFactory()
            ->createProcessWriter()
            ->updateStateMachineProcessCollection($stateMachineProcessCollectionRequestTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineProcessDefinitionCriteriaTransfer $stateMachineProcessDefinitionCriteriaTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionTransfer
     */
    public function getStateMachineProcessDefinitionCollection(
        StateMachineProcessDefinitionCriteriaTransfer $stateMachineProcessDefinitionCriteriaTransfer
    ): StateMachineProcessDefinitionCollectionTransfer {
        return $this->getRepository()->getStateMachineProcessDefinitionCollection($stateMachineProcessDefinitionCriteriaTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionRequestTransfer $stateMachineProcessDefinitionCollectionRequestTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionResponseTransfer
     */
    public function createStateMachineProcessDefinitionCollection(
        StateMachineProcessDefinitionCollectionRequestTransfer $stateMachineProcessDefinitionCollectionRequestTransfer
    ): StateMachineProcessDefinitionCollectionResponseTransfer {
        return $this->getFactory()
            ->createDefinitionWriter()
            ->createStateMachineProcessDefinitionCollection($stateMachineProcessDefinitionCollectionRequestTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineDefinitionValidationResponseTransfer
     */
    public function validateStateMachineProcessDefinition(
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
    ): StateMachineDefinitionValidationResponseTransfer {
        return $this->getFactory()
            ->createDefinitionValidator()
            ->validate($stateMachineProcessDefinitionTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionRequestTransfer $stateMachineProcessDefinitionCollectionRequestTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionResponseTransfer
     */
    public function updateStateMachineProcessDefinitionCollection(
        StateMachineProcessDefinitionCollectionRequestTransfer $stateMachineProcessDefinitionCollectionRequestTransfer
    ): StateMachineProcessDefinitionCollectionResponseTransfer {
        return $this->getFactory()
            ->createDefinitionWriter()
            ->updateStateMachineProcessDefinitionCollection($stateMachineProcessDefinitionCollectionRequestTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionRequestTransfer $stateMachineProcessDefinitionCollectionRequestTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionResponseTransfer
     */
    public function deleteStateMachineProcessDefinitionCollection(
        StateMachineProcessDefinitionCollectionRequestTransfer $stateMachineProcessDefinitionCollectionRequestTransfer
    ): StateMachineProcessDefinitionCollectionResponseTransfer {
        return $this->getFactory()
            ->createDefinitionWriter()
            ->deleteStateMachineProcessDefinitionCollection($stateMachineProcessDefinitionCollectionRequestTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceCriteriaTransfer $stateMachineProcessDefinitionInstanceCriteriaTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceCollectionTransfer
     */
    public function getStateMachineProcessDefinitionInstanceCollection(
        StateMachineProcessDefinitionInstanceCriteriaTransfer $stateMachineProcessDefinitionInstanceCriteriaTransfer
    ): StateMachineProcessDefinitionInstanceCollectionTransfer {
        return $this->getRepository()->getStateMachineProcessDefinitionInstanceCollection($stateMachineProcessDefinitionInstanceCriteriaTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineDefinitionTriggerCriteriaTransfer $stateMachineDefinitionTriggerCriteriaTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionTransfer
     */
    public function getStateMachineDefinitionTriggerCollection(
        StateMachineDefinitionTriggerCriteriaTransfer $stateMachineDefinitionTriggerCriteriaTransfer
    ): StateMachineDefinitionTriggerCollectionTransfer {
        return $this->getRepository()->getStateMachineDefinitionTriggerCollection($stateMachineDefinitionTriggerCriteriaTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineTriggerEventCriteriaTransfer $stateMachineTriggerEventCriteriaTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineTriggerEventCollectionTransfer
     */
    public function getStateMachineTriggerEventCollection(
        StateMachineTriggerEventCriteriaTransfer $stateMachineTriggerEventCriteriaTransfer
    ): StateMachineTriggerEventCollectionTransfer {
        return $this->getFactory()
            ->createTriggerEventReader()
            ->getStateMachineTriggerEventCollection($stateMachineTriggerEventCriteriaTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionRequestTransfer $stateMachineDefinitionTriggerCollectionRequestTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionResponseTransfer
     */
    public function updateStateMachineDefinitionTriggerCollection(
        StateMachineDefinitionTriggerCollectionRequestTransfer $stateMachineDefinitionTriggerCollectionRequestTransfer
    ): StateMachineDefinitionTriggerCollectionResponseTransfer {
        return $this->getFactory()
            ->createTriggerWriter()
            ->updateStateMachineDefinitionTriggerCollection($stateMachineDefinitionTriggerCollectionRequestTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineEventTriggerRequestTransfer $stateMachineEventTriggerRequestTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineEventTriggerResponseTransfer
     */
    public function startStateMachineInstance(
        StateMachineEventTriggerRequestTransfer $stateMachineEventTriggerRequestTransfer
    ): StateMachineEventTriggerResponseTransfer {
        return $this->getFactory()
            ->createStateMachineInstanceStarter()
            ->startStateMachineInstance($stateMachineEventTriggerRequestTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineEventTriggerRequestTransfer $stateMachineEventTriggerRequestTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineEventTriggerResponseTransfer
     */
    public function triggerStateMachineInstanceEvent(
        StateMachineEventTriggerRequestTransfer $stateMachineEventTriggerRequestTransfer
    ): StateMachineEventTriggerResponseTransfer {
        return $this->getFactory()
            ->createStateMachineInstanceEventTrigger()
            ->triggerTransitionEventForRunningInstance($stateMachineEventTriggerRequestTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return int
     */
    public function checkDynamicConditions(): int
    {
        return $this->getFactory()
            ->createConditionChecker()
            ->check();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return int
     */
    public function checkDynamicTimeouts(): int
    {
        return $this->getFactory()
            ->createTimeoutChecker()
            ->check();
    }
}

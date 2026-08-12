<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Persistence;

use Generated\Shared\Transfer\StateMachineDefinitionTriggerTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Orm\Zed\StateMachine\Persistence\SpyStateMachineProcessQuery;
use Spryker\Zed\Kernel\Persistence\AbstractEntityManager;
use Spryker\Zed\Workflow\WorkflowConfig;

/**
 * @method \Spryker\Zed\Workflow\Persistence\WorkflowPersistenceFactory getFactory()
 */
class WorkflowEntityManager extends AbstractEntityManager implements WorkflowEntityManagerInterface
{
    /**
     * {@inheritDoc}
     *
     * @module StateMachine
     */
    public function createStateMachineProcess(
        StateMachineProcessTransfer $stateMachineProcessTransfer
    ): StateMachineProcessTransfer {
        $stateMachineProcessEntity = SpyStateMachineProcessQuery::create()
            ->filterByName($stateMachineProcessTransfer->getProcessNameOrFail())
            ->filterByStateMachineName($stateMachineProcessTransfer->getStateMachineNameOrFail())
            ->findOneOrCreate();

        $stateMachineProcessEntity->fromArray($stateMachineProcessTransfer->modifiedToArray());
        $stateMachineProcessEntity->setType(WorkflowConfig::PROCESS_TYPE_DATABASE);
        $stateMachineProcessEntity->save();

        $stateMachineProcessTransfer->setIdStateMachineProcess($stateMachineProcessEntity->getIdStateMachineProcess());

        return $stateMachineProcessTransfer;
    }

    /**
     * @module StateMachine
     */
    public function updateStateMachineProcess(
        StateMachineProcessTransfer $stateMachineProcessTransfer
    ): StateMachineProcessTransfer {
        $stateMachineProcessEntity = SpyStateMachineProcessQuery::create()
            ->filterByIdStateMachineProcess($stateMachineProcessTransfer->getIdStateMachineProcessOrFail())
            ->findOne();

        if ($stateMachineProcessEntity === null) {
            return $stateMachineProcessTransfer;
        }

        if ($stateMachineProcessTransfer->getStatus() !== null) {
            $stateMachineProcessEntity->setStatus($stateMachineProcessTransfer->getStatus());
        }

        if ($stateMachineProcessTransfer->getDescription() !== null) {
            $stateMachineProcessEntity->setDescription($stateMachineProcessTransfer->getDescription());
        }

        $stateMachineProcessEntity->save();

        return $this->getFactory()
            ->createWorkflowMapper()
            ->mapProcessEntityToStateMachineProcessTransfer($stateMachineProcessEntity, $stateMachineProcessTransfer);
    }

    public function saveStateMachineProcessDefinition(
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
    ): StateMachineProcessDefinitionTransfer {
        $stateMachineProcessDefinitionEntity = $this->getFactory()
            ->getStateMachineProcessDefinitionQuery()
            ->filterByIdStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer->getIdStateMachineProcessDefinition())
            ->findOneOrCreate();

        $stateMachineProcessDefinitionEntity->fromArray($stateMachineProcessDefinitionTransfer->modifiedToArray());
        $stateMachineProcessDefinitionEntity->setFkStateMachineProcess(
            $stateMachineProcessDefinitionTransfer->getStateMachineProcessOrFail()->getIdStateMachineProcessOrFail(),
        );

        if ($stateMachineProcessDefinitionTransfer->getUser() !== null) {
            $stateMachineProcessDefinitionEntity->setFkUser($stateMachineProcessDefinitionTransfer->getUser()->getIdUser());
        }

        $stateMachineProcessDefinitionEntity->save();
        $stateMachineProcessDefinitionTransfer->setIdStateMachineProcessDefinition(
            $stateMachineProcessDefinitionEntity->getIdStateMachineProcessDefinition(),
        );

        return $stateMachineProcessDefinitionTransfer;
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function updateStateMachineProcessDefinition(
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
    ): void {
        $stateMachineProcessDefinitionEntity = $this->getFactory()
            ->getStateMachineProcessDefinitionQuery()
            ->findOneByIdStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer->getIdStateMachineProcessDefinitionOrFail());

        if ($stateMachineProcessDefinitionEntity === null) {
            return;
        }

        $stateMachineProcessDefinitionEntity->setStatus($stateMachineProcessDefinitionTransfer->getStatusOrFail());
        $stateMachineProcessDefinitionEntity->save();
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function deactivateStateMachineProcessDefinitions(
        StateMachineProcessDefinitionConditionsTransfer $stateMachineProcessDefinitionConditionsTransfer
    ): void {
        $stateMachineProcessDefinitionQuery = $this->getFactory()->getStateMachineProcessDefinitionQuery();

        if ($stateMachineProcessDefinitionConditionsTransfer->getStateMachineProcessIds() !== []) {
            $stateMachineProcessDefinitionQuery->filterByFkStateMachineProcess_In(
                $stateMachineProcessDefinitionConditionsTransfer->getStateMachineProcessIds(),
            );
        }

        $stateMachineProcessDefinitionQuery->update(
            ['Status' => WorkflowConfig::PROCESS_DEFINITION_STATUS_INACTIVE],
        );
    }

    public function saveStateMachineProcessDefinitionInstance(
        StateMachineProcessDefinitionInstanceTransfer $stateMachineProcessDefinitionInstanceTransfer
    ): StateMachineProcessDefinitionInstanceTransfer {
        $idStateMachineProcessDefinition = $stateMachineProcessDefinitionInstanceTransfer
            ->getStateMachineProcessDefinitionOrFail()
            ->getIdStateMachineProcessDefinitionOrFail();

        $stateMachineProcessDefinitionInstanceEntity = $this->getFactory()
            ->getStateMachineProcessDefinitionInstanceQuery()
            ->filterByFkStateMachineProcessDefinition($idStateMachineProcessDefinition)
            ->filterByIdentifier($stateMachineProcessDefinitionInstanceTransfer->getIdentifierOrFail())
            ->findOneOrCreate();

        $stateMachineItemStateTransfer = $stateMachineProcessDefinitionInstanceTransfer->getStateMachineItemState();
        if ($stateMachineItemStateTransfer !== null && $stateMachineItemStateTransfer->getIdItemState() !== null) {
            $stateMachineProcessDefinitionInstanceEntity->setFkStateMachineItemState($stateMachineItemStateTransfer->getIdItemState());
        }

        $stateMachineProcessDefinitionInstanceEntity->setFinishedAt($stateMachineProcessDefinitionInstanceTransfer->getFinishedAt());

        $stateMachineProcessDefinitionInstanceEntity->save();
        $stateMachineProcessDefinitionInstanceTransfer->setIdStateMachineProcessDefinitionInstance(
            $stateMachineProcessDefinitionInstanceEntity->getIdStateMachineProcessDefinitionInstance(),
        );

        return $stateMachineProcessDefinitionInstanceTransfer;
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function updateStateMachineProcessDefinitionInstance(
        StateMachineProcessDefinitionInstanceTransfer $stateMachineProcessDefinitionInstanceTransfer
    ): void {
        $idStateMachineProcessDefinition = $stateMachineProcessDefinitionInstanceTransfer
            ->getStateMachineProcessDefinitionOrFail()
            ->getIdStateMachineProcessDefinitionOrFail();

        $stateMachineProcessDefinitionInstanceEntity = $this->getFactory()
            ->getStateMachineProcessDefinitionInstanceQuery()
            ->filterByFkStateMachineProcessDefinition($idStateMachineProcessDefinition)
            ->filterByIdentifier($stateMachineProcessDefinitionInstanceTransfer->getIdentifierOrFail())
            ->findOne();

        if ($stateMachineProcessDefinitionInstanceEntity === null) {
            return;
        }

        $stateMachineProcessDefinitionInstanceEntity->setFkStateMachineItemState(
            $stateMachineProcessDefinitionInstanceTransfer->getStateMachineItemStateOrFail()->getIdItemStateOrFail(),
        );
        $stateMachineProcessDefinitionInstanceEntity->save();
    }

    public function saveStateMachineDefinitionTrigger(
        StateMachineDefinitionTriggerTransfer $stateMachineDefinitionTriggerTransfer
    ): StateMachineDefinitionTriggerTransfer {
        $idStateMachineProcess = $stateMachineDefinitionTriggerTransfer
            ->getStateMachineProcessOrFail()
            ->getIdStateMachineProcessOrFail();

        $stateMachineProcessDefinitionTriggerEntity = $this->getFactory()
            ->getStateMachineProcessDefinitionTriggerQuery()
            ->filterByFkStateMachineProcess($idStateMachineProcess)
            ->filterByEventName($stateMachineDefinitionTriggerTransfer->getEventNameOrFail())
            ->findOneOrCreate();

        $stateMachineProcessDefinitionTriggerEntity->save();
        $stateMachineDefinitionTriggerTransfer->setIdStateMachineProcessDefinitionTrigger(
            $stateMachineProcessDefinitionTriggerEntity->getIdStateMachineProcessDefinitionTrigger(),
        );

        return $stateMachineDefinitionTriggerTransfer;
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function deleteStateMachineDefinitionTrigger(
        StateMachineDefinitionTriggerTransfer $stateMachineDefinitionTriggerTransfer
    ): void {
        $this->getFactory()
            ->getStateMachineProcessDefinitionTriggerQuery()
            ->filterByIdStateMachineProcessDefinitionTrigger(
                $stateMachineDefinitionTriggerTransfer->getIdStateMachineProcessDefinitionTriggerOrFail(),
            )
            ->delete();
    }

    public function deleteStateMachineProcessDefinition(
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
    ): void {
        $this->getFactory()
            ->getStateMachineProcessDefinitionQuery()
            ->filterByIdStateMachineProcessDefinition(
                $stateMachineProcessDefinitionTransfer->getIdStateMachineProcessDefinitionOrFail(),
            )
            ->delete();
    }
}

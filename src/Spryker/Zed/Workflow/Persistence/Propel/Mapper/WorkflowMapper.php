<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Persistence\Propel\Mapper;

use Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerTransfer;
use Generated\Shared\Transfer\StateMachineItemStateTransfer;
use Generated\Shared\Transfer\StateMachineProcessCollectionTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceCollectionTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Orm\Zed\StateMachine\Persistence\SpyStateMachineProcess;
use Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinition;
use Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionInstance;
use Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionTrigger;
use Propel\Runtime\Collection\ObjectCollection;

class WorkflowMapper
{
    public function mapProcessEntityToStateMachineProcessTransfer(
        SpyStateMachineProcess $stateMachineProcessEntity,
        StateMachineProcessTransfer $stateMachineProcessTransfer
    ): StateMachineProcessTransfer {
        $stateMachineProcessTransfer->fromArray($stateMachineProcessEntity->toArray(), true);
        $stateMachineProcessTransfer->setProcessName($stateMachineProcessEntity->getName());
        $stateMachineProcessTransfer->setStateMachineName($stateMachineProcessEntity->getStateMachineName());

        return $stateMachineProcessTransfer;
    }

    /**
     * @param \Propel\Runtime\Collection\ObjectCollection<\Orm\Zed\StateMachine\Persistence\SpyStateMachineProcess> $stateMachineProcessEntities
     * @param \Generated\Shared\Transfer\StateMachineProcessCollectionTransfer $stateMachineProcessCollectionTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineProcessCollectionTransfer
     */
    public function mapProcessEntitiesToStateMachineProcessCollectionTransfer(
        ObjectCollection $stateMachineProcessEntities,
        StateMachineProcessCollectionTransfer $stateMachineProcessCollectionTransfer
    ): StateMachineProcessCollectionTransfer {
        foreach ($stateMachineProcessEntities as $stateMachineProcessEntity) {
            $stateMachineProcessCollectionTransfer->addStateMachineProcess(
                $this->mapProcessEntityToStateMachineProcessTransfer(
                    $stateMachineProcessEntity,
                    new StateMachineProcessTransfer(),
                ),
            );
        }

        return $stateMachineProcessCollectionTransfer;
    }

    public function mapProcessDefinitionEntityToTransfer(
        SpyStateMachineProcessDefinition $stateMachineProcessDefinitionEntity,
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
    ): StateMachineProcessDefinitionTransfer {
        $stateMachineProcessDefinitionTransfer->fromArray($stateMachineProcessDefinitionEntity->toArray(), true);

        $stateMachineProcessDefinitionTransfer->setStateMachineProcess(
            $this->mapProcessEntityToStateMachineProcessTransfer(
                $stateMachineProcessDefinitionEntity->getStateMachineProcess(),
                new StateMachineProcessTransfer(),
            ),
        );

        return $stateMachineProcessDefinitionTransfer;
    }

    /**
     * @param \Propel\Runtime\Collection\ObjectCollection<\Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinition> $stateMachineProcessDefinitionEntities
     * @param \Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionTransfer $stateMachineProcessDefinitionCollectionTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionTransfer
     */
    public function mapProcessDefinitionEntitiesToStateMachineProcessDefinitionCollectionTransfer(
        ObjectCollection $stateMachineProcessDefinitionEntities,
        StateMachineProcessDefinitionCollectionTransfer $stateMachineProcessDefinitionCollectionTransfer
    ): StateMachineProcessDefinitionCollectionTransfer {
        foreach ($stateMachineProcessDefinitionEntities as $stateMachineProcessDefinitionEntity) {
            $stateMachineProcessDefinitionCollectionTransfer->addStateMachineProcessDefinition(
                $this->mapProcessDefinitionEntityToTransfer(
                    $stateMachineProcessDefinitionEntity,
                    new StateMachineProcessDefinitionTransfer(),
                ),
            );
        }

        return $stateMachineProcessDefinitionCollectionTransfer;
    }

    public function mapProcessDefinitionInstanceEntityToTransfer(
        SpyStateMachineProcessDefinitionInstance $stateMachineProcessDefinitionInstanceEntity,
        StateMachineProcessDefinitionInstanceTransfer $stateMachineProcessDefinitionInstanceTransfer,
        StateMachineProcessDefinitionInstanceConditionsTransfer $stateMachineProcessDefinitionInstanceConditionsTransfer
    ): StateMachineProcessDefinitionInstanceTransfer {
        $stateMachineProcessDefinitionInstanceTransfer->fromArray($stateMachineProcessDefinitionInstanceEntity->toArray(), true);
        $stateMachineProcessDefinitionTransfer = (new StateMachineProcessDefinitionTransfer())
            ->setIdStateMachineProcessDefinition($stateMachineProcessDefinitionInstanceEntity->getFkStateMachineProcessDefinition());

        if ($stateMachineProcessDefinitionInstanceConditionsTransfer->getWithStateMachineProcessDefinition()) {
            $stateMachineProcessDefinitionEntity = $stateMachineProcessDefinitionInstanceEntity->getStateMachineProcessDefinition();
            $stateMachineProcessDefinitionTransfer->setVersion($stateMachineProcessDefinitionEntity->getVersion());

            if ($stateMachineProcessDefinitionInstanceConditionsTransfer->getWithStateMachineProcess()) {
                $stateMachineProcessDefinitionTransfer->setStateMachineProcess(
                    $this->mapProcessEntityToStateMachineProcessTransfer(
                        $stateMachineProcessDefinitionEntity->getStateMachineProcess(),
                        new StateMachineProcessTransfer(),
                    ),
                );
            }
        }

        $stateMachineProcessDefinitionInstanceTransfer->setStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);

        $stateMachineItemStateEntity = $stateMachineProcessDefinitionInstanceConditionsTransfer->getWithStateMachineItemState()
            ? $stateMachineProcessDefinitionInstanceEntity->getStateMachineItemState()
            : null;
        if ($stateMachineItemStateEntity !== null) {
            $stateMachineProcessDefinitionInstanceTransfer->setStateMachineItemState(
                (new StateMachineItemStateTransfer())
                    ->setIdItemState($stateMachineItemStateEntity->getIdStateMachineItemState())
                    ->setName($stateMachineItemStateEntity->getName()),
            );
        }

        return $stateMachineProcessDefinitionInstanceTransfer;
    }

    /**
     * @param \Propel\Runtime\Collection\ObjectCollection<\Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionInstance> $stateMachineProcessDefinitionInstanceEntities
     * @param \Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceCollectionTransfer $stateMachineProcessDefinitionInstanceCollectionTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceCollectionTransfer
     */
    public function mapProcessDefinitionInstanceEntitiesToStateMachineProcessDefinitionInstanceCollectionTransfer(
        ObjectCollection $stateMachineProcessDefinitionInstanceEntities,
        StateMachineProcessDefinitionInstanceCollectionTransfer $stateMachineProcessDefinitionInstanceCollectionTransfer,
        StateMachineProcessDefinitionInstanceConditionsTransfer $stateMachineProcessDefinitionInstanceConditionsTransfer
    ): StateMachineProcessDefinitionInstanceCollectionTransfer {
        foreach ($stateMachineProcessDefinitionInstanceEntities as $stateMachineProcessDefinitionInstanceEntity) {
            $stateMachineProcessDefinitionInstanceCollectionTransfer->addStateMachineProcessDefinitionInstance(
                $this->mapProcessDefinitionInstanceEntityToTransfer(
                    $stateMachineProcessDefinitionInstanceEntity,
                    new StateMachineProcessDefinitionInstanceTransfer(),
                    $stateMachineProcessDefinitionInstanceConditionsTransfer,
                ),
            );
        }

        return $stateMachineProcessDefinitionInstanceCollectionTransfer;
    }

    /**
     * @param \Propel\Runtime\Collection\ObjectCollection<\Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionTrigger> $stateMachineProcessDefinitionTriggerEntities
     * @param \Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionTransfer $stateMachineDefinitionTriggerCollectionTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionTransfer
     */
    public function mapProcessDefinitionTriggerEntitiesToStateMachineDefinitionTriggerCollectionTransfer(
        ObjectCollection $stateMachineProcessDefinitionTriggerEntities,
        StateMachineDefinitionTriggerCollectionTransfer $stateMachineDefinitionTriggerCollectionTransfer
    ): StateMachineDefinitionTriggerCollectionTransfer {
        foreach ($stateMachineProcessDefinitionTriggerEntities as $stateMachineProcessDefinitionTriggerEntity) {
            $stateMachineDefinitionTriggerCollectionTransfer->addStateMachineDefinitionTrigger(
                $this->mapProcessDefinitionTriggerEntityToTransfer(
                    $stateMachineProcessDefinitionTriggerEntity,
                    new StateMachineDefinitionTriggerTransfer(),
                ),
            );
        }

        return $stateMachineDefinitionTriggerCollectionTransfer;
    }

    protected function mapProcessDefinitionTriggerEntityToTransfer(
        SpyStateMachineProcessDefinitionTrigger $stateMachineProcessDefinitionTriggerEntity,
        StateMachineDefinitionTriggerTransfer $stateMachineDefinitionTriggerTransfer
    ): StateMachineDefinitionTriggerTransfer {
        $stateMachineDefinitionTriggerTransfer->fromArray($stateMachineProcessDefinitionTriggerEntity->toArray(), true);

        $stateMachineProcessTransfer = (new StateMachineProcessTransfer())
           ->setIdStateMachineProcess($stateMachineProcessDefinitionTriggerEntity->getFkStateMachineProcess());
        $stateMachineDefinitionTriggerTransfer->setStateMachineProcess($stateMachineProcessTransfer);

        return $stateMachineDefinitionTriggerTransfer;
    }
}

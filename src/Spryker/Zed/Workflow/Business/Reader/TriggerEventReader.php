<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Reader;

use Generated\Shared\Transfer\StateMachineProcessConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineTriggerEventCollectionTransfer;
use Generated\Shared\Transfer\StateMachineTriggerEventCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineTriggerEventTransfer;
use Spryker\Zed\Workflow\Dependency\Plugin\StateMachineProcessTriggerPluginInterface;
use Spryker\Zed\Workflow\Persistence\WorkflowRepositoryInterface;

class TriggerEventReader implements TriggerEventReaderInterface
{
    /**
     * @param \Spryker\Zed\Workflow\Persistence\WorkflowRepositoryInterface $repository
     * @param array<\Spryker\Zed\Workflow\Dependency\Plugin\StateMachineProcessTriggerPluginInterface> $triggerPlugins
     */
    public function __construct(
        protected WorkflowRepositoryInterface $repository,
        protected array $triggerPlugins
    ) {
    }

    public function getStateMachineTriggerEventCollection(
        StateMachineTriggerEventCriteriaTransfer $stateMachineTriggerEventCriteriaTransfer
    ): StateMachineTriggerEventCollectionTransfer {
        $stateMachineTriggerEventCollectionTransfer = new StateMachineTriggerEventCollectionTransfer();

        foreach ($this->getMatchingTriggerPlugins($stateMachineTriggerEventCriteriaTransfer) as $triggerPlugin) {
            $stateMachineTriggerEventCollectionTransfer->addStateMachineTriggerEvent(
                (new StateMachineTriggerEventTransfer())
                    ->setEventName($triggerPlugin->getEventName())
                    ->setName($this->getName($triggerPlugin))
                    ->setDescription($triggerPlugin->getDescription())
                    ->setSubjectType($triggerPlugin->getSubjectType()),
            );
        }

        return $stateMachineTriggerEventCollectionTransfer;
    }

    protected function getName(StateMachineProcessTriggerPluginInterface $triggerPlugin): string
    {
        $name = $triggerPlugin->getName();

        if ($name === '') {
            return $triggerPlugin->getEventName();
        }

        return $name;
    }

    /**
     * @return array<\Spryker\Zed\Workflow\Dependency\Plugin\StateMachineProcessTriggerPluginInterface>
     */
    protected function getMatchingTriggerPlugins(StateMachineTriggerEventCriteriaTransfer $stateMachineTriggerEventCriteriaTransfer): array
    {
        $stateMachineTriggerEventConditionsTransfer = $stateMachineTriggerEventCriteriaTransfer->getStateMachineTriggerEventConditions();

        if ($stateMachineTriggerEventConditionsTransfer === null) {
            return $this->triggerPlugins;
        }

        $subjectTypes = $this->getSubjectTypesForStateMachineProcesses($stateMachineTriggerEventConditionsTransfer->getStateMachineProcessIds());

        return array_values(array_filter(
            $this->triggerPlugins,
            static fn (StateMachineProcessTriggerPluginInterface $triggerPlugin): bool => in_array($triggerPlugin->getSubjectType(), $subjectTypes, true),
        ));
    }

    /**
     * @param array<int> $stateMachineProcessIds
     *
     * @return array<string>
     */
    protected function getSubjectTypesForStateMachineProcesses(array $stateMachineProcessIds): array
    {
        if ($stateMachineProcessIds === []) {
            return [];
        }

        $stateMachineProcessCollectionTransfer = $this->repository->getStateMachineProcessCollection(
            (new StateMachineProcessCriteriaTransfer())->setStateMachineProcessConditions(
                (new StateMachineProcessConditionsTransfer())->setStateMachineProcessIds($stateMachineProcessIds),
            ),
        );

        $subjectTypes = [];

        foreach ($stateMachineProcessCollectionTransfer->getStateMachineProcesses() as $stateMachineProcessTransfer) {
            if ($stateMachineProcessTransfer->getSubjectType() !== null) {
                $subjectTypes[] = $stateMachineProcessTransfer->getSubjectType();
            }
        }

        return array_values(array_unique($subjectTypes));
    }
}

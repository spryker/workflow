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

        $subjectTypes = $this->resolveSubjectTypesForStateMachineProcesses($stateMachineTriggerEventCriteriaTransfer);

        foreach ($subjectTypes as $subjectType) {
            $this->addTriggerEventsForSubjectType($subjectType, $stateMachineTriggerEventCollectionTransfer);
        }

        return $stateMachineTriggerEventCollectionTransfer;
    }

    protected function addTriggerEventsForSubjectType(
        string $subjectType,
        StateMachineTriggerEventCollectionTransfer $stateMachineTriggerEventCollectionTransfer
    ): void {
        foreach ($this->getTriggerPluginsBySubjectType($subjectType) as $triggerPlugin) {
            $stateMachineTriggerEventCollectionTransfer->addStateMachineTriggerEvent(
                (new StateMachineTriggerEventTransfer())
                    ->setEventName($triggerPlugin->getEventName())
                    ->setName($triggerPlugin->getName())
                    ->setDescription($triggerPlugin->getDescription())
                    ->setSubjectType($subjectType),
            );
        }
    }

    /**
     * @return array<string>
     */
    protected function resolveSubjectTypesForStateMachineProcesses(
        StateMachineTriggerEventCriteriaTransfer $stateMachineTriggerEventCriteriaTransfer
    ): array {
        $stateMachineTriggerEventConditionsTransfer = $stateMachineTriggerEventCriteriaTransfer->getStateMachineTriggerEventConditions();
        if ($stateMachineTriggerEventConditionsTransfer === null || $stateMachineTriggerEventConditionsTransfer->getStateMachineProcessIds() === []) {
            return [];
        }

        $stateMachineProcessCollectionTransfer = $this->repository->getStateMachineProcessCollection(
            (new StateMachineProcessCriteriaTransfer())->setStateMachineProcessConditions(
                (new StateMachineProcessConditionsTransfer())
                    ->setStateMachineProcessIds($stateMachineTriggerEventConditionsTransfer->getStateMachineProcessIds()),
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

    /**
     * @return array<\Spryker\Zed\Workflow\Dependency\Plugin\StateMachineProcessTriggerPluginInterface>
     */
    protected function getTriggerPluginsBySubjectType(string $subjectType): array
    {
        $triggerPlugins = [];
        foreach ($this->triggerPlugins as $triggerPlugin) {
            if ($triggerPlugin->getSubjectType() !== $subjectType) {
                continue;
            }

            $triggerPlugins[] = $triggerPlugin;
        }

        return $triggerPlugins;
    }
}

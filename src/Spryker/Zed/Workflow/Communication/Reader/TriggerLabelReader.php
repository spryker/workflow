<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Reader;

use ArrayObject;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerConditionsTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineTriggerEventConditionsTransfer;
use Generated\Shared\Transfer\StateMachineTriggerEventCriteriaTransfer;
use Spryker\Zed\Workflow\Business\WorkflowFacadeInterface;

class TriggerLabelReader implements TriggerLabelReaderInterface
{
    public function __construct(protected WorkflowFacadeInterface $workflowFacade)
    {
    }

    /**
     * @param array<int> $stateMachineProcessIds
     *
     * @return array<int, array<string, string>>
     */
    public function getTriggerLabelsGroupedByIdStateMachineProcess(array $stateMachineProcessIds): array
    {
        if ($stateMachineProcessIds === []) {
            return [];
        }

        $stateMachineDefinitionTriggerTransfers = $this->getStateMachineDefinitionTriggers($stateMachineProcessIds);
        if ($stateMachineDefinitionTriggerTransfers->count() === 0) {
            return [];
        }

        $friendlyNamesIndexedByEventName = $this->getFriendlyNamesIndexedByEventName($stateMachineProcessIds);

        $triggerLabelsGroupedByIdStateMachineProcess = [];
        foreach ($stateMachineDefinitionTriggerTransfers as $stateMachineDefinitionTriggerTransfer) {
            $idStateMachineProcess = $stateMachineDefinitionTriggerTransfer->getStateMachineProcessOrFail()->getIdStateMachineProcessOrFail();
            $eventName = $stateMachineDefinitionTriggerTransfer->getEventNameOrFail();
            $triggerLabelsGroupedByIdStateMachineProcess[$idStateMachineProcess][$eventName] = $friendlyNamesIndexedByEventName[$eventName] ?? $eventName;
        }

        return $triggerLabelsGroupedByIdStateMachineProcess;
    }

    /**
     * @return array<string, \Generated\Shared\Transfer\StateMachineDefinitionTriggerTransfer>
     */
    public function getStateMachineDefinitionTriggersIndexedByEventName(int $idStateMachineProcess): array
    {
        $stateMachineDefinitionTriggerTransfersIndexedByEventName = [];
        foreach ($this->getStateMachineDefinitionTriggers([$idStateMachineProcess]) as $stateMachineDefinitionTriggerTransfer) {
            $stateMachineDefinitionTriggerTransfersIndexedByEventName[$stateMachineDefinitionTriggerTransfer->getEventNameOrFail()] = $stateMachineDefinitionTriggerTransfer;
        }

        return $stateMachineDefinitionTriggerTransfersIndexedByEventName;
    }

    /**
     * @param array<int> $stateMachineProcessIds
     *
     * @return \ArrayObject<int, \Generated\Shared\Transfer\StateMachineDefinitionTriggerTransfer>
     */
    protected function getStateMachineDefinitionTriggers(array $stateMachineProcessIds): ArrayObject
    {
        return $this->workflowFacade->getStateMachineDefinitionTriggerCollection(
            (new StateMachineDefinitionTriggerCriteriaTransfer())
                ->setStateMachineDefinitionTriggerConditions(
                    (new StateMachineDefinitionTriggerConditionsTransfer())->setStateMachineProcessIds($stateMachineProcessIds),
                ),
        )->getStateMachineDefinitionTriggers();
    }

    /**
     * @param array<int> $stateMachineProcessIds
     *
     * @return array<string, string>
     */
    protected function getFriendlyNamesIndexedByEventName(array $stateMachineProcessIds): array
    {
        $stateMachineTriggerEventCollectionTransfer = $this->workflowFacade->getStateMachineTriggerEventCollection(
            (new StateMachineTriggerEventCriteriaTransfer())
                ->setStateMachineTriggerEventConditions(
                    (new StateMachineTriggerEventConditionsTransfer())->setStateMachineProcessIds($stateMachineProcessIds),
                ),
        );

        $friendlyNamesIndexedByEventName = [];
        foreach ($stateMachineTriggerEventCollectionTransfer->getStateMachineTriggerEvents() as $stateMachineTriggerEventTransfer) {
            $friendlyNamesIndexedByEventName[$stateMachineTriggerEventTransfer->getEventNameOrFail()] = $stateMachineTriggerEventTransfer->getNameOrFail();
        }

        return $friendlyNamesIndexedByEventName;
    }
}

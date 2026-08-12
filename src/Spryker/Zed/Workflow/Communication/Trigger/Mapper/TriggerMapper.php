<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Trigger\Mapper;

use Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Spryker\Zed\Workflow\Communication\Form\TriggerForm;

class TriggerMapper implements TriggerMapperInterface
{
    /**
     * {@inheritDoc}
     */
    public function mapFormDataToTriggerCollectionRequestTransfer(
        array $formData,
        int $idStateMachineProcess,
        StateMachineDefinitionTriggerCollectionTransfer $persistedStateMachineDefinitionTriggerCollectionTransfer
    ): StateMachineDefinitionTriggerCollectionRequestTransfer {
        $stateMachineDefinitionTriggerCollectionRequestTransfer = (new StateMachineDefinitionTriggerCollectionRequestTransfer())
            ->setIsTransactional(true);

        $this->mapEventNamesToBeAdded($formData, $idStateMachineProcess, $stateMachineDefinitionTriggerCollectionRequestTransfer);
        $this->mapEventNamesToBeRemoved(
            $formData,
            $persistedStateMachineDefinitionTriggerCollectionTransfer,
            $stateMachineDefinitionTriggerCollectionRequestTransfer,
        );

        return $stateMachineDefinitionTriggerCollectionRequestTransfer;
    }

    /**
     * @param array<string, mixed> $formData
     * @param int $idStateMachineProcess
     * @param \Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionRequestTransfer $stateMachineDefinitionTriggerCollectionRequestTransfer
     *
     * @return void
     */
    protected function mapEventNamesToBeAdded(
        array $formData,
        int $idStateMachineProcess,
        StateMachineDefinitionTriggerCollectionRequestTransfer $stateMachineDefinitionTriggerCollectionRequestTransfer
    ): void {
        if (empty($formData[TriggerForm::FIELD_EVENT_NAMES_TO_BE_ADDED])) {
            return;
        }

        $stateMachineProcessTransfer = (new StateMachineProcessTransfer())->setIdStateMachineProcess($idStateMachineProcess);

        foreach ($formData[TriggerForm::FIELD_EVENT_NAMES_TO_BE_ADDED] as $eventName) {
            $stateMachineDefinitionTriggerCollectionRequestTransfer->addStateMachineDefinitionTriggerToAdd(
                (new StateMachineDefinitionTriggerTransfer())
                    ->setEventName($eventName)
                    ->setStateMachineProcess($stateMachineProcessTransfer),
            );
        }
    }

    /**
     * @param array<string, mixed> $formData
     * @param \Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionTransfer $persistedStateMachineDefinitionTriggerCollectionTransfer
     * @param \Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionRequestTransfer $stateMachineDefinitionTriggerCollectionRequestTransfer
     *
     * @return void
     */
    protected function mapEventNamesToBeRemoved(
        array $formData,
        StateMachineDefinitionTriggerCollectionTransfer $persistedStateMachineDefinitionTriggerCollectionTransfer,
        StateMachineDefinitionTriggerCollectionRequestTransfer $stateMachineDefinitionTriggerCollectionRequestTransfer
    ): void {
        if (empty($formData[TriggerForm::FIELD_EVENT_NAMES_TO_BE_REMOVED])) {
            return;
        }

        $persistedTriggersByEventName = $this->indexPersistedTriggersByEventName($persistedStateMachineDefinitionTriggerCollectionTransfer);

        foreach ($formData[TriggerForm::FIELD_EVENT_NAMES_TO_BE_REMOVED] as $eventName) {
            if (!$eventName || !isset($persistedTriggersByEventName[$eventName])) {
                continue;
            }

            $stateMachineDefinitionTriggerCollectionRequestTransfer->addStateMachineDefinitionTriggerToRemove(
                $persistedTriggersByEventName[$eventName],
            );
        }
    }

    /**
     * @param \Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionTransfer $persistedStateMachineDefinitionTriggerCollectionTransfer
     *
     * @return array<string, \Generated\Shared\Transfer\StateMachineDefinitionTriggerTransfer>
     */
    protected function indexPersistedTriggersByEventName(
        StateMachineDefinitionTriggerCollectionTransfer $persistedStateMachineDefinitionTriggerCollectionTransfer
    ): array {
        $persistedTriggersByEventName = [];
        foreach ($persistedStateMachineDefinitionTriggerCollectionTransfer->getStateMachineDefinitionTriggers() as $stateMachineDefinitionTriggerTransfer) {
            $persistedTriggersByEventName[(string)$stateMachineDefinitionTriggerTransfer->getEventName()] = $stateMachineDefinitionTriggerTransfer;
        }

        return $persistedTriggersByEventName;
    }
}

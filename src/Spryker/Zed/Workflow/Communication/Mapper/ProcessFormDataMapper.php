<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Mapper;

use Generated\Shared\Transfer\StateMachineDefinitionTriggerTransfer;
use Generated\Shared\Transfer\StateMachineProcessCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Generated\Shared\Transfer\UserTransfer;
use Spryker\Zed\User\Business\UserFacadeInterface;
use Spryker\Zed\Workflow\Communication\Form\ProcessEditForm;
use Spryker\Zed\Workflow\Communication\Form\ProcessForm;
use Spryker\Zed\Workflow\Communication\Form\StateMachineDefinitionForm;

class ProcessFormDataMapper implements ProcessFormDataMapperInterface
{
    public function __construct(protected UserFacadeInterface $userFacade)
    {
    }

    /**
     * @param array<string, mixed> $processFormData
     */
    public function mapProcessFormDataToStateMachineProcessTransfer(
        array $processFormData,
        StateMachineProcessTransfer $stateMachineProcessTransfer,
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
    ): StateMachineProcessTransfer {
        $name = (string)$processFormData[ProcessForm::FIELD_NAME];

        $stateMachineProcessTransfer
            ->setStateMachineName($name)
            ->setProcessName($name)
            ->setSubjectType($processFormData[ProcessForm::FIELD_SUBJECT_TYPE])
            ->setDescription($processFormData[ProcessForm::FIELD_DESCRIPTION] ?? null)
            ->addStateMachineProcessDefinition(
                $this->mapStateMachineDefinitionFormDataToStateMachineProcessDefinitionTransfer(
                    $processFormData[ProcessForm::FIELD_STATE_MACHINE_DEFINITION],
                    $stateMachineProcessDefinitionTransfer,
                ),
            );

        return $this->addStateMachineDefinitionTriggers($processFormData[ProcessForm::FIELD_TRIGGERS], $stateMachineProcessTransfer);
    }

    /**
     * @param array<string, mixed> $processEditFormData
     * @param array<string, \Generated\Shared\Transfer\StateMachineDefinitionTriggerTransfer> $attachedStateMachineDefinitionTriggerTransfers
     */
    public function mapProcessEditFormDataToStateMachineProcessCollectionRequestTransfer(
        array $processEditFormData,
        array $attachedStateMachineDefinitionTriggerTransfers,
        StateMachineProcessTransfer $stateMachineProcessTransfer,
        StateMachineProcessCollectionRequestTransfer $stateMachineProcessCollectionRequestTransfer
    ): StateMachineProcessCollectionRequestTransfer {
        $submittedEventNames = $processEditFormData[ProcessEditForm::FIELD_TRIGGERS];
        $idStateMachineProcess = $stateMachineProcessTransfer->getIdStateMachineProcessOrFail();

        $stateMachineProcessCollectionRequestTransfer->addStateMachineProcess(
            $stateMachineProcessTransfer->setDescription((string)$processEditFormData[ProcessEditForm::FIELD_DESCRIPTION]),
        );

        foreach (array_diff($submittedEventNames, array_keys($attachedStateMachineDefinitionTriggerTransfers)) as $eventName) {
            $stateMachineProcessCollectionRequestTransfer->addStateMachineDefinitionTriggerToAdd(
                $this->createStateMachineDefinitionTriggerTransfer($eventName, $idStateMachineProcess),
            );
        }

        foreach (array_diff_key($attachedStateMachineDefinitionTriggerTransfers, array_flip($submittedEventNames)) as $stateMachineDefinitionTriggerTransfer) {
            $stateMachineProcessCollectionRequestTransfer->addStateMachineDefinitionTriggerToRemove($stateMachineDefinitionTriggerTransfer);
        }

        return $stateMachineProcessCollectionRequestTransfer;
    }

    /**
     * @param array<string, string|null> $stateMachineDefinitionFormData
     */
    public function mapStateMachineDefinitionFormDataToStateMachineProcessDefinitionTransfer(
        array $stateMachineDefinitionFormData,
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
    ): StateMachineProcessDefinitionTransfer {
        return $stateMachineProcessDefinitionTransfer
            ->setInitialState($stateMachineDefinitionFormData[StateMachineDefinitionForm::FIELD_INITIAL_STATE])
            ->setDefinition($stateMachineDefinitionFormData[StateMachineDefinitionForm::FIELD_DEFINITION])
            ->setUser($this->findCurrentUser());
    }

    protected function findCurrentUser(): ?UserTransfer
    {
        if (!$this->userFacade->hasCurrentUser()) {
            return null;
        }

        return $this->userFacade->getCurrentUser();
    }

    /**
     * @param array<string> $eventNames
     */
    protected function addStateMachineDefinitionTriggers(
        array $eventNames,
        StateMachineProcessTransfer $stateMachineProcessTransfer
    ): StateMachineProcessTransfer {
        foreach ($eventNames as $eventName) {
            $stateMachineProcessTransfer->addStateMachineDefinitionTrigger(
                (new StateMachineDefinitionTriggerTransfer())->setEventName($eventName),
            );
        }

        return $stateMachineProcessTransfer;
    }

    protected function createStateMachineDefinitionTriggerTransfer(string $eventName, int $idStateMachineProcess): StateMachineDefinitionTriggerTransfer
    {
        return (new StateMachineDefinitionTriggerTransfer())
            ->setEventName($eventName)
            ->setStateMachineProcess((new StateMachineProcessTransfer())->setIdStateMachineProcess($idStateMachineProcess));
    }
}

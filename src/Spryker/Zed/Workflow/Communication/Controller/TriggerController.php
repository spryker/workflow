<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Controller;

use Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerConditionsTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineTriggerEventConditionsTransfer;
use Generated\Shared\Transfer\StateMachineTriggerEventCriteriaTransfer;
use Spryker\Service\UtilText\Model\Url\Url;
use Spryker\Zed\Kernel\Communication\Controller\AbstractController;
use Spryker\Zed\Workflow\Communication\Form\TriggerForm;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @method \Spryker\Zed\Workflow\Business\WorkflowFacadeInterface getFacade()
 * @method \Spryker\Zed\Workflow\Communication\WorkflowCommunicationFactory getFactory()
 * @method \Spryker\Zed\Workflow\WorkflowConfig getConfig()
 */
class TriggerController extends AbstractController
{
    /**
     * @var string
     */
    protected const PARAM_ID_PROCESS = 'id-process';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\ProcessController::indexAction()
     *
     * @var string
     */
    protected const URL_PROCESS = '/workflow/process';

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|array<string, mixed>
     */
    public function indexAction(Request $request): array|RedirectResponse
    {
        if ($request->query->get(static::PARAM_ID_PROCESS) === null) {
            return $this->redirectResponse(Url::generate(static::URL_PROCESS)->build());
        }

        $idStateMachineProcess = $this->castId($request->query->get(static::PARAM_ID_PROCESS));

        $stateMachineDefinitionTriggerCollectionTransfer = $this->getFacade()->getStateMachineDefinitionTriggerCollection(
            (new StateMachineDefinitionTriggerCriteriaTransfer())
                ->setStateMachineDefinitionTriggerConditions(
                    (new StateMachineDefinitionTriggerConditionsTransfer())->addIdStateMachineProcess($idStateMachineProcess),
                ),
        );

        $eventsAvailableForStateMachineProcess = $this->getAvailableTriggerEventsForStateMachineProcess($idStateMachineProcess);

        $selectedEventNames = [];
        foreach ($stateMachineDefinitionTriggerCollectionTransfer->getStateMachineDefinitionTriggers() as $stateMachineDefinitionTriggerTransfer) {
            $selectedEventNames[] = $stateMachineDefinitionTriggerTransfer->getEventName();
        }

        $triggerForm = $this->getFactory()->createTriggerForm(
            [
                TriggerForm::FIELD_ID_STATE_MACHINE_PROCESS => $idStateMachineProcess,
                TriggerForm::FIELD_EVENT_NAMES => $selectedEventNames,
            ],
            [
                TriggerForm::OPTION_AVAILABLE_EVENTS => $eventsAvailableForStateMachineProcess,
            ],
        );
        $triggerForm->handleRequest($request);

        if ($triggerForm->isSubmitted() && $triggerForm->isValid()) {
            return $this->handleSave(
                $request->request->all($triggerForm->getName()),
                $stateMachineDefinitionTriggerCollectionTransfer,
            );
        }

        return $this->viewResponse([
            'triggerForm' => $triggerForm->createView(),
            'idStateMachineProcess' => $idStateMachineProcess,
            'processName' => $this->findProcessName($idStateMachineProcess),
            'hasAvailableEvents' => $eventsAvailableForStateMachineProcess !== [],
        ]);
    }

    protected function findProcessName(int $idStateMachineProcess): ?string
    {
        $stateMachineProcessCollectionTransfer = $this->getFacade()->getStateMachineProcessCollection(
            (new StateMachineProcessCriteriaTransfer())->setStateMachineProcessConditions(
                (new StateMachineProcessConditionsTransfer())->addIdStateMachineProcess($idStateMachineProcess),
            ),
        );

        $stateMachineProcessTransfer = $stateMachineProcessCollectionTransfer->getStateMachineProcesses()->getIterator()->current();

        return $stateMachineProcessTransfer !== null ? $stateMachineProcessTransfer->getProcessName() : null;
    }

    /**
     * @param array<string, mixed> $data
     * @param \Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionTransfer $persistedStateMachineDefinitionTriggerCollectionTransfer
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function handleSave(
        array $data,
        StateMachineDefinitionTriggerCollectionTransfer $persistedStateMachineDefinitionTriggerCollectionTransfer
    ): RedirectResponse {
        $idStateMachineProcess = (int)$data[TriggerForm::FIELD_ID_STATE_MACHINE_PROCESS];

        $stateMachineDefinitionTriggerCollectionRequestTransfer = $this->getFactory()
            ->createTriggerMapper()
            ->mapFormDataToTriggerCollectionRequestTransfer(
                $data,
                $idStateMachineProcess,
                $persistedStateMachineDefinitionTriggerCollectionTransfer,
            );

        $this->getFacade()->updateStateMachineDefinitionTriggerCollection($stateMachineDefinitionTriggerCollectionRequestTransfer);
        $this->addSuccessMessage('The triggers were saved.');

        return $this->redirectResponse(Url::generate(static::URL_PROCESS)->build());
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected function getAvailableTriggerEventsForStateMachineProcess(int $idStateMachineProcess): array
    {
        $stateMachineTriggerEventCollectionTransfer = $this->getFacade()->getStateMachineTriggerEventCollection(
            (new StateMachineTriggerEventCriteriaTransfer())
                ->setStateMachineTriggerEventConditions(
                    (new StateMachineTriggerEventConditionsTransfer())->addIdStateMachineProcess($idStateMachineProcess),
                ),
        );

        $availableEvents = [];
        foreach ($stateMachineTriggerEventCollectionTransfer->getStateMachineTriggerEvents() as $stateMachineTriggerEventTransfer) {
            $eventName = $stateMachineTriggerEventTransfer->getEventNameOrFail();
            $availableEvents[$eventName] = [
                TriggerForm::TRIGGER_EVENT_NAME => $eventName,
                TriggerForm::TRIGGER_EVENT_LABEL => (string)$stateMachineTriggerEventTransfer->getName(),
                TriggerForm::TRIGGER_EVENT_DESCRIPTION => (string)$stateMachineTriggerEventTransfer->getDescription(),
            ];
        }

        return $availableEvents;
    }
}

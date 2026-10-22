<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Controller;

use Generated\Shared\Transfer\StateMachineEventTriggerRequestTransfer;
use Generated\Shared\Transfer\StateMachineItemTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Spryker\Service\UtilText\Model\Url\Url;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @method \Spryker\Zed\Workflow\Business\WorkflowFacadeInterface getFacade()
 * @method \Spryker\Zed\Workflow\Communication\WorkflowCommunicationFactory getFactory()
 * @method \Spryker\Zed\Workflow\WorkflowConfig getConfig()
 */
class InstanceController extends AbstractWorkflowController
{
    protected const string PARAM_IDENTIFIER = 'identifier';

    protected const string PARAM_EVENT = 'event';

    protected const string PARAM_BACK_ID_DEFINITION = 'back-id-definition';

    protected const string PARAM_REDIRECT_TARGET = 'redirect-target';

    protected const string REDIRECT_TARGET_VERSION_VIEW = 'version-view';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\InstanceController::viewAction()
     */
    protected const string URL_INSTANCE_VIEW = '/workflow/instance/view';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\InstanceController::triggerAction()
     */
    protected const string URL_INSTANCE_TRIGGER = '/workflow/instance/trigger';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\VersionController::viewAction()
     */
    protected const string URL_VERSION_VIEW = '/workflow/version/view';

    public function tableAction(Request $request): JsonResponse
    {
        $idStateMachineProcess = $this->castId($request->query->get(static::PARAM_ID_PROCESS));
        $idBackStateMachineProcessDefinition = $this->castId($request->query->get(static::PARAM_BACK_ID_DEFINITION));
        $stateMachineProcessTransfer = $this->getFactory()->createStateMachineProcessReader()->findStateMachineProcess($idStateMachineProcess);

        if ($stateMachineProcessTransfer === null) {
            return $this->jsonResponse([], 404);
        }

        return $this->jsonResponse(
            $this->getFactory()->createInstanceTable($stateMachineProcessTransfer, $idBackStateMachineProcessDefinition)->fetchData(),
        );
    }

    public function triggerAction(Request $request): RedirectResponse
    {
        $idStateMachineProcessDefinition = $this->castId($request->query->get(static::PARAM_ID_DEFINITION));
        $identifier = $this->castId($request->query->get(static::PARAM_IDENTIFIER));
        $eventName = (string)$request->request->get(static::PARAM_EVENT, $request->query->get(static::PARAM_EVENT));
        $redirectUrl = $this->buildTriggerRedirectUrl($request, $idStateMachineProcessDefinition, $identifier);

        $instanceEventTriggerForm = $this->getFactory()->createInstanceEventTriggerForm()->handleRequest($request);

        if (!$instanceEventTriggerForm->isSubmitted() || !$instanceEventTriggerForm->isValid()) {
            $this->addErrorMessage(static::MESSAGE_CSRF_TOKEN_INVALID);

            return $this->redirectResponse($redirectUrl);
        }

        if ($eventName === '') {
            $this->addErrorMessage('Could not trigger the event.');

            return $this->redirectResponse($redirectUrl);
        }

        $stateMachineEventTriggerResponseTransfer = $this->getFacade()->triggerStateMachineInstanceEvent(
            (new StateMachineEventTriggerRequestTransfer())
                ->setEventName($eventName)
                ->setIdentifier($identifier)
                ->setStateMachineProcessDefinition(
                    (new StateMachineProcessDefinitionTransfer())->setIdStateMachineProcessDefinition($idStateMachineProcessDefinition),
                ),
        );

        foreach ($stateMachineEventTriggerResponseTransfer->getErrors() as $errorTransfer) {
            $this->addErrorMessage($errorTransfer->getMessageOrFail());
        }

        if ($stateMachineEventTriggerResponseTransfer->getErrors()->count() === 0) {
            $this->addSuccessMessage('The event "%event%" was triggered.', ['%event%' => $eventName]);
        }

        return $this->redirectResponse($redirectUrl);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|array<string, mixed>
     */
    public function viewAction(Request $request): array|RedirectResponse
    {
        $idStateMachineProcessDefinition = $this->castId($request->query->get(static::PARAM_ID_DEFINITION));
        $identifier = $this->castId($request->query->get(static::PARAM_IDENTIFIER));

        $stateMachineProcessDefinitionInstanceTransfer = $this->findStateMachineProcessDefinitionInstance($idStateMachineProcessDefinition, $identifier);

        if ($stateMachineProcessDefinitionInstanceTransfer === null) {
            $this->addErrorMessage('The instance was not found.');

            return $this->redirectResponse(Url::generate(static::URL_PROCESS_LIST)->build());
        }

        $stateMachineProcessDefinitionWithDefinitionTransfer = $this->findStateMachineProcessVersionOrFlashError($idStateMachineProcessDefinition);

        if ($stateMachineProcessDefinitionWithDefinitionTransfer === null) {
            return $this->redirectResponse(Url::generate(static::URL_PROCESS_LIST)->build());
        }

        $stateMachineProcessDefinitionTransfer = $stateMachineProcessDefinitionInstanceTransfer->getStateMachineProcessDefinitionOrFail();
        $stateMachineProcessTransfer = $stateMachineProcessDefinitionTransfer->getStateMachineProcessOrFail();
        $idBackStateMachineProcessDefinition = $this->getIdBackStateMachineProcessDefinition($request, $idStateMachineProcessDefinition);
        $currentState = $stateMachineProcessDefinitionInstanceTransfer->getStateMachineItemState()?->getName();

        return $this->viewResponse([
            'stateMachineName' => $stateMachineProcessTransfer->getStateMachineName(),
            'processName' => $stateMachineProcessTransfer->getProcessName(),
            'version' => $stateMachineProcessDefinitionTransfer->getVersion(),
            'identifier' => $identifier,
            'currentState' => $currentState,
            'definition' => $stateMachineProcessDefinitionWithDefinitionTransfer->getDefinition(),
            'initialState' => $stateMachineProcessDefinitionWithDefinitionTransfer->getInitialState(),
            'idStateMachineProcessDefinition' => $idStateMachineProcessDefinition,
            'idBackStateMachineProcessDefinition' => $idBackStateMachineProcessDefinition,
            'transitionHistory' => $this->getTransitionHistory($stateMachineProcessTransfer->getIdStateMachineProcessOrFail(), $identifier),
            'manualEvents' => $this->getManualEvents($stateMachineProcessDefinitionTransfer, $currentState),
            'manualEventTriggerForm' => $this->getFactory()
                ->createInstanceEventTriggerForm([
                    'action' => Url::generate(static::URL_INSTANCE_TRIGGER, [
                        static::PARAM_ID_DEFINITION => $idStateMachineProcessDefinition,
                        static::PARAM_IDENTIFIER => $identifier,
                        static::PARAM_BACK_ID_DEFINITION => $idBackStateMachineProcessDefinition,
                    ])->build(),
                ])
                ->createView(),
        ]);
    }

    protected function findStateMachineProcessDefinitionInstance(
        int $idStateMachineProcessDefinition,
        int $identifier
    ): ?StateMachineProcessDefinitionInstanceTransfer {
        return $this->getFacade()->getStateMachineProcessDefinitionInstanceCollection(
            (new StateMachineProcessDefinitionInstanceCriteriaTransfer())
                ->setStateMachineProcessDefinitionInstanceConditions(
                    (new StateMachineProcessDefinitionInstanceConditionsTransfer())
                        ->addIdStateMachineProcessDefinition($idStateMachineProcessDefinition)
                        ->addIdentifier($identifier)
                        ->setWithStateMachineItemState(true)
                        ->setWithStateMachineProcessDefinition(true)
                        ->setWithStateMachineProcess(true),
                ),
        )->getStateMachineProcessDefinitionInstances()->getIterator()->current() ?: null;
    }

    /**
     * @return array<string>
     */
    protected function getManualEvents(StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer, ?string $currentState): array
    {
        $stateMachineProcessTransfer = $stateMachineProcessDefinitionTransfer->getStateMachineProcessOrFail();

        return $this->getFactory()->getStateMachineFacade()->getManualEventsForStateMachineItem(
            (new StateMachineItemTransfer())
                ->setStateMachineName($stateMachineProcessTransfer->getStateMachineName())
                ->setProcessName($stateMachineProcessTransfer->getProcessName())
                ->setStateName($currentState)
                ->setVersion($stateMachineProcessDefinitionTransfer->getVersion()),
        );
    }

    protected function buildTriggerRedirectUrl(Request $request, int $idStateMachineProcessDefinition, int $identifier): string
    {
        $idBackStateMachineProcessDefinition = $this->getIdBackStateMachineProcessDefinition($request, $idStateMachineProcessDefinition);

        if ($request->query->get(static::PARAM_REDIRECT_TARGET) === static::REDIRECT_TARGET_VERSION_VIEW) {
            return Url::generate(static::URL_VERSION_VIEW, [static::PARAM_ID_DEFINITION => $idBackStateMachineProcessDefinition])->build();
        }

        return Url::generate(static::URL_INSTANCE_VIEW, [
            static::PARAM_ID_DEFINITION => $idStateMachineProcessDefinition,
            static::PARAM_IDENTIFIER => $identifier,
            static::PARAM_BACK_ID_DEFINITION => $idBackStateMachineProcessDefinition,
        ])->build();
    }

    protected function getIdBackStateMachineProcessDefinition(Request $request, int $idStateMachineProcessDefinition): int
    {
        if (!$request->query->has(static::PARAM_BACK_ID_DEFINITION)) {
            return $idStateMachineProcessDefinition;
        }

        return $this->castId($request->query->get(static::PARAM_BACK_ID_DEFINITION));
    }

    /**
     * @return array<array<string, mixed>>
     */
    protected function getTransitionHistory(int $idStateMachineProcess, int $identifier): array
    {
        $stateMachineItemTransfers = $this->getFactory()
            ->getStateMachineFacade()
            ->getStateHistoryByStateItemIdentifier($idStateMachineProcess, $identifier);

        $transitionHistory = [];
        foreach ($stateMachineItemTransfers as $stateMachineItemTransfer) {
            $transitionHistory[] = [
                'stateName' => $stateMachineItemTransfer->getStateName(),
                'createdAt' => $stateMachineItemTransfer->getCreatedAt(),
            ];
        }

        return $transitionHistory;
    }
}

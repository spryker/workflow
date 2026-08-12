<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Controller;

use Generated\Shared\Transfer\StateMachineEventTriggerRequestTransfer;
use Generated\Shared\Transfer\StateMachineItemTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Spryker\Service\UtilText\Model\Url\Url;
use Spryker\Zed\Kernel\Communication\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @method \Spryker\Zed\Workflow\Business\WorkflowFacadeInterface getFacade()
 * @method \Spryker\Zed\Workflow\Communication\WorkflowCommunicationFactory getFactory()
 * @method \Spryker\Zed\Workflow\WorkflowConfig getConfig()
 */
class InstanceController extends AbstractController
{
    /**
     * @var string
     */
    protected const PARAM_ID_DEFINITION = 'id-definition';

    /**
     * @var string
     */
    protected const PARAM_IDENTIFIER = 'identifier';

    /**
     * @var string
     */
    protected const PARAM_EVENT = 'event';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\InstanceController::viewAction()
     *
     * @var string
     */
    protected const URL_INSTANCE_VIEW = '/workflow/instance/view';

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
        if (!$request->query->get(static::PARAM_ID_DEFINITION)) {
            return $this->redirectResponse(static::URL_PROCESS);
        }

        $idStateMachineProcessDefinition = $this->castId($request->query->get(static::PARAM_ID_DEFINITION));

        $stateMachineProcessDefinitionCollectionTransfer = $this->getFacade()->getStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCriteriaTransfer())
                ->setStateMachineProcessDefinitionConditions(
                    (new StateMachineProcessDefinitionConditionsTransfer())->addIdStateMachineProcessDefinition($idStateMachineProcessDefinition),
                ),
        );

        $stateMachineProcessDefinitionTransfer = $stateMachineProcessDefinitionCollectionTransfer->getStateMachineProcessDefinitions()->getIterator()->current() ?: null;
        $stateMachineProcessTransfer = $stateMachineProcessDefinitionTransfer?->getStateMachineProcessOrFail();

        return $this->viewResponse([
            'idStateMachineProcessDefinition' => $idStateMachineProcessDefinition,
            'idStateMachineProcess' => $stateMachineProcessTransfer?->getIdStateMachineProcess(),
            'processName' => $stateMachineProcessTransfer?->getProcessName(),
            'instanceTable' => $this->getFactory()->createInstanceTable($idStateMachineProcessDefinition)->render(),
        ]);
    }

    public function tableAction(Request $request): JsonResponse
    {
        $idStateMachineProcessDefinition = $this->castId($request->query->get(static::PARAM_ID_DEFINITION));

        return $this->jsonResponse(
            $this->getFactory()->createInstanceTable($idStateMachineProcessDefinition)->fetchData(),
        );
    }

    public function triggerAction(Request $request): RedirectResponse
    {
        $idStateMachineProcessDefinition = $this->castId($request->query->get(static::PARAM_ID_DEFINITION));
        $identifier = $this->castId($request->query->get(static::PARAM_IDENTIFIER));
        $eventName = (string)$request->query->get(static::PARAM_EVENT);

        if ($eventName === '') {
            $this->addErrorMessage('Could not trigger the event.');

            return $this->redirectResponse($this->buildInstanceViewUrl($idStateMachineProcessDefinition, $identifier));
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

        return $this->redirectResponse($this->buildInstanceViewUrl($idStateMachineProcessDefinition, $identifier));
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

        $stateMachineProcessDefinitionInstanceCollectionTransfer = $this->getFacade()->getStateMachineProcessDefinitionInstanceCollection(
            (new StateMachineProcessDefinitionInstanceCriteriaTransfer())
                ->setStateMachineProcessDefinitionInstanceConditions(
                    (new StateMachineProcessDefinitionInstanceConditionsTransfer())
                        ->addIdStateMachineProcessDefinition($idStateMachineProcessDefinition)
                        ->addIdentifier($identifier)
                        ->setWithStateMachineItemState(true)
                        ->setWithStateMachineProcessDefinition(true)
                        ->setWithStateMachineProcess(true),
                ),
        );

        $stateMachineProcessDefinitionInstanceTransfer = $stateMachineProcessDefinitionInstanceCollectionTransfer->getStateMachineProcessDefinitionInstances()->getIterator()->current() ?: null;

        if ($stateMachineProcessDefinitionInstanceTransfer === null) {
            $this->addErrorMessage('The instance was not found.');

            return $this->redirectResponse(Url::generate(static::URL_PROCESS)->build());
        }

        $stateMachineProcessDefinitionTransfer = $stateMachineProcessDefinitionInstanceTransfer->getStateMachineProcessDefinitionOrFail();
        $stateMachineProcessTransfer = $stateMachineProcessDefinitionTransfer->getStateMachineProcessOrFail();
        $stateMachineItemStateTransfer = $stateMachineProcessDefinitionInstanceTransfer->getStateMachineItemState();
        $currentState = $stateMachineItemStateTransfer !== null ? $stateMachineItemStateTransfer->getName() : null;
        $idStateMachineProcess = $stateMachineProcessTransfer->getIdStateMachineProcessOrFail();

        $manualEvents = $this->getFactory()->getStateMachineFacade()->getManualEventsForStateMachineItem(
            (new StateMachineItemTransfer())
                ->setStateMachineName($stateMachineProcessTransfer->getStateMachineName())
                ->setProcessName($stateMachineProcessTransfer->getProcessName())
                ->setStateName($currentState)
                ->setVersion($stateMachineProcessDefinitionTransfer->getVersion()),
        );

        return $this->viewResponse([
            'stateMachineName' => $stateMachineProcessTransfer->getStateMachineName(),
            'processName' => $stateMachineProcessTransfer->getProcessName(),
            'version' => $stateMachineProcessDefinitionTransfer->getVersion(),
            'identifier' => $identifier,
            'currentState' => $currentState,
            'idStateMachineProcessDefinition' => $idStateMachineProcessDefinition,
            'transitionHistory' => $this->getTransitionHistory($idStateMachineProcess, $identifier),
            'manualEvents' => $manualEvents,
        ]);
    }

    protected function buildInstanceViewUrl(int $idStateMachineProcessDefinition, int $identifier): string
    {
        return Url::generate(static::URL_INSTANCE_VIEW, [
            static::PARAM_ID_DEFINITION => $idStateMachineProcessDefinition,
            static::PARAM_IDENTIFIER => $identifier,
        ])->build();
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

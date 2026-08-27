<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Controller;

use Generated\Shared\Transfer\StateMachineProcessCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Spryker\Service\UtilText\Model\Url\Url;
use Spryker\Zed\Workflow\Communication\Form\ProcessForm;
use Spryker\Zed\Workflow\WorkflowConfig;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @method \Spryker\Zed\Workflow\Business\WorkflowFacadeInterface getFacade()
 * @method \Spryker\Zed\Workflow\Communication\WorkflowCommunicationFactory getFactory()
 */
class ProcessController extends AbstractWorkflowController
{
    protected const string MESSAGE_WORKFLOW_ACTIVATED = 'The workflow was activated.';

    protected const string MESSAGE_WORKFLOW_DEACTIVATED = 'The workflow was deactivated.';

    /**
     * @return array<string, mixed>
     */
    public function indexAction(): array
    {
        return $this->viewResponse([
            'processTable' => $this->getFactory()->createProcessTable()->render(),
        ]);
    }

    public function tableAction(): JsonResponse
    {
        return $this->jsonResponse(
            $this->getFactory()->createProcessTable()->fetchData(),
        );
    }

    /**
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|array<string, mixed>
     */
    public function createAction(Request $request): array|RedirectResponse
    {
        $processForm = $this->getFactory()->createProcessForm();
        $processForm->handleRequest($request);

        if ($processForm->isSubmitted() && $processForm->isValid()) {
            return $this->handleCreate($processForm->getData());
        }

        return $this->viewResponse([
            'processForm' => $processForm->createView(),
        ]);
    }

    public function activateAction(Request $request): RedirectResponse
    {
        $idStateMachineProcess = $this->castId($request->query->get(static::PARAM_ID_PROCESS));

        $processActivationForm = $this->getFactory()->createProcessActivationForm()->handleRequest($request);

        if (!$processActivationForm->isSubmitted() || !$processActivationForm->isValid()) {
            $this->addErrorMessage(static::MESSAGE_CSRF_TOKEN_INVALID);

            return $this->redirectResponse(Url::generate(static::URL_PROCESS_LIST)->build());
        }

        return $this->updateProcessStatus(
            $idStateMachineProcess,
            WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE,
            static::MESSAGE_WORKFLOW_ACTIVATED,
        );
    }

    public function deactivateAction(Request $request): RedirectResponse
    {
        $idStateMachineProcess = $this->castId($request->query->get(static::PARAM_ID_PROCESS));

        $processActivationForm = $this->getFactory()->createProcessActivationForm()->handleRequest($request);

        if (!$processActivationForm->isSubmitted() || !$processActivationForm->isValid()) {
            $this->addErrorMessage(static::MESSAGE_CSRF_TOKEN_INVALID);

            return $this->redirectResponse(Url::generate(static::URL_PROCESS_LIST)->build());
        }

        return $this->updateProcessStatus(
            $idStateMachineProcess,
            WorkflowConfig::PROCESS_DEFINITION_STATUS_INACTIVE,
            static::MESSAGE_WORKFLOW_DEACTIVATED,
        );
    }

    protected function updateProcessStatus(
        int $idStateMachineProcess,
        string $status,
        string $successMessage
    ): RedirectResponse {
        $processListUrl = Url::generate(static::URL_PROCESS_LIST)->build();

        $stateMachineProcessTransfer = $this->findStateMachineProcessOrFlashError($idStateMachineProcess);

        if ($stateMachineProcessTransfer === null) {
            return $this->redirectResponse($processListUrl);
        }

        $stateMachineProcessCollectionResponseTransfer = $this->getFacade()->updateStateMachineProcessCollection(
            (new StateMachineProcessCollectionRequestTransfer())
                ->addStateMachineProcess(
                    (new StateMachineProcessTransfer())
                        ->setIdStateMachineProcess($idStateMachineProcess)
                        ->setStatus($status),
                )
                ->setIsTransactional(true),
        );

        if ($stateMachineProcessCollectionResponseTransfer->getErrors()->count() > 0) {
            foreach ($stateMachineProcessCollectionResponseTransfer->getErrors() as $errorTransfer) {
                $this->addErrorMessage($errorTransfer->getMessageOrFail());
            }

            return $this->redirectResponse($processListUrl);
        }

        $this->addSuccessMessage($successMessage);

        return $this->redirectResponse($processListUrl);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function handleCreate(array $data): RedirectResponse
    {
        $name = (string)$data[ProcessForm::FIELD_NAME];
        $stateMachineProcessTransfer = (new StateMachineProcessTransfer())
            ->setStateMachineName($name)
            ->setProcessName($name)
            ->setSubjectType($data[ProcessForm::FIELD_SUBJECT_TYPE])
            ->setDescription($data[ProcessForm::FIELD_DESCRIPTION] ?? null);

        $stateMachineProcessCollectionResponseTransfer = $this->getFacade()->createStateMachineProcessCollection(
            (new StateMachineProcessCollectionRequestTransfer())
                ->addStateMachineProcess($stateMachineProcessTransfer),
        );

        if ($stateMachineProcessCollectionResponseTransfer->getErrors()->count() > 0) {
            foreach ($stateMachineProcessCollectionResponseTransfer->getErrors() as $errorTransfer) {
                $this->addErrorMessage($errorTransfer->getMessageOrFail());
            }

            return $this->redirectResponse(Url::generate(static::URL_PROCESS_LIST)->build());
        }

        $this->addSuccessMessage('The workflow was created.');

        return $this->redirectResponse(Url::generate(static::URL_PROCESS_LIST)->build());
    }
}

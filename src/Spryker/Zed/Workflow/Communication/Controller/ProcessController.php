<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Controller;

use Generated\Shared\Transfer\StateMachineProcessCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessCollectionResponseTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Spryker\Service\UtilText\Model\Url\Url;
use Spryker\Zed\Workflow\Communication\Form\ProcessForm;
use Spryker\Zed\Workflow\WorkflowConfig;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
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

    protected const string MESSAGE_WORKFLOW_CREATED = 'The workflow was created.';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\VersionController::viewAction()
     */
    protected const string URL_VERSION_VIEW = '/workflow/version/view';

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
        $processFormOptions = $this->getFactory()->createProcessFormDataProvider()->getOptions();

        $processForm = $this->getFactory()->createProcessForm([], $processFormOptions);
        $processForm->handleRequest($request);

        if ($processForm->isSubmitted() && $processForm->isValid()) {
            $stateMachineProcessCollectionResponseTransfer = $this->createStateMachineProcess($processForm->getData());

            if ($stateMachineProcessCollectionResponseTransfer->getErrors()->count() === 0) {
                $this->addSuccessMessage(static::MESSAGE_WORKFLOW_CREATED);

                return $this->redirectResponse(Url::generate(static::URL_PROCESS_LIST)->build());
            }

            $this->addFormErrors($processForm, $stateMachineProcessCollectionResponseTransfer);
        }

        return $this->viewResponse([
            'processForm' => $processForm->createView(),
            'availablePluginsIndexedBySubjectType' => $this->getFactory()->createAvailablePluginReader()->getAvailablePluginsIndexedBySubjectType(
                $processFormOptions[ProcessForm::OPTION_SUBJECT_TYPES],
            ),
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
            $this->buildStatusChangeRedirectUrl($request),
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
            $this->buildStatusChangeRedirectUrl($request),
        );
    }

    protected function updateProcessStatus(
        int $idStateMachineProcess,
        string $status,
        string $successMessage,
        string $redirectUrl
    ): RedirectResponse {
        $stateMachineProcessTransfer = $this->findStateMachineProcessOrFlashError($idStateMachineProcess);

        if ($stateMachineProcessTransfer === null) {
            return $this->redirectResponse($redirectUrl);
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

            return $this->redirectResponse($redirectUrl);
        }

        $this->addSuccessMessage($successMessage);

        return $this->redirectResponse($redirectUrl);
    }

    protected function buildStatusChangeRedirectUrl(Request $request): string
    {
        $idStateMachineProcessDefinition = $request->query->getInt(static::PARAM_ID_DEFINITION);

        if ($idStateMachineProcessDefinition === 0) {
            return Url::generate(static::URL_PROCESS_LIST)->build();
        }

        return Url::generate(static::URL_VERSION_VIEW, [static::PARAM_ID_DEFINITION => $idStateMachineProcessDefinition])->build();
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function createStateMachineProcess(array $data): StateMachineProcessCollectionResponseTransfer
    {
        $stateMachineProcessTransfer = $this->getFactory()->createProcessFormDataMapper()->mapProcessFormDataToStateMachineProcessTransfer(
            $data,
            new StateMachineProcessTransfer(),
            new StateMachineProcessDefinitionTransfer(),
        );

        return $this->getFacade()->createStateMachineProcessCollection(
            (new StateMachineProcessCollectionRequestTransfer())
                ->addStateMachineProcess($stateMachineProcessTransfer),
        );
    }

    protected function addFormErrors(
        FormInterface $processForm,
        StateMachineProcessCollectionResponseTransfer $stateMachineProcessCollectionResponseTransfer
    ): void {
        foreach ($stateMachineProcessCollectionResponseTransfer->getErrors() as $errorTransfer) {
            $processForm->addError(new FormError($errorTransfer->getMessageOrFail()));
        }
    }
}

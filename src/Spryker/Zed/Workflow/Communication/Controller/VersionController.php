<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Controller;

use Generated\Shared\Transfer\StateMachineProcessConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionResponseTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Spryker\Service\UtilText\Model\Url\Url;
use Spryker\Zed\Kernel\Communication\Controller\AbstractController;
use Spryker\Zed\Workflow\Communication\Form\VersionForm;
use Spryker\Zed\Workflow\WorkflowConfig;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @method \Spryker\Zed\Workflow\Business\WorkflowFacadeInterface getFacade()
 * @method \Spryker\Zed\Workflow\Communication\WorkflowCommunicationFactory getFactory()
 */
class VersionController extends AbstractController
{
    /**
     * @var string
     */
    protected const MESSAGE_CSRF_TOKEN_INVALID = 'CSRF token is not valid.';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\ProcessController::indexAction()
     *
     * @var string
     */
    protected const URL_PROCESS_LIST = '/workflow/process';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\VersionController::indexAction()
     *
     * @var string
     */
    protected const URL_VERSION_LIST = '/workflow/version';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\VersionController::createAction()
     *
     * @var string
     */
    protected const URL_VERSION_CREATE = '/workflow/version/create';

    /**
     * @var string
     */
    protected const PARAM_ID_PROCESS = 'id-process';

    /**
     * @var string
     */
    protected const PARAM_ID_DEFINITION = 'id-definition';

    /**
     * @var string
     */
    protected const PARAM_BACK = 'back';

    /**
     * @var string
     */
    protected const BACK_ORIGIN_PROCESS = 'process';

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|array<string, mixed>
     */
    public function indexAction(Request $request): array|RedirectResponse
    {
        if (!$request->query->get(static::PARAM_ID_PROCESS)) {
            return $this->redirectResponse(static::URL_PROCESS_LIST);
        }

        $idStateMachineProcess = $this->castId($request->query->get(static::PARAM_ID_PROCESS));

        $stateMachineProcessCollectionTransfer = $this->getFacade()->getStateMachineProcessCollection(
            (new StateMachineProcessCriteriaTransfer())->setStateMachineProcessConditions(
                (new StateMachineProcessConditionsTransfer())->addIdStateMachineProcess($idStateMachineProcess),
            ),
        );

        $stateMachineProcessTransfer = $stateMachineProcessCollectionTransfer->getStateMachineProcesses()->getIterator()->current();

        return $this->viewResponse([
            'idStateMachineProcess' => $idStateMachineProcess,
            'stateMachineName' => $stateMachineProcessTransfer->getStateMachineName(),
            'processName' => $stateMachineProcessTransfer->getProcessName(),
            'versionTable' => $this->getFactory()->createVersionTable($idStateMachineProcess)->render(),
        ]);
    }

    public function tableAction(Request $request): JsonResponse
    {
        $idStateMachineProcess = $this->castId($request->query->get(static::PARAM_ID_PROCESS));

        return $this->jsonResponse(
            $this->getFactory()->createVersionTable($idStateMachineProcess)->fetchData(),
        );
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|array<string, mixed>
     */
    public function viewAction(Request $request): array|RedirectResponse
    {
        $idStateMachineProcessDefinition = $this->castId($request->query->get(static::PARAM_ID_DEFINITION));

        $stateMachineProcessDefinitionCollectionTransfer = $this->getFacade()->getStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCriteriaTransfer())
                ->setStateMachineProcessDefinitionConditions(
                    (new StateMachineProcessDefinitionConditionsTransfer())->addIdStateMachineProcessDefinition($idStateMachineProcessDefinition),
                ),
        );

        $stateMachineProcessDefinitionTransfer = $stateMachineProcessDefinitionCollectionTransfer->getStateMachineProcessDefinitions()->getIterator()->current();

        $idStateMachineProcess = $stateMachineProcessDefinitionTransfer->getStateMachineProcessOrFail()->getIdStateMachineProcessOrFail();

        $stateMachineProcessCollectionTransfer = $this->getFacade()->getStateMachineProcessCollection(
            (new StateMachineProcessCriteriaTransfer())->setStateMachineProcessConditions(
                (new StateMachineProcessConditionsTransfer())->addIdStateMachineProcess($idStateMachineProcess),
            ),
        );

        $stateMachineProcessTransfer = $stateMachineProcessCollectionTransfer->getStateMachineProcesses()->getIterator()->current() ?: null;

        $backLink = $this->resolveBackLink((string)$request->query->get(static::PARAM_BACK), $idStateMachineProcess);

        return $this->viewResponse([
            'stateMachineName' => $stateMachineProcessTransfer->getStateMachineName(),
            'processName' => $stateMachineProcessTransfer->getProcessName(),
            'version' => $stateMachineProcessDefinitionTransfer->getVersion(),
            'status' => $stateMachineProcessDefinitionTransfer->getStatus(),
            'definition' => $stateMachineProcessDefinitionTransfer->getDefinition(),
            'idStateMachineProcess' => $idStateMachineProcess,
            'idStateMachineProcessDefinition' => $idStateMachineProcessDefinition,
            'isActive' => $stateMachineProcessDefinitionTransfer->getStatus() === WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE,
            'backUrl' => $backLink['url'],
            'backLabel' => $backLink['label'],
            'versionActivationForm' => $this->getFactory()->createVersionActivationForm()->createView(),
        ]);
    }

    /**
     * Resolves the "Back" button target based on where the version view was opened from: the workflows
     * (process) list when back=process, otherwise the versions list of the owning process.
     *
     * @return array{url: string, label: string}
     */
    protected function resolveBackLink(string $backOrigin, int $idStateMachineProcess): array
    {
        if ($backOrigin === static::BACK_ORIGIN_PROCESS) {
            return [
                'url' => Url::generate(static::URL_PROCESS_LIST)->build(),
                'label' => 'Back to workflows',
            ];
        }

        return [
            'url' => Url::generate(static::URL_VERSION_LIST, [static::PARAM_ID_PROCESS => $idStateMachineProcess])->build(),
            'label' => 'Back to versions',
        ];
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
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|array<string, mixed>
     */
    public function createAction(Request $request): array|RedirectResponse
    {
        $idStateMachineProcess = $this->castId($request->query->get(static::PARAM_ID_PROCESS));

        $versionForm = $this->getFactory()->createVersionForm(
            $this->buildVersionFormData($request, $idStateMachineProcess),
        );

        $versionForm->handleRequest($request);

        if ($versionForm->isSubmitted() && $versionForm->isValid()) {
            $data = $versionForm->getData();

            $stateMachineProcessDefinitionTransfer = (new StateMachineProcessDefinitionTransfer())
                ->setStateMachineProcess((new StateMachineProcessTransfer())->setIdStateMachineProcess($idStateMachineProcess))
                ->setInitialState($data[VersionForm::FIELD_INITIAL_STATE])
                ->setDefinition($data[VersionForm::FIELD_DEFINITION])
                ->setStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_INACTIVE);

            $stateMachineProcessDefinitionCollectionResponseTransfer = $this->getFacade()->createStateMachineProcessDefinitionCollection(
                (new StateMachineProcessDefinitionCollectionRequestTransfer())
                    ->addStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer),
            );

            if ($stateMachineProcessDefinitionCollectionResponseTransfer->getErrors()->count() > 0) {
                $this->addValidationErrorMessages($stateMachineProcessDefinitionCollectionResponseTransfer);

                return $this->redirectResponse(
                    Url::generate(static::URL_VERSION_CREATE, [static::PARAM_ID_PROCESS => $idStateMachineProcess])->build(),
                );
            }

            $this->addSuccessMessage('The version was saved.');

            return $this->redirectResponse(
                Url::generate(static::URL_VERSION_LIST, [static::PARAM_ID_PROCESS => $idStateMachineProcess])->build(),
            );
        }

        return $this->viewResponse([
            'versionForm' => $versionForm->createView(),
            'idStateMachineProcess' => $idStateMachineProcess,
            'processName' => $this->findProcessName($idStateMachineProcess),
        ]);
    }

    /**
     * Seeds the create form. When an id-definition is supplied (the "Edit" action), the source version's
     * initial state and definition are prefilled so saving produces a new version cloned from it.
     *
     * @return array<string, mixed>
     */
    protected function buildVersionFormData(Request $request, int $idStateMachineProcess): array
    {
        $data = [VersionForm::FIELD_ID_STATE_MACHINE_PROCESS => $idStateMachineProcess];

        $idStateMachineProcessDefinition = $request->query->get(static::PARAM_ID_DEFINITION);

        if ($idStateMachineProcessDefinition === null) {
            return $data;
        }

        $stateMachineProcessDefinitionTransfer = $this->getFacade()->getStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCriteriaTransfer())
                ->setStateMachineProcessDefinitionConditions(
                    (new StateMachineProcessDefinitionConditionsTransfer())->addIdStateMachineProcessDefinition($this->castId($idStateMachineProcessDefinition)),
                ),
        )->getStateMachineProcessDefinitions()->getIterator()->current() ?: null;

        if ($stateMachineProcessDefinitionTransfer === null) {
            return $data;
        }

        $data[VersionForm::FIELD_INITIAL_STATE] = $stateMachineProcessDefinitionTransfer->getInitialState();
        $data[VersionForm::FIELD_DEFINITION] = $stateMachineProcessDefinitionTransfer->getDefinition();

        return $data;
    }

    public function validateDefinitionAction(Request $request): JsonResponse
    {
        $versionFormData = $request->request->all(VersionForm::FORM_NAME);
        $idStateMachineProcess = $this->castId($versionFormData[VersionForm::FIELD_ID_STATE_MACHINE_PROCESS] ?? null);

        $stateMachineProcessDefinitionTransfer = (new StateMachineProcessDefinitionTransfer())
            ->setStateMachineProcess((new StateMachineProcessTransfer())->setIdStateMachineProcess($idStateMachineProcess))
            ->setInitialState((string)($versionFormData[VersionForm::FIELD_INITIAL_STATE] ?? ''))
            ->setDefinition((string)($versionFormData[VersionForm::FIELD_DEFINITION] ?? ''));

        $stateMachineDefinitionValidationResponseTransfer = $this->getFacade()->validateStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);

        $errors = [];
        foreach ($stateMachineDefinitionValidationResponseTransfer->getErrors() as $stateMachineDefinitionValidationErrorTransfer) {
            $errors[] = $stateMachineDefinitionValidationErrorTransfer->toArray(true, true);
        }

        return $this->jsonResponse([
            'isValid' => $stateMachineDefinitionValidationResponseTransfer->getIsValid(),
            'errors' => $errors,
        ]);
    }

    public function activateAction(Request $request): RedirectResponse
    {
        $idStateMachineProcessDefinition = $this->castId($request->query->get(static::PARAM_ID_DEFINITION));

        $versionActivationForm = $this->getFactory()->createVersionActivationForm()->handleRequest($request);

        if (!$versionActivationForm->isSubmitted() || !$versionActivationForm->isValid()) {
            $this->addErrorMessage(static::MESSAGE_CSRF_TOKEN_INVALID);

            return $this->redirectResponse(Url::generate(static::URL_PROCESS_LIST)->build());
        }

        $stateMachineProcessDefinitionCollectionResponseTransfer = $this->getFacade()->updateStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCollectionRequestTransfer())
                ->addStateMachineProcessDefinition(
                    (new StateMachineProcessDefinitionTransfer())
                        ->setIdStateMachineProcessDefinition($idStateMachineProcessDefinition)
                        ->setStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE),
                )
                ->setIsTransactional(true),
        );

        if ($stateMachineProcessDefinitionCollectionResponseTransfer->getErrors()->count() > 0) {
            $this->addValidationErrorMessages($stateMachineProcessDefinitionCollectionResponseTransfer);

            return $this->redirectResponse($this->buildVersionListUrl($stateMachineProcessDefinitionCollectionResponseTransfer));
        }

        $this->addSuccessMessage('The version was activated.');

        return $this->redirectResponse($this->buildVersionListUrl($stateMachineProcessDefinitionCollectionResponseTransfer));
    }

    public function deactivateAction(Request $request): RedirectResponse
    {
        $idStateMachineProcessDefinition = $this->castId($request->query->get(static::PARAM_ID_DEFINITION));

        $versionActivationForm = $this->getFactory()->createVersionActivationForm()->handleRequest($request);

        if (!$versionActivationForm->isSubmitted() || !$versionActivationForm->isValid()) {
            $this->addErrorMessage(static::MESSAGE_CSRF_TOKEN_INVALID);

            return $this->redirectResponse(Url::generate(static::URL_PROCESS_LIST)->build());
        }

        $stateMachineProcessDefinitionCollectionResponseTransfer = $this->getFacade()->updateStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCollectionRequestTransfer())
                ->addStateMachineProcessDefinition(
                    (new StateMachineProcessDefinitionTransfer())
                        ->setIdStateMachineProcessDefinition($idStateMachineProcessDefinition)
                        ->setStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_INACTIVE),
                )
                ->setIsTransactional(true),
        );

        if ($stateMachineProcessDefinitionCollectionResponseTransfer->getErrors()->count() > 0) {
            $this->addValidationErrorMessages($stateMachineProcessDefinitionCollectionResponseTransfer);

            return $this->redirectResponse($this->buildVersionListUrl($stateMachineProcessDefinitionCollectionResponseTransfer));
        }

        $this->addSuccessMessage('The version was deactivated.');

        return $this->redirectResponse($this->buildVersionListUrl($stateMachineProcessDefinitionCollectionResponseTransfer));
    }

    public function deleteAction(Request $request): RedirectResponse
    {
        $idStateMachineProcessDefinition = $this->castId($request->query->get(static::PARAM_ID_DEFINITION));
        $idStateMachineProcess = $this->castId($request->query->get(static::PARAM_ID_PROCESS));

        $versionDeleteForm = $this->getFactory()->createVersionDeleteForm()->handleRequest($request);

        if (!$versionDeleteForm->isSubmitted() || !$versionDeleteForm->isValid()) {
            $this->addErrorMessage(static::MESSAGE_CSRF_TOKEN_INVALID);

            return $this->redirectResponse(
                Url::generate(static::URL_VERSION_LIST, [static::PARAM_ID_PROCESS => $idStateMachineProcess])->build(),
            );
        }

        $stateMachineProcessDefinitionCollectionResponseTransfer = $this->getFacade()->deleteStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCollectionRequestTransfer())
                ->addStateMachineProcessDefinition(
                    (new StateMachineProcessDefinitionTransfer())
                        ->setIdStateMachineProcessDefinition($idStateMachineProcessDefinition),
                )
                ->setIsTransactional(true),
        );

        $versionListUrl = Url::generate(static::URL_VERSION_LIST, [static::PARAM_ID_PROCESS => $idStateMachineProcess])->build();

        if ($stateMachineProcessDefinitionCollectionResponseTransfer->getErrors()->count() > 0) {
            $this->addValidationErrorMessages($stateMachineProcessDefinitionCollectionResponseTransfer);

            return $this->redirectResponse($versionListUrl);
        }

        $this->addSuccessMessage('The version was deleted.');

        return $this->redirectResponse($versionListUrl);
    }

    protected function addValidationErrorMessages(
        StateMachineProcessDefinitionCollectionResponseTransfer $stateMachineProcessDefinitionCollectionResponseTransfer
    ): void {
        foreach ($stateMachineProcessDefinitionCollectionResponseTransfer->getErrors() as $errorTransfer) {
            $this->addErrorMessage($errorTransfer->getMessageOrFail());
        }
    }

    protected function buildVersionListUrl(
        StateMachineProcessDefinitionCollectionResponseTransfer $stateMachineProcessDefinitionCollectionResponseTransfer
    ): string {
        $stateMachineProcessDefinitionTransfer = $stateMachineProcessDefinitionCollectionResponseTransfer
            ->getStateMachineProcessDefinitions()
            ->getIterator()
            ->current();
        if ($stateMachineProcessDefinitionTransfer === null) {
            return Url::generate(static::URL_PROCESS_LIST)->build();
        }

        $idStateMachineProcess = $stateMachineProcessDefinitionTransfer->getStateMachineProcessOrFail()->getIdStateMachineProcess();

        return Url::generate(static::URL_VERSION_LIST, [static::PARAM_ID_PROCESS => (int)$idStateMachineProcess])->build();
    }
}

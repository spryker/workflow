<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Controller;

use Generated\Shared\Transfer\StateMachineProcessCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionResponseTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Spryker\Service\UtilText\Model\Url\Url;
use Spryker\Zed\Workflow\Communication\Form\ProcessEditForm;
use Spryker\Zed\Workflow\Communication\Form\StateMachineDefinitionForm;
use Spryker\Zed\Workflow\Communication\Form\VersionForm;
use Spryker\Zed\Workflow\WorkflowConfig;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @method \Spryker\Zed\Workflow\Business\WorkflowFacadeInterface getFacade()
 * @method \Spryker\Zed\Workflow\Communication\WorkflowCommunicationFactory getFactory()
 */
class VersionController extends AbstractWorkflowController
{
    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\VersionController::indexAction()
     */
    protected const string URL_VERSION_LIST = '/workflow/version';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\VersionController::viewAction()
     */
    protected const string URL_VERSION_VIEW = '/workflow/version/view';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\ProcessController::activateAction()
     */
    protected const string URL_PROCESS_ACTIVATE = '/workflow/process/activate';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\ProcessController::deactivateAction()
     */
    protected const string URL_PROCESS_DEACTIVATE = '/workflow/process/deactivate';

    protected const string MESSAGE_WORKFLOW_DETAILS_SAVED = 'The workflow details were saved.';

    protected const string MESSAGE_VERSION_SAVED = 'The version was saved.';

    protected const string MESSAGE_VERSION_DELETED = 'The version was deleted.';

    protected const string LABEL_BACK_TO_WORKFLOWS = 'Back to workflows';

    protected const string LABEL_BACK_TO_WORKFLOW = 'Back to workflow';

    protected const string LABEL_BACK_TO_VERSIONS = 'Back to versions';

    protected const string KEY_BACK_LINK_URL = 'url';

    protected const string KEY_BACK_LINK_LABEL = 'label';

    public const string PARAM_BACK = 'back';

    public const string BACK_ORIGIN_WORKFLOW_LIST = 'workflows';

    public const string BACK_ORIGIN_VERSION_LIST = 'versions';

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

        $stateMachineProcessTransfer = $this->findStateMachineProcessOrFlashError($idStateMachineProcess);

        if ($stateMachineProcessTransfer === null) {
            return $this->redirectResponse(static::URL_PROCESS_LIST);
        }

        $activeStateMachineProcessDefinitionTransfer = $this->getFactory()
            ->createStateMachineProcessVersionReader()
            ->findActiveStateMachineProcessDefinition($idStateMachineProcess);
        $idBackStateMachineProcessDefinition = $request->query->getInt(static::PARAM_ID_DEFINITION)
            ?: $activeStateMachineProcessDefinitionTransfer?->getIdStateMachineProcessDefinition();

        return $this->viewResponse([
            'idStateMachineProcess' => $idStateMachineProcess,
            'stateMachineName' => $stateMachineProcessTransfer->getStateMachineName(),
            'processName' => $stateMachineProcessTransfer->getProcessName(),
            'activeVersion' => $activeStateMachineProcessDefinitionTransfer,
            'versionTable' => $this->getFactory()->createVersionTable($idStateMachineProcess)->render(),
            'backLink' => $idBackStateMachineProcessDefinition
                ? $this->buildVersionViewBackLink($idBackStateMachineProcessDefinition)
                : $this->buildWorkflowListBackLink(),
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
        $stateMachineProcessDefinitionTransfer = $this->findStateMachineProcessVersionOrFlashError($idStateMachineProcessDefinition);

        if ($stateMachineProcessDefinitionTransfer === null) {
            return $this->redirectResponse(static::URL_PROCESS_LIST);
        }

        $stateMachineProcessTransfer = $stateMachineProcessDefinitionTransfer->getStateMachineProcessOrFail();
        $idStateMachineProcess = $stateMachineProcessTransfer->getIdStateMachineProcessOrFail();
        $attachedStateMachineDefinitionTriggerTransfers = $this->getFactory()
            ->createTriggerLabelReader()
            ->getStateMachineDefinitionTriggersIndexedByEventName($idStateMachineProcess);
        $attachedEventNames = array_keys($attachedStateMachineDefinitionTriggerTransfers);
        $processEditForm = $this->createProcessEditForm($stateMachineProcessTransfer, $attachedEventNames)
            ->handleRequest($request);

        if ($processEditForm->isSubmitted() && $processEditForm->isValid()) {
            return $this->saveProcessDetails($processEditForm, $idStateMachineProcess, $attachedStateMachineDefinitionTriggerTransfers, $request);
        }

        $stateMachineDefinitionValidationResponseTransfer = $this->getFacade()->validateStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);
        $isProcessActive = $stateMachineProcessTransfer->getStatus() === WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE;
        $hasActiveVersion = $this->getFactory()
            ->createStateMachineProcessVersionReader()
            ->findActiveStateMachineProcessDefinition($idStateMachineProcess) !== null;

        return $this->viewResponse([
            'stateMachineName' => $stateMachineProcessTransfer->getStateMachineName(),
            'processName' => $stateMachineProcessTransfer->getProcessName(),
            'subjectType' => $stateMachineProcessTransfer->getSubjectType(),
            'description' => $stateMachineProcessTransfer->getDescription(),
            'triggerEventNames' => $attachedEventNames,
            'version' => $stateMachineProcessDefinitionTransfer->getVersion(),
            'status' => $stateMachineProcessDefinitionTransfer->getStatus(),
            'definition' => $stateMachineProcessDefinitionTransfer->getDefinition(),
            'initialState' => $stateMachineProcessDefinitionTransfer->getInitialState(),
            'idStateMachineProcess' => $idStateMachineProcess,
            'idStateMachineProcessDefinition' => $idStateMachineProcessDefinition,
            'backLink' => $this->buildWorkflowListBackLink(),
            'isValid' => $stateMachineDefinitionValidationResponseTransfer->getIsValid(),
            'validationErrors' => $this->buildDefinitionValidationErrors($stateMachineDefinitionValidationResponseTransfer),
            'isProcessActive' => $isProcessActive,
            'processStatusChangeUrl' => Url::generate(
                $isProcessActive ? static::URL_PROCESS_DEACTIVATE : static::URL_PROCESS_ACTIVATE,
                [
                    static::PARAM_ID_PROCESS => $idStateMachineProcess,
                    static::PARAM_ID_DEFINITION => $idStateMachineProcessDefinition,
                ],
            )->build(),
            'processStatusConfirmation' => $this->getFactory()->createProcessStatusConfirmationBuilder()->buildActionConfirmationTransfer(
                $stateMachineProcessTransfer->getProcessNameOrFail(),
                $this->getFactory()->createTriggerLabelReader()->getTriggerLabelsGroupedByIdStateMachineProcess([$idStateMachineProcess])[$idStateMachineProcess] ?? [],
                $isProcessActive,
                $hasActiveVersion,
            ),
            'processEditForm' => $processEditForm->createView(),
            'processActivationForm' => $this->getFactory()->createProcessActivationForm()->createView(),
            'instanceTable' => $this->getFactory()->createInstanceTable($stateMachineProcessTransfer, $idStateMachineProcessDefinition)->render(),
        ]);
    }

    /**
     * @param array<string> $triggerEventNames
     */
    protected function createProcessEditForm(StateMachineProcessTransfer $stateMachineProcessTransfer, array $triggerEventNames): FormInterface
    {
        return $this->getFactory()->createProcessEditForm(
            [
                ProcessEditForm::FIELD_DESCRIPTION => $stateMachineProcessTransfer->getDescription(),
                ProcessEditForm::FIELD_TRIGGERS => $triggerEventNames,
            ],
            $this->getFactory()->createProcessEditFormDataProvider()->getOptions($stateMachineProcessTransfer->getIdStateMachineProcessOrFail()),
        );
    }

    /**
     * @param array<string, \Generated\Shared\Transfer\StateMachineDefinitionTriggerTransfer> $attachedStateMachineDefinitionTriggerTransfers
     */
    protected function saveProcessDetails(
        FormInterface $processEditForm,
        int $idStateMachineProcess,
        array $attachedStateMachineDefinitionTriggerTransfers,
        Request $request
    ): RedirectResponse {
        $stateMachineProcessCollectionResponseTransfer = $this->getFacade()->updateStateMachineProcessCollection(
            $this->getFactory()->createProcessFormDataMapper()->mapProcessEditFormDataToStateMachineProcessCollectionRequestTransfer(
                $processEditForm->getData(),
                $attachedStateMachineDefinitionTriggerTransfers,
                (new StateMachineProcessTransfer())->setIdStateMachineProcess($idStateMachineProcess),
                (new StateMachineProcessCollectionRequestTransfer())->setIsTransactional(true),
            ),
        );

        return $this->redirectWithResult(
            $stateMachineProcessCollectionResponseTransfer->getErrors(),
            static::MESSAGE_WORKFLOW_DETAILS_SAVED,
            Url::generate(static::URL_VERSION_VIEW, $request->query->all())->build(),
        );
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|array<string, mixed>
     */
    public function createAction(Request $request): array|RedirectResponse
    {
        $idStateMachineProcess = $this->castId($request->query->get(static::PARAM_ID_PROCESS));
        $stateMachineProcessTransfer = $this->findStateMachineProcessOrFlashError($idStateMachineProcess);

        if ($stateMachineProcessTransfer === null) {
            return $this->redirectResponse(static::URL_PROCESS_LIST);
        }

        $sourceStateMachineProcessDefinitionTransfer = $this->findSourceStateMachineProcessDefinition($request, $idStateMachineProcess);
        $versionForm = $this->getFactory()
            ->createVersionForm($this->buildVersionFormData($idStateMachineProcess, $sourceStateMachineProcessDefinitionTransfer))
            ->handleRequest($request);

        if ($versionForm->isSubmitted() && $versionForm->isValid()) {
            $stateMachineProcessDefinitionCollectionResponseTransfer = $this->createStateMachineProcessDefinition($versionForm->getData(), $idStateMachineProcess);

            if ($stateMachineProcessDefinitionCollectionResponseTransfer->getErrors()->count() === 0) {
                $this->addSuccessMessage(static::MESSAGE_VERSION_SAVED);
                $idCreatedStateMachineProcessDefinition = $stateMachineProcessDefinitionCollectionResponseTransfer
                    ->getStateMachineProcessDefinitions()
                    ->offsetGet(0)
                    ->getIdStateMachineProcessDefinitionOrFail();

                return $this->redirectResponse(
                    Url::generate(static::URL_VERSION_VIEW, [static::PARAM_ID_DEFINITION => $idCreatedStateMachineProcessDefinition])->build(),
                );
            }

            $this->addErrorMessages($stateMachineProcessDefinitionCollectionResponseTransfer->getErrors());
        }

        $subjectType = (string)$stateMachineProcessTransfer->getSubjectType();

        return $this->viewResponse([
            'idStateMachineProcess' => $idStateMachineProcess,
            'processName' => $stateMachineProcessTransfer->getProcessName(),
            'subjectType' => $subjectType,
            'description' => $stateMachineProcessTransfer->getDescription(),
            'sourceVersion' => $sourceStateMachineProcessDefinitionTransfer?->getVersion(),
            'triggerEventNames' => array_keys($this->getFactory()->createTriggerLabelReader()->getStateMachineDefinitionTriggersIndexedByEventName($idStateMachineProcess)),
            'backLink' => $this->buildVersionCreateBackLink(
                $request->query->getString(static::PARAM_BACK),
                $idStateMachineProcess,
                $sourceStateMachineProcessDefinitionTransfer,
            ),
            'availablePlugins' => $this->getFactory()->createAvailablePluginReader()->getAvailablePlugins($subjectType),
            'versionForm' => $versionForm->createView(),
        ]);
    }

    /**
     * @param array<string, mixed> $versionFormData
     */
    protected function createStateMachineProcessDefinition(
        array $versionFormData,
        int $idStateMachineProcess
    ): StateMachineProcessDefinitionCollectionResponseTransfer {
        $stateMachineProcessDefinitionTransfer = $this->getFactory()->createProcessFormDataMapper()->mapStateMachineDefinitionFormDataToStateMachineProcessDefinitionTransfer(
            $versionFormData[VersionForm::FIELD_STATE_MACHINE_DEFINITION],
            (new StateMachineProcessDefinitionTransfer())
                ->setStateMachineProcess((new StateMachineProcessTransfer())->setIdStateMachineProcess($idStateMachineProcess))
                ->setStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_INACTIVE),
        );

        return $this->getFacade()->createStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCollectionRequestTransfer())
                ->addStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer),
        );
    }

    protected function findSourceStateMachineProcessDefinition(
        Request $request,
        int $idStateMachineProcess
    ): ?StateMachineProcessDefinitionTransfer {
        $idStateMachineProcessDefinition = $request->query->get(static::PARAM_ID_DEFINITION);

        if ($idStateMachineProcessDefinition === null) {
            return null;
        }

        return $this->getFactory()
            ->createStateMachineProcessVersionReader()
            ->findStateMachineProcessDefinitionOfProcess($this->castId($idStateMachineProcessDefinition), $idStateMachineProcess);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildVersionFormData(
        int $idStateMachineProcess,
        ?StateMachineProcessDefinitionTransfer $sourceStateMachineProcessDefinitionTransfer
    ): array {
        $versionFormData = [VersionForm::FIELD_ID_STATE_MACHINE_PROCESS => $idStateMachineProcess];

        if ($sourceStateMachineProcessDefinitionTransfer === null) {
            return $versionFormData;
        }

        $versionFormData[VersionForm::FIELD_STATE_MACHINE_DEFINITION] = [
            StateMachineDefinitionForm::FIELD_INITIAL_STATE => $sourceStateMachineProcessDefinitionTransfer->getInitialState(),
            StateMachineDefinitionForm::FIELD_DEFINITION => $sourceStateMachineProcessDefinitionTransfer->getDefinition(),
        ];

        return $versionFormData;
    }

    public function deleteAction(Request $request): RedirectResponse
    {
        $idStateMachineProcessDefinition = $this->castId($request->query->get(static::PARAM_ID_DEFINITION));
        $idStateMachineProcess = $this->castId($request->query->get(static::PARAM_ID_PROCESS));
        $versionListUrl = Url::generate(static::URL_VERSION_LIST, [static::PARAM_ID_PROCESS => $idStateMachineProcess])->build();

        $versionDeleteForm = $this->getFactory()->createVersionDeleteForm()->handleRequest($request);

        if (!$versionDeleteForm->isSubmitted() || !$versionDeleteForm->isValid()) {
            $this->addErrorMessage(static::MESSAGE_CSRF_TOKEN_INVALID);

            return $this->redirectResponse($versionListUrl);
        }

        $stateMachineProcessDefinitionCollectionResponseTransfer = $this->getFacade()->deleteStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCollectionRequestTransfer())
                ->addStateMachineProcessDefinition(
                    (new StateMachineProcessDefinitionTransfer())
                        ->setIdStateMachineProcessDefinition($idStateMachineProcessDefinition),
                )
                ->setIsTransactional(true),
        );

        return $this->redirectWithResult($stateMachineProcessDefinitionCollectionResponseTransfer->getErrors(), static::MESSAGE_VERSION_DELETED, $versionListUrl);
    }

    /**
     * @return array<string, string>
     */
    protected function buildVersionCreateBackLink(
        string $backOrigin,
        int $idStateMachineProcess,
        ?StateMachineProcessDefinitionTransfer $sourceStateMachineProcessDefinitionTransfer
    ): array {
        if ($backOrigin === static::BACK_ORIGIN_WORKFLOW_LIST) {
            return $this->buildWorkflowListBackLink();
        }

        if ($backOrigin === static::BACK_ORIGIN_VERSION_LIST || $sourceStateMachineProcessDefinitionTransfer === null) {
            return [
                static::KEY_BACK_LINK_URL => Url::generate(static::URL_VERSION_LIST, [static::PARAM_ID_PROCESS => $idStateMachineProcess])->build(),
                static::KEY_BACK_LINK_LABEL => static::LABEL_BACK_TO_VERSIONS,
            ];
        }

        return $this->buildVersionViewBackLink($sourceStateMachineProcessDefinitionTransfer->getIdStateMachineProcessDefinitionOrFail());
    }

    /**
     * @return array<string, string>
     */
    protected function buildVersionViewBackLink(int $idStateMachineProcessDefinition): array
    {
        return [
            static::KEY_BACK_LINK_URL => Url::generate(static::URL_VERSION_VIEW, [static::PARAM_ID_DEFINITION => $idStateMachineProcessDefinition])->build(),
            static::KEY_BACK_LINK_LABEL => static::LABEL_BACK_TO_WORKFLOW,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function buildWorkflowListBackLink(): array
    {
        return [
            static::KEY_BACK_LINK_URL => Url::generate(static::URL_PROCESS_LIST)->build(),
            static::KEY_BACK_LINK_LABEL => static::LABEL_BACK_TO_WORKFLOWS,
        ];
    }
}

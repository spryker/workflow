<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Controller;

use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionResponseTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Spryker\Service\UtilText\Model\Url\Url;
use Spryker\Zed\Workflow\WorkflowConfig;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @method \Spryker\Zed\Workflow\Business\WorkflowFacadeInterface getFacade()
 * @method \Spryker\Zed\Workflow\Communication\WorkflowCommunicationFactory getFactory()
 */
class VersionStatusController extends AbstractWorkflowController
{
    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\VersionController::indexAction()
     */
    protected const string URL_VERSION_LIST = '/workflow/version';

    protected const string MESSAGE_VERSION_ACTIVATION_FAILED = 'Version could not be activated because it is invalid, please check the errors: %errors%';

    protected const string VALIDATION_ERROR_SEPARATOR = '; ';

    protected const string MESSAGE_VERSION_ACTIVATED = 'The version was activated.';

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
        $versionListUrl = $this->buildVersionListUrl($stateMachineProcessDefinitionCollectionResponseTransfer);

        if ($stateMachineProcessDefinitionCollectionResponseTransfer->getErrors()->count() > 0) {
            $this->addActivationValidationErrorMessage($stateMachineProcessDefinitionCollectionResponseTransfer);

            return $this->redirectResponse($versionListUrl);
        }

        $this->addSuccessMessage(static::MESSAGE_VERSION_ACTIVATED);

        return $this->redirectResponse($versionListUrl);
    }

    protected function addActivationValidationErrorMessage(
        StateMachineProcessDefinitionCollectionResponseTransfer $stateMachineProcessDefinitionCollectionResponseTransfer
    ): void {
        $errorMessages = [];
        foreach ($stateMachineProcessDefinitionCollectionResponseTransfer->getErrors() as $errorTransfer) {
            $errorMessages[] = $errorTransfer->getMessageOrFail();
        }

        $this->addErrorMessage(
            static::MESSAGE_VERSION_ACTIVATION_FAILED,
            ['%errors%' => implode(static::VALIDATION_ERROR_SEPARATOR, $errorMessages)],
        );
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

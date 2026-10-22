<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Controller;

use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Spryker\Zed\Workflow\Communication\Form\VersionForm;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @method \Spryker\Zed\Workflow\Business\WorkflowFacadeInterface getFacade()
 * @method \Spryker\Zed\Workflow\Communication\WorkflowCommunicationFactory getFactory()
 */
class VersionDefinitionController extends AbstractWorkflowController
{
    public function validateAction(Request $request): JsonResponse
    {
        $versionForm = $this->getFactory()->createVersionForm();
        $versionForm->submit($request->request->all(VersionForm::FORM_NAME));
        $versionFormData = $versionForm->getData();
        $idStateMachineProcess = $this->castId($versionFormData[VersionForm::FIELD_ID_STATE_MACHINE_PROCESS]);

        $stateMachineProcessDefinitionTransfer = $this->getFactory()->createProcessFormDataMapper()->mapStateMachineDefinitionFormDataToStateMachineProcessDefinitionTransfer(
            $versionFormData[VersionForm::FIELD_STATE_MACHINE_DEFINITION],
            (new StateMachineProcessDefinitionTransfer())
                ->setStateMachineProcess((new StateMachineProcessTransfer())->setIdStateMachineProcess($idStateMachineProcess)),
        );

        $stateMachineDefinitionValidationResponseTransfer = $this->getFacade()->validateStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);

        return $this->jsonResponse([
            'isValid' => $stateMachineDefinitionValidationResponseTransfer->getIsValid(),
            'errors' => $this->buildDefinitionValidationErrors($stateMachineDefinitionValidationResponseTransfer),
        ]);
    }
}

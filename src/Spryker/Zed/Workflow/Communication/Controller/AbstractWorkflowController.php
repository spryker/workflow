<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Controller;

use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Spryker\Zed\Kernel\Communication\Controller\AbstractController;

/**
 * @method \Spryker\Zed\Workflow\Business\WorkflowFacadeInterface getFacade()
 * @method \Spryker\Zed\Workflow\Communication\WorkflowCommunicationFactory getFactory()
 * @method \Spryker\Zed\Workflow\WorkflowConfig getConfig()
 */
abstract class AbstractWorkflowController extends AbstractController
{
    protected const string PARAM_ID_PROCESS = 'id-process';

    protected const string PARAM_ID_DEFINITION = 'id-definition';

    protected const string ERROR_MESSAGE_PROCESS_DOES_NOT_EXIST = 'Workflow with ID %id% does not exist.';

    protected const string ERROR_MESSAGE_VERSION_DOES_NOT_EXIST = 'Workflow version with ID %id% does not exist.';

    protected const string ERROR_MESSAGE_PARAMETER_ID = '%id%';

    protected const string MESSAGE_CSRF_TOKEN_INVALID = 'CSRF token is not valid.';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\ProcessController::indexAction()
     */
    protected const string URL_PROCESS_LIST = '/workflow/process';

    protected function findStateMachineProcessOrFlashError(int $idStateMachineProcess): ?StateMachineProcessTransfer
    {
        $stateMachineProcessTransfer = $this->getFactory()
            ->createStateMachineProcessReader()
            ->findStateMachineProcess($idStateMachineProcess);

        if ($stateMachineProcessTransfer === null) {
            $this->addErrorMessage(static::ERROR_MESSAGE_PROCESS_DOES_NOT_EXIST, [
                static::ERROR_MESSAGE_PARAMETER_ID => $idStateMachineProcess,
            ]);
        }

        return $stateMachineProcessTransfer;
    }

    protected function findStateMachineProcessVersionOrFlashError(
        int $idStateMachineProcessDefinition
    ): ?StateMachineProcessDefinitionTransfer {
        $stateMachineProcessDefinitionTransfer = $this->getFactory()
            ->createStateMachineProcessVersionReader()
            ->findStateMachineProcessDefinition($idStateMachineProcessDefinition);

        if ($stateMachineProcessDefinitionTransfer === null) {
            $this->addErrorMessage(static::ERROR_MESSAGE_VERSION_DOES_NOT_EXIST, [
                static::ERROR_MESSAGE_PARAMETER_ID => $idStateMachineProcessDefinition,
            ]);
        }

        return $stateMachineProcessDefinitionTransfer;
    }
}

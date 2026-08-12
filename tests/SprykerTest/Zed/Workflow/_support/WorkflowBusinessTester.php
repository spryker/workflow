<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow;

use Codeception\Actor;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Spryker\Zed\Workflow\WorkflowConfig;

/**
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method \Codeception\Lib\Friend haveFriend($name, $actorClass = null)
 * @method \Spryker\Zed\Workflow\Business\WorkflowFacadeInterface getFacade()
 * @method \Spryker\Zed\Workflow\Business\WorkflowBusinessFactory getFactory()
 *
 * @SuppressWarnings(PHPMD)
 */
class WorkflowBusinessTester extends Actor
{
    use _generated\WorkflowBusinessTesterActions;

    /**
     * Promotes a definition version to `active` through the facade (exclusive activation). Reused across
     * definition-activation and instance-start tests.
     */
    public function activateStateMachineProcessDefinition(StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer): void
    {
        $this->getFacade()->updateStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCollectionRequestTransfer())
                ->addStateMachineProcessDefinition(
                    (new StateMachineProcessDefinitionTransfer())
                        ->setIdStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer->getIdStateMachineProcessDefinitionOrFail())
                        ->setStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE),
                ),
        );
    }
}

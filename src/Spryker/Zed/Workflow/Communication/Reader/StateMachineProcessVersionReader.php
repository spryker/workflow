<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Reader;

use Generated\Shared\Transfer\StateMachineProcessDefinitionConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Generated\Shared\Transfer\UserConditionsTransfer;
use Generated\Shared\Transfer\UserCriteriaTransfer;
use Generated\Shared\Transfer\UserTransfer;
use Spryker\Zed\User\Business\UserFacadeInterface;
use Spryker\Zed\Workflow\Business\WorkflowFacadeInterface;
use Spryker\Zed\Workflow\WorkflowConfig;

class StateMachineProcessVersionReader implements StateMachineProcessVersionReaderInterface
{
    public function __construct(
        protected WorkflowFacadeInterface $workflowFacade,
        protected UserFacadeInterface $userFacade
    ) {
    }

    public function findStateMachineProcessDefinition(int $idStateMachineProcessDefinition): ?StateMachineProcessDefinitionTransfer
    {
        return $this->workflowFacade->getStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCriteriaTransfer())->setStateMachineProcessDefinitionConditions(
                (new StateMachineProcessDefinitionConditionsTransfer())->addIdStateMachineProcessDefinition($idStateMachineProcessDefinition),
            ),
        )->getStateMachineProcessDefinitions()->getIterator()->current() ?: null;
    }

    public function findStateMachineProcessDefinitionOfProcess(
        int $idStateMachineProcessDefinition,
        int $idStateMachineProcess
    ): ?StateMachineProcessDefinitionTransfer {
        $stateMachineProcessDefinitionCriteriaTransfer = (new StateMachineProcessDefinitionCriteriaTransfer())
            ->setStateMachineProcessDefinitionConditions(
                (new StateMachineProcessDefinitionConditionsTransfer())
                    ->addIdStateMachineProcessDefinition($idStateMachineProcessDefinition)
                    ->addIdStateMachineProcess($idStateMachineProcess),
            );

        return $this->workflowFacade->getStateMachineProcessDefinitionCollection($stateMachineProcessDefinitionCriteriaTransfer)
            ->getStateMachineProcessDefinitions()->getIterator()->current() ?: null;
    }

    public function findActiveStateMachineProcessDefinition(int $idStateMachineProcess): ?StateMachineProcessDefinitionTransfer
    {
        $stateMachineProcessDefinitionCriteriaTransfer = (new StateMachineProcessDefinitionCriteriaTransfer())
            ->setStateMachineProcessDefinitionConditions(
                (new StateMachineProcessDefinitionConditionsTransfer())
                    ->addIdStateMachineProcess($idStateMachineProcess)
                    ->addStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE),
            );

        $stateMachineProcessDefinitionTransfer = $this->workflowFacade->getStateMachineProcessDefinitionCollection($stateMachineProcessDefinitionCriteriaTransfer)
            ->getStateMachineProcessDefinitions()->getIterator()->current() ?: null;

        if ($stateMachineProcessDefinitionTransfer === null) {
            return null;
        }

        return $this->expandWithAuthor($stateMachineProcessDefinitionTransfer);
    }

    protected function expandWithAuthor(
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
    ): StateMachineProcessDefinitionTransfer {
        $idUser = $stateMachineProcessDefinitionTransfer->getUser()?->getIdUser();

        if ($idUser === null) {
            return $stateMachineProcessDefinitionTransfer;
        }

        return $stateMachineProcessDefinitionTransfer->setUser($this->findUser($idUser));
    }

    protected function findUser(int $idUser): ?UserTransfer
    {
        return $this->userFacade->getUserCollection(
            (new UserCriteriaTransfer())->setUserConditions((new UserConditionsTransfer())->addIdUser($idUser)),
        )->getUsers()->getIterator()->current() ?: null;
    }
}

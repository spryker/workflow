<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Communication\Reader;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Generated\Shared\Transfer\UserCollectionTransfer;
use Generated\Shared\Transfer\UserCriteriaTransfer;
use Generated\Shared\Transfer\UserTransfer;
use PHPUnit\Framework\MockObject\MockObject;
use Spryker\Zed\User\Business\UserFacadeInterface;
use Spryker\Zed\Workflow\Business\WorkflowFacadeInterface;
use Spryker\Zed\Workflow\Communication\Reader\StateMachineProcessVersionReader;
use Spryker\Zed\Workflow\WorkflowConfig;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group Workflow
 * @group Communication
 * @group Reader
 * @group StateMachineProcessVersionReaderTest
 * Add your own group annotations below this line
 */
class StateMachineProcessVersionReaderTest extends Unit
{
    protected const int ID_STATE_MACHINE_PROCESS = 7;

    protected const int ID_STATE_MACHINE_PROCESS_DEFINITION = 11;

    protected const int ID_USER = 3;

    protected const string AUTHOR_FIRST_NAME = 'Jane';

    protected const string AUTHOR_LAST_NAME = 'Doe';

    public function testFindStateMachineProcessDefinitionOfProcessRequestsTheVersionOnlyWithinTheGivenProcess(): void
    {
        // Arrange
        $workflowFacadeMock = $this->createMock(WorkflowFacadeInterface::class);
        $workflowFacadeMock->expects($this->once())
            ->method('getStateMachineProcessDefinitionCollection')
            ->with($this->callback(function (StateMachineProcessDefinitionCriteriaTransfer $stateMachineProcessDefinitionCriteriaTransfer): bool {
                $stateMachineProcessDefinitionConditionsTransfer = $stateMachineProcessDefinitionCriteriaTransfer->getStateMachineProcessDefinitionConditionsOrFail();

                return $stateMachineProcessDefinitionConditionsTransfer->getStateMachineProcessDefinitionIds() === [static::ID_STATE_MACHINE_PROCESS_DEFINITION]
                    && $stateMachineProcessDefinitionConditionsTransfer->getStateMachineProcessIds() === [static::ID_STATE_MACHINE_PROCESS];
            }))
            ->willReturn(new StateMachineProcessDefinitionCollectionTransfer());

        $stateMachineProcessVersionReader = new StateMachineProcessVersionReader($workflowFacadeMock, $this->createMock(UserFacadeInterface::class));

        // Act
        $stateMachineProcessDefinitionTransfer = $stateMachineProcessVersionReader->findStateMachineProcessDefinitionOfProcess(
            static::ID_STATE_MACHINE_PROCESS_DEFINITION,
            static::ID_STATE_MACHINE_PROCESS,
        );

        // Assert
        $this->assertNull($stateMachineProcessDefinitionTransfer);
    }

    public function testFindActiveStateMachineProcessDefinitionRequestsTheActiveVersionOfTheGivenProcess(): void
    {
        // Arrange
        $workflowFacadeMock = $this->createMock(WorkflowFacadeInterface::class);
        $workflowFacadeMock->expects($this->once())
            ->method('getStateMachineProcessDefinitionCollection')
            ->with($this->callback(function (StateMachineProcessDefinitionCriteriaTransfer $stateMachineProcessDefinitionCriteriaTransfer): bool {
                $stateMachineProcessDefinitionConditionsTransfer = $stateMachineProcessDefinitionCriteriaTransfer->getStateMachineProcessDefinitionConditionsOrFail();

                return $stateMachineProcessDefinitionConditionsTransfer->getStateMachineProcessIds() === [static::ID_STATE_MACHINE_PROCESS]
                    && $stateMachineProcessDefinitionConditionsTransfer->getStatuses() === [WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE];
            }))
            ->willReturn(new StateMachineProcessDefinitionCollectionTransfer());

        $stateMachineProcessVersionReader = new StateMachineProcessVersionReader($workflowFacadeMock, $this->createMock(UserFacadeInterface::class));

        // Act
        $stateMachineProcessDefinitionTransfer = $stateMachineProcessVersionReader->findActiveStateMachineProcessDefinition(static::ID_STATE_MACHINE_PROCESS);

        // Assert
        $this->assertNull($stateMachineProcessDefinitionTransfer);
    }

    public function testFindActiveStateMachineProcessDefinitionExpandsTheVersionWithItsAuthor(): void
    {
        // Arrange
        $userFacadeMock = $this->createMock(UserFacadeInterface::class);
        $userFacadeMock->expects($this->once())
            ->method('getUserCollection')
            ->with($this->callback(function (UserCriteriaTransfer $userCriteriaTransfer): bool {
                return $userCriteriaTransfer->getUserConditionsOrFail()->getUserIds() === [static::ID_USER];
            }))
            ->willReturn(
                (new UserCollectionTransfer())->addUser(
                    (new UserTransfer())
                        ->setIdUser(static::ID_USER)
                        ->setFirstName(static::AUTHOR_FIRST_NAME)
                        ->setLastName(static::AUTHOR_LAST_NAME),
                ),
            );

        $stateMachineProcessVersionReader = new StateMachineProcessVersionReader(
            $this->createWorkflowFacadeMockReturning((new StateMachineProcessDefinitionTransfer())->setUser((new UserTransfer())->setIdUser(static::ID_USER))),
            $userFacadeMock,
        );

        // Act
        $stateMachineProcessDefinitionTransfer = $stateMachineProcessVersionReader->findActiveStateMachineProcessDefinition(static::ID_STATE_MACHINE_PROCESS);

        // Assert
        $this->assertSame(static::AUTHOR_FIRST_NAME, $stateMachineProcessDefinitionTransfer->getUser()->getFirstName());
        $this->assertSame(static::AUTHOR_LAST_NAME, $stateMachineProcessDefinitionTransfer->getUser()->getLastName());
    }

    public function testFindActiveStateMachineProcessDefinitionDoesNotLookUpAnAuthorForAVersionWithoutOne(): void
    {
        // Arrange
        $userFacadeMock = $this->createMock(UserFacadeInterface::class);
        $userFacadeMock->expects($this->never())->method('getUserCollection');

        $stateMachineProcessVersionReader = new StateMachineProcessVersionReader(
            $this->createWorkflowFacadeMockReturning(new StateMachineProcessDefinitionTransfer()),
            $userFacadeMock,
        );

        // Act
        $stateMachineProcessDefinitionTransfer = $stateMachineProcessVersionReader->findActiveStateMachineProcessDefinition(static::ID_STATE_MACHINE_PROCESS);

        // Assert
        $this->assertNull($stateMachineProcessDefinitionTransfer->getUser());
    }

    public function testFindActiveStateMachineProcessDefinitionDropsTheAuthorWhenTheUserNoLongerExists(): void
    {
        // Arrange
        $userFacadeMock = $this->createMock(UserFacadeInterface::class);
        $userFacadeMock->method('getUserCollection')->willReturn(new UserCollectionTransfer());

        $stateMachineProcessVersionReader = new StateMachineProcessVersionReader(
            $this->createWorkflowFacadeMockReturning((new StateMachineProcessDefinitionTransfer())->setUser((new UserTransfer())->setIdUser(static::ID_USER))),
            $userFacadeMock,
        );

        // Act
        $stateMachineProcessDefinitionTransfer = $stateMachineProcessVersionReader->findActiveStateMachineProcessDefinition(static::ID_STATE_MACHINE_PROCESS);

        // Assert
        $this->assertNull($stateMachineProcessDefinitionTransfer->getUser());
    }

    protected function createWorkflowFacadeMockReturning(
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
    ): WorkflowFacadeInterface&MockObject {
        $workflowFacadeMock = $this->createMock(WorkflowFacadeInterface::class);
        $workflowFacadeMock->method('getStateMachineProcessDefinitionCollection')
            ->willReturn((new StateMachineProcessDefinitionCollectionTransfer())->addStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer));

        return $workflowFacadeMock;
    }
}

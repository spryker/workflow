<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Communication\Mapper;

use ArrayObject;
use Codeception\Test\Unit;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerTransfer;
use Generated\Shared\Transfer\StateMachineProcessCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Generated\Shared\Transfer\UserTransfer;
use Spryker\Zed\User\Business\UserFacadeInterface;
use Spryker\Zed\Workflow\Communication\Form\ProcessEditForm;
use Spryker\Zed\Workflow\Communication\Form\ProcessForm;
use Spryker\Zed\Workflow\Communication\Form\StateMachineDefinitionForm;
use Spryker\Zed\Workflow\Communication\Mapper\ProcessFormDataMapper;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group Workflow
 * @group Communication
 * @group Mapper
 * @group ProcessFormDataMapperTest
 * Add your own group annotations below this line
 */
class ProcessFormDataMapperTest extends Unit
{
    protected const int ID_USER = 7;

    protected const string INITIAL_STATE = 'new';

    protected const string DEFINITION = '<statemachine/>';

    protected const int ID_STATE_MACHINE_PROCESS = 3;

    protected const int ID_STATE_MACHINE_PROCESS_DEFINITION_TRIGGER = 5;

    protected const string DESCRIPTION = 'Approves merchants.';

    protected const string EVENT_NAME_KEPT = 'Entity.spy_merchant.create';

    protected const string EVENT_NAME_ADDED = 'Entity.spy_merchant.update';

    protected const string EVENT_NAME_REMOVED = 'Entity.spy_merchant.delete';

    public function testMapStateMachineDefinitionFormDataToStateMachineProcessDefinitionTransferMapsTheDefinitionAndTheCurrentUser(): void
    {
        // Arrange
        $processFormDataMapper = new ProcessFormDataMapper($this->createUserFacadeMockWithCurrentUser());

        // Act
        $stateMachineProcessDefinitionTransfer = $processFormDataMapper->mapStateMachineDefinitionFormDataToStateMachineProcessDefinitionTransfer(
            $this->createStateMachineDefinitionFormData(),
            new StateMachineProcessDefinitionTransfer(),
        );

        // Assert
        $this->assertSame(static::INITIAL_STATE, $stateMachineProcessDefinitionTransfer->getInitialState());
        $this->assertSame(static::DEFINITION, $stateMachineProcessDefinitionTransfer->getDefinition());
        $this->assertSame(static::ID_USER, $stateMachineProcessDefinitionTransfer->getUserOrFail()->getIdUser());
    }

    public function testMapStateMachineDefinitionFormDataToStateMachineProcessDefinitionTransferLeavesTheUserEmptyWithoutCurrentUser(): void
    {
        // Arrange
        $userFacadeMock = $this->createMock(UserFacadeInterface::class);
        $userFacadeMock->method('hasCurrentUser')->willReturn(false);
        $userFacadeMock->expects($this->never())->method('getCurrentUser');

        // Act
        $stateMachineProcessDefinitionTransfer = (new ProcessFormDataMapper($userFacadeMock))->mapStateMachineDefinitionFormDataToStateMachineProcessDefinitionTransfer(
            $this->createStateMachineDefinitionFormData(),
            new StateMachineProcessDefinitionTransfer(),
        );

        // Assert
        $this->assertNull($stateMachineProcessDefinitionTransfer->getUser());
    }

    public function testMapProcessFormDataToStateMachineProcessTransferMapsTheStateMachineDefinitionOfTheFirstVersion(): void
    {
        // Arrange
        $processFormDataMapper = new ProcessFormDataMapper($this->createUserFacadeMockWithCurrentUser());

        // Act
        $stateMachineProcessTransfer = $processFormDataMapper->mapProcessFormDataToStateMachineProcessTransfer(
            [
                ProcessForm::FIELD_NAME => 'my-workflow',
                ProcessForm::FIELD_SUBJECT_TYPE => 'spy_customer',
                ProcessForm::FIELD_TRIGGERS => ['Entity.spy_customer.create'],
                ProcessForm::FIELD_STATE_MACHINE_DEFINITION => $this->createStateMachineDefinitionFormData(),
            ],
            new StateMachineProcessTransfer(),
            new StateMachineProcessDefinitionTransfer(),
        );

        // Assert
        $stateMachineProcessDefinitionTransfer = $stateMachineProcessTransfer->getStateMachineProcessDefinitions()->offsetGet(0);
        $this->assertSame(static::INITIAL_STATE, $stateMachineProcessDefinitionTransfer->getInitialState());
        $this->assertSame(static::DEFINITION, $stateMachineProcessDefinitionTransfer->getDefinition());
        $this->assertSame(static::ID_USER, $stateMachineProcessDefinitionTransfer->getUserOrFail()->getIdUser());
    }

    public function testMapProcessEditFormDataToStateMachineProcessCollectionRequestTransferRequestsOnlyTheChangedTriggers(): void
    {
        // Arrange
        $processFormDataMapper = new ProcessFormDataMapper($this->createMock(UserFacadeInterface::class));
        $removedStateMachineDefinitionTriggerTransfer = (new StateMachineDefinitionTriggerTransfer())
            ->setIdStateMachineProcessDefinitionTrigger(static::ID_STATE_MACHINE_PROCESS_DEFINITION_TRIGGER)
            ->setEventName(static::EVENT_NAME_REMOVED);

        // Act
        $stateMachineProcessCollectionRequestTransfer = $processFormDataMapper->mapProcessEditFormDataToStateMachineProcessCollectionRequestTransfer(
            [
                ProcessEditForm::FIELD_DESCRIPTION => static::DESCRIPTION,
                ProcessEditForm::FIELD_TRIGGERS => [static::EVENT_NAME_KEPT, static::EVENT_NAME_ADDED],
            ],
            [
                static::EVENT_NAME_KEPT => (new StateMachineDefinitionTriggerTransfer())->setEventName(static::EVENT_NAME_KEPT),
                static::EVENT_NAME_REMOVED => $removedStateMachineDefinitionTriggerTransfer,
            ],
            (new StateMachineProcessTransfer())->setIdStateMachineProcess(static::ID_STATE_MACHINE_PROCESS),
            new StateMachineProcessCollectionRequestTransfer(),
        );

        // Assert
        $this->assertSame(static::DESCRIPTION, $stateMachineProcessCollectionRequestTransfer->getStateMachineProcesses()->offsetGet(0)->getDescription());
        $this->assertSame([static::EVENT_NAME_ADDED], $this->getEventNames($stateMachineProcessCollectionRequestTransfer->getStateMachineDefinitionTriggersToAdd()));
        $this->assertSame([static::EVENT_NAME_REMOVED], $this->getEventNames($stateMachineProcessCollectionRequestTransfer->getStateMachineDefinitionTriggersToRemove()));
        $this->assertSame(
            static::ID_STATE_MACHINE_PROCESS,
            $stateMachineProcessCollectionRequestTransfer->getStateMachineDefinitionTriggersToAdd()->offsetGet(0)->getStateMachineProcessOrFail()->getIdStateMachineProcess(),
        );
        $this->assertSame(
            static::ID_STATE_MACHINE_PROCESS_DEFINITION_TRIGGER,
            $stateMachineProcessCollectionRequestTransfer->getStateMachineDefinitionTriggersToRemove()->offsetGet(0)->getIdStateMachineProcessDefinitionTrigger(),
        );
    }

    /**
     * @param \ArrayObject<int, \Generated\Shared\Transfer\StateMachineDefinitionTriggerTransfer> $stateMachineDefinitionTriggerTransfers
     *
     * @return list<string>
     */
    protected function getEventNames(ArrayObject $stateMachineDefinitionTriggerTransfers): array
    {
        $eventNames = [];
        foreach ($stateMachineDefinitionTriggerTransfers as $stateMachineDefinitionTriggerTransfer) {
            $eventNames[] = $stateMachineDefinitionTriggerTransfer->getEventNameOrFail();
        }

        return $eventNames;
    }

    protected function createUserFacadeMockWithCurrentUser(): UserFacadeInterface
    {
        $userFacadeMock = $this->createMock(UserFacadeInterface::class);
        $userFacadeMock->method('hasCurrentUser')->willReturn(true);
        $userFacadeMock->method('getCurrentUser')->willReturn((new UserTransfer())->setIdUser(static::ID_USER));

        return $userFacadeMock;
    }

    /**
     * @return array<string, string>
     */
    protected function createStateMachineDefinitionFormData(): array
    {
        return [
            StateMachineDefinitionForm::FIELD_INITIAL_STATE => static::INITIAL_STATE,
            StateMachineDefinitionForm::FIELD_DEFINITION => static::DEFINITION,
        ];
    }
}

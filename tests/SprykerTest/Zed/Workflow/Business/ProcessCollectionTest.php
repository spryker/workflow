<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Business;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerConditionsTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerTransfer;
use Generated\Shared\Transfer\StateMachineProcessCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Spryker\Zed\Workflow\WorkflowConfig;
use SprykerTest\Zed\Workflow\Helper\WorkflowHelper;
use SprykerTest\Zed\Workflow\WorkflowBusinessTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group Workflow
 * @group Business
 * @group ProcessCollectionTest
 * Add your own group annotations below this line
 */
class ProcessCollectionTest extends Unit
{
    /**
     * @var string
     */
    protected const DUPLICATE_NAME_ERROR_MESSAGE = 'A state machine with this name already exists.';

    /**
     * @var string
     */
    protected const PROCESS_NOT_FOUND_ERROR_MESSAGE = 'The workflow was not found.';

    /**
     * @var int
     */
    protected const ID_STATE_MACHINE_PROCESS_NON_EXISTENT = 99999999;

    /**
     * @var string
     */
    protected const INTERNAL_STATE_MACHINE_NAME = 'SprykerTestInternalStateMachine';

    protected const string EVENT_NAME = 'Entity.spy_customer.create';

    protected const string SECOND_EVENT_NAME = 'Entity.spy_customer.update';

    protected const string THIRD_EVENT_NAME = 'Entity.spy_customer.delete';

    protected const string DESCRIPTION = 'Onboards new customers.';

    protected const string OTHER_PROCESS_NAME = 'OtherProcess';

    protected const string UNDEFINED_INITIAL_STATE = 'undefined';

    protected WorkflowBusinessTester $tester;

    public function testCreateStateMachineProcessCollectionPersistsProcessAsInactiveDatabaseType(): void
    {
        // Arrange
        $name = $this->tester->generateUniqueName('process');
        $stateMachineProcessCollectionRequestTransfer = (new StateMachineProcessCollectionRequestTransfer())
            ->addStateMachineProcess(
                (new StateMachineProcessTransfer())
                    ->setStateMachineName($name)
                    ->setProcessName($name)
                    ->setSubjectType(WorkflowHelper::SUBJECT_TYPE),
            );

        // Act
        $stateMachineProcessCollectionResponseTransfer = $this->tester->getFacade()
            ->createStateMachineProcessCollection($stateMachineProcessCollectionRequestTransfer);

        // Assert
        $stateMachineProcessTransfer = $stateMachineProcessCollectionResponseTransfer->getStateMachineProcesses()->getIterator()->current();
        $this->assertCount(0, $stateMachineProcessCollectionResponseTransfer->getErrors());
        $this->assertNotNull($stateMachineProcessTransfer->getIdStateMachineProcess());
        $this->assertSame(
            WorkflowConfig::PROCESS_DEFINITION_STATUS_INACTIVE,
            $stateMachineProcessTransfer->getStatus(),
        );
    }

    public function testCreateStateMachineProcessCollectionPersistsProvidedTriggersAndVersion(): void
    {
        // Arrange
        $name = $this->tester->generateUniqueName('process');
        $stateMachineProcessCollectionRequestTransfer = (new StateMachineProcessCollectionRequestTransfer())
            ->addStateMachineProcess($this->createStateMachineProcessTransferWithTriggersAndVersion($name));

        // Act
        $stateMachineProcessCollectionResponseTransfer = $this->tester->getFacade()
            ->createStateMachineProcessCollection($stateMachineProcessCollectionRequestTransfer);

        // Assert
        $this->assertCount(0, $stateMachineProcessCollectionResponseTransfer->getErrors());
        $idStateMachineProcess = $stateMachineProcessCollectionResponseTransfer->getStateMachineProcesses()->getIterator()->current()->getIdStateMachineProcessOrFail();
        $this->assertEqualsCanonicalizing(
            [static::EVENT_NAME, static::SECOND_EVENT_NAME],
            $this->getPersistedTriggerEventNames($idStateMachineProcess),
        );

        $stateMachineProcessDefinitionTransfers = $this->getPersistedStateMachineProcessDefinitions($idStateMachineProcess);
        $this->assertCount(1, $stateMachineProcessDefinitionTransfers);
        $this->assertSame(1, $stateMachineProcessDefinitionTransfers[0]->getVersion());
        $this->assertSame(WorkflowHelper::INITIAL_STATE, $stateMachineProcessDefinitionTransfers[0]->getInitialState());
        $this->assertSame(WorkflowConfig::PROCESS_DEFINITION_STATUS_INACTIVE, $stateMachineProcessDefinitionTransfers[0]->getStatus());
    }

    public function testCreateStateMachineProcessCollectionPersistsTheAuthorOfTheProvidedVersion(): void
    {
        // Arrange
        $userTransfer = $this->tester->haveUser();
        $stateMachineProcessTransfer = $this->createStateMachineProcessTransferWithTriggersAndVersion($this->tester->generateUniqueName('process'));
        $stateMachineProcessTransfer->getStateMachineProcessDefinitions()->getIterator()->current()->setUser($userTransfer);

        // Act
        $stateMachineProcessCollectionResponseTransfer = $this->tester->getFacade()->createStateMachineProcessCollection(
            (new StateMachineProcessCollectionRequestTransfer())->addStateMachineProcess($stateMachineProcessTransfer),
        );

        // Assert
        $idStateMachineProcess = $stateMachineProcessCollectionResponseTransfer->getStateMachineProcesses()->getIterator()->current()->getIdStateMachineProcessOrFail();
        $stateMachineProcessDefinitionTransfers = $this->getPersistedStateMachineProcessDefinitions($idStateMachineProcess);
        $this->assertSame($userTransfer->getIdUser(), $stateMachineProcessDefinitionTransfers[0]->getUser()->getIdUser());
    }

    public function testCreateStateMachineProcessCollectionPersistsNoTriggersOrVersionForARejectedProcess(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $stateMachineProcessCollectionRequestTransfer = (new StateMachineProcessCollectionRequestTransfer())
            ->addStateMachineProcess(
                $this->createStateMachineProcessTransferWithTriggersAndVersion($stateMachineProcessTransfer->getProcessNameOrFail()),
            );

        // Act
        $stateMachineProcessCollectionResponseTransfer = $this->tester->getFacade()
            ->createStateMachineProcessCollection($stateMachineProcessCollectionRequestTransfer);

        // Assert
        $idStateMachineProcess = $stateMachineProcessTransfer->getIdStateMachineProcessOrFail();
        $this->assertCount(1, $stateMachineProcessCollectionResponseTransfer->getErrors());
        $this->assertSame([], $this->getPersistedTriggerEventNames($idStateMachineProcess));
        $this->assertSame([], $this->getPersistedStateMachineProcessDefinitions($idStateMachineProcess));
    }

    public function testCreateStateMachineProcessCollectionDoesNotCreateAProcessWithAnInvalidVersion(): void
    {
        // Arrange
        $name = $this->tester->generateUniqueName('process');
        $stateMachineProcessTransfer = $this->createStateMachineProcessTransferWithTriggersAndVersion($name);
        $stateMachineProcessTransfer->getStateMachineProcessDefinitions()->getIterator()->current()
            ->setDefinition(sprintf(WorkflowHelper::VALID_DEFINITION_TEMPLATE, static::OTHER_PROCESS_NAME));

        // Act
        $stateMachineProcessCollectionResponseTransfer = $this->tester->getFacade()->createStateMachineProcessCollection(
            (new StateMachineProcessCollectionRequestTransfer())->addStateMachineProcess($stateMachineProcessTransfer),
        );

        // Assert
        $this->assertCount(0, $stateMachineProcessCollectionResponseTransfer->getStateMachineProcesses());
        $this->assertGreaterThan(0, $stateMachineProcessCollectionResponseTransfer->getErrors()->count());
        $this->assertSame($name, $stateMachineProcessCollectionResponseTransfer->getErrors()->getIterator()->current()->getEntityIdentifier());
        $this->assertCount(0, $this->tester->getFacade()->getStateMachineProcessCollection(
            (new StateMachineProcessCriteriaTransfer())
                ->setStateMachineProcessConditions((new StateMachineProcessConditionsTransfer())->addStateMachineName($name)),
        )->getStateMachineProcesses());
    }

    public function testCreateStateMachineProcessCollectionPersistsVersionBreakingOnlyActivationRules(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->createStateMachineProcessTransferWithTriggersAndVersion($this->tester->generateUniqueName('process'));
        $stateMachineProcessTransfer->getStateMachineProcessDefinitions()->getIterator()->current()
            ->setInitialState(static::UNDEFINED_INITIAL_STATE);

        // Act
        $stateMachineProcessCollectionResponseTransfer = $this->tester->getFacade()->createStateMachineProcessCollection(
            (new StateMachineProcessCollectionRequestTransfer())->addStateMachineProcess($stateMachineProcessTransfer),
        );

        // Assert
        $this->assertCount(0, $stateMachineProcessCollectionResponseTransfer->getErrors());
        $stateMachineProcessDefinitionTransfers = $this->getPersistedStateMachineProcessDefinitions(
            $stateMachineProcessCollectionResponseTransfer->getStateMachineProcesses()->offsetGet(0)->getIdStateMachineProcessOrFail(),
        );
        $this->assertCount(1, $stateMachineProcessDefinitionTransfers);
        $this->assertSame(static::UNDEFINED_INITIAL_STATE, $stateMachineProcessDefinitionTransfers[0]->getInitialState());
    }

    public function testCreateStateMachineProcessCollectionRejectsDuplicateNameWithErrorMessage(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $stateMachineProcessCollectionRequestTransfer = (new StateMachineProcessCollectionRequestTransfer())
            ->addStateMachineProcess(
                (new StateMachineProcessTransfer())
                    ->setStateMachineName($stateMachineProcessTransfer->getStateMachineNameOrFail())
                    ->setProcessName($stateMachineProcessTransfer->getProcessNameOrFail())
                    ->setSubjectType(WorkflowHelper::SUBJECT_TYPE),
            );

        // Act
        $stateMachineProcessCollectionResponseTransfer = $this->tester->getFacade()
            ->createStateMachineProcessCollection($stateMachineProcessCollectionRequestTransfer);

        // Assert
        $this->assertCount(0, $stateMachineProcessCollectionResponseTransfer->getStateMachineProcesses());
        $this->assertSame(1, $stateMachineProcessCollectionResponseTransfer->getErrors()->count());
        $this->assertSame(
            static::DUPLICATE_NAME_ERROR_MESSAGE,
            $stateMachineProcessCollectionResponseTransfer->getErrors()->getIterator()->current()->getMessage(),
        );
    }

    public function testUpdateStateMachineProcessCollectionChangesStatus(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $activeStatus = WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE;

        // Act
        $this->tester->getFacade()->updateStateMachineProcessCollection(
            (new StateMachineProcessCollectionRequestTransfer())
                ->addStateMachineProcess(
                    (new StateMachineProcessTransfer())
                        ->setIdStateMachineProcess($stateMachineProcessTransfer->getIdStateMachineProcessOrFail())
                        ->setStatus($activeStatus),
                ),
        );

        // Assert
        $this->assertSame($activeStatus, $this->findProcessStatusById($stateMachineProcessTransfer->getIdStateMachineProcessOrFail()));
    }

    public function testUpdateStateMachineProcessCollectionAddsAndRemovesRequestedTriggers(): void
    {
        // Arrange
        $idStateMachineProcess = $this->haveStateMachineProcessWithTriggers();

        // Act
        $stateMachineProcessCollectionResponseTransfer = $this->tester->getFacade()->updateStateMachineProcessCollection(
            (new StateMachineProcessCollectionRequestTransfer())
                ->addStateMachineProcess((new StateMachineProcessTransfer())->setIdStateMachineProcess($idStateMachineProcess))
                ->addStateMachineDefinitionTriggerToAdd($this->createStateMachineDefinitionTriggerTransfer(static::THIRD_EVENT_NAME, $idStateMachineProcess))
                ->addStateMachineDefinitionTriggerToRemove($this->getPersistedStateMachineDefinitionTriggersIndexedByEventName($idStateMachineProcess)[static::EVENT_NAME]),
        );

        // Assert
        $this->assertCount(0, $stateMachineProcessCollectionResponseTransfer->getErrors());
        $this->assertEqualsCanonicalizing(
            [static::SECOND_EVENT_NAME, static::THIRD_EVENT_NAME],
            $this->getPersistedTriggerEventNames($idStateMachineProcess),
        );
    }

    public function testUpdateStateMachineProcessCollectionKeepsTriggersWhenNoTriggerChangesAreRequested(): void
    {
        // Arrange
        $idStateMachineProcess = $this->haveStateMachineProcessWithTriggers();

        // Act
        $this->tester->getFacade()->updateStateMachineProcessCollection(
            (new StateMachineProcessCollectionRequestTransfer())
                ->addStateMachineProcess(
                    (new StateMachineProcessTransfer())
                        ->setIdStateMachineProcess($idStateMachineProcess)
                        ->setStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE),
                ),
        );

        // Assert
        $this->assertEqualsCanonicalizing(
            [static::EVENT_NAME, static::SECOND_EVENT_NAME],
            $this->getPersistedTriggerEventNames($idStateMachineProcess),
        );
    }

    public function testUpdateStateMachineProcessCollectionChangesDescription(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $idStateMachineProcess = $stateMachineProcessTransfer->getIdStateMachineProcessOrFail();

        // Act
        $this->tester->getFacade()->updateStateMachineProcessCollection(
            (new StateMachineProcessCollectionRequestTransfer())
                ->addStateMachineProcess(
                    (new StateMachineProcessTransfer())
                        ->setIdStateMachineProcess($idStateMachineProcess)
                        ->setDescription(static::DESCRIPTION),
                ),
        );

        // Assert
        $this->assertSame(static::DESCRIPTION, $this->findProcessById($idStateMachineProcess)?->getDescription());
    }

    public function testUpdateStateMachineProcessCollectionClearsDescriptionWhenEmptyStringIsProvided(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $idStateMachineProcess = $stateMachineProcessTransfer->getIdStateMachineProcessOrFail();
        $this->updateProcessDescription($idStateMachineProcess, static::DESCRIPTION);

        // Act
        $this->updateProcessDescription($idStateMachineProcess, '');

        // Assert
        $this->assertSame('', (string)$this->findProcessById($idStateMachineProcess)?->getDescription());
    }

    public function testUpdateStateMachineProcessCollectionKeepsTriggersWhenProcessDoesNotExist(): void
    {
        // Arrange
        $idStateMachineProcess = $this->haveStateMachineProcessWithTriggers();

        // Act
        $this->tester->getFacade()->updateStateMachineProcessCollection(
            (new StateMachineProcessCollectionRequestTransfer())
                ->addStateMachineProcess((new StateMachineProcessTransfer())->setIdStateMachineProcess(static::ID_STATE_MACHINE_PROCESS_NON_EXISTENT))
                ->addStateMachineDefinitionTriggerToRemove($this->getPersistedStateMachineDefinitionTriggersIndexedByEventName($idStateMachineProcess)[static::EVENT_NAME]),
        );

        // Assert
        $this->assertEqualsCanonicalizing(
            [static::EVENT_NAME, static::SECOND_EVENT_NAME],
            $this->getPersistedTriggerEventNames($idStateMachineProcess),
        );
    }

    public function testUpdateStateMachineProcessCollectionReturnsErrorWhenProcessDoesNotExist(): void
    {
        // Act
        $stateMachineProcessCollectionResponseTransfer = $this->tester->getFacade()->updateStateMachineProcessCollection(
            (new StateMachineProcessCollectionRequestTransfer())
                ->addStateMachineProcess(
                    (new StateMachineProcessTransfer())
                        ->setIdStateMachineProcess(static::ID_STATE_MACHINE_PROCESS_NON_EXISTENT)
                        ->setStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE),
                ),
        );

        // Assert
        $this->assertCount(0, $stateMachineProcessCollectionResponseTransfer->getStateMachineProcesses());
        $this->assertCount(1, $stateMachineProcessCollectionResponseTransfer->getErrors());
        $this->assertSame(
            static::PROCESS_NOT_FOUND_ERROR_MESSAGE,
            $stateMachineProcessCollectionResponseTransfer->getErrors()->getIterator()->current()->getMessage(),
        );
    }

    public function testGetStateMachineProcessCollectionFiltersByStateMachineName(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();

        // Act
        $stateMachineProcessCollectionTransfer = $this->tester->getFacade()->getStateMachineProcessCollection(
            (new StateMachineProcessCriteriaTransfer())
                ->setStateMachineProcessConditions(
                    (new StateMachineProcessConditionsTransfer())
                        ->addStateMachineName($stateMachineProcessTransfer->getStateMachineNameOrFail()),
                ),
        );

        // Assert
        $this->assertCount(1, $stateMachineProcessCollectionTransfer->getStateMachineProcesses());
        $this->assertSame(
            $stateMachineProcessTransfer->getIdStateMachineProcess(),
            $stateMachineProcessCollectionTransfer->getStateMachineProcesses()->getIterator()->current()->getIdStateMachineProcess(),
        );
    }

    protected function createStateMachineProcessTransferWithTriggersAndVersion(string $name): StateMachineProcessTransfer
    {
        return (new StateMachineProcessTransfer())
            ->setStateMachineName($name)
            ->setProcessName($name)
            ->setSubjectType(WorkflowHelper::SUBJECT_TYPE)
            ->addStateMachineDefinitionTrigger((new StateMachineDefinitionTriggerTransfer())->setEventName(static::EVENT_NAME))
            ->addStateMachineDefinitionTrigger((new StateMachineDefinitionTriggerTransfer())->setEventName(static::SECOND_EVENT_NAME))
            ->addStateMachineProcessDefinition(
                (new StateMachineProcessDefinitionTransfer())
                    ->setInitialState(WorkflowHelper::INITIAL_STATE)
                    ->setDefinition(sprintf(WorkflowHelper::VALID_DEFINITION_TEMPLATE, $name)),
            );
    }

    protected function createStateMachineDefinitionTriggerTransfer(string $eventName, int $idStateMachineProcess): StateMachineDefinitionTriggerTransfer
    {
        return (new StateMachineDefinitionTriggerTransfer())
            ->setEventName($eventName)
            ->setStateMachineProcess((new StateMachineProcessTransfer())->setIdStateMachineProcess($idStateMachineProcess));
    }

    /**
     * @return list<string>
     */
    protected function getPersistedTriggerEventNames(int $idStateMachineProcess): array
    {
        return array_keys($this->getPersistedStateMachineDefinitionTriggersIndexedByEventName($idStateMachineProcess));
    }

    /**
     * @return array<string, \Generated\Shared\Transfer\StateMachineDefinitionTriggerTransfer>
     */
    protected function getPersistedStateMachineDefinitionTriggersIndexedByEventName(int $idStateMachineProcess): array
    {
        $stateMachineDefinitionTriggerCollectionTransfer = $this->tester->getFacade()->getStateMachineDefinitionTriggerCollection(
            (new StateMachineDefinitionTriggerCriteriaTransfer())
                ->setStateMachineDefinitionTriggerConditions(
                    (new StateMachineDefinitionTriggerConditionsTransfer())->addIdStateMachineProcess($idStateMachineProcess),
                ),
        );

        $stateMachineDefinitionTriggerTransfersIndexedByEventName = [];
        foreach ($stateMachineDefinitionTriggerCollectionTransfer->getStateMachineDefinitionTriggers() as $stateMachineDefinitionTriggerTransfer) {
            $stateMachineDefinitionTriggerTransfersIndexedByEventName[$stateMachineDefinitionTriggerTransfer->getEventNameOrFail()] = $stateMachineDefinitionTriggerTransfer;
        }

        return $stateMachineDefinitionTriggerTransfersIndexedByEventName;
    }

    /**
     * @return list<\Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer>
     */
    protected function getPersistedStateMachineProcessDefinitions(int $idStateMachineProcess): array
    {
        $stateMachineProcessDefinitionCollectionTransfer = $this->tester->getFacade()->getStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCriteriaTransfer())
                ->setStateMachineProcessDefinitionConditions(
                    (new StateMachineProcessDefinitionConditionsTransfer())->addIdStateMachineProcess($idStateMachineProcess),
                ),
        );

        return array_values($stateMachineProcessDefinitionCollectionTransfer->getStateMachineProcessDefinitions()->getArrayCopy());
    }

    protected function findProcessStatusById(int $idStateMachineProcess): ?string
    {
        return $this->findProcessById($idStateMachineProcess)?->getStatus();
    }

    protected function findProcessById(int $idStateMachineProcess): ?StateMachineProcessTransfer
    {
        return $this->tester->getFacade()->getStateMachineProcessCollection(
            (new StateMachineProcessCriteriaTransfer())
                ->setStateMachineProcessConditions(
                    (new StateMachineProcessConditionsTransfer())
                        ->addIdStateMachineProcess($idStateMachineProcess),
                ),
        )->getStateMachineProcesses()->getIterator()->current();
    }

    protected function haveStateMachineProcessWithTriggers(): int
    {
        $stateMachineProcessCollectionResponseTransfer = $this->tester->getFacade()->createStateMachineProcessCollection(
            (new StateMachineProcessCollectionRequestTransfer())->addStateMachineProcess(
                $this->createStateMachineProcessTransferWithTriggersAndVersion($this->tester->generateUniqueName('process')),
            ),
        );

        return $stateMachineProcessCollectionResponseTransfer->getStateMachineProcesses()->getIterator()->current()->getIdStateMachineProcessOrFail();
    }

    protected function updateProcessDescription(int $idStateMachineProcess, string $description): void
    {
        $this->tester->getFacade()->updateStateMachineProcessCollection(
            (new StateMachineProcessCollectionRequestTransfer())
                ->addStateMachineProcess(
                    (new StateMachineProcessTransfer())
                        ->setIdStateMachineProcess($idStateMachineProcess)
                        ->setDescription($description),
                ),
        );
    }
}

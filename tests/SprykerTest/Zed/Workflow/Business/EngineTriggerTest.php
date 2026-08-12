<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Business;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerTransfer;
use Generated\Shared\Transfer\StateMachineEventTriggerRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Spryker\Zed\StateMachine\Business\StateMachineFacadeInterface;
use Spryker\Zed\Workflow\WorkflowDependencyProvider;
use SprykerTest\Zed\Workflow\WorkflowBusinessTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group Workflow
 * @group Business
 * @group EngineTriggerTest
 * Add your own group annotations below this line
 */
class EngineTriggerTest extends Unit
{
    /**
     * @var int
     */
    protected const IDENTIFIER = 987654;

    /**
     * @var string
     */
    protected const EVENT_NAME = 'e2e.engine.start';

    protected WorkflowBusinessTester $tester;

    public function testStartStateMachineInstanceCreatesInstanceForMatchedTrigger(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->haveActiveProcessWithTrigger();
        $this->tester->setDependency(WorkflowDependencyProvider::FACADE_STATE_MACHINE, $this->createStateMachineFacadeMock());

        // Act
        $stateMachineEventTriggerResponseTransfer = $this->tester->getFacade()->startStateMachineInstance(
            (new StateMachineEventTriggerRequestTransfer())
                ->setEventName(static::EVENT_NAME)
                ->setIdentifier(static::IDENTIFIER),
        );

        // Assert
        $this->assertCount(0, $stateMachineEventTriggerResponseTransfer->getErrors());
        $this->assertTrue($this->hasInstance($stateMachineProcessTransfer, static::IDENTIFIER));
    }

    public function testRunningInstanceRemainsOnItsOriginalVersionAfterANewVersionIsActivated(): void
    {
        // Arrange: an active v1 with a trigger, a started instance pinned to v1, then a fresh v2 to activate.
        $stateMachineProcessTransfer = $this->haveActiveProcessWithTrigger();
        $firstStateMachineProcessDefinitionTransfer = $this->findActiveDefinitionForProcess($stateMachineProcessTransfer);
        $this->tester->setDependency(WorkflowDependencyProvider::FACADE_STATE_MACHINE, $this->createStateMachineFacadeMock());

        $this->tester->getFacade()->startStateMachineInstance(
            (new StateMachineEventTriggerRequestTransfer())
                ->setEventName(static::EVENT_NAME)
                ->setIdentifier(static::IDENTIFIER),
        );

        $secondStateMachineProcessDefinitionTransfer = $this->tester->haveStateMachineProcessDefinition($stateMachineProcessTransfer);

        // Act: activate the new version (this deactivates v1).
        $this->tester->activateStateMachineProcessDefinition($secondStateMachineProcessDefinitionTransfer);

        // Assert: the in-progress instance is still pinned to v1 and was NOT moved to v2.
        $this->assertSame(1, $this->countInstancesForDefinition($firstStateMachineProcessDefinitionTransfer, static::IDENTIFIER));
        $this->assertSame(0, $this->countInstancesForDefinition($secondStateMachineProcessDefinitionTransfer, static::IDENTIFIER));
    }

    public function testNewInstanceStartedAfterVersionActivationRunsOnTheNewlyActiveVersion(): void
    {
        // Arrange: an active v1 with a trigger, then a fresh v2 activated (which deactivates v1).
        $stateMachineProcessTransfer = $this->haveActiveProcessWithTrigger();
        $firstStateMachineProcessDefinitionTransfer = $this->findActiveDefinitionForProcess($stateMachineProcessTransfer);
        $secondStateMachineProcessDefinitionTransfer = $this->tester->haveStateMachineProcessDefinition($stateMachineProcessTransfer);
        $this->tester->activateStateMachineProcessDefinition($secondStateMachineProcessDefinitionTransfer);
        $this->tester->setDependency(WorkflowDependencyProvider::FACADE_STATE_MACHINE, $this->createStateMachineFacadeMock());

        // Act: start an instance for a new identifier after the version switch.
        $this->tester->getFacade()->startStateMachineInstance(
            (new StateMachineEventTriggerRequestTransfer())
                ->setEventName(static::EVENT_NAME)
                ->setIdentifier(static::IDENTIFIER),
        );

        // Assert: the new instance runs on v2, not on the now-inactive v1.
        $this->assertSame(1, $this->countInstancesForDefinition($secondStateMachineProcessDefinitionTransfer, static::IDENTIFIER));
        $this->assertSame(0, $this->countInstancesForDefinition($firstStateMachineProcessDefinitionTransfer, static::IDENTIFIER));
    }

    public function testStartStateMachineInstanceDoesNotStartSecondInstanceForSameIdentifier(): void
    {
        // Arrange
        $this->haveActiveProcessWithTrigger();
        $this->tester->setDependency(WorkflowDependencyProvider::FACADE_STATE_MACHINE, $this->createStateMachineFacadeMock());
        $stateMachineEventTriggerRequestTransfer = (new StateMachineEventTriggerRequestTransfer())
            ->setEventName(static::EVENT_NAME)
            ->setIdentifier(static::IDENTIFIER);
        $this->tester->getFacade()->startStateMachineInstance($stateMachineEventTriggerRequestTransfer);

        // Act
        $this->tester->getFacade()->startStateMachineInstance($stateMachineEventTriggerRequestTransfer);

        // Assert
        $this->assertSame(1, $this->countInstances(static::IDENTIFIER));
    }

    public function testStartStateMachineInstanceReturnsErrorWhenNoActiveDefinition(): void
    {
        // Arrange: an ACTIVE process with a registered trigger but its only definition left INACTIVE.
        $stateMachineProcessTransfer = $this->tester->haveActiveStateMachineProcess();
        $this->tester->haveStateMachineProcessDefinition($stateMachineProcessTransfer);
        $this->registerTrigger($stateMachineProcessTransfer);
        $this->tester->setDependency(WorkflowDependencyProvider::FACADE_STATE_MACHINE, $this->createStateMachineFacadeMock());

        // Act
        $stateMachineEventTriggerResponseTransfer = $this->tester->getFacade()->startStateMachineInstance(
            (new StateMachineEventTriggerRequestTransfer())
                ->setEventName(static::EVENT_NAME)
                ->setIdentifier(static::IDENTIFIER),
        );

        // Assert: no instance is created and the exact "no active version" error is returned.
        $this->assertSame(0, $this->countInstances(static::IDENTIFIER));
        $this->assertCount(1, $stateMachineEventTriggerResponseTransfer->getErrors());
        $this->assertSame(
            'No active version to start for the matched process.',
            $stateMachineEventTriggerResponseTransfer->getErrors()->getIterator()->current()->getMessage(),
        );
    }

    public function testTriggerStateMachineInstanceEventReturnsErrorWhenNoRunningInstance(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->haveActiveProcessWithTrigger();
        $this->tester->setDependency(WorkflowDependencyProvider::FACADE_STATE_MACHINE, $this->createStateMachineFacadeMock(1));

        // Act: no instance was started for this identifier.
        $stateMachineEventTriggerResponseTransfer = $this->tester->getFacade()->triggerStateMachineInstanceEvent(
            (new StateMachineEventTriggerRequestTransfer())
                ->setEventName(static::EVENT_NAME)
                ->setIdentifier(static::IDENTIFIER)
                ->setStateMachineProcessDefinition($this->findActiveDefinitionForProcess($stateMachineProcessTransfer)),
        );

        // Assert
        $this->assertCount(1, $stateMachineEventTriggerResponseTransfer->getErrors());
        $this->assertSame(
            'No running instance with a current state found for this version.',
            $stateMachineEventTriggerResponseTransfer->getErrors()->getIterator()->current()->getMessage(),
        );
    }

    public function testCheckDynamicConditionsSumsEngineAffectedCountPerStateMachine(): void
    {
        // Arrange
        $this->tester->haveStateMachineProcess();
        $this->tester->setDependency(
            WorkflowDependencyProvider::FACADE_STATE_MACHINE,
            $this->createStateMachineFacadeMock(3),
        );

        // Act
        $affectedItemCount = $this->tester->getFacade()->checkDynamicConditions();

        // Assert: each active DB state machine name contributes the stubbed engine count (3).
        $this->assertGreaterThanOrEqual(3, $affectedItemCount);
    }

    public function testCheckDynamicTimeoutsSumsEngineAffectedCountPerStateMachine(): void
    {
        // Arrange
        $this->tester->haveStateMachineProcess();
        $this->tester->setDependency(
            WorkflowDependencyProvider::FACADE_STATE_MACHINE,
            $this->createStateMachineFacadeMock(2),
        );

        // Act
        $affectedItemCount = $this->tester->getFacade()->checkDynamicTimeouts();

        // Assert
        $this->assertGreaterThanOrEqual(2, $affectedItemCount);
    }

    public function testCheckDynamicConditionsRunsAllAndOnlyDatabaseStateMachines(): void
    {
        // Arrange: one ACTIVE db state machine (must be scanned) and one INACTIVE db state machine (must be skipped).
        $activeStateMachineProcessTransfer = $this->tester->haveActiveStateMachineProcess();
        $inactiveStateMachineProcessTransfer = $this->tester->haveStateMachineProcess();

        $scannedStateMachineNames = [];
        $this->tester->setDependency(
            WorkflowDependencyProvider::FACADE_STATE_MACHINE,
            $this->createStateMachineNameCapturingFacadeMock($scannedStateMachineNames),
        );

        // Act
        $this->tester->getFacade()->checkDynamicConditions();

        // Assert: ALL active DB-authored state machines are scanned (the active one is present)...
        $this->assertContains($activeStateMachineProcessTransfer->getStateMachineNameOrFail(), $scannedStateMachineNames);
        // ...and ONLY DB-authored ones are scanned: the inactive DB process is skipped and every scanned
        // name resolves to a DB-authored process (the process getter is scoped to type = database).
        $this->assertNotContains($inactiveStateMachineProcessTransfer->getStateMachineNameOrFail(), $scannedStateMachineNames);
        foreach ($scannedStateMachineNames as $scannedStateMachineName) {
            $this->assertTrue(
                $this->isDatabaseStateMachineName($scannedStateMachineName),
                sprintf('The engine was invoked for non-database state machine "%s".', $scannedStateMachineName),
            );
        }
    }

    protected function haveActiveProcessWithTrigger(): StateMachineProcessTransfer
    {
        $stateMachineProcessTransfer = $this->tester->haveActiveStateMachineProcess();
        $stateMachineProcessDefinitionTransfer = $this->tester->haveStateMachineProcessDefinition($stateMachineProcessTransfer);
        $this->tester->activateStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);
        $this->registerTrigger($stateMachineProcessTransfer);

        return $stateMachineProcessTransfer;
    }

    protected function registerTrigger(StateMachineProcessTransfer $stateMachineProcessTransfer): void
    {
        $this->tester->getFacade()->updateStateMachineDefinitionTriggerCollection(
            (new StateMachineDefinitionTriggerCollectionRequestTransfer())
                ->addStateMachineDefinitionTriggerToAdd(
                    (new StateMachineDefinitionTriggerTransfer())
                        ->setEventName(static::EVENT_NAME)
                        ->setStateMachineProcess($stateMachineProcessTransfer),
                )
                ->setIsTransactional(true),
        );
    }

    /**
     * Mocks ONLY the cross-module core StateMachine facade bridge (the single external dependency the
     * convention allows mocking); every DSM internal (facade, writers, repository, entity manager) runs real.
     *
     * @return \Spryker\Zed\StateMachine\Business\StateMachineFacadeInterface
     */
    protected function createStateMachineFacadeMock(int $affectedCount = 0): StateMachineFacadeInterface
    {
        $stateMachineFacadeMock = $this->getMockBuilder(StateMachineFacadeInterface::class)->getMock();
        $stateMachineFacadeMock->method('triggerForNewStateMachineItem')->willReturn($affectedCount);
        $stateMachineFacadeMock->method('triggerEvent')->willReturn($affectedCount);
        $stateMachineFacadeMock->method('checkConditions')->willReturn($affectedCount);
        $stateMachineFacadeMock->method('checkTimeouts')->willReturn($affectedCount);

        return $stateMachineFacadeMock;
    }

    /**
     * Mocks the core StateMachine facade and records every state machine name passed to checkConditions(),
     * so the test can assert the checker's discovery set equals the DB-authored state machines.
     *
     * @param array<string> $scannedStateMachineNames
     *
     * @return \Spryker\Zed\StateMachine\Business\StateMachineFacadeInterface
     */
    protected function createStateMachineNameCapturingFacadeMock(array &$scannedStateMachineNames): StateMachineFacadeInterface
    {
        $stateMachineFacadeMock = $this->getMockBuilder(StateMachineFacadeInterface::class)->getMock();
        $stateMachineFacadeMock->method('checkConditions')->willReturnCallback(
            function (string $stateMachineName) use (&$scannedStateMachineNames): int {
                $scannedStateMachineNames[] = $stateMachineName;

                return 0;
            },
        );

        return $stateMachineFacadeMock;
    }

    protected function isDatabaseStateMachineName(string $stateMachineName): bool
    {
        // getStateMachineProcessCollection is scoped to type = database, so a match proves the name is
        // DB-authored (file-based core state machines never appear here).
        return $this->tester->getFacade()->getStateMachineProcessCollection(
            (new StateMachineProcessCriteriaTransfer())
                ->setStateMachineProcessConditions(
                    (new StateMachineProcessConditionsTransfer())
                        ->addStateMachineName($stateMachineName),
                ),
        )->getStateMachineProcesses()->count() > 0;
    }

    protected function findActiveDefinitionForProcess(StateMachineProcessTransfer $stateMachineProcessTransfer): StateMachineProcessDefinitionTransfer
    {
        return $this->tester->getFacade()->getStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCriteriaTransfer())
                ->setStateMachineProcessDefinitionConditions(
                    (new StateMachineProcessDefinitionConditionsTransfer())
                        ->addIdStateMachineProcess($stateMachineProcessTransfer->getIdStateMachineProcessOrFail()),
                ),
        )->getStateMachineProcessDefinitions()->getIterator()->current();
    }

    protected function hasInstance(StateMachineProcessTransfer $stateMachineProcessTransfer, int $identifier): bool
    {
        $stateMachineProcessDefinitionTransfer = $this->tester->getFacade()->getStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCriteriaTransfer())
                ->setStateMachineProcessDefinitionConditions(
                    (new StateMachineProcessDefinitionConditionsTransfer())
                        ->addIdStateMachineProcess($stateMachineProcessTransfer->getIdStateMachineProcessOrFail()),
                ),
        )->getStateMachineProcessDefinitions()->getIterator()->current();

        return $this->countInstancesForDefinition($stateMachineProcessDefinitionTransfer, $identifier) > 0;
    }

    protected function countInstances(int $identifier): int
    {
        return $this->tester->getFacade()->getStateMachineProcessDefinitionInstanceCollection(
            (new StateMachineProcessDefinitionInstanceCriteriaTransfer())
                ->setStateMachineProcessDefinitionInstanceConditions(
                    (new StateMachineProcessDefinitionInstanceConditionsTransfer())
                        ->addIdentifier($identifier),
                ),
        )->getStateMachineProcessDefinitionInstances()->count();
    }

    protected function countInstancesForDefinition(StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer, int $identifier): int
    {
        return $this->tester->getFacade()->getStateMachineProcessDefinitionInstanceCollection(
            (new StateMachineProcessDefinitionInstanceCriteriaTransfer())
                ->setStateMachineProcessDefinitionInstanceConditions(
                    (new StateMachineProcessDefinitionInstanceConditionsTransfer())
                        ->addIdStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer->getIdStateMachineProcessDefinitionOrFail())
                        ->addIdentifier($identifier),
                ),
        )->getStateMachineProcessDefinitionInstances()->count();
    }
}

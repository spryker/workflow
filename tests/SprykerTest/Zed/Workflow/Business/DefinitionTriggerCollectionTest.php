<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Business;

use ArrayObject;
use Codeception\Test\Unit;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerConditionsTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use SprykerTest\Zed\Workflow\WorkflowBusinessTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group Workflow
 * @group Business
 * @group DefinitionTriggerCollectionTest
 * Add your own group annotations below this line
 */
class DefinitionTriggerCollectionTest extends Unit
{
    /**
     * @var string
     */
    protected const EVENT_NAME = 'Entity.spy_customer.create';

    /**
     * @var string
     */
    protected const SECOND_EVENT_NAME = 'Entity.spy_customer.update';

    protected WorkflowBusinessTester $tester;

    public function testUpdateStateMachineDefinitionTriggerCollectionAddsTrigger(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();

        // Act
        $stateMachineDefinitionTriggerCollectionResponseTransfer = $this->tester->getFacade()
            ->updateStateMachineDefinitionTriggerCollection($this->createAddTriggerRequest($stateMachineProcessTransfer));

        // Assert
        $this->assertCount(1, $stateMachineDefinitionTriggerCollectionResponseTransfer->getStateMachineDefinitionTriggers());
        $this->assertCount(1, $this->findTriggersByProcess($stateMachineProcessTransfer));
    }

    public function testUpdateStateMachineDefinitionTriggerCollectionRemovesTrigger(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $this->tester->getFacade()->updateStateMachineDefinitionTriggerCollection($this->createAddTriggerRequest($stateMachineProcessTransfer));
        $persistedStateMachineDefinitionTriggerTransfer = $this->findTriggersByProcess($stateMachineProcessTransfer)->getIterator()->current();

        // Act
        $this->tester->getFacade()->updateStateMachineDefinitionTriggerCollection(
            (new StateMachineDefinitionTriggerCollectionRequestTransfer())
                ->addStateMachineDefinitionTriggerToRemove($persistedStateMachineDefinitionTriggerTransfer)
                ->setIsTransactional(true),
        );

        // Assert
        $this->assertCount(0, $this->findTriggersByProcess($stateMachineProcessTransfer));
    }

    public function testGetStateMachineDefinitionTriggerCollectionFiltersByEventName(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $this->tester->getFacade()->updateStateMachineDefinitionTriggerCollection($this->createAddTriggerRequest($stateMachineProcessTransfer));

        // Act
        $stateMachineDefinitionTriggerCollectionTransfer = $this->tester->getFacade()->getStateMachineDefinitionTriggerCollection(
            (new StateMachineDefinitionTriggerCriteriaTransfer())
                ->setStateMachineDefinitionTriggerConditions(
                    (new StateMachineDefinitionTriggerConditionsTransfer())
                        ->addIdStateMachineProcess($stateMachineProcessTransfer->getIdStateMachineProcessOrFail())
                        ->addEventName(static::EVENT_NAME),
                ),
        );

        // Assert
        $this->assertCount(1, $stateMachineDefinitionTriggerCollectionTransfer->getStateMachineDefinitionTriggers());
        $this->assertSame(
            static::EVENT_NAME,
            $stateMachineDefinitionTriggerCollectionTransfer->getStateMachineDefinitionTriggers()->getIterator()->current()->getEventName(),
        );
    }

    /**
     * Documents the current behaviour: a process is allowed to have no triggers.
     */
    public function testUpdateStateMachineDefinitionTriggerCollectionAllowsEmptyTriggerSet(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();

        // Act - no triggers to add and none to remove.
        $stateMachineDefinitionTriggerCollectionResponseTransfer = $this->tester->getFacade()
            ->updateStateMachineDefinitionTriggerCollection(
                (new StateMachineDefinitionTriggerCollectionRequestTransfer())->setIsTransactional(true),
            );

        // Assert
        $this->assertCount(0, $stateMachineDefinitionTriggerCollectionResponseTransfer->getErrors());
        $this->assertCount(0, $this->findTriggersByProcess($stateMachineProcessTransfer));
    }

    public function testUpdateStateMachineDefinitionTriggerCollectionAddsMultipleTriggers(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $expectedEventNames = [static::EVENT_NAME, static::SECOND_EVENT_NAME];
        sort($expectedEventNames);

        // Act
        $stateMachineDefinitionTriggerCollectionResponseTransfer = $this->tester->getFacade()
            ->updateStateMachineDefinitionTriggerCollection($this->createAddTriggerRequest($stateMachineProcessTransfer, $expectedEventNames));

        // Assert
        $this->assertCount(0, $stateMachineDefinitionTriggerCollectionResponseTransfer->getErrors());
        $this->assertCount(2, $this->findTriggersByProcess($stateMachineProcessTransfer));
        $this->assertSame($expectedEventNames, $this->findTriggerEventNamesByProcess($stateMachineProcessTransfer));
    }

    public function testUpdateStateMachineDefinitionTriggerCollectionRemovesAllTriggers(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $this->tester->getFacade()->updateStateMachineDefinitionTriggerCollection(
            $this->createAddTriggerRequest($stateMachineProcessTransfer, [static::EVENT_NAME, static::SECOND_EVENT_NAME]),
        );

        $persistedStateMachineDefinitionTriggerCollectionRequestTransfer = new StateMachineDefinitionTriggerCollectionRequestTransfer();
        foreach ($this->findTriggersByProcess($stateMachineProcessTransfer) as $stateMachineDefinitionTriggerTransfer) {
            $persistedStateMachineDefinitionTriggerCollectionRequestTransfer->addStateMachineDefinitionTriggerToRemove($stateMachineDefinitionTriggerTransfer);
        }

        // Act
        $this->tester->getFacade()->updateStateMachineDefinitionTriggerCollection(
            $persistedStateMachineDefinitionTriggerCollectionRequestTransfer->setIsTransactional(true),
        );

        // Assert
        $this->assertCount(0, $this->findTriggersByProcess($stateMachineProcessTransfer));
    }

    /**
     * @param array<string> $eventNames
     */
    protected function createAddTriggerRequest(
        StateMachineProcessTransfer $stateMachineProcessTransfer,
        array $eventNames = [self::EVENT_NAME]
    ): StateMachineDefinitionTriggerCollectionRequestTransfer {
        $stateMachineDefinitionTriggerCollectionRequestTransfer = (new StateMachineDefinitionTriggerCollectionRequestTransfer())
            ->setIsTransactional(true);

        foreach ($eventNames as $eventName) {
            $stateMachineDefinitionTriggerCollectionRequestTransfer->addStateMachineDefinitionTriggerToAdd(
                (new StateMachineDefinitionTriggerTransfer())
                    ->setEventName($eventName)
                    ->setStateMachineProcess($stateMachineProcessTransfer),
            );
        }

        return $stateMachineDefinitionTriggerCollectionRequestTransfer;
    }

    /**
     * @return array<string>
     */
    protected function findTriggerEventNamesByProcess(StateMachineProcessTransfer $stateMachineProcessTransfer): array
    {
        $eventNames = [];
        foreach ($this->findTriggersByProcess($stateMachineProcessTransfer) as $stateMachineDefinitionTriggerTransfer) {
            $eventNames[] = $stateMachineDefinitionTriggerTransfer->getEventNameOrFail();
        }

        sort($eventNames);

        return $eventNames;
    }

    /**
     * @return \ArrayObject<array-key, \Generated\Shared\Transfer\StateMachineDefinitionTriggerTransfer>
     */
    protected function findTriggersByProcess(StateMachineProcessTransfer $stateMachineProcessTransfer): ArrayObject
    {
        return $this->tester->getFacade()->getStateMachineDefinitionTriggerCollection(
            (new StateMachineDefinitionTriggerCriteriaTransfer())
                ->setStateMachineDefinitionTriggerConditions(
                    (new StateMachineDefinitionTriggerConditionsTransfer())
                        ->addIdStateMachineProcess($stateMachineProcessTransfer->getIdStateMachineProcessOrFail()),
                ),
        )->getStateMachineDefinitionTriggers();
    }
}

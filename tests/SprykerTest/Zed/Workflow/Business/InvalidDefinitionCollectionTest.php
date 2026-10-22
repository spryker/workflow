<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Business;

use ArrayObject;
use Codeception\Test\Unit;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionResponseTransfer;
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
 * @group InvalidDefinitionCollectionTest
 * Add your own group annotations below this line
 */
class InvalidDefinitionCollectionTest extends Unit
{
    protected const string MALFORMED_DEFINITION = '<statemachine><process name="broken"></statemachine>';

    protected const string DEFINITION_WITHOUT_TRANSITIONS_AND_EVENTS_TEMPLATE = '<statemachine><process name="%s" main="true"><states><state name="new"/></states></process></statemachine>';

    protected const string INITIAL_STATE = 'new';

    protected const string UNDEFINED_INITIAL_STATE = 'undefined';

    protected const string VALID_TIMEOUT = '1 hour';

    protected const string INVALID_TIMEOUT = '14 dayz';

    protected WorkflowBusinessTester $tester;

    public function testCreateStateMachineProcessDefinitionCollectionRejectsMalformedDefinition(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();

        // Act
        $stateMachineProcessDefinitionCollectionResponseTransfer = $this->createVersion($stateMachineProcessTransfer, static::MALFORMED_DEFINITION, static::INITIAL_STATE);

        // Assert
        $this->assertCount(1, $stateMachineProcessDefinitionCollectionResponseTransfer->getErrors());
        $this->assertSame('The definition is not valid XML.', $stateMachineProcessDefinitionCollectionResponseTransfer->getErrors()->offsetGet(0)->getMessage());
        $this->assertCount(0, $stateMachineProcessDefinitionCollectionResponseTransfer->getStateMachineProcessDefinitions());
        $this->assertCount(0, $this->findDefinitionsByProcess($stateMachineProcessTransfer));
    }

    public function testCreateStateMachineProcessDefinitionCollectionRejectsDefinitionWithoutRequiredElements(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $definition = sprintf(static::DEFINITION_WITHOUT_TRANSITIONS_AND_EVENTS_TEMPLATE, $stateMachineProcessTransfer->getProcessNameOrFail());

        // Act
        $stateMachineProcessDefinitionCollectionResponseTransfer = $this->createVersion($stateMachineProcessTransfer, $definition, static::INITIAL_STATE);

        // Assert
        $this->assertCount(2, $stateMachineProcessDefinitionCollectionResponseTransfer->getErrors());
        $this->assertCount(0, $this->findDefinitionsByProcess($stateMachineProcessTransfer));
    }

    public function testCreateStateMachineProcessDefinitionCollectionRejectsMismatchedMainProcessName(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $definition = sprintf(WorkflowHelper::VALID_DEFINITION_TEMPLATE, $stateMachineProcessTransfer->getProcessNameOrFail() . '-other');

        // Act
        $stateMachineProcessDefinitionCollectionResponseTransfer = $this->createVersion($stateMachineProcessTransfer, $definition, static::INITIAL_STATE);

        // Assert
        $this->assertCount(1, $stateMachineProcessDefinitionCollectionResponseTransfer->getErrors());
        $this->assertCount(0, $this->findDefinitionsByProcess($stateMachineProcessTransfer));
    }

    public function testCreateStateMachineProcessDefinitionCollectionPersistsDefinitionBreakingOnlyActivationRulesAsInactive(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $definition = sprintf(WorkflowHelper::VALID_DEFINITION_TEMPLATE, $stateMachineProcessTransfer->getProcessNameOrFail());

        // Act
        $stateMachineProcessDefinitionCollectionResponseTransfer = $this->createVersion($stateMachineProcessTransfer, $definition, static::UNDEFINED_INITIAL_STATE);

        // Assert
        $this->assertCount(0, $stateMachineProcessDefinitionCollectionResponseTransfer->getErrors());
        $stateMachineProcessDefinitionTransfer = $stateMachineProcessDefinitionCollectionResponseTransfer->getStateMachineProcessDefinitions()->offsetGet(0);
        $this->assertSame(WorkflowConfig::PROCESS_DEFINITION_STATUS_INACTIVE, $stateMachineProcessDefinitionTransfer->getStatus());
        $this->assertSame(static::UNDEFINED_INITIAL_STATE, $stateMachineProcessDefinitionTransfer->getInitialState());
    }

    public function testActivatingAnInvalidVersionIsRejected(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $idDefinition = $this->createInactiveVersion(
            $stateMachineProcessTransfer,
            sprintf(WorkflowHelper::VALID_DEFINITION_TEMPLATE, $stateMachineProcessTransfer->getProcessNameOrFail()),
            static::UNDEFINED_INITIAL_STATE,
        )->getIdStateMachineProcessDefinitionOrFail();

        // Act
        $responseTransfer = $this->tester->getFacade()->updateStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCollectionRequestTransfer())
                ->addStateMachineProcessDefinition(
                    (new StateMachineProcessDefinitionTransfer())
                        ->setIdStateMachineProcessDefinition($idDefinition)
                        ->setStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE),
                )
                ->setIsTransactional(true),
        );

        // Assert
        $this->assertGreaterThan(0, $responseTransfer->getErrors()->count());
        $reloadedTransfer = $this->findDefinitionById($idDefinition);
        $this->assertNotNull($reloadedTransfer);
        $this->assertSame(WorkflowConfig::PROCESS_DEFINITION_STATUS_INACTIVE, $reloadedTransfer->getStatus());
    }

    public function testCreateStateMachineProcessDefinitionCollectionRejectsDefinitionWithInvalidTimeout(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();

        // Act
        $stateMachineProcessDefinitionCollectionResponseTransfer = $this->createVersion(
            $stateMachineProcessTransfer,
            $this->buildDefinitionWithTimeoutTransition($stateMachineProcessTransfer->getProcessNameOrFail(), static::INVALID_TIMEOUT),
            static::INITIAL_STATE,
        );

        // Assert
        $this->assertCount(1, $stateMachineProcessDefinitionCollectionResponseTransfer->getErrors());
        $this->assertStringContainsString(static::INVALID_TIMEOUT, (string)$stateMachineProcessDefinitionCollectionResponseTransfer->getErrors()->offsetGet(0)->getMessage());
        $this->assertCount(0, $this->findDefinitionsByProcess($stateMachineProcessTransfer));
    }

    public function testActivatingAValidVersionSucceedsAndRecomputesTransitionFlags(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $idDefinition = $this->createInactiveVersion(
            $stateMachineProcessTransfer,
            $this->buildDefinitionWithTimeoutTransition($stateMachineProcessTransfer->getProcessNameOrFail(), static::VALID_TIMEOUT),
            static::INITIAL_STATE,
        )->getIdStateMachineProcessDefinitionOrFail();

        // Act
        $responseTransfer = $this->tester->getFacade()->updateStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCollectionRequestTransfer())
                ->addStateMachineProcessDefinition(
                    (new StateMachineProcessDefinitionTransfer())
                        ->setIdStateMachineProcessDefinition($idDefinition)
                        ->setStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE),
                )
                ->setIsTransactional(true),
        );

        // Assert
        $this->assertCount(0, $responseTransfer->getErrors());
        $reloadedTransfer = $this->findDefinitionById($idDefinition);
        $this->assertNotNull($reloadedTransfer);
        $this->assertSame(WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE, $reloadedTransfer->getStatus());
        $this->assertTrue($reloadedTransfer->getHasTimeoutTransitions());
    }

    protected function createInactiveVersion(
        StateMachineProcessTransfer $stateMachineProcessTransfer,
        string $definition,
        string $initialState
    ): StateMachineProcessDefinitionTransfer {
        return $this->createVersion($stateMachineProcessTransfer, $definition, $initialState)
            ->getStateMachineProcessDefinitions()
            ->getIterator()
            ->current();
    }

    protected function createVersion(
        StateMachineProcessTransfer $stateMachineProcessTransfer,
        string $definition,
        string $initialState
    ): StateMachineProcessDefinitionCollectionResponseTransfer {
        return $this->tester->getFacade()->createStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCollectionRequestTransfer())
                ->addStateMachineProcessDefinition(
                    (new StateMachineProcessDefinitionTransfer())
                        ->setStateMachineProcess($stateMachineProcessTransfer)
                        ->setInitialState($initialState)
                        ->setDefinition($definition),
                ),
        );
    }

    /**
     * @return \ArrayObject<int, \Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer>
     */
    protected function findDefinitionsByProcess(StateMachineProcessTransfer $stateMachineProcessTransfer): ArrayObject
    {
        return $this->tester->getFacade()->getStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCriteriaTransfer())
                ->setStateMachineProcessDefinitionConditions(
                    (new StateMachineProcessDefinitionConditionsTransfer())
                        ->addIdStateMachineProcess($stateMachineProcessTransfer->getIdStateMachineProcessOrFail()),
                ),
        )->getStateMachineProcessDefinitions();
    }

    protected function findDefinitionById(int $idStateMachineProcessDefinition): ?StateMachineProcessDefinitionTransfer
    {
        return $this->tester->getFacade()->getStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCriteriaTransfer())
                ->setStateMachineProcessDefinitionConditions(
                    (new StateMachineProcessDefinitionConditionsTransfer())
                        ->addIdStateMachineProcessDefinition($idStateMachineProcessDefinition),
                ),
        )->getStateMachineProcessDefinitions()->getIterator()->current();
    }

    protected function buildDefinitionWithTimeoutTransition(string $processName, string $timeout): string
    {
        return sprintf(
            '<?xml version="1.0"?>'
            . '<statemachine xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="http://static.spryker.com/state-machine-01.xsd">'
            . '<process name="%s" main="true">'
            . '<states><state name="new" display="New"/><state name="logged" display="Logged"/></states>'
            . '<transitions><transition><source>new</source><target>logged</target><event>log-it</event></transition></transitions>'
            . '<events><event name="log-it" timeout="%s"/></events>'
            . '</process>'
            . '</statemachine>',
            $processName,
            $timeout,
        );
    }
}

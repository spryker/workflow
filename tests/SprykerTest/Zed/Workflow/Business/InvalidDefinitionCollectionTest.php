<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Business;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Spryker\Zed\Workflow\WorkflowConfig;
use SprykerTest\Zed\Workflow\WorkflowBusinessTester;

/**
 * Covers the deferred-validation model: a version is created WITHOUT validation (an inactive version may
 * hold invalid XML), and validation runs only on activation.
 *
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

    protected WorkflowBusinessTester $tester;

    public function testCreateStateMachineProcessDefinitionCollectionPersistsInvalidDefinitionAsInactive(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();

        // Act
        $responseTransfer = $this->tester->getFacade()->createStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCollectionRequestTransfer())
                ->addStateMachineProcessDefinition(
                    (new StateMachineProcessDefinitionTransfer())
                        ->setStateMachineProcess($stateMachineProcessTransfer)
                        ->setInitialState('new')
                        ->setDefinition(static::MALFORMED_DEFINITION),
                ),
        );

        // Assert
        $definitionTransfer = $responseTransfer->getStateMachineProcessDefinitions()->getIterator()->current();
        $this->assertCount(0, $responseTransfer->getErrors());
        $this->assertSame(WorkflowConfig::PROCESS_DEFINITION_STATUS_INACTIVE, $definitionTransfer->getStatus());
        $this->assertSame(static::MALFORMED_DEFINITION, $definitionTransfer->getDefinition());
    }

    public function testActivatingAnInvalidVersionIsRejected(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $idDefinition = $this->createInactiveVersion($stateMachineProcessTransfer, static::MALFORMED_DEFINITION)
            ->getIdStateMachineProcessDefinitionOrFail();

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

    public function testActivatingAValidVersionSucceedsAndRecomputesTransitionFlags(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $idDefinition = $this->createInactiveVersion(
            $stateMachineProcessTransfer,
            $this->buildDefinitionWithTimeoutTransition($stateMachineProcessTransfer->getProcessNameOrFail()),
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
        string $definition
    ): StateMachineProcessDefinitionTransfer {
        return $this->tester->getFacade()->createStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCollectionRequestTransfer())
                ->addStateMachineProcessDefinition(
                    (new StateMachineProcessDefinitionTransfer())
                        ->setStateMachineProcess($stateMachineProcessTransfer)
                        ->setInitialState('new')
                        ->setDefinition($definition),
                ),
        )->getStateMachineProcessDefinitions()->getIterator()->current();
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

    protected function buildDefinitionWithTimeoutTransition(string $processName): string
    {
        return sprintf(
            '<?xml version="1.0"?>'
            . '<statemachine xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="http://static.spryker.com/state-machine-01.xsd">'
            . '<process name="%s" main="true">'
            . '<states><state name="new" display="New"/><state name="logged" display="Logged"/></states>'
            . '<transitions><transition><source>new</source><target>logged</target><event>log-it</event></transition></transitions>'
            . '<events><event name="log-it" timeout="1 hour"/></events>'
            . '</process>'
            . '</statemachine>',
            $processName,
        );
    }
}

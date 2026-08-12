<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Business;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\StateMachineDefinitionValidationErrorTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionValidationResponseTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
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
 * @group DefinitionCollectionTest
 * Add your own group annotations below this line
 */
class DefinitionCollectionTest extends Unit
{
    protected WorkflowBusinessTester $tester;

    public function testCreateStateMachineProcessDefinitionCollectionPersistsFirstVersionAsInactive(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();

        // Act
        $stateMachineProcessDefinitionCollectionResponseTransfer = $this->tester->getFacade()
            ->createStateMachineProcessDefinitionCollection(
                (new StateMachineProcessDefinitionCollectionRequestTransfer())
                    ->addStateMachineProcessDefinition(
                        (new StateMachineProcessDefinitionTransfer())
                            ->setStateMachineProcess($stateMachineProcessTransfer)
                            ->setInitialState('new')
                            ->setDefinition(sprintf(WorkflowHelper::VALID_DEFINITION_TEMPLATE, $stateMachineProcessTransfer->getProcessNameOrFail())),
                    ),
            );

        // Assert
        $stateMachineProcessDefinitionTransfer = $stateMachineProcessDefinitionCollectionResponseTransfer->getStateMachineProcessDefinitions()->getIterator()->current();
        $this->assertCount(0, $stateMachineProcessDefinitionCollectionResponseTransfer->getErrors());
        $this->assertSame(1, $stateMachineProcessDefinitionTransfer->getVersion());
        $this->assertSame(
            WorkflowConfig::PROCESS_DEFINITION_STATUS_INACTIVE,
            $stateMachineProcessDefinitionTransfer->getStatus(),
        );
    }

    public function testCreateStateMachineProcessDefinitionCollectionIncrementsVersion(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $this->tester->haveStateMachineProcessDefinition($stateMachineProcessTransfer);

        // Act
        $secondStateMachineProcessDefinitionTransfer = $this->tester->haveStateMachineProcessDefinition($stateMachineProcessTransfer);

        // Assert
        $this->assertSame(2, $secondStateMachineProcessDefinitionTransfer->getVersion());
    }

    public function testValidateStateMachineProcessDefinitionAcceptsValidDefinition(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $stateMachineProcessDefinitionTransfer = (new StateMachineProcessDefinitionTransfer())
            ->setStateMachineProcess($stateMachineProcessTransfer)
            ->setInitialState('new')
            ->setDefinition(sprintf(WorkflowHelper::VALID_DEFINITION_TEMPLATE, $stateMachineProcessTransfer->getProcessNameOrFail()));

        // Act
        $stateMachineDefinitionValidationResponseTransfer = $this->tester->getFacade()
            ->validateStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);

        // Assert
        $this->assertTrue($stateMachineDefinitionValidationResponseTransfer->getIsValid());
        $this->assertCount(0, $stateMachineDefinitionValidationResponseTransfer->getErrors());
    }

    public function testValidateStateMachineProcessDefinitionRejectsMalformedXml(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $stateMachineProcessDefinitionTransfer = (new StateMachineProcessDefinitionTransfer())
            ->setStateMachineProcess($stateMachineProcessTransfer)
            ->setInitialState('new')
            ->setDefinition('<statemachine><process name="broken"></statemachine>');

        // Act
        $stateMachineDefinitionValidationResponseTransfer = $this->tester->getFacade()
            ->validateStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);

        // Assert
        $this->assertFalse($stateMachineDefinitionValidationResponseTransfer->getIsValid());
        $this->assertValidationError($stateMachineDefinitionValidationResponseTransfer, 'invalid_xml', 'The definition is not valid XML.');
    }

    public function testValidateStateMachineProcessDefinitionRejectsEmptyDefinition(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $stateMachineProcessDefinitionTransfer = (new StateMachineProcessDefinitionTransfer())
            ->setStateMachineProcess($stateMachineProcessTransfer)
            ->setInitialState('new')
            ->setDefinition('   ');

        // Act
        $stateMachineDefinitionValidationResponseTransfer = $this->tester->getFacade()
            ->validateStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);

        // Assert
        $this->assertFalse($stateMachineDefinitionValidationResponseTransfer->getIsValid());
        $this->assertValidationError($stateMachineDefinitionValidationResponseTransfer, 'empty_definition', 'The definition must not be empty.');
    }

    public function testValidateStateMachineProcessDefinitionRejectsMismatchedMainProcessName(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $stateMachineProcessDefinitionTransfer = (new StateMachineProcessDefinitionTransfer())
            ->setStateMachineProcess($stateMachineProcessTransfer)
            ->setInitialState('new')
            ->setDefinition(sprintf(WorkflowHelper::VALID_DEFINITION_TEMPLATE, 'a-different-name'));

        // Act
        $stateMachineDefinitionValidationResponseTransfer = $this->tester->getFacade()
            ->validateStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);

        // Assert
        $this->assertValidationError(
            $stateMachineDefinitionValidationResponseTransfer,
            'process_name_mismatch',
            sprintf('The main process name "a-different-name" in the definition must match the process name "%s".', $stateMachineProcessTransfer->getProcessNameOrFail()),
        );
    }

    public function testValidateStateMachineProcessDefinitionRejectsInitialStateNotAmongDefinedStates(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $stateMachineProcessDefinitionTransfer = (new StateMachineProcessDefinitionTransfer())
            ->setStateMachineProcess($stateMachineProcessTransfer)
            ->setInitialState('does-not-exist')
            ->setDefinition(sprintf(WorkflowHelper::VALID_DEFINITION_TEMPLATE, $stateMachineProcessTransfer->getProcessNameOrFail()));

        // Act
        $stateMachineDefinitionValidationResponseTransfer = $this->tester->getFacade()
            ->validateStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);

        // Assert
        $stateMachineDefinitionValidationErrorTransfer = $this->assertValidationError(
            $stateMachineDefinitionValidationResponseTransfer,
            'missing_initial_state',
            'Initial state "does-not-exist" does not exist as a defined state.',
        );
        $this->assertSame('does-not-exist', $stateMachineDefinitionValidationErrorTransfer->getStateName());
    }

    public function testValidateStateMachineProcessDefinitionRejectsEmptyInitialState(): void
    {
        // Arrange - no initial state provided (empty field).
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $stateMachineProcessDefinitionTransfer = (new StateMachineProcessDefinitionTransfer())
            ->setStateMachineProcess($stateMachineProcessTransfer)
            ->setDefinition(sprintf(WorkflowHelper::VALID_DEFINITION_TEMPLATE, $stateMachineProcessTransfer->getProcessNameOrFail()));

        // Act
        $stateMachineDefinitionValidationResponseTransfer = $this->tester->getFacade()
            ->validateStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);

        // Assert - an empty initial state is rejected the same way as an unknown one.
        $this->assertFalse($stateMachineDefinitionValidationResponseTransfer->getIsValid());
        $this->assertValidationError(
            $stateMachineDefinitionValidationResponseTransfer,
            'missing_initial_state',
            'Initial state "" does not exist as a defined state.',
        );
    }

    public function testValidateStateMachineProcessDefinitionRejectsDuplicateState(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $stateMachineProcessDefinitionTransfer = (new StateMachineProcessDefinitionTransfer())
            ->setStateMachineProcess($stateMachineProcessTransfer)
            ->setInitialState('new')
            ->setDefinition($this->wrapProcessBody(
                $stateMachineProcessTransfer->getProcessNameOrFail(),
                '<states>
                    <state name="new" display="New"/>
                    <state name="logged" display="Logged"/>
                    <state name="new" display="New again"/>
                </states>
                <transitions>
                    <transition><source>new</source><target>logged</target><event>log-it</event></transition>
                </transitions>
                <events><event name="log-it" manual="true"/></events>',
            ));

        // Act
        $stateMachineDefinitionValidationResponseTransfer = $this->tester->getFacade()
            ->validateStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);

        // Assert
        $stateMachineDefinitionValidationErrorTransfer = $this->assertValidationError(
            $stateMachineDefinitionValidationResponseTransfer,
            'duplicate_state',
            'Duplicate state "new".',
        );
        $this->assertSame('new', $stateMachineDefinitionValidationErrorTransfer->getStateName());
    }

    public function testValidateStateMachineProcessDefinitionRejectsTransitionToUndefinedTargetState(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $stateMachineProcessDefinitionTransfer = (new StateMachineProcessDefinitionTransfer())
            ->setStateMachineProcess($stateMachineProcessTransfer)
            ->setInitialState('new')
            ->setDefinition($this->wrapProcessBody(
                $stateMachineProcessTransfer->getProcessNameOrFail(),
                '<states>
                    <state name="new" display="New"/>
                </states>
                <transitions>
                    <transition><source>new</source><target>ghost</target><event>log-it</event></transition>
                </transitions>
                <events><event name="log-it" manual="true"/></events>',
            ));

        // Act
        $stateMachineDefinitionValidationResponseTransfer = $this->tester->getFacade()
            ->validateStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);

        // Assert
        $stateMachineDefinitionValidationErrorTransfer = $this->assertValidationError(
            $stateMachineDefinitionValidationResponseTransfer,
            'undefined_target_state',
            'Transition targets undefined state "ghost".',
        );
        $this->assertSame('ghost', $stateMachineDefinitionValidationErrorTransfer->getStateName());
    }

    public function testValidateStateMachineProcessDefinitionRejectsUnreachableState(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $stateMachineProcessDefinitionTransfer = (new StateMachineProcessDefinitionTransfer())
            ->setStateMachineProcess($stateMachineProcessTransfer)
            ->setInitialState('new')
            ->setDefinition($this->wrapProcessBody(
                $stateMachineProcessTransfer->getProcessNameOrFail(),
                '<states>
                    <state name="new" display="New"/>
                    <state name="logged" display="Logged"/>
                    <state name="orphan" display="Orphan"/>
                </states>
                <transitions>
                    <transition><source>new</source><target>logged</target><event>log-it</event></transition>
                </transitions>
                <events><event name="log-it" manual="true"/></events>',
            ));

        // Act
        $stateMachineDefinitionValidationResponseTransfer = $this->tester->getFacade()
            ->validateStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);

        // Assert
        $stateMachineDefinitionValidationErrorTransfer = $this->assertValidationError(
            $stateMachineDefinitionValidationResponseTransfer,
            'unreachable_state',
            'State "orphan" is unreachable.',
        );
        $this->assertSame('orphan', $stateMachineDefinitionValidationErrorTransfer->getStateName());
    }

    public function testValidateStateMachineProcessDefinitionRejectsDuplicateEvent(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $stateMachineProcessDefinitionTransfer = (new StateMachineProcessDefinitionTransfer())
            ->setStateMachineProcess($stateMachineProcessTransfer)
            ->setInitialState('new')
            ->setDefinition($this->wrapProcessBody(
                $stateMachineProcessTransfer->getProcessNameOrFail(),
                '<states>
                    <state name="new" display="New"/>
                    <state name="logged" display="Logged"/>
                </states>
                <transitions>
                    <transition><source>new</source><target>logged</target><event>log-it</event></transition>
                </transitions>
                <events>
                    <event name="log-it" manual="true"/>
                    <event name="log-it" manual="true"/>
                </events>',
            ));

        // Act
        $stateMachineDefinitionValidationResponseTransfer = $this->tester->getFacade()
            ->validateStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);

        // Assert
        $stateMachineDefinitionValidationErrorTransfer = $this->assertValidationError(
            $stateMachineDefinitionValidationResponseTransfer,
            'duplicate_event',
            'Duplicate event "log-it".',
        );
        $this->assertSame('log-it', $stateMachineDefinitionValidationErrorTransfer->getEventName());
    }

    public function testValidateStateMachineProcessDefinitionRejectsUnknownCommand(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $stateMachineProcessDefinitionTransfer = (new StateMachineProcessDefinitionTransfer())
            ->setStateMachineProcess($stateMachineProcessTransfer)
            ->setInitialState('new')
            ->setDefinition($this->wrapProcessBody(
                $stateMachineProcessTransfer->getProcessNameOrFail(),
                '<states>
                    <state name="new" display="New"/>
                    <state name="logged" display="Logged"/>
                </states>
                <transitions>
                    <transition><source>new</source><target>logged</target><event>log-it</event></transition>
                </transitions>
                <events>
                    <event name="log-it" manual="true" command="Nonexistent/Command"/>
                </events>',
            ));

        // Act
        $stateMachineDefinitionValidationResponseTransfer = $this->tester->getFacade()
            ->validateStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);

        // Assert
        $stateMachineDefinitionValidationErrorTransfer = $this->assertValidationError(
            $stateMachineDefinitionValidationResponseTransfer,
            'unknown_command',
            'Unknown command "Nonexistent/Command".',
        );
        $this->assertSame('log-it', $stateMachineDefinitionValidationErrorTransfer->getEventName());
    }

    public function testValidateStateMachineProcessDefinitionRejectsUnknownCondition(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $stateMachineProcessDefinitionTransfer = (new StateMachineProcessDefinitionTransfer())
            ->setStateMachineProcess($stateMachineProcessTransfer)
            ->setInitialState('new')
            ->setDefinition($this->wrapProcessBody(
                $stateMachineProcessTransfer->getProcessNameOrFail(),
                '<states>
                    <state name="new" display="New"/>
                    <state name="logged" display="Logged"/>
                </states>
                <transitions>
                    <transition condition="Nonexistent/Condition"><source>new</source><target>logged</target><event>log-it</event></transition>
                </transitions>
                <events>
                    <event name="log-it" manual="true"/>
                </events>',
            ));

        // Act
        $stateMachineDefinitionValidationResponseTransfer = $this->tester->getFacade()
            ->validateStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);

        // Assert
        $this->assertValidationError(
            $stateMachineDefinitionValidationResponseTransfer,
            'unknown_condition',
            'Unknown condition "Nonexistent/Condition".',
        );
    }

    public function testValidateStateMachineProcessDefinitionRejectsMissingStates(): void
    {
        // Arrange - a well-formed definition whose process has no <states> section.
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $stateMachineProcessDefinitionTransfer = (new StateMachineProcessDefinitionTransfer())
            ->setStateMachineProcess($stateMachineProcessTransfer)
            ->setInitialState('new')
            ->setDefinition($this->wrapProcessBody(
                $stateMachineProcessTransfer->getProcessNameOrFail(),
                '<transitions>
                    <transition><source>new</source><target>logged</target><event>log-it</event></transition>
                </transitions>
                <events>
                    <event name="log-it" manual="true"/>
                </events>',
            ));

        // Act
        $stateMachineDefinitionValidationResponseTransfer = $this->tester->getFacade()
            ->validateStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);

        // Assert
        $this->assertHasValidationError(
            $stateMachineDefinitionValidationResponseTransfer,
            'missing_required_element',
            'The definition must contain at least one <state>.',
        );
    }

    public function testValidateStateMachineProcessDefinitionRejectsMissingTransitions(): void
    {
        // Arrange - a well-formed definition whose process has no <transitions> section.
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $stateMachineProcessDefinitionTransfer = (new StateMachineProcessDefinitionTransfer())
            ->setStateMachineProcess($stateMachineProcessTransfer)
            ->setInitialState('new')
            ->setDefinition($this->wrapProcessBody(
                $stateMachineProcessTransfer->getProcessNameOrFail(),
                '<states>
                    <state name="new" display="New"/>
                </states>
                <events>
                    <event name="log-it" manual="true"/>
                </events>',
            ));

        // Act
        $stateMachineDefinitionValidationResponseTransfer = $this->tester->getFacade()
            ->validateStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);

        // Assert
        $this->assertHasValidationError(
            $stateMachineDefinitionValidationResponseTransfer,
            'missing_required_element',
            'The definition must contain at least one <transition>.',
        );
    }

    public function testValidateStateMachineProcessDefinitionRejectsMissingEvents(): void
    {
        // Arrange - a well-formed definition whose process has no <events> section.
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $stateMachineProcessDefinitionTransfer = (new StateMachineProcessDefinitionTransfer())
            ->setStateMachineProcess($stateMachineProcessTransfer)
            ->setInitialState('new')
            ->setDefinition($this->wrapProcessBody(
                $stateMachineProcessTransfer->getProcessNameOrFail(),
                '<states>
                    <state name="new" display="New"/>
                    <state name="logged" display="Logged"/>
                </states>
                <transitions>
                    <transition><source>new</source><target>logged</target><event>log-it</event></transition>
                </transitions>',
            ));

        // Act
        $stateMachineDefinitionValidationResponseTransfer = $this->tester->getFacade()
            ->validateStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);

        // Assert
        $this->assertHasValidationError(
            $stateMachineDefinitionValidationResponseTransfer,
            'missing_required_element',
            'The definition must contain at least one <event>.',
        );
    }

    public function testUpdateStateMachineProcessDefinitionCollectionActivatesExclusively(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $firstStateMachineProcessDefinitionTransfer = $this->tester->haveStateMachineProcessDefinition($stateMachineProcessTransfer);
        $secondStateMachineProcessDefinitionTransfer = $this->tester->haveStateMachineProcessDefinition($stateMachineProcessTransfer);
        $this->tester->activateStateMachineProcessDefinition($firstStateMachineProcessDefinitionTransfer);

        // Act
        $this->tester->activateStateMachineProcessDefinition($secondStateMachineProcessDefinitionTransfer);

        // Assert
        $activeStatus = WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE;
        $inactiveStatus = WorkflowConfig::PROCESS_DEFINITION_STATUS_INACTIVE;
        $reloadedFirstStateMachineProcessDefinitionTransfer = $this->findStateMachineProcessDefinitionById($firstStateMachineProcessDefinitionTransfer->getIdStateMachineProcessDefinitionOrFail());
        $reloadedSecondStateMachineProcessDefinitionTransfer = $this->findStateMachineProcessDefinitionById($secondStateMachineProcessDefinitionTransfer->getIdStateMachineProcessDefinitionOrFail());
        $this->assertNotNull($reloadedFirstStateMachineProcessDefinitionTransfer);
        $this->assertNotNull($reloadedSecondStateMachineProcessDefinitionTransfer);
        $this->assertSame($inactiveStatus, $reloadedFirstStateMachineProcessDefinitionTransfer->getStatus());
        $this->assertSame($activeStatus, $reloadedSecondStateMachineProcessDefinitionTransfer->getStatus());
    }

    public function testUpdateStateMachineProcessDefinitionCollectionReturnsErrorForUnknownDefinition(): void
    {
        // Arrange: an id that does not correspond to any persisted definition.
        $stateMachineProcessDefinitionCollectionRequestTransfer = (new StateMachineProcessDefinitionCollectionRequestTransfer())
            ->addStateMachineProcessDefinition(
                (new StateMachineProcessDefinitionTransfer())
                    ->setIdStateMachineProcessDefinition(999999999)
                    ->setStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE),
            );

        // Act
        $stateMachineProcessDefinitionCollectionResponseTransfer = $this->tester->getFacade()
            ->updateStateMachineProcessDefinitionCollection($stateMachineProcessDefinitionCollectionRequestTransfer);

        // Assert
        $this->assertCount(1, $stateMachineProcessDefinitionCollectionResponseTransfer->getErrors());
        $this->assertSame(
            'The version was not found.',
            $stateMachineProcessDefinitionCollectionResponseTransfer->getErrors()->getIterator()->current()->getMessage(),
        );
    }

    public function testUpdateStateMachineProcessDefinitionCollectionDeactivatesWhenStatusIsNotActive(): void
    {
        // Arrange: an activated definition that we then downgrade to inactive.
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $stateMachineProcessDefinitionTransfer = $this->tester->haveStateMachineProcessDefinition($stateMachineProcessTransfer);
        $this->tester->activateStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);

        // Act: request a non-active status for the currently-active definition.
        $this->tester->getFacade()->updateStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCollectionRequestTransfer())
                ->addStateMachineProcessDefinition(
                    (new StateMachineProcessDefinitionTransfer())
                        ->setIdStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer->getIdStateMachineProcessDefinitionOrFail())
                        ->setStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_INACTIVE),
                ),
        );

        // Assert
        $reloadedStateMachineProcessDefinitionTransfer = $this->findStateMachineProcessDefinitionById($stateMachineProcessDefinitionTransfer->getIdStateMachineProcessDefinitionOrFail());
        $this->assertNotNull($reloadedStateMachineProcessDefinitionTransfer);
        $this->assertSame(
            WorkflowConfig::PROCESS_DEFINITION_STATUS_INACTIVE,
            $reloadedStateMachineProcessDefinitionTransfer->getStatus(),
        );
    }

    public function testGetStateMachineProcessDefinitionCollectionFiltersByVersion(): void
    {
        // Arrange
        $stateMachineProcessTransfer = $this->tester->haveStateMachineProcess();
        $this->tester->haveStateMachineProcessDefinition($stateMachineProcessTransfer);
        $this->tester->haveStateMachineProcessDefinition($stateMachineProcessTransfer);

        // Act
        $stateMachineProcessDefinitionCollectionTransfer = $this->tester->getFacade()->getStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCriteriaTransfer())
                ->setStateMachineProcessDefinitionConditions(
                    (new StateMachineProcessDefinitionConditionsTransfer())
                        ->addIdStateMachineProcess($stateMachineProcessTransfer->getIdStateMachineProcessOrFail())
                        ->addVersion(2),
                ),
        );

        // Assert
        $this->assertCount(1, $stateMachineProcessDefinitionCollectionTransfer->getStateMachineProcessDefinitions());
        $this->assertSame(2, $stateMachineProcessDefinitionCollectionTransfer->getStateMachineProcessDefinitions()->getIterator()->current()->getVersion());
    }

    protected function findStateMachineProcessDefinitionById(int $idStateMachineProcessDefinition): ?StateMachineProcessDefinitionTransfer
    {
        return $this->tester->getFacade()->getStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCriteriaTransfer())
                ->setStateMachineProcessDefinitionConditions(
                    (new StateMachineProcessDefinitionConditionsTransfer())
                        ->addIdStateMachineProcessDefinition($idStateMachineProcessDefinition),
                ),
        )->getStateMachineProcessDefinitions()->getIterator()->current();
    }

    /**
     * Asserts the response is invalid and carries exactly one error of the expected type and message, then
     * returns that error so a caller can additionally assert its state-/event-name payload.
     */
    protected function assertValidationError(
        StateMachineDefinitionValidationResponseTransfer $stateMachineDefinitionValidationResponseTransfer,
        string $expectedType,
        string $expectedMessage
    ): StateMachineDefinitionValidationErrorTransfer {
        $this->assertFalse($stateMachineDefinitionValidationResponseTransfer->getIsValid());
        $this->assertCount(1, $stateMachineDefinitionValidationResponseTransfer->getErrors());

        $stateMachineDefinitionValidationErrorTransfer = $stateMachineDefinitionValidationResponseTransfer->getErrors()->getIterator()->current();
        $this->assertSame($expectedType, $stateMachineDefinitionValidationErrorTransfer->getType());
        $this->assertSame($expectedMessage, $stateMachineDefinitionValidationErrorTransfer->getMessage());

        return $stateMachineDefinitionValidationErrorTransfer;
    }

    /**
     * Asserts the response is invalid and carries an error of the expected type and message among its
     * errors (there may be additional errors, e.g. a missing states section also breaks the initial state).
     */
    protected function assertHasValidationError(
        StateMachineDefinitionValidationResponseTransfer $stateMachineDefinitionValidationResponseTransfer,
        string $expectedType,
        string $expectedMessage
    ): void {
        $this->assertFalse($stateMachineDefinitionValidationResponseTransfer->getIsValid());

        foreach ($stateMachineDefinitionValidationResponseTransfer->getErrors() as $stateMachineDefinitionValidationErrorTransfer) {
            if (
                $stateMachineDefinitionValidationErrorTransfer->getType() === $expectedType
                && $stateMachineDefinitionValidationErrorTransfer->getMessage() === $expectedMessage
            ) {
                return;
            }
        }

        $this->fail(sprintf('Expected validation error "%s" (%s) was not found.', $expectedMessage, $expectedType));
    }

    protected function wrapProcessBody(string $processName, string $processBody): string
    {
        return sprintf(
            '<?xml version="1.0"?>'
            . '<statemachine xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="http://static.spryker.com/state-machine-01.xsd">'
            . '<process name="%s" main="true">%s</process>'
            . '</statemachine>',
            $processName,
            $processBody,
        );
    }
}

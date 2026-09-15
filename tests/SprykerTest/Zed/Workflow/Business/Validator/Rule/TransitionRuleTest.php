<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Business\Validator\Rule;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\StateMachineDefinitionValidationResponseTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use SimpleXMLElement;
use Spryker\Zed\Workflow\Business\Registry\PluginRegistryInterface;
use Spryker\Zed\Workflow\Business\Validator\Rule\DefinitionValidationContext;
use Spryker\Zed\Workflow\Business\Validator\Rule\TransitionRule;

/**
 * Reachability is seeded from the authoritative initial state — not the first-declared state. Regression
 * cover for the report where a non-first initial state was flagged unreachable (SM2) and a first-declared
 * non-initial state escaped the unreachable check (SM3).
 *
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group Workflow
 * @group Business
 * @group Validator
 * @group Rule
 * @group TransitionRuleTest
 * Add your own group annotations below this line
 */
class TransitionRuleTest extends Unit
{
    public function testInitialStateIsNeverFlaggedUnreachableEvenWhenDeclaredAfterOtherStates(): void
    {
        // Arrange
        $xml = <<<'XML'
<statemachine xmlns="spryker:state-machine-01">
    <process name="test" main="true">
        <states>
            <state name="state 1"/>
            <state name="state 2"/>
            <state name="state 3"/>
            <state name="state 4"/>
        </states>
        <transitions>
            <transition><source>state 3</source><target>state 4</target><event>event 1</event></transition>
        </transitions>
        <events><event name="event 1"/></events>
    </process>
</statemachine>
XML;
        $response = new StateMachineDefinitionValidationResponseTransfer();

        // Act
        $this->createRule()->validate($this->createContext($xml, 'state 3'), $response);

        // Assert
        $unreachable = $this->collectUnreachableStateNames($response);
        $this->assertNotContains('state 3', $unreachable, 'The initial state must never be reported unreachable.');
        $this->assertContains('state 1', $unreachable);
        $this->assertContains('state 2', $unreachable);
    }

    public function testFirstDeclaredNonInitialStateWithoutIncomingTransitionIsFlaggedUnreachable(): void
    {
        // Arrange
        $xml = <<<'XML'
<statemachine xmlns="spryker:state-machine-01">
    <process name="test" main="true">
        <states>
            <state name="state 1"/>
            <state name="state 2"/>
            <state name="state 3"/>
        </states>
        <transitions>
            <transition><source>state 2</source><target>state 3</target><event>event 1</event></transition>
        </transitions>
        <events><event name="event 1"/></events>
    </process>
</statemachine>
XML;
        $response = new StateMachineDefinitionValidationResponseTransfer();

        // Act
        $this->createRule()->validate($this->createContext($xml, 'state 2'), $response);

        // Assert
        $this->assertContains('state 1', $this->collectUnreachableStateNames($response));
    }

    public function testUnreachableCheckIsSkippedWhenInitialStateIsMissing(): void
    {
        // Arrange
        $xml = <<<'XML'
<statemachine xmlns="spryker:state-machine-01">
    <process name="test" main="true">
        <states>
            <state name="state 1"/>
            <state name="state 2"/>
        </states>
        <transitions>
            <transition><source>state 1</source><target>state 2</target><event>event 1</event></transition>
        </transitions>
        <events><event name="event 1"/></events>
    </process>
</statemachine>
XML;
        $response = new StateMachineDefinitionValidationResponseTransfer();

        // Act
        $this->createRule()->validate($this->createContext($xml, null), $response);

        // Assert
        $this->assertSame([], $this->collectUnreachableStateNames($response));
    }

    protected function createRule(): TransitionRule
    {
        $pluginRegistry = $this->createMock(PluginRegistryInterface::class);
        $pluginRegistry->method('getConditionNamesBySubjectType')->willReturn([]);

        return new TransitionRule($pluginRegistry);
    }

    protected function createContext(string $xml, ?string $initialState): DefinitionValidationContext
    {
        return new DefinitionValidationContext(
            (new StateMachineProcessDefinitionTransfer())->setInitialState($initialState),
            new SimpleXMLElement($xml),
            'spy_customer',
            'test',
        );
    }

    /**
     * @return array<string>
     */
    protected function collectUnreachableStateNames(StateMachineDefinitionValidationResponseTransfer $response): array
    {
        $stateNames = [];
        foreach ($response->getErrors() as $errorTransfer) {
            if ($errorTransfer->getType() === 'unreachable_state') {
                $stateNames[] = $errorTransfer->getStateName();
            }
        }

        return $stateNames;
    }
}

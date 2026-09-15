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
use Spryker\Zed\Workflow\Business\Validator\Rule\DefinitionValidationContext;
use Spryker\Zed\Workflow\Business\Validator\Rule\InitialStateRule;

/**
 * SM1 regression: a missing initial state must produce a clear "not specified" message rather than the
 * confusing 'Initial state "" does not exist as a defined state.'
 *
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group Workflow
 * @group Business
 * @group Validator
 * @group Rule
 * @group InitialStateRuleTest
 * Add your own group annotations below this line
 */
class InitialStateRuleTest extends Unit
{
    protected const string XML = <<<'XML'
<statemachine xmlns="spryker:state-machine-01">
    <process name="test" main="true">
        <states>
            <state name="state 1"/>
            <state name="state 2"/>
        </states>
    </process>
</statemachine>
XML;

    public function testMissingInitialStateReportsNotSpecifiedWithoutEmptyQuotedName(): void
    {
        // Arrange
        $response = new StateMachineDefinitionValidationResponseTransfer();

        // Act
        (new InitialStateRule())->validate($this->createContext(null), $response);

        // Assert
        $message = $response->getErrors()->getIterator()->current()->getMessage();
        $this->assertStringContainsString('No initial state is specified', (string)$message);
        $this->assertStringNotContainsString('""', (string)$message, 'The message must not contain an empty quoted state name.');
    }

    public function testEmptyStringInitialStateIsTreatedAsNotSpecified(): void
    {
        // Arrange
        $response = new StateMachineDefinitionValidationResponseTransfer();

        // Act
        (new InitialStateRule())->validate($this->createContext(''), $response);

        // Assert
        $message = $response->getErrors()->getIterator()->current()->getMessage();
        $this->assertStringContainsString('No initial state is specified', (string)$message);
    }

    public function testNamedInitialStateThatIsNotDefinedStillReportsTheDoesNotExistMessage(): void
    {
        // Arrange
        $response = new StateMachineDefinitionValidationResponseTransfer();

        // Act
        (new InitialStateRule())->validate($this->createContext('state 9'), $response);

        // Assert
        $message = $response->getErrors()->getIterator()->current()->getMessage();
        $this->assertStringContainsString('Initial state "state 9" does not exist', (string)$message);
    }

    public function testDefinedInitialStateProducesNoError(): void
    {
        // Arrange
        $response = new StateMachineDefinitionValidationResponseTransfer();

        // Act
        (new InitialStateRule())->validate($this->createContext('state 1'), $response);

        // Assert
        $this->assertCount(0, $response->getErrors());
    }

    protected function createContext(?string $initialState): DefinitionValidationContext
    {
        return new DefinitionValidationContext(
            (new StateMachineProcessDefinitionTransfer())->setInitialState($initialState),
            new SimpleXMLElement(static::XML),
            'spy_customer',
            'test',
        );
    }
}

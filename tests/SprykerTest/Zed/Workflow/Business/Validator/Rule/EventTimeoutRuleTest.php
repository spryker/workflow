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
use Spryker\Zed\Workflow\Business\Validator\Rule\EventTimeoutRule;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group Workflow
 * @group Business
 * @group Validator
 * @group Rule
 * @group EventTimeoutRuleTest
 * Add your own group annotations below this line
 */
class EventTimeoutRuleTest extends Unit
{
    protected const string EVENT_NAME = 'expire';

    protected const string DEFINITION_TEMPLATE = '<statemachine><process name="test" main="true"><events><event name="expire"%s/></events></process></statemachine>';

    /**
     * @dataProvider provideInvalidTimeouts
     */
    public function testValidateReportsAnInvalidTimeout(string $timeout): void
    {
        // Arrange
        $stateMachineDefinitionValidationResponseTransfer = new StateMachineDefinitionValidationResponseTransfer();

        // Act
        (new EventTimeoutRule())->validate($this->createContext(sprintf(' timeout="%s"', $timeout)), $stateMachineDefinitionValidationResponseTransfer);

        // Assert
        $this->assertCount(1, $stateMachineDefinitionValidationResponseTransfer->getErrors());
        $stateMachineDefinitionValidationErrorTransfer = $stateMachineDefinitionValidationResponseTransfer->getErrors()->offsetGet(0);
        $this->assertSame(EventTimeoutRule::VALIDATION_ERROR_TYPE_INVALID_TIMEOUT, $stateMachineDefinitionValidationErrorTransfer->getType());
        $this->assertSame(static::EVENT_NAME, $stateMachineDefinitionValidationErrorTransfer->getEventName());
    }

    /**
     * @return array<string, array<string>>
     */
    public function provideInvalidTimeouts(): array
    {
        return [
            'unparseable text' => ['abc'],
            'number without unit' => ['14'],
            'misspelled unit' => ['14 dayz'],
            'empty value' => [''],
            'zero duration' => ['0 days'],
            'negative duration' => ['-1 day'],
        ];
    }

    /**
     * @dataProvider provideValidTimeouts
     */
    public function testValidateAcceptsAPositiveTimeout(string $timeout): void
    {
        // Arrange
        $stateMachineDefinitionValidationResponseTransfer = new StateMachineDefinitionValidationResponseTransfer();

        // Act
        (new EventTimeoutRule())->validate($this->createContext(sprintf(' timeout="%s"', $timeout)), $stateMachineDefinitionValidationResponseTransfer);

        // Assert
        $this->assertCount(0, $stateMachineDefinitionValidationResponseTransfer->getErrors());
    }

    /**
     * @return array<string, array<string>>
     */
    public function provideValidTimeouts(): array
    {
        return [
            'days without space' => ['14days'],
            'days' => ['14 days'],
            'hour' => ['1 hour'],
        ];
    }

    public function testValidateIgnoresAnEventWithoutTimeout(): void
    {
        // Arrange
        $stateMachineDefinitionValidationResponseTransfer = new StateMachineDefinitionValidationResponseTransfer();

        // Act
        (new EventTimeoutRule())->validate($this->createContext(''), $stateMachineDefinitionValidationResponseTransfer);

        // Assert
        $this->assertCount(0, $stateMachineDefinitionValidationResponseTransfer->getErrors());
    }

    protected function createContext(string $eventAttributes): DefinitionValidationContext
    {
        return new DefinitionValidationContext(
            new StateMachineProcessDefinitionTransfer(),
            new SimpleXMLElement(sprintf(static::DEFINITION_TEMPLATE, $eventAttributes)),
            'spy_customer',
            'test',
        );
    }
}

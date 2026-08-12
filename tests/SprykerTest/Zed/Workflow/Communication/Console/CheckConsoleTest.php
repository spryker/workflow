<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Communication\Console;

use Codeception\Test\Unit;
use Spryker\Zed\Kernel\Communication\Console\Console;
use Spryker\Zed\StateMachine\Business\StateMachineFacadeInterface;
use Spryker\Zed\Workflow\Communication\Console\CheckConditionConsole;
use Spryker\Zed\Workflow\Communication\Console\CheckTimeoutConsole;
use Spryker\Zed\Workflow\WorkflowDependencyProvider;
use SprykerTest\Zed\Workflow\WorkflowCommunicationTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group Workflow
 * @group Communication
 * @group Console
 * @group CheckConsoleTest
 * Add your own group annotations below this line
 */
class CheckConsoleTest extends Unit
{
    protected WorkflowCommunicationTester $tester;

    public function testCheckConditionConsoleReturnsSuccess(): void
    {
        // Arrange
        $this->tester->setDependency(WorkflowDependencyProvider::FACADE_STATE_MACHINE, $this->createStateMachineFacadeMock());
        $commandTester = $this->tester->getConsoleTester(new CheckConditionConsole());

        // Act
        $commandTester->execute([]);

        // Assert
        $this->assertSame(Console::CODE_SUCCESS, $commandTester->getStatusCode());
    }

    public function testCheckTimeoutConsoleReturnsSuccess(): void
    {
        // Arrange
        $this->tester->setDependency(WorkflowDependencyProvider::FACADE_STATE_MACHINE, $this->createStateMachineFacadeMock());
        $commandTester = $this->tester->getConsoleTester(new CheckTimeoutConsole());

        // Act
        $commandTester->execute([]);

        // Assert
        $this->assertSame(Console::CODE_SUCCESS, $commandTester->getStatusCode());
    }

    protected function createStateMachineFacadeMock(): StateMachineFacadeInterface
    {
        $stateMachineFacadeMock = $this->getMockBuilder(StateMachineFacadeInterface::class)->getMock();
        $stateMachineFacadeMock->method('checkConditions')->willReturn(0);
        $stateMachineFacadeMock->method('checkTimeouts')->willReturn(0);

        return $stateMachineFacadeMock;
    }
}

<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Communication\Reader;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\StateMachineTransitionHandlerCollectionTransfer;
use Generated\Shared\Transfer\StateMachineTransitionHandlerCriteriaTransfer;
use Spryker\Zed\Workflow\Business\WorkflowFacadeInterface;
use Spryker\Zed\Workflow\Communication\Reader\AvailablePluginReader;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group Workflow
 * @group Communication
 * @group Reader
 * @group AvailablePluginReaderTest
 * Add your own group annotations below this line
 */
class AvailablePluginReaderTest extends Unit
{
    protected const string SUBJECT_TYPE = 'spy_merchant';

    protected const string OTHER_SUBJECT_TYPE = 'spy_company';

    public function testGetAvailablePluginsReturnsCommandAndConditionNamesOfTheSubjectType(): void
    {
        // Arrange
        $availablePluginReader = new AvailablePluginReader($this->createWorkflowFacadeMock());

        // Act
        $availablePlugins = $availablePluginReader->getAvailablePlugins(static::SUBJECT_TYPE);

        // Assert
        $this->assertSame([
            AvailablePluginReader::AVAILABLE_PLUGINS_COMMANDS => [static::SUBJECT_TYPE . '/command'],
            AvailablePluginReader::AVAILABLE_PLUGINS_CONDITIONS => [static::SUBJECT_TYPE . '/condition'],
        ], $availablePlugins);
    }

    public function testGetAvailablePluginsIndexedBySubjectTypeReturnsThePluginsOfEverySubjectType(): void
    {
        // Arrange
        $availablePluginReader = new AvailablePluginReader($this->createWorkflowFacadeMock());

        // Act
        $availablePluginsIndexedBySubjectType = $availablePluginReader->getAvailablePluginsIndexedBySubjectType([
            static::SUBJECT_TYPE,
            static::OTHER_SUBJECT_TYPE,
        ]);

        // Assert
        $this->assertSame([static::SUBJECT_TYPE, static::OTHER_SUBJECT_TYPE], array_keys($availablePluginsIndexedBySubjectType));
        $this->assertSame(
            [static::OTHER_SUBJECT_TYPE . '/command'],
            $availablePluginsIndexedBySubjectType[static::OTHER_SUBJECT_TYPE][AvailablePluginReader::AVAILABLE_PLUGINS_COMMANDS],
        );
    }

    protected function createWorkflowFacadeMock(): WorkflowFacadeInterface
    {
        $workflowFacadeMock = $this->createMock(WorkflowFacadeInterface::class);
        $workflowFacadeMock->method('getStateMachineTransitionHandlerCollection')
            ->willReturnCallback(static function (StateMachineTransitionHandlerCriteriaTransfer $stateMachineTransitionHandlerCriteriaTransfer): StateMachineTransitionHandlerCollectionTransfer {
                $subjectType = $stateMachineTransitionHandlerCriteriaTransfer->getStateMachineTransitionHandlerConditionsOrFail()->getSubjectTypeOrFail();

                return (new StateMachineTransitionHandlerCollectionTransfer())
                    ->addCommandName($subjectType . '/command')
                    ->addConditionName($subjectType . '/condition');
            });

        return $workflowFacadeMock;
    }
}

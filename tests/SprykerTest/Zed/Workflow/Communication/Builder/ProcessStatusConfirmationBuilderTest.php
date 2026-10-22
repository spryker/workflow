<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Communication\Builder;

use Codeception\Test\Unit;
use Spryker\Zed\Workflow\Communication\Builder\ProcessStatusConfirmationBuilder;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group Workflow
 * @group Communication
 * @group Builder
 * @group ProcessStatusConfirmationBuilderTest
 * Add your own group annotations below this line
 */
class ProcessStatusConfirmationBuilderTest extends Unit
{
    protected const string PROCESS_NAME = 'Merchant onboarding';

    protected const array TRIGGER_LABELS_INDEXED_BY_EVENT_NAME = [
        'merchant.created' => 'Merchant created',
        'merchant.updated' => 'merchant.updated',
    ];

    protected const string QUOTED_TRIGGER_LABELS = '“Merchant created”, “merchant.updated”';

    public function testBuildConfirmationForActiveProcessAsksToDeactivate(): void
    {
        // Arrange
        $processStatusConfirmationBuilder = new ProcessStatusConfirmationBuilder();

        // Act
        $actionConfirmationTransfer = $processStatusConfirmationBuilder->buildActionConfirmationTransfer(static::PROCESS_NAME, static::TRIGGER_LABELS_INDEXED_BY_EVENT_NAME, true, true);

        // Assert
        $this->assertSame('Deactivate %name%?', $actionConfirmationTransfer->getTitle());
        $this->assertSame('New %triggers% events will no longer start this workflow', $actionConfirmationTransfer->getMessage());
        $this->assertSame('Deactivate', $actionConfirmationTransfer->getConfirmButtonLabel());
        $this->assertSame('danger', $actionConfirmationTransfer->getType());
        $this->assertSame([
            '%name%' => static::PROCESS_NAME,
            '%triggers%' => static::QUOTED_TRIGGER_LABELS,
        ], $actionConfirmationTransfer->getTranslationParameters());
    }

    public function testBuildConfirmationForInactiveProcessAsksToActivate(): void
    {
        // Arrange
        $processStatusConfirmationBuilder = new ProcessStatusConfirmationBuilder();

        // Act
        $actionConfirmationTransfer = $processStatusConfirmationBuilder->buildActionConfirmationTransfer(static::PROCESS_NAME, static::TRIGGER_LABELS_INDEXED_BY_EVENT_NAME, false, true);

        // Assert
        $this->assertSame('Activate %name%?', $actionConfirmationTransfer->getTitle());
        $this->assertSame('New %triggers% events will start this workflow', $actionConfirmationTransfer->getMessage());
        $this->assertSame('Activate', $actionConfirmationTransfer->getConfirmButtonLabel());
        $this->assertSame('primary', $actionConfirmationTransfer->getType());
    }

    public function testBuildConfirmationForActiveProcessWithoutTriggersUsesMessageWithoutTriggers(): void
    {
        // Arrange
        $processStatusConfirmationBuilder = new ProcessStatusConfirmationBuilder();

        // Act
        $actionConfirmationTransfer = $processStatusConfirmationBuilder->buildActionConfirmationTransfer(static::PROCESS_NAME, [], true, true);

        // Assert
        $this->assertSame('New events will no longer start this workflow', $actionConfirmationTransfer->getMessage());
    }

    public function testBuildConfirmationForInactiveProcessWithoutTriggersWarnsThatNothingStartsTheWorkflow(): void
    {
        // Arrange
        $processStatusConfirmationBuilder = new ProcessStatusConfirmationBuilder();

        // Act
        $actionConfirmationTransfer = $processStatusConfirmationBuilder->buildActionConfirmationTransfer(static::PROCESS_NAME, [], false, true);

        // Assert
        $this->assertSame('No trigger is set, so no event will start this workflow', $actionConfirmationTransfer->getMessage());
    }

    public function testBuildConfirmationForInactiveProcessWithoutActiveVersionAsksToConfirmActivation(): void
    {
        // Arrange
        $processStatusConfirmationBuilder = new ProcessStatusConfirmationBuilder();

        // Act
        $actionConfirmationTransfer = $processStatusConfirmationBuilder->buildActionConfirmationTransfer(static::PROCESS_NAME, static::TRIGGER_LABELS_INDEXED_BY_EVENT_NAME, false, false);

        // Assert
        $this->assertSame('Activate %name%?', $actionConfirmationTransfer->getTitle());
        $this->assertSame('This workflow has no active version. Are you sure you want to activate it?', $actionConfirmationTransfer->getMessage());
        $this->assertSame('Activate', $actionConfirmationTransfer->getConfirmButtonLabel());
    }

    public function testBuildConfirmationForActiveProcessWithoutActiveVersionAsksToDeactivate(): void
    {
        // Arrange
        $processStatusConfirmationBuilder = new ProcessStatusConfirmationBuilder();

        // Act
        $actionConfirmationTransfer = $processStatusConfirmationBuilder->buildActionConfirmationTransfer(static::PROCESS_NAME, static::TRIGGER_LABELS_INDEXED_BY_EVENT_NAME, true, false);

        // Assert
        $this->assertSame('New %triggers% events will no longer start this workflow', $actionConfirmationTransfer->getMessage());
    }
}

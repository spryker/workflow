<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Builder;

use Generated\Shared\Transfer\ActionConfirmationTransfer;

class ProcessStatusConfirmationBuilder implements ProcessStatusConfirmationBuilderInterface
{
    protected const string DEACTIVATE_TITLE = 'Deactivate %name%?';

    protected const string DEACTIVATE_MESSAGE = 'New %triggers% events will no longer start this workflow';

    protected const string DEACTIVATE_MESSAGE_WITHOUT_TRIGGERS = 'New events will no longer start this workflow';

    protected const string DEACTIVATE_BUTTON = 'Deactivate';

    protected const string ACTIVATE_TITLE = 'Activate %name%?';

    protected const string ACTIVATE_MESSAGE = 'New %triggers% events will start this workflow';

    protected const string ACTIVATE_MESSAGE_WITHOUT_TRIGGERS = 'No trigger is set, so no event will start this workflow';

    protected const string ACTIVATE_MESSAGE_WITHOUT_ACTIVE_VERSION = 'This workflow has no active version. Are you sure you want to activate it?';

    protected const string ACTIVATE_BUTTON = 'Activate';

    /**
     * @uses \Spryker\Zed\Gui\Communication\Table\AbstractTable::CONFIRMATION_TYPE_DANGER
     */
    protected const string TYPE_DANGER = 'danger';

    /**
     * @uses \Spryker\Zed\Gui\Communication\Table\AbstractTable::CONFIRMATION_TYPE_PRIMARY
     */
    protected const string TYPE_PRIMARY = 'primary';

    protected const string PARAMETER_NAME = '%name%';

    protected const string PARAMETER_TRIGGERS = '%triggers%';

    protected const string TRIGGER_LABEL_SEPARATOR = ', ';

    protected const string TRIGGER_LABEL_QUOTE_FORMAT = '“%s”';

    /**
     * @param array<string, string> $triggerLabelsIndexedByEventName
     */
    public function buildActionConfirmationTransfer(
        string $processName,
        array $triggerLabelsIndexedByEventName,
        bool $isProcessActive,
        bool $hasActiveVersion
    ): ActionConfirmationTransfer {
        $hasTriggers = $triggerLabelsIndexedByEventName !== [];
        $actionConfirmationTransfer = (new ActionConfirmationTransfer())->setTranslationParameters([
            static::PARAMETER_NAME => $processName,
            static::PARAMETER_TRIGGERS => $this->quoteTriggerLabels($triggerLabelsIndexedByEventName),
        ]);

        if ($isProcessActive) {
            return $actionConfirmationTransfer
                ->setTitle(static::DEACTIVATE_TITLE)
                ->setMessage($hasTriggers ? static::DEACTIVATE_MESSAGE : static::DEACTIVATE_MESSAGE_WITHOUT_TRIGGERS)
                ->setConfirmButtonLabel(static::DEACTIVATE_BUTTON)
                ->setType(static::TYPE_DANGER);
        }

        return $actionConfirmationTransfer
            ->setTitle(static::ACTIVATE_TITLE)
            ->setMessage($this->resolveActivateMessage($hasTriggers, $hasActiveVersion))
            ->setConfirmButtonLabel(static::ACTIVATE_BUTTON)
            ->setType(static::TYPE_PRIMARY);
    }

    protected function resolveActivateMessage(bool $hasTriggers, bool $hasActiveVersion): string
    {
        if (!$hasActiveVersion) {
            return static::ACTIVATE_MESSAGE_WITHOUT_ACTIVE_VERSION;
        }

        return $hasTriggers ? static::ACTIVATE_MESSAGE : static::ACTIVATE_MESSAGE_WITHOUT_TRIGGERS;
    }

    /**
     * @param array<string, string> $triggerLabelsIndexedByEventName
     */
    protected function quoteTriggerLabels(array $triggerLabelsIndexedByEventName): string
    {
        $quotedTriggerLabels = array_map(
            static fn (string $triggerLabel): string => sprintf(static::TRIGGER_LABEL_QUOTE_FORMAT, $triggerLabel),
            $triggerLabelsIndexedByEventName,
        );

        return implode(static::TRIGGER_LABEL_SEPARATOR, $quotedTriggerLabels);
    }
}

<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Form\DataProvider;

use Generated\Shared\Transfer\StateMachineTriggerEventCollectionTransfer;
use Generated\Shared\Transfer\StateMachineTriggerEventTransfer;
use Spryker\Zed\Workflow\Business\WorkflowFacadeInterface;

abstract class AbstractTriggerEventFormDataProvider
{
    protected const string CHOICE_ATTRIBUTE_TITLE = 'title';

    protected const string TITLE_SEPARATOR = ' — ';

    public function __construct(protected WorkflowFacadeInterface $workflowFacade)
    {
    }

    /**
     * @return array<string, string>
     */
    protected function getEventChoices(StateMachineTriggerEventCollectionTransfer $stateMachineTriggerEventCollectionTransfer): array
    {
        $eventChoices = [];

        foreach ($stateMachineTriggerEventCollectionTransfer->getStateMachineTriggerEvents() as $stateMachineTriggerEventTransfer) {
            $eventChoices[$stateMachineTriggerEventTransfer->getNameOrFail()] = $stateMachineTriggerEventTransfer->getEventNameOrFail();
        }

        return $eventChoices;
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected function getEventChoiceAttributes(StateMachineTriggerEventCollectionTransfer $stateMachineTriggerEventCollectionTransfer): array
    {
        $eventChoiceAttributes = [];

        foreach ($stateMachineTriggerEventCollectionTransfer->getStateMachineTriggerEvents() as $stateMachineTriggerEventTransfer) {
            $eventChoiceAttributes[$stateMachineTriggerEventTransfer->getNameOrFail()] = $this->getEventChoiceAttribute($stateMachineTriggerEventTransfer);
        }

        return $eventChoiceAttributes;
    }

    /**
     * @return array<string, string>
     */
    protected function getEventChoiceAttribute(StateMachineTriggerEventTransfer $stateMachineTriggerEventTransfer): array
    {
        return [
            static::CHOICE_ATTRIBUTE_TITLE => $this->getEventTitle($stateMachineTriggerEventTransfer),
        ];
    }

    protected function getEventTitle(StateMachineTriggerEventTransfer $stateMachineTriggerEventTransfer): string
    {
        $eventName = $stateMachineTriggerEventTransfer->getEventNameOrFail();
        $description = (string)$stateMachineTriggerEventTransfer->getDescription();

        if ($description === '') {
            return $eventName;
        }

        return $eventName . static::TITLE_SEPARATOR . $description;
    }
}

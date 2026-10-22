<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Form\DataProvider;

use Generated\Shared\Transfer\StateMachineTriggerEventCollectionTransfer;
use Generated\Shared\Transfer\StateMachineTriggerEventCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineTriggerEventTransfer;
use Spryker\Zed\Workflow\Communication\Form\ProcessForm;

class ProcessFormDataProvider extends AbstractTriggerEventFormDataProvider
{
    protected const string CHOICE_ATTRIBUTE_SUBJECT_TYPE = 'data-subject-type';

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        $stateMachineTriggerEventCollectionTransfer = $this->workflowFacade->getStateMachineTriggerEventCollection(
            new StateMachineTriggerEventCriteriaTransfer(),
        );

        return [
            ProcessForm::OPTION_SUBJECT_TYPES => $this->getSubjectTypeChoices($stateMachineTriggerEventCollectionTransfer),
            ProcessForm::OPTION_EVENT_CHOICES => $this->getEventChoices($stateMachineTriggerEventCollectionTransfer),
            ProcessForm::OPTION_EVENT_CHOICE_ATTRIBUTES => $this->getEventChoiceAttributes($stateMachineTriggerEventCollectionTransfer),
            ProcessForm::OPTION_EVENT_SUBJECT_TYPES => $this->getSubjectTypesIndexedByEventName($stateMachineTriggerEventCollectionTransfer),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getSubjectTypesIndexedByEventName(StateMachineTriggerEventCollectionTransfer $stateMachineTriggerEventCollectionTransfer): array
    {
        $subjectTypesIndexedByEventName = [];

        foreach ($stateMachineTriggerEventCollectionTransfer->getStateMachineTriggerEvents() as $stateMachineTriggerEventTransfer) {
            $subjectTypesIndexedByEventName[$stateMachineTriggerEventTransfer->getEventNameOrFail()] = $stateMachineTriggerEventTransfer->getSubjectTypeOrFail();
        }

        return $subjectTypesIndexedByEventName;
    }

    /**
     * @return array<string, string>
     */
    protected function getSubjectTypeChoices(StateMachineTriggerEventCollectionTransfer $stateMachineTriggerEventCollectionTransfer): array
    {
        $subjectTypeChoices = [];

        foreach ($stateMachineTriggerEventCollectionTransfer->getStateMachineTriggerEvents() as $stateMachineTriggerEventTransfer) {
            $subjectType = $stateMachineTriggerEventTransfer->getSubjectTypeOrFail();
            $subjectTypeChoices[$subjectType] = $subjectType;
        }

        return $subjectTypeChoices;
    }

    /**
     * @return array<string, string>
     */
    protected function getEventChoiceAttribute(StateMachineTriggerEventTransfer $stateMachineTriggerEventTransfer): array
    {
        return parent::getEventChoiceAttribute($stateMachineTriggerEventTransfer) + [
            static::CHOICE_ATTRIBUTE_SUBJECT_TYPE => $stateMachineTriggerEventTransfer->getSubjectTypeOrFail(),
        ];
    }
}

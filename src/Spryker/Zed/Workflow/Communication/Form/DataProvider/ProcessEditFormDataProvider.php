<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Form\DataProvider;

use Generated\Shared\Transfer\StateMachineTriggerEventConditionsTransfer;
use Generated\Shared\Transfer\StateMachineTriggerEventCriteriaTransfer;
use Spryker\Zed\Workflow\Communication\Form\ProcessEditForm;

class ProcessEditFormDataProvider extends AbstractTriggerEventFormDataProvider
{
    /**
     * @return array<string, mixed>
     */
    public function getOptions(int $idStateMachineProcess): array
    {
        $stateMachineTriggerEventCollectionTransfer = $this->workflowFacade->getStateMachineTriggerEventCollection(
            (new StateMachineTriggerEventCriteriaTransfer())->setStateMachineTriggerEventConditions(
                (new StateMachineTriggerEventConditionsTransfer())->addIdStateMachineProcess($idStateMachineProcess),
            ),
        );

        return [
            ProcessEditForm::OPTION_EVENT_CHOICES => $this->getEventChoices($stateMachineTriggerEventCollectionTransfer),
            ProcessEditForm::OPTION_EVENT_CHOICE_ATTRIBUTES => $this->getEventChoiceAttributes($stateMachineTriggerEventCollectionTransfer),
        ];
    }
}

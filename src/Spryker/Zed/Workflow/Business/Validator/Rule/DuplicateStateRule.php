<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Validator\Rule;

use Generated\Shared\Transfer\StateMachineDefinitionValidationErrorTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionValidationResponseTransfer;

class DuplicateStateRule implements DefinitionValidationRuleInterface
{
    /**
     * @var string
     */
    protected const VALIDATION_ERROR_TYPE_DUPLICATE_STATE = 'duplicate_state';

    public function validate(
        DefinitionValidationContext $definitionValidationContext,
        StateMachineDefinitionValidationResponseTransfer $stateMachineDefinitionValidationResponseTransfer
    ): void {
        $seenStateNames = [];
        foreach ($definitionValidationContext->getRootElement()->children() as $xmlProcess) {
            if (!isset($xmlProcess->states)) {
                continue;
            }

            foreach ($xmlProcess->states->children() as $xmlState) {
                $stateName = (string)$xmlState->attributes()['name'];
                if (in_array($stateName, $seenStateNames, true)) {
                    $stateMachineDefinitionValidationResponseTransfer->addError(
                        (new StateMachineDefinitionValidationErrorTransfer())
                            ->setType(static::VALIDATION_ERROR_TYPE_DUPLICATE_STATE)
                            ->setMessage(sprintf('Duplicate state "%s".', $stateName))
                            ->setStateName($stateName),
                    );

                    continue;
                }

                $seenStateNames[] = $stateName;
            }
        }
    }
}

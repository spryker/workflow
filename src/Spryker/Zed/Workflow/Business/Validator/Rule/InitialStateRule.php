<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Validator\Rule;

use Generated\Shared\Transfer\StateMachineDefinitionValidationErrorTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionValidationResponseTransfer;

class InitialStateRule implements DefinitionValidationRuleInterface
{
    use StateNameCollectorTrait;

    /**
     * @var string
     */
    protected const VALIDATION_ERROR_TYPE_MISSING_INITIAL_STATE = 'missing_initial_state';

    public function validate(
        DefinitionValidationContext $definitionValidationContext,
        StateMachineDefinitionValidationResponseTransfer $stateMachineDefinitionValidationResponseTransfer
    ): void {
        $stateNames = $this->collectStateNames($definitionValidationContext->getRootElement());
        $initialState = $definitionValidationContext->getStateMachineProcessDefinition()->getInitialState();
        if ($initialState !== null && in_array($initialState, $stateNames, true)) {
            return;
        }

        $stateMachineDefinitionValidationResponseTransfer->addError(
            (new StateMachineDefinitionValidationErrorTransfer())
                ->setType(static::VALIDATION_ERROR_TYPE_MISSING_INITIAL_STATE)
                ->setMessage(sprintf('Initial state "%s" does not exist as a defined state.', (string)$initialState))
                ->setStateName($initialState),
        );
    }
}

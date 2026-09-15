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

    protected const string VALIDATION_ERROR_TYPE_MISSING_INITIAL_STATE = 'missing_initial_state';

    protected const string MESSAGE_INITIAL_STATE_MISSING = 'No initial state is specified. Mark one state as the initial state.';

    protected const string MESSAGE_INITIAL_STATE_NOT_DEFINED = 'Initial state "%s" does not exist as a defined state.';

    public function validate(
        DefinitionValidationContext $definitionValidationContext,
        StateMachineDefinitionValidationResponseTransfer $stateMachineDefinitionValidationResponseTransfer
    ): void {
        $stateNames = $this->collectStateNames($definitionValidationContext->getRootElement());
        $initialState = $definitionValidationContext->getStateMachineProcessDefinition()->getInitialState();

        if ($this->addErrorWhenInitialStateMissing($initialState, $stateMachineDefinitionValidationResponseTransfer)) {
            return;
        }

        $this->addErrorWhenInitialStateNotDefined((string)$initialState, $stateNames, $stateMachineDefinitionValidationResponseTransfer);
    }

    protected function addErrorWhenInitialStateMissing(
        ?string $initialState,
        StateMachineDefinitionValidationResponseTransfer $stateMachineDefinitionValidationResponseTransfer
    ): bool {
        if ($initialState !== null && $initialState !== '') {
            return false;
        }

        $stateMachineDefinitionValidationResponseTransfer->addError(
            (new StateMachineDefinitionValidationErrorTransfer())
                ->setType(static::VALIDATION_ERROR_TYPE_MISSING_INITIAL_STATE)
                ->setMessage(static::MESSAGE_INITIAL_STATE_MISSING)
                ->setStateName($initialState),
        );

        return true;
    }

    /**
     * @param array<string> $stateNames
     */
    protected function addErrorWhenInitialStateNotDefined(
        string $initialState,
        array $stateNames,
        StateMachineDefinitionValidationResponseTransfer $stateMachineDefinitionValidationResponseTransfer
    ): void {
        if (in_array($initialState, $stateNames, true)) {
            return;
        }

        $stateMachineDefinitionValidationResponseTransfer->addError(
            (new StateMachineDefinitionValidationErrorTransfer())
                ->setType(static::VALIDATION_ERROR_TYPE_MISSING_INITIAL_STATE)
                ->setMessage(sprintf(static::MESSAGE_INITIAL_STATE_NOT_DEFINED, $initialState))
                ->setStateName($initialState),
        );
    }
}

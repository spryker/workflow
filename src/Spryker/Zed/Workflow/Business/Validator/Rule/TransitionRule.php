<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Validator\Rule;

use Generated\Shared\Transfer\StateMachineDefinitionValidationErrorTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionValidationResponseTransfer;
use SimpleXMLElement;
use Spryker\Zed\Workflow\Business\Registry\PluginRegistryInterface;

class TransitionRule implements DefinitionValidationRuleInterface
{
    use StateNameCollectorTrait;

    /**
     * @var string
     */
    protected const VALIDATION_ERROR_TYPE_UNKNOWN_CONDITION = 'unknown_condition';

    /**
     * @var string
     */
    protected const VALIDATION_ERROR_TYPE_UNDEFINED_TARGET_STATE = 'undefined_target_state';

    /**
     * @var string
     */
    protected const VALIDATION_ERROR_TYPE_UNREACHABLE_STATE = 'unreachable_state';

    public function __construct(protected PluginRegistryInterface $pluginRegistry)
    {
    }

    public function validate(
        DefinitionValidationContext $definitionValidationContext,
        StateMachineDefinitionValidationResponseTransfer $stateMachineDefinitionValidationResponseTransfer
    ): void {
        $stateNames = $this->collectStateNames($definitionValidationContext->getRootElement());
        $reachableStates = [];
        $allowedConditionNames = $this->pluginRegistry->getConditionNamesBySubjectType((string)$definitionValidationContext->getSubjectType());

        foreach ($definitionValidationContext->getRootElement()->children() as $xmlProcess) {
            if (!isset($xmlProcess->transitions)) {
                continue;
            }

            $this->validateProcessTransitions(
                $xmlProcess,
                $stateNames,
                $allowedConditionNames,
                $reachableStates,
                $stateMachineDefinitionValidationResponseTransfer,
            );
        }

        $this->validateUnreachableStates($stateNames, $reachableStates, $stateMachineDefinitionValidationResponseTransfer);
    }

    /**
     * @param array<string> $stateNames
     * @param array<string> $allowedConditionNames
     * @param array<string> $reachableStates
     *
     * @return void
     */
    protected function validateProcessTransitions(
        SimpleXMLElement $xmlProcess,
        array $stateNames,
        array $allowedConditionNames,
        array &$reachableStates,
        StateMachineDefinitionValidationResponseTransfer $stateMachineDefinitionValidationResponseTransfer
    ): void {
        foreach ($xmlProcess->transitions->children() as $xmlTransition) {
            $targetState = (string)$xmlTransition->target;
            $reachableStates[] = $targetState;

            if (!in_array($targetState, $stateNames, true)) {
                $stateMachineDefinitionValidationResponseTransfer->addError(
                    (new StateMachineDefinitionValidationErrorTransfer())
                        ->setType(static::VALIDATION_ERROR_TYPE_UNDEFINED_TARGET_STATE)
                        ->setMessage(sprintf('Transition targets undefined state "%s".', $targetState))
                        ->setStateName($targetState),
                );
            }

            $condition = (string)$xmlTransition->attributes()['condition'];
            if ($condition !== '' && !in_array($condition, $allowedConditionNames, true)) {
                $stateMachineDefinitionValidationResponseTransfer->addError(
                    (new StateMachineDefinitionValidationErrorTransfer())
                        ->setType(static::VALIDATION_ERROR_TYPE_UNKNOWN_CONDITION)
                        ->setMessage(sprintf('Unknown condition "%s".', $condition)),
                );
            }
        }
    }

    /**
     * @param array<string> $stateNames
     * @param array<string> $reachableStates
     *
     * @return void
     */
    protected function validateUnreachableStates(
        array $stateNames,
        array $reachableStates,
        StateMachineDefinitionValidationResponseTransfer $stateMachineDefinitionValidationResponseTransfer
    ): void {
        $entryState = $stateNames[0] ?? null;
        foreach ($stateNames as $stateName) {
            if ($stateName === $entryState || in_array($stateName, $reachableStates, true)) {
                continue;
            }

            $stateMachineDefinitionValidationResponseTransfer->addError(
                (new StateMachineDefinitionValidationErrorTransfer())
                    ->setType(static::VALIDATION_ERROR_TYPE_UNREACHABLE_STATE)
                    ->setMessage(sprintf('State "%s" is unreachable.', $stateName))
                    ->setStateName($stateName),
            );
        }
    }
}

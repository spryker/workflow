<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Validator\Rule;

use Generated\Shared\Transfer\StateMachineDefinitionValidationErrorTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionValidationResponseTransfer;
use SimpleXMLElement;

class RequiredElementsRule implements DefinitionValidationRuleInterface
{
    /**
     * @var string
     */
    protected const VALIDATION_ERROR_TYPE_MISSING_REQUIRED_ELEMENT = 'missing_required_element';

    /**
     * @var string
     */
    protected const ELEMENT_STATES = 'states';

    /**
     * @var string
     */
    protected const ELEMENT_STATE = 'state';

    /**
     * @var string
     */
    protected const ELEMENT_TRANSITIONS = 'transitions';

    /**
     * @var string
     */
    protected const ELEMENT_TRANSITION = 'transition';

    /**
     * @var string
     */
    protected const ELEMENT_EVENTS = 'events';

    /**
     * @var string
     */
    protected const ELEMENT_EVENT = 'event';

    /**
     * A usable state machine needs at least one state, one transition and one event. This rule fails a
     * well-formed definition whose main process is missing any of these sections (or leaves them empty).
     */
    public function validate(
        DefinitionValidationContext $definitionValidationContext,
        StateMachineDefinitionValidationResponseTransfer $stateMachineDefinitionValidationResponseTransfer
    ): void {
        $mainProcessElement = $this->findMainProcessElement($definitionValidationContext->getRootElement());
        if ($mainProcessElement === null) {
            return;
        }

        $requiredChildElementsByContainer = [
            static::ELEMENT_STATES => static::ELEMENT_STATE,
            static::ELEMENT_TRANSITIONS => static::ELEMENT_TRANSITION,
            static::ELEMENT_EVENTS => static::ELEMENT_EVENT,
        ];

        foreach ($requiredChildElementsByContainer as $container => $child) {
            if ($this->hasChildElement($mainProcessElement, $container, $child)) {
                continue;
            }

            $stateMachineDefinitionValidationResponseTransfer->addError(
                (new StateMachineDefinitionValidationErrorTransfer())
                    ->setType(static::VALIDATION_ERROR_TYPE_MISSING_REQUIRED_ELEMENT)
                    ->setMessage(sprintf('The definition must contain at least one <%s>.', $child)),
            );
        }
    }

    protected function findMainProcessElement(SimpleXMLElement $rootElement): ?SimpleXMLElement
    {
        $firstProcessElement = null;
        foreach ($rootElement->children() as $processElement) {
            $firstProcessElement = $firstProcessElement ?? $processElement;

            if ((string)$processElement->attributes()['main'] === 'true') {
                return $processElement;
            }
        }

        return $firstProcessElement;
    }

    protected function hasChildElement(SimpleXMLElement $processElement, string $container, string $child): bool
    {
        return isset($processElement->{$container}) && $processElement->{$container}->{$child}->count() > 0;
    }
}

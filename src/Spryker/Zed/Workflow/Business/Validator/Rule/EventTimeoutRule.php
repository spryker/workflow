<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Validator\Rule;

use DateInterval;
use DateMalformedIntervalStringException;
use DateTimeImmutable;
use Generated\Shared\Transfer\StateMachineDefinitionValidationErrorTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionValidationResponseTransfer;
use SimpleXMLElement;

class EventTimeoutRule implements DefinitionValidationRuleInterface
{
    public const string VALIDATION_ERROR_TYPE_INVALID_TIMEOUT = 'invalid_timeout';

    protected const string EVENT_TIMEOUT_ATTRIBUTE = 'timeout';

    protected const string EVENT_NAME_ATTRIBUTE = 'name';

    protected const string MESSAGE_INVALID_TIMEOUT = 'The timeout "%s" of the event "%s" must be a positive duration as "<number> <unit>", where the unit is second, minute, hour, day, week, month or year, for example "30 seconds" or "14 days".';

    public function validate(
        DefinitionValidationContext $definitionValidationContext,
        StateMachineDefinitionValidationResponseTransfer $stateMachineDefinitionValidationResponseTransfer
    ): void {
        foreach ($definitionValidationContext->getRootElement()->children() as $xmlProcess) {
            if (!isset($xmlProcess->events)) {
                continue;
            }

            foreach ($xmlProcess->events->children() as $xmlEvent) {
                $this->validateEventTimeout($xmlEvent, $stateMachineDefinitionValidationResponseTransfer);
            }
        }
    }

    protected function validateEventTimeout(
        SimpleXMLElement $xmlEvent,
        StateMachineDefinitionValidationResponseTransfer $stateMachineDefinitionValidationResponseTransfer
    ): void {
        $timeoutAttribute = $xmlEvent->attributes()[static::EVENT_TIMEOUT_ATTRIBUTE];

        if ($timeoutAttribute === null || $this->isPositiveDuration((string)$timeoutAttribute)) {
            return;
        }

        $eventName = (string)$xmlEvent->attributes()[static::EVENT_NAME_ATTRIBUTE];

        $stateMachineDefinitionValidationResponseTransfer->addError(
            (new StateMachineDefinitionValidationErrorTransfer())
                ->setType(static::VALIDATION_ERROR_TYPE_INVALID_TIMEOUT)
                ->setMessage(sprintf(static::MESSAGE_INVALID_TIMEOUT, $timeoutAttribute, $eventName))
                ->setEventName($eventName),
        );
    }

    protected function isPositiveDuration(string $timeout): bool
    {
        try {
            $dateInterval = DateInterval::createFromDateString($timeout);
        } catch (DateMalformedIntervalStringException) {
            return false;
        }

        $referenceDateTime = new DateTimeImmutable();

        return $referenceDateTime->add($dateInterval) > $referenceDateTime;
    }
}

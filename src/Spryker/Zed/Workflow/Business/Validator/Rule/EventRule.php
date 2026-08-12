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

class EventRule implements DefinitionValidationRuleInterface
{
    /**
     * @var string
     */
    protected const VALIDATION_ERROR_TYPE_UNKNOWN_COMMAND = 'unknown_command';

    /**
     * @var string
     */
    protected const VALIDATION_ERROR_TYPE_DUPLICATE_EVENT = 'duplicate_event';

    public function __construct(protected PluginRegistryInterface $pluginRegistry)
    {
    }

    public function validate(
        DefinitionValidationContext $definitionValidationContext,
        StateMachineDefinitionValidationResponseTransfer $stateMachineDefinitionValidationResponseTransfer
    ): void {
        $eventNames = [];
        $allowedCommandNames = $this->pluginRegistry->getCommandNamesBySubjectType((string)$definitionValidationContext->getSubjectType());

        foreach ($definitionValidationContext->getRootElement()->children() as $xmlProcess) {
            if (!isset($xmlProcess->events)) {
                continue;
            }

            $this->validateProcessEvents($xmlProcess, $eventNames, $allowedCommandNames, $stateMachineDefinitionValidationResponseTransfer);
        }
    }

    /**
     * @param array<string> $eventNames
     * @param array<string> $allowedCommandNames
     *
     * @return void
     */
    protected function validateProcessEvents(
        SimpleXMLElement $xmlProcess,
        array &$eventNames,
        array $allowedCommandNames,
        StateMachineDefinitionValidationResponseTransfer $stateMachineDefinitionValidationResponseTransfer
    ): void {
        foreach ($xmlProcess->events->children() as $xmlEvent) {
            $eventName = (string)$xmlEvent->attributes()['name'];
            $this->assertUniqueEvent($eventName, $eventNames, $stateMachineDefinitionValidationResponseTransfer);

            $command = (string)$xmlEvent->attributes()['command'];
            if ($command !== '' && !in_array($command, $allowedCommandNames, true)) {
                $stateMachineDefinitionValidationResponseTransfer->addError(
                    (new StateMachineDefinitionValidationErrorTransfer())
                        ->setType(static::VALIDATION_ERROR_TYPE_UNKNOWN_COMMAND)
                        ->setMessage(sprintf('Unknown command "%s".', $command))
                        ->setEventName($eventName),
                );
            }
        }
    }

    /**
     * @param array<string> $eventNames
     *
     * @return void
     */
    protected function assertUniqueEvent(
        string $eventName,
        array &$eventNames,
        StateMachineDefinitionValidationResponseTransfer $stateMachineDefinitionValidationResponseTransfer
    ): void {
        if (in_array($eventName, $eventNames, true)) {
            $stateMachineDefinitionValidationResponseTransfer->addError(
                (new StateMachineDefinitionValidationErrorTransfer())
                    ->setType(static::VALIDATION_ERROR_TYPE_DUPLICATE_EVENT)
                    ->setMessage(sprintf('Duplicate event "%s".', $eventName))
                    ->setEventName($eventName),
            );

            return;
        }

        $eventNames[] = $eventName;
    }
}

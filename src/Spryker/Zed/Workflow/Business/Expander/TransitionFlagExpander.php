<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Expander;

use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use SimpleXMLElement;
use Throwable;

class TransitionFlagExpander implements TransitionFlagExpanderInterface
{
    public function expandWithTransitionFlags(
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
    ): StateMachineProcessDefinitionTransfer {
        $stateMachineProcessDefinitionTransfer
            ->setHasConditionTransitions(false)
            ->setHasTimeoutTransitions(false);

        $rootElement = $this->loadXml($stateMachineProcessDefinitionTransfer->getDefinition());
        if ($rootElement === null) {
            return $stateMachineProcessDefinitionTransfer;
        }

        foreach ($rootElement->children() as $xmlProcess) {
            $this->detectConditionTransitions($xmlProcess, $stateMachineProcessDefinitionTransfer);
            $this->detectTimeoutTransitions($xmlProcess, $stateMachineProcessDefinitionTransfer);
        }

        return $stateMachineProcessDefinitionTransfer;
    }

    protected function detectConditionTransitions(
        SimpleXMLElement $xmlProcess,
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
    ): void {
        if (!isset($xmlProcess->transitions)) {
            return;
        }

        foreach ($xmlProcess->transitions->children() as $xmlTransition) {
            if ((string)$xmlTransition->attributes()['condition'] !== '') {
                $stateMachineProcessDefinitionTransfer->setHasConditionTransitions(true);

                return;
            }
        }
    }

    protected function detectTimeoutTransitions(
        SimpleXMLElement $xmlProcess,
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
    ): void {
        if (!isset($xmlProcess->events)) {
            return;
        }

        foreach ($xmlProcess->events->children() as $xmlEvent) {
            if ((string)$xmlEvent->attributes()['timeout'] !== '') {
                $stateMachineProcessDefinitionTransfer->setHasTimeoutTransitions(true);

                return;
            }
        }
    }

    protected function loadXml(?string $definition): ?SimpleXMLElement
    {
        if ($definition === null || trim($definition) === '') {
            return null;
        }

        try {
            return new SimpleXMLElement($definition);
        } catch (Throwable) {
            return null;
        }
    }
}

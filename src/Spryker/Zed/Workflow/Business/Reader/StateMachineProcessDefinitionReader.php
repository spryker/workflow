<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Reader;

use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use SimpleXMLElement;

class StateMachineProcessDefinitionReader implements StateMachineProcessDefinitionReaderInterface
{
    /**
     * A state is final (end state) when it never appears as the source of any transition.
     *
     * @return array<string>
     */
    public function getStateMachineProcessDefinitionFinalStateNames(
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
    ): array {
        $rootElement = new SimpleXMLElement($stateMachineProcessDefinitionTransfer->getDefinitionOrFail());

        $stateNames = [];
        $sourceStateNames = [];
        foreach ($rootElement->children() as $xmlProcess) {
            foreach ($xmlProcess->states->state as $xmlState) {
                $stateNames[] = (string)$xmlState->attributes()['name'];
            }

            foreach ($xmlProcess->transitions->transition as $xmlTransition) {
                $sourceStateNames[] = (string)$xmlTransition->source;
            }
        }

        return array_values(array_diff(array_unique($stateNames), $sourceStateNames));
    }
}

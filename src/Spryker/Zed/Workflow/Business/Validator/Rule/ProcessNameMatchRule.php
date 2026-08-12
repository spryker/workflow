<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Validator\Rule;

use Generated\Shared\Transfer\StateMachineDefinitionValidationErrorTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionValidationResponseTransfer;

class ProcessNameMatchRule implements DefinitionValidationRuleInterface
{
    /**
     * @var string
     */
    protected const VALIDATION_ERROR_TYPE_PROCESS_NAME_MISMATCH = 'process_name_mismatch';

    /**
     * The authored main <process name="..."> MUST equal the owning process' name. A mismatch corrupts the
     * engine's name-keyed state/instance lookups (two workflows sharing an authored name collide).
     */
    public function validate(
        DefinitionValidationContext $definitionValidationContext,
        StateMachineDefinitionValidationResponseTransfer $stateMachineDefinitionValidationResponseTransfer
    ): void {
        $processName = $definitionValidationContext->getProcessName();
        if ($processName === null) {
            return;
        }

        foreach ($definitionValidationContext->getRootElement()->children() as $xmlProcess) {
            if ((string)$xmlProcess->attributes()['main'] !== 'true') {
                continue;
            }

            $authoredProcessName = (string)$xmlProcess->attributes()['name'];
            if ($authoredProcessName === $processName) {
                continue;
            }

            $stateMachineDefinitionValidationResponseTransfer->addError(
                (new StateMachineDefinitionValidationErrorTransfer())
                    ->setType(static::VALIDATION_ERROR_TYPE_PROCESS_NAME_MISMATCH)
                    ->setMessage(sprintf(
                        'The main process name "%s" in the definition must match the process name "%s".',
                        $authoredProcessName,
                        $processName,
                    )),
            );
        }
    }
}

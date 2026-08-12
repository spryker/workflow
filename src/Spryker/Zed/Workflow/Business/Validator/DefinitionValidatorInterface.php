<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Validator;

use Generated\Shared\Transfer\StateMachineDefinitionValidationResponseTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;

interface DefinitionValidatorInterface
{
    /**
     * Validates a single definition transfer, resolving subject type and process name from its owning
     * process id, and returns its validation response. Validation is identical for every caller.
     */
    public function validate(
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
    ): StateMachineDefinitionValidationResponseTransfer;
}

<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Validator;

use ArrayObject;
use Generated\Shared\Transfer\StateMachineDefinitionValidationResponseTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;

interface DefinitionValidatorInterface
{
    public function validate(
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
    ): StateMachineDefinitionValidationResponseTransfer;

    /**
     * @param \ArrayObject<int, \Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer> $stateMachineProcessDefinitionTransfers
     */
    public function validateCollection(ArrayObject $stateMachineProcessDefinitionTransfers): StateMachineDefinitionValidationResponseTransfer;
}

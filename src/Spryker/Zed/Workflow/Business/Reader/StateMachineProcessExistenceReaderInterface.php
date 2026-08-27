<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Reader;

use ArrayObject;

interface StateMachineProcessExistenceReaderInterface
{
    /**
     * @param \ArrayObject<int, \Generated\Shared\Transfer\StateMachineProcessTransfer> $stateMachineProcessTransfers
     *
     * @return array<int, true>
     */
    public function getExistingStateMachineProcessIds(ArrayObject $stateMachineProcessTransfers): array;

    /**
     * @param \ArrayObject<int, \Generated\Shared\Transfer\StateMachineProcessTransfer> $stateMachineProcessTransfers
     *
     * @return array<string, true>
     */
    public function getTakenStateMachineNames(ArrayObject $stateMachineProcessTransfers): array;
}

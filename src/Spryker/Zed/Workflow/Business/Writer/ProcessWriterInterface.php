<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Writer;

use Generated\Shared\Transfer\StateMachineProcessCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessCollectionResponseTransfer;

interface ProcessWriterInterface
{
    public function createStateMachineProcessCollection(
        StateMachineProcessCollectionRequestTransfer $stateMachineProcessCollectionRequestTransfer
    ): StateMachineProcessCollectionResponseTransfer;

    public function updateStateMachineProcessCollection(
        StateMachineProcessCollectionRequestTransfer $stateMachineProcessCollectionRequestTransfer
    ): StateMachineProcessCollectionResponseTransfer;
}

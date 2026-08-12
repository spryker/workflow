<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Writer;

use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionResponseTransfer;

interface DefinitionWriterInterface
{
    public function createStateMachineProcessDefinitionCollection(
        StateMachineProcessDefinitionCollectionRequestTransfer $stateMachineProcessDefinitionCollectionRequestTransfer
    ): StateMachineProcessDefinitionCollectionResponseTransfer;

    public function updateStateMachineProcessDefinitionCollection(
        StateMachineProcessDefinitionCollectionRequestTransfer $stateMachineProcessDefinitionCollectionRequestTransfer
    ): StateMachineProcessDefinitionCollectionResponseTransfer;

    public function deleteStateMachineProcessDefinitionCollection(
        StateMachineProcessDefinitionCollectionRequestTransfer $stateMachineProcessDefinitionCollectionRequestTransfer
    ): StateMachineProcessDefinitionCollectionResponseTransfer;
}

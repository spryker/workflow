<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Reader;

use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;

interface StateMachineProcessVersionReaderInterface
{
    public function findStateMachineProcessDefinition(int $idStateMachineProcessDefinition): ?StateMachineProcessDefinitionTransfer;

    public function findStateMachineProcessDefinitionOfProcess(
        int $idStateMachineProcessDefinition,
        int $idStateMachineProcess
    ): ?StateMachineProcessDefinitionTransfer;

    public function findActiveStateMachineProcessDefinition(int $idStateMachineProcess): ?StateMachineProcessDefinitionTransfer;
}

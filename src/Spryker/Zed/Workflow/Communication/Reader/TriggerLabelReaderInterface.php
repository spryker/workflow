<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Reader;

interface TriggerLabelReaderInterface
{
    /**
     * @param array<int> $stateMachineProcessIds
     *
     * @return array<int, array<string, string>>
     */
    public function getTriggerLabelsGroupedByIdStateMachineProcess(array $stateMachineProcessIds): array;

    /**
     * @return array<string, \Generated\Shared\Transfer\StateMachineDefinitionTriggerTransfer>
     */
    public function getStateMachineDefinitionTriggersIndexedByEventName(int $idStateMachineProcess): array;
}

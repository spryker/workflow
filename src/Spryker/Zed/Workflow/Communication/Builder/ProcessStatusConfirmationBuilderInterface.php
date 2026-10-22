<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Builder;

use Generated\Shared\Transfer\ActionConfirmationTransfer;

interface ProcessStatusConfirmationBuilderInterface
{
    /**
     * @param array<string, string> $triggerLabelsIndexedByEventName
     */
    public function buildActionConfirmationTransfer(
        string $processName,
        array $triggerLabelsIndexedByEventName,
        bool $isProcessActive,
        bool $hasActiveVersion
    ): ActionConfirmationTransfer;
}

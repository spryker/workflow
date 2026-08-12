<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Checker;

interface TransitionCheckerInterface
{
    /**
     * Discovers every active DB-authored state machine that declares the relevant transition type and
     * runs the engine check for each. Returns the total number of affected items.
     */
    public function check(): int;
}

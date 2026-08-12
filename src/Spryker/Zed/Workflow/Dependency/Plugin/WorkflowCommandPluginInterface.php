<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Dependency\Plugin;

use Spryker\Zed\StateMachine\Dependency\Plugin\CommandPluginInterface;

interface WorkflowCommandPluginInterface extends CommandPluginInterface
{
    /**
     * Specification:
     * - Registry key used in the compiled definition XML (command="...").
     *
     * @api
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Specification:
     * - Subject type this command applies to.
     *
     * @api
     *
     * @return string
     */
    public function getSubjectType(): string;
}

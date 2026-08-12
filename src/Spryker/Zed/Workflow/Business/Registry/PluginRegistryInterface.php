<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Registry;

interface PluginRegistryInterface
{
    /**
     * @return array<\Spryker\Zed\StateMachine\Dependency\Plugin\CommandPluginInterface>
     */
    public function getCommandPluginsBySubjectType(string $subjectType): array;

    /**
     * @return array<\Spryker\Zed\StateMachine\Dependency\Plugin\ConditionPluginInterface>
     */
    public function getConditionPluginsBySubjectType(string $subjectType): array;

    /**
     * @return array<string>
     */
    public function getCommandNamesBySubjectType(string $subjectType): array;

    /**
     * @return array<string>
     */
    public function getConditionNamesBySubjectType(string $subjectType): array;
}

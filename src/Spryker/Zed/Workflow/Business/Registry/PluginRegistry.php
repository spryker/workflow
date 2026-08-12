<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Registry;

class PluginRegistry implements PluginRegistryInterface
{
    /**
     * @param array<\Spryker\Zed\Workflow\Dependency\Plugin\WorkflowCommandPluginInterface> $commandPlugins
     * @param array<\Spryker\Zed\Workflow\Dependency\Plugin\WorkflowConditionPluginInterface> $conditionPlugins
     */
    public function __construct(
        protected array $commandPlugins,
        protected array $conditionPlugins
    ) {
    }

    /**
     * @return array<\Spryker\Zed\StateMachine\Dependency\Plugin\CommandPluginInterface>
     */
    public function getCommandPluginsBySubjectType(string $subjectType): array
    {
        return $this->filterPluginsBySubjectType($this->commandPlugins, $subjectType);
    }

    /**
     * @return array<\Spryker\Zed\StateMachine\Dependency\Plugin\ConditionPluginInterface>
     */
    public function getConditionPluginsBySubjectType(string $subjectType): array
    {
        return $this->filterPluginsBySubjectType($this->conditionPlugins, $subjectType);
    }

    /**
     * @return array<string>
     */
    public function getCommandNamesBySubjectType(string $subjectType): array
    {
        return $this->filterNamesBySubjectType($this->commandPlugins, $subjectType);
    }

    /**
     * @return array<string>
     */
    public function getConditionNamesBySubjectType(string $subjectType): array
    {
        return $this->filterNamesBySubjectType($this->conditionPlugins, $subjectType);
    }

    /**
     * @param array<\Spryker\Zed\Workflow\Dependency\Plugin\WorkflowCommandPluginInterface|\Spryker\Zed\Workflow\Dependency\Plugin\WorkflowConditionPluginInterface> $plugins
     *
     * @return array<string>
     */
    protected function filterNamesBySubjectType(array $plugins, string $subjectType): array
    {
        $names = [];
        foreach ($plugins as $plugin) {
            if ($plugin->getSubjectType() !== $subjectType) {
                continue;
            }

            $names[] = $plugin->getName();
        }

        return $names;
    }

    /**
     * @template TPlugin of \Spryker\Zed\Workflow\Dependency\Plugin\WorkflowCommandPluginInterface|\Spryker\Zed\Workflow\Dependency\Plugin\WorkflowConditionPluginInterface
     *
     * @param array<TPlugin> $plugins
     *
     * @return array<string, TPlugin>
     */
    protected function filterPluginsBySubjectType(array $plugins, string $subjectType): array
    {
        $indexedPlugins = [];
        foreach ($plugins as $plugin) {
            if ($plugin->getSubjectType() !== $subjectType) {
                continue;
            }

            $indexedPlugins[$plugin->getName()] = $plugin;
        }

        return $indexedPlugins;
    }
}

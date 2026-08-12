<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow;

use Orm\Zed\StateMachine\Persistence\SpyStateMachineProcessQuery;
use Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionInstanceQuery;
use Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionQuery;
use Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionTriggerQuery;
use Spryker\Zed\Kernel\AbstractBundleDependencyProvider;
use Spryker\Zed\Kernel\Container;

/**
 * @method \Spryker\Zed\Workflow\WorkflowConfig getConfig()
 */
class WorkflowDependencyProvider extends AbstractBundleDependencyProvider
{
    /**
     * @var string
     */
    public const FACADE_STATE_MACHINE = 'FACADE_STATE_MACHINE';

    /**
     * @var string
     */
    public const PLUGINS_COMMAND = 'PLUGINS_COMMAND';

    /**
     * @var string
     */
    public const PLUGINS_CONDITION = 'PLUGINS_CONDITION';

    /**
     * @var string
     */
    public const PLUGINS_TRIGGER = 'PLUGINS_TRIGGER';

    /**
     * @var string
     */
    public const PROPEL_QUERY_STATE_MACHINE_PROCESS = 'PROPEL_QUERY_STATE_MACHINE_PROCESS';

    /**
     * @var string
     */
    public const PROPEL_QUERY_STATE_MACHINE_PROCESS_DEFINITION = 'PROPEL_QUERY_STATE_MACHINE_PROCESS_DEFINITION';

    /**
     * @var string
     */
    public const PROPEL_QUERY_STATE_MACHINE_PROCESS_DEFINITION_INSTANCE = 'PROPEL_QUERY_STATE_MACHINE_PROCESS_DEFINITION_INSTANCE';

    /**
     * @var string
     */
    public const PROPEL_QUERY_STATE_MACHINE_PROCESS_DEFINITION_TRIGGER = 'PROPEL_QUERY_STATE_MACHINE_PROCESS_DEFINITION_TRIGGER';

    public function provideBusinessLayerDependencies(Container $container): Container
    {
        $container = parent::provideBusinessLayerDependencies($container);

        $container = $this->addStateMachineFacade($container);
        $container = $this->addCommandPlugins($container);
        $container = $this->addConditionPlugins($container);
        $container = $this->addTriggerPlugins($container);

        return $container;
    }

    public function provideCommunicationLayerDependencies(Container $container): Container
    {
        $container = parent::provideCommunicationLayerDependencies($container);

        $container = $this->addStateMachineFacade($container);
        $container = $this->addStateMachineProcessPropelQuery($container);
        $container = $this->addStateMachineProcessDefinitionPropelQuery($container);
        $container = $this->addStateMachineProcessDefinitionInstancePropelQuery($container);
        $container = $this->addStateMachineProcessDefinitionTriggerPropelQuery($container);

        return $container;
    }

    protected function addStateMachineFacade(Container $container): Container
    {
        $container->set(static::FACADE_STATE_MACHINE, static function (Container $container) {
            return $container->getLocator()->stateMachine()->facade();
        });

        return $container;
    }

    protected function addCommandPlugins(Container $container): Container
    {
        $container->set(static::PLUGINS_COMMAND, function () {
            return $this->getCommandPlugins();
        });

        return $container;
    }

    protected function addConditionPlugins(Container $container): Container
    {
        $container->set(static::PLUGINS_CONDITION, function () {
            return $this->getConditionPlugins();
        });

        return $container;
    }

    protected function addTriggerPlugins(Container $container): Container
    {
        $container->set(static::PLUGINS_TRIGGER, function () {
            return $this->getTriggerPlugins();
        });

        return $container;
    }

    protected function addStateMachineProcessPropelQuery(Container $container): Container
    {
        $container->set(static::PROPEL_QUERY_STATE_MACHINE_PROCESS, $container->factory(function (): SpyStateMachineProcessQuery {
            return SpyStateMachineProcessQuery::create();
        }));

        return $container;
    }

    protected function addStateMachineProcessDefinitionPropelQuery(Container $container): Container
    {
        $container->set(static::PROPEL_QUERY_STATE_MACHINE_PROCESS_DEFINITION, $container->factory(function (): SpyStateMachineProcessDefinitionQuery {
            return SpyStateMachineProcessDefinitionQuery::create();
        }));

        return $container;
    }

    protected function addStateMachineProcessDefinitionInstancePropelQuery(Container $container): Container
    {
        $container->set(static::PROPEL_QUERY_STATE_MACHINE_PROCESS_DEFINITION_INSTANCE, $container->factory(function (): SpyStateMachineProcessDefinitionInstanceQuery {
            return SpyStateMachineProcessDefinitionInstanceQuery::create();
        }));

        return $container;
    }

    protected function addStateMachineProcessDefinitionTriggerPropelQuery(Container $container): Container
    {
        $container->set(static::PROPEL_QUERY_STATE_MACHINE_PROCESS_DEFINITION_TRIGGER, $container->factory(function (): SpyStateMachineProcessDefinitionTriggerQuery {
            return SpyStateMachineProcessDefinitionTriggerQuery::create();
        }));

        return $container;
    }

    /**
     * @return array<\Spryker\Zed\Workflow\Dependency\Plugin\WorkflowCommandPluginInterface>
     */
    protected function getCommandPlugins(): array
    {
        return [];
    }

    /**
     * @return array<\Spryker\Zed\Workflow\Dependency\Plugin\WorkflowConditionPluginInterface>
     */
    protected function getConditionPlugins(): array
    {
        return [];
    }

    /**
     * @return array<\Spryker\Zed\Workflow\Dependency\Plugin\StateMachineProcessTriggerPluginInterface>
     */
    protected function getTriggerPlugins(): array
    {
        return [];
    }
}

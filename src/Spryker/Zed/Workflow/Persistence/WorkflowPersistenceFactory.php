<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Persistence;

use Orm\Zed\StateMachine\Persistence\SpyStateMachineItemStateQuery;
use Orm\Zed\StateMachine\Persistence\SpyStateMachineProcessQuery;
use Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionInstanceQuery;
use Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionQuery;
use Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionTriggerQuery;
use Spryker\Zed\Kernel\Persistence\AbstractPersistenceFactory;
use Spryker\Zed\Workflow\Persistence\Propel\Mapper\WorkflowMapper;

/**
 * @method \Spryker\Zed\Workflow\WorkflowConfig getConfig()
 * @method \Spryker\Zed\Workflow\Persistence\WorkflowRepositoryInterface getRepository()
 * @method \Spryker\Zed\Workflow\Persistence\WorkflowEntityManagerInterface getEntityManager()
 */
class WorkflowPersistenceFactory extends AbstractPersistenceFactory
{
    public function createWorkflowMapper(): WorkflowMapper
    {
        return new WorkflowMapper();
    }

    public function getStateMachineProcessQuery(): SpyStateMachineProcessQuery
    {
        return SpyStateMachineProcessQuery::create();
    }

    public function getStateMachineProcessDefinitionQuery(): SpyStateMachineProcessDefinitionQuery
    {
        return SpyStateMachineProcessDefinitionQuery::create();
    }

    public function getStateMachineProcessDefinitionInstanceQuery(): SpyStateMachineProcessDefinitionInstanceQuery
    {
        return SpyStateMachineProcessDefinitionInstanceQuery::create();
    }

    public function getStateMachineProcessDefinitionTriggerQuery(): SpyStateMachineProcessDefinitionTriggerQuery
    {
        return SpyStateMachineProcessDefinitionTriggerQuery::create();
    }

    public function getStateMachineItemStateQuery(): SpyStateMachineItemStateQuery
    {
        return SpyStateMachineItemStateQuery::create();
    }
}

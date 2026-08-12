<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication;

use Orm\Zed\StateMachine\Persistence\SpyStateMachineProcessQuery;
use Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionInstanceQuery;
use Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionQuery;
use Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionTriggerQuery;
use Spryker\Zed\Kernel\Communication\AbstractCommunicationFactory;
use Spryker\Zed\StateMachine\Business\StateMachineFacadeInterface;
use Spryker\Zed\Workflow\Communication\Form\ProcessActivationForm;
use Spryker\Zed\Workflow\Communication\Form\ProcessForm;
use Spryker\Zed\Workflow\Communication\Form\TriggerForm;
use Spryker\Zed\Workflow\Communication\Form\VersionActivationForm;
use Spryker\Zed\Workflow\Communication\Form\VersionDeleteForm;
use Spryker\Zed\Workflow\Communication\Form\VersionForm;
use Spryker\Zed\Workflow\Communication\Table\InstanceTable;
use Spryker\Zed\Workflow\Communication\Table\ProcessTable;
use Spryker\Zed\Workflow\Communication\Table\VersionTable;
use Spryker\Zed\Workflow\Communication\Trigger\Mapper\TriggerMapper;
use Spryker\Zed\Workflow\Communication\Trigger\Mapper\TriggerMapperInterface;
use Spryker\Zed\Workflow\WorkflowDependencyProvider;
use Symfony\Component\Form\FormInterface;

/**
 * @method \Spryker\Zed\Workflow\Business\WorkflowFacadeInterface getFacade()
 * @method \Spryker\Zed\Workflow\WorkflowConfig getConfig()
 * @method \Spryker\Zed\Workflow\Persistence\WorkflowRepositoryInterface getRepository()
 */
class WorkflowCommunicationFactory extends AbstractCommunicationFactory
{
    public function createProcessTable(): ProcessTable
    {
        return new ProcessTable(
            $this->getStateMachineProcessPropelQuery(),
            $this->getStateMachineProcessDefinitionTriggerPropelQuery(),
            $this->getStateMachineProcessDefinitionPropelQuery(),
            $this->getFacade(),
        );
    }

    public function createVersionTable(int $idStateMachineProcess): VersionTable
    {
        return new VersionTable(
            $this->getStateMachineProcessDefinitionPropelQuery(),
            $this->getStateMachineProcessDefinitionInstancePropelQuery(),
            $idStateMachineProcess,
        );
    }

    public function createInstanceTable(int $idStateMachineProcessDefinition): InstanceTable
    {
        return new InstanceTable(
            $this->getStateMachineProcessDefinitionInstancePropelQuery(),
            $this->getStateMachineFacade(),
            $this->getFacade(),
            $idStateMachineProcessDefinition,
        );
    }

    public function getStateMachineProcessPropelQuery(): SpyStateMachineProcessQuery
    {
        return $this->getProvidedDependency(WorkflowDependencyProvider::PROPEL_QUERY_STATE_MACHINE_PROCESS);
    }

    public function getStateMachineProcessDefinitionPropelQuery(): SpyStateMachineProcessDefinitionQuery
    {
        return $this->getProvidedDependency(WorkflowDependencyProvider::PROPEL_QUERY_STATE_MACHINE_PROCESS_DEFINITION);
    }

    public function getStateMachineProcessDefinitionInstancePropelQuery(): SpyStateMachineProcessDefinitionInstanceQuery
    {
        return $this->getProvidedDependency(WorkflowDependencyProvider::PROPEL_QUERY_STATE_MACHINE_PROCESS_DEFINITION_INSTANCE);
    }

    public function getStateMachineProcessDefinitionTriggerPropelQuery(): SpyStateMachineProcessDefinitionTriggerQuery
    {
        return $this->getProvidedDependency(WorkflowDependencyProvider::PROPEL_QUERY_STATE_MACHINE_PROCESS_DEFINITION_TRIGGER);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    public function createProcessForm(array $data = [], array $options = []): FormInterface
    {
        return $this->getFormFactory()->create(ProcessForm::class, $data, $options);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    public function createVersionForm(array $data = [], array $options = []): FormInterface
    {
        return $this->getFormFactory()->create(VersionForm::class, $data, $options);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    public function createTriggerForm(array $data = [], array $options = []): FormInterface
    {
        return $this->getFormFactory()->create(TriggerForm::class, $data, $options);
    }

    public function createTriggerMapper(): TriggerMapperInterface
    {
        return new TriggerMapper();
    }

    public function createProcessActivationForm(): FormInterface
    {
        return $this->getFormFactory()->create(ProcessActivationForm::class);
    }

    public function createVersionActivationForm(): FormInterface
    {
        return $this->getFormFactory()->create(VersionActivationForm::class);
    }

    public function createVersionDeleteForm(): FormInterface
    {
        return $this->getFormFactory()->create(VersionDeleteForm::class);
    }

    public function getStateMachineFacade(): StateMachineFacadeInterface
    {
        return $this->getProvidedDependency(WorkflowDependencyProvider::FACADE_STATE_MACHINE);
    }
}

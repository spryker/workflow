<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication;

use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Orm\Zed\StateMachine\Persistence\SpyStateMachineProcessQuery;
use Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionInstanceQuery;
use Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionQuery;
use Spryker\Zed\Kernel\Communication\AbstractCommunicationFactory;
use Spryker\Zed\StateMachine\Business\StateMachineFacadeInterface;
use Spryker\Zed\User\Business\UserFacadeInterface;
use Spryker\Zed\Workflow\Communication\Builder\ProcessStatusConfirmationBuilder;
use Spryker\Zed\Workflow\Communication\Builder\ProcessStatusConfirmationBuilderInterface;
use Spryker\Zed\Workflow\Communication\Form\DataProvider\ProcessEditFormDataProvider;
use Spryker\Zed\Workflow\Communication\Form\DataProvider\ProcessFormDataProvider;
use Spryker\Zed\Workflow\Communication\Form\InstanceEventTriggerForm;
use Spryker\Zed\Workflow\Communication\Form\ProcessActivationForm;
use Spryker\Zed\Workflow\Communication\Form\ProcessEditForm;
use Spryker\Zed\Workflow\Communication\Form\ProcessForm;
use Spryker\Zed\Workflow\Communication\Form\VersionActivationForm;
use Spryker\Zed\Workflow\Communication\Form\VersionDeleteForm;
use Spryker\Zed\Workflow\Communication\Form\VersionForm;
use Spryker\Zed\Workflow\Communication\Mapper\ProcessFormDataMapper;
use Spryker\Zed\Workflow\Communication\Mapper\ProcessFormDataMapperInterface;
use Spryker\Zed\Workflow\Communication\Reader\AvailablePluginReader;
use Spryker\Zed\Workflow\Communication\Reader\AvailablePluginReaderInterface;
use Spryker\Zed\Workflow\Communication\Reader\StateMachineProcessReader;
use Spryker\Zed\Workflow\Communication\Reader\StateMachineProcessReaderInterface;
use Spryker\Zed\Workflow\Communication\Reader\StateMachineProcessVersionReader;
use Spryker\Zed\Workflow\Communication\Reader\StateMachineProcessVersionReaderInterface;
use Spryker\Zed\Workflow\Communication\Reader\TriggerLabelReader;
use Spryker\Zed\Workflow\Communication\Reader\TriggerLabelReaderInterface;
use Spryker\Zed\Workflow\Communication\Table\InstanceTable;
use Spryker\Zed\Workflow\Communication\Table\ProcessTable;
use Spryker\Zed\Workflow\Communication\Table\VersionTable;
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
            $this->getStateMachineProcessDefinitionPropelQuery(),
            $this->createTriggerLabelReader(),
            $this->createProcessStatusConfirmationBuilder(),
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

    public function createInstanceTable(
        StateMachineProcessTransfer $stateMachineProcessTransfer,
        int $idBackStateMachineProcessDefinition
    ): InstanceTable {
        return new InstanceTable(
            $this->getStateMachineProcessDefinitionInstancePropelQuery(),
            $this->getStateMachineFacade(),
            $stateMachineProcessTransfer,
            $idBackStateMachineProcessDefinition,
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
    public function createProcessEditForm(array $data = [], array $options = []): FormInterface
    {
        return $this->getFormFactory()->create(ProcessEditForm::class, $data, $options);
    }

    public function createProcessFormDataProvider(): ProcessFormDataProvider
    {
        return new ProcessFormDataProvider($this->getFacade());
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    public function createVersionForm(array $data = [], array $options = []): FormInterface
    {
        return $this->getFormFactory()->create(VersionForm::class, $data, $options);
    }

    public function createProcessEditFormDataProvider(): ProcessEditFormDataProvider
    {
        return new ProcessEditFormDataProvider($this->getFacade());
    }

    public function createStateMachineProcessReader(): StateMachineProcessReaderInterface
    {
        return new StateMachineProcessReader($this->getFacade());
    }

    public function createTriggerLabelReader(): TriggerLabelReaderInterface
    {
        return new TriggerLabelReader($this->getFacade());
    }

    public function createAvailablePluginReader(): AvailablePluginReaderInterface
    {
        return new AvailablePluginReader($this->getFacade());
    }

    public function createProcessStatusConfirmationBuilder(): ProcessStatusConfirmationBuilderInterface
    {
        return new ProcessStatusConfirmationBuilder();
    }

    public function createProcessFormDataMapper(): ProcessFormDataMapperInterface
    {
        return new ProcessFormDataMapper($this->getUserFacade());
    }

    public function createStateMachineProcessVersionReader(): StateMachineProcessVersionReaderInterface
    {
        return new StateMachineProcessVersionReader($this->getFacade(), $this->getUserFacade());
    }

    public function createProcessActivationForm(): FormInterface
    {
        return $this->getFormFactory()->create(ProcessActivationForm::class);
    }

    public function createVersionActivationForm(): FormInterface
    {
        return $this->getFormFactory()->create(VersionActivationForm::class);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createInstanceEventTriggerForm(array $options = []): FormInterface
    {
        return $this->getFormFactory()->create(InstanceEventTriggerForm::class, null, $options);
    }

    public function createVersionDeleteForm(): FormInterface
    {
        return $this->getFormFactory()->create(VersionDeleteForm::class);
    }

    public function getStateMachineFacade(): StateMachineFacadeInterface
    {
        return $this->getProvidedDependency(WorkflowDependencyProvider::FACADE_STATE_MACHINE);
    }

    public function getUserFacade(): UserFacadeInterface
    {
        return $this->getProvidedDependency(WorkflowDependencyProvider::FACADE_USER);
    }
}

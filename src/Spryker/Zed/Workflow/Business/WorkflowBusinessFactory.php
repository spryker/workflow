<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business;

use Spryker\Zed\DataImport\Business\DataImportFactoryTrait;
use Spryker\Zed\DataImport\Business\Model\DataImporterInterface;
use Spryker\Zed\DataImport\Business\Model\DataImportStep\DataImportStepInterface;
use Spryker\Zed\DataImport\Business\Model\DataSet\DataSetStepBroker;
use Spryker\Zed\Kernel\Business\AbstractBusinessFactory;
use Spryker\Zed\StateMachine\Business\StateMachineFacadeInterface;
use Spryker\Zed\Workflow\Business\Checker\ConditionChecker;
use Spryker\Zed\Workflow\Business\Checker\TimeoutChecker;
use Spryker\Zed\Workflow\Business\Checker\TransitionCheckerInterface;
use Spryker\Zed\Workflow\Business\DataImport\Step\WorkflowWriterStep;
use Spryker\Zed\Workflow\Business\Expander\TransitionFlagExpander;
use Spryker\Zed\Workflow\Business\Expander\TransitionFlagExpanderInterface;
use Spryker\Zed\Workflow\Business\Reader\StateMachineProcessDefinitionReader;
use Spryker\Zed\Workflow\Business\Reader\StateMachineProcessDefinitionReaderInterface;
use Spryker\Zed\Workflow\Business\Reader\TriggerEventReader;
use Spryker\Zed\Workflow\Business\Reader\TriggerEventReaderInterface;
use Spryker\Zed\Workflow\Business\Registry\PluginRegistry;
use Spryker\Zed\Workflow\Business\Registry\PluginRegistryInterface;
use Spryker\Zed\Workflow\Business\StateMachine\PersistentStateMachineHandler;
use Spryker\Zed\Workflow\Business\Trigger\StateMachineInstanceEventTrigger;
use Spryker\Zed\Workflow\Business\Trigger\StateMachineInstanceEventTriggerInterface;
use Spryker\Zed\Workflow\Business\Trigger\StateMachineInstanceStarter;
use Spryker\Zed\Workflow\Business\Trigger\StateMachineInstanceStarterInterface;
use Spryker\Zed\Workflow\Business\Validator\DefinitionValidator;
use Spryker\Zed\Workflow\Business\Validator\DefinitionValidatorInterface;
use Spryker\Zed\Workflow\Business\Validator\Rule\DefinitionValidationRuleInterface;
use Spryker\Zed\Workflow\Business\Validator\Rule\DuplicateStateRule;
use Spryker\Zed\Workflow\Business\Validator\Rule\EventRule;
use Spryker\Zed\Workflow\Business\Validator\Rule\InitialStateRule;
use Spryker\Zed\Workflow\Business\Validator\Rule\ProcessNameMatchRule;
use Spryker\Zed\Workflow\Business\Validator\Rule\RequiredElementsRule;
use Spryker\Zed\Workflow\Business\Validator\Rule\TransitionRule;
use Spryker\Zed\Workflow\Business\Writer\DefinitionWriter;
use Spryker\Zed\Workflow\Business\Writer\DefinitionWriterInterface;
use Spryker\Zed\Workflow\Business\Writer\ProcessWriter;
use Spryker\Zed\Workflow\Business\Writer\ProcessWriterInterface;
use Spryker\Zed\Workflow\Business\Writer\TriggerWriter;
use Spryker\Zed\Workflow\Business\Writer\TriggerWriterInterface;
use Spryker\Zed\Workflow\WorkflowDependencyProvider;

/**
 * @method \Spryker\Zed\Workflow\WorkflowConfig getConfig()
 * @method \Spryker\Zed\Workflow\Persistence\WorkflowRepositoryInterface getRepository()
 * @method \Spryker\Zed\Workflow\Persistence\WorkflowEntityManagerInterface getEntityManager()
 */
class WorkflowBusinessFactory extends AbstractBusinessFactory
{
    use DataImportFactoryTrait;

    public function createWorkflowDataImporter(): DataImporterInterface
    {
        /** @var \Spryker\Zed\DataImport\Business\Model\DataImporterInterface&\Spryker\Zed\DataImport\Business\Model\DataSet\DataSetStepBrokerAwareInterface $dataImporter */
        $dataImporter = $this->getCsvDataImporterFromConfig(
            $this->getConfig()->getWorkflowDataImporterDataSourceConfiguration(),
        );

        $dataSetStepBroker = new DataSetStepBroker();
        $dataSetStepBroker->addStep($this->createWorkflowWriterStep());

        $dataImporter->addDataSetStepBroker($dataSetStepBroker);

        return $dataImporter;
    }

    public function createWorkflowWriterStep(): DataImportStepInterface
    {
        return new WorkflowWriterStep(
            $this->createProcessWriter(),
            $this->createDefinitionWriter(),
            $this->createTriggerWriter(),
            $this->getRepository(),
        );
    }

    public function createStateMachineProcessDefinitionReader(): StateMachineProcessDefinitionReaderInterface
    {
        return new StateMachineProcessDefinitionReader();
    }

    public function createConditionChecker(): TransitionCheckerInterface
    {
        return new ConditionChecker(
            $this->getRepository(),
            $this->getStateMachineFacade(),
        );
    }

    public function createTimeoutChecker(): TransitionCheckerInterface
    {
        return new TimeoutChecker(
            $this->getRepository(),
            $this->getStateMachineFacade(),
        );
    }

    public function createPluginRegistry(): PluginRegistryInterface
    {
        return new PluginRegistry(
            $this->getCommandPlugins(),
            $this->getConditionPlugins(),
        );
    }

    public function createTriggerEventReader(): TriggerEventReaderInterface
    {
        return new TriggerEventReader(
            $this->getRepository(),
            $this->getTriggerPlugins(),
        );
    }

    public function createPersistentStateMachineHandler(): PersistentStateMachineHandler
    {
        return new PersistentStateMachineHandler(
            $this->createPluginRegistry(),
            $this->getRepository(),
            $this->getEntityManager(),
            $this->createStateMachineProcessDefinitionReader(),
            $this->getConfig(),
        );
    }

    public function createDefinitionValidator(): DefinitionValidatorInterface
    {
        return new DefinitionValidator(
            $this->getRepository(),
            $this->getDefinitionValidationRules(),
        );
    }

    /**
     * @return array<\Spryker\Zed\Workflow\Business\Validator\Rule\DefinitionValidationRuleInterface>
     */
    public function getDefinitionValidationRules(): array
    {
        return [
            $this->createRequiredElementsRule(),
            $this->createDuplicateStateRule(),
            $this->createEventRule(),
            $this->createInitialStateRule(),
            $this->createTransitionRule(),
            $this->createProcessNameMatchRule(),
        ];
    }

    public function createRequiredElementsRule(): DefinitionValidationRuleInterface
    {
        return new RequiredElementsRule();
    }

    public function createDuplicateStateRule(): DefinitionValidationRuleInterface
    {
        return new DuplicateStateRule();
    }

    public function createEventRule(): DefinitionValidationRuleInterface
    {
        return new EventRule($this->createPluginRegistry());
    }

    public function createInitialStateRule(): DefinitionValidationRuleInterface
    {
        return new InitialStateRule();
    }

    public function createTransitionRule(): DefinitionValidationRuleInterface
    {
        return new TransitionRule($this->createPluginRegistry());
    }

    public function createProcessNameMatchRule(): DefinitionValidationRuleInterface
    {
        return new ProcessNameMatchRule();
    }

    public function createProcessWriter(): ProcessWriterInterface
    {
        return new ProcessWriter(
            $this->getEntityManager(),
            $this->getRepository(),
        );
    }

    public function createDefinitionWriter(): DefinitionWriterInterface
    {
        return new DefinitionWriter(
            $this->getEntityManager(),
            $this->getRepository(),
            $this->createDefinitionValidator(),
            $this->createTransitionFlagExpander(),
        );
    }

    public function createTransitionFlagExpander(): TransitionFlagExpanderInterface
    {
        return new TransitionFlagExpander();
    }

    public function createTriggerWriter(): TriggerWriterInterface
    {
        return new TriggerWriter(
            $this->getEntityManager(),
        );
    }

    public function createStateMachineInstanceStarter(): StateMachineInstanceStarterInterface
    {
        return new StateMachineInstanceStarter(
            $this->getRepository(),
            $this->getEntityManager(),
            $this->getStateMachineFacade(),
        );
    }

    public function createStateMachineInstanceEventTrigger(): StateMachineInstanceEventTriggerInterface
    {
        return new StateMachineInstanceEventTrigger(
            $this->getRepository(),
            $this->getStateMachineFacade(),
        );
    }

    public function getStateMachineFacade(): StateMachineFacadeInterface
    {
        return $this->getProvidedDependency(WorkflowDependencyProvider::FACADE_STATE_MACHINE);
    }

    /**
     * @return array<\Spryker\Zed\Workflow\Dependency\Plugin\WorkflowCommandPluginInterface>
     */
    public function getCommandPlugins(): array
    {
        return $this->getProvidedDependency(WorkflowDependencyProvider::PLUGINS_COMMAND);
    }

    /**
     * @return array<\Spryker\Zed\Workflow\Dependency\Plugin\WorkflowConditionPluginInterface>
     */
    public function getConditionPlugins(): array
    {
        return $this->getProvidedDependency(WorkflowDependencyProvider::PLUGINS_CONDITION);
    }

    /**
     * @return array<\Spryker\Zed\Workflow\Dependency\Plugin\StateMachineProcessTriggerPluginInterface>
     */
    public function getTriggerPlugins(): array
    {
        return $this->getProvidedDependency(WorkflowDependencyProvider::PLUGINS_TRIGGER);
    }
}

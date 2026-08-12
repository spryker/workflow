<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Workflow\Helper;

use Codeception\Module;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerTransfer;
use Generated\Shared\Transfer\StateMachineProcessCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Spryker\Zed\Workflow\Business\WorkflowFacadeInterface;
use Spryker\Zed\Workflow\WorkflowConfig;
use SprykerTest\Shared\Testify\Helper\LocatorHelperTrait;

class WorkflowHelper extends Module
{
    use LocatorHelperTrait;

    /**
     * @var string
     */
    public const SUBJECT_TYPE = 'spy_customer';

    /**
     * @var string
     */
    public const INITIAL_STATE = 'new';

    /**
     * A minimal, valid state-machine-01 definition: initial state `new`, one manual transition to
     * a final state `logged`. The `<process name>` must match the owning process name.
     *
     * @var string
     */
    public const VALID_DEFINITION_TEMPLATE = <<<'XML'
<?xml version="1.0"?>
<statemachine xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="http://static.spryker.com/state-machine-01.xsd">
    <process name="%s" main="true">
        <states>
            <state name="new" display="New"/>
            <state name="logged" display="Logged"/>
        </states>
        <transitions>
            <transition>
                <source>new</source>
                <target>logged</target>
                <event>log-it</event>
            </transition>
        </transitions>
        <events>
            <event name="log-it" manual="true"/>
        </events>
    </process>
</statemachine>
XML;

    /**
     * Creates a DB-authored process through the facade (real flow) and returns the persisted transfer.
     */
    public function haveStateMachineProcess(?string $name = null, string $subjectType = self::SUBJECT_TYPE): StateMachineProcessTransfer
    {
        $name = $name ?? $this->generateUniqueName('process');

        $stateMachineProcessCollectionResponseTransfer = $this->getFacade()->createStateMachineProcessCollection(
            (new StateMachineProcessCollectionRequestTransfer())
                ->addStateMachineProcess(
                    (new StateMachineProcessTransfer())
                        ->setStateMachineName($name)
                        ->setProcessName($name)
                        ->setSubjectType($subjectType),
                ),
        );

        return $stateMachineProcessCollectionResponseTransfer->getStateMachineProcesses()->getIterator()->current();
    }

    /**
     * Creates and activates a DB-authored process (status = active) so it participates in condition/timeout
     * discovery and instance starts.
     */
    public function haveActiveStateMachineProcess(?string $name = null, string $subjectType = self::SUBJECT_TYPE): StateMachineProcessTransfer
    {
        $stateMachineProcessTransfer = $this->haveStateMachineProcess($name, $subjectType);
        $this->activateStateMachineProcess($stateMachineProcessTransfer);

        return $stateMachineProcessTransfer;
    }

    /**
     * Creates a definition version for a process through the facade (real flow). The definition XML uses
     * the process name so the compiled `<process name>` matches. Returns the persisted definition transfer.
     */
    public function haveStateMachineProcessDefinition(
        StateMachineProcessTransfer $stateMachineProcessTransfer,
        string $initialState = self::INITIAL_STATE
    ): StateMachineProcessDefinitionTransfer {
        $stateMachineProcessDefinitionCollectionResponseTransfer = $this->getFacade()->createStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCollectionRequestTransfer())
                ->addStateMachineProcessDefinition(
                    (new StateMachineProcessDefinitionTransfer())
                        ->setStateMachineProcess($stateMachineProcessTransfer)
                        ->setInitialState($initialState)
                        ->setDefinition(sprintf(static::VALID_DEFINITION_TEMPLATE, $stateMachineProcessTransfer->getProcessNameOrFail())),
                ),
        );

        return $stateMachineProcessDefinitionCollectionResponseTransfer->getStateMachineProcessDefinitions()->getIterator()->current();
    }

    /**
     * Promotes a process to `active` through the facade.
     */
    public function activateStateMachineProcess(StateMachineProcessTransfer $stateMachineProcessTransfer): void
    {
        $this->getFacade()->updateStateMachineProcessCollection(
            (new StateMachineProcessCollectionRequestTransfer())
                ->addStateMachineProcess(
                    (new StateMachineProcessTransfer())
                        ->setIdStateMachineProcess($stateMachineProcessTransfer->getIdStateMachineProcessOrFail())
                        ->setStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE),
                ),
        );
    }

    public function activateStateMachineProcessDefinition(StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer): void
    {
        $this->getFacade()->updateStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCollectionRequestTransfer())
                ->addStateMachineProcessDefinition(
                    (new StateMachineProcessDefinitionTransfer())
                        ->setIdStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer->getIdStateMachineProcessDefinitionOrFail())
                        ->setStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE),
                )
                ->setIsTransactional(true),
        );
    }

    public function registerStateMachineTrigger(StateMachineProcessTransfer $stateMachineProcessTransfer, string $eventName): void
    {
        $this->getFacade()->updateStateMachineDefinitionTriggerCollection(
            (new StateMachineDefinitionTriggerCollectionRequestTransfer())
                ->addStateMachineDefinitionTriggerToAdd(
                    (new StateMachineDefinitionTriggerTransfer())
                        ->setEventName($eventName)
                        ->setStateMachineProcess($stateMachineProcessTransfer),
                )
                ->setIsTransactional(true),
        );
    }

    public function haveActiveWorkflowWithTrigger(
        string $subjectType,
        string $eventName,
        ?string $name = null,
        ?string $definition = null,
        string $initialState = self::INITIAL_STATE
    ): StateMachineProcessTransfer {
        $uniqueName = $this->generateUniqueName($name ?? 'workflow');

        // Keep the authored `<process name>` in sync with the uniquified process name so the definition validates.
        if ($definition !== null && $name !== null && $name !== '') {
            $definition = str_replace($name, $uniqueName, $definition);
        }

        $stateMachineProcessTransfer = $this->haveActiveStateMachineProcess($uniqueName, $subjectType);

        $definition = $definition ?? sprintf(static::VALID_DEFINITION_TEMPLATE, $stateMachineProcessTransfer->getProcessNameOrFail());
        $stateMachineProcessDefinitionTransfer = $this->getFacade()->createStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCollectionRequestTransfer())
                ->addStateMachineProcessDefinition(
                    (new StateMachineProcessDefinitionTransfer())
                        ->setStateMachineProcess($stateMachineProcessTransfer)
                        ->setInitialState($initialState)
                        ->setDefinition($definition),
                ),
        )->getStateMachineProcessDefinitions()->getIterator()->current();

        $this->activateStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer);
        $this->registerStateMachineTrigger($stateMachineProcessTransfer, $eventName);

        return $stateMachineProcessTransfer;
    }

    public function generateUniqueName(string $prefix): string
    {
        return sprintf('%s_%s', $prefix, uniqid('', true));
    }

    protected function getFacade(): WorkflowFacadeInterface
    {
        return $this->getLocator()->workflow()->facade();
    }
}

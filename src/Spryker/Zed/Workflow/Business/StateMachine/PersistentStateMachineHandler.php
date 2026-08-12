<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\StateMachine;

use Generated\Shared\Transfer\StateMachineItemStateTransfer;
use Generated\Shared\Transfer\StateMachineItemTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Spryker\Zed\StateMachine\Business\Exception\StateMachineException;
use Spryker\Zed\Workflow\Business\Reader\StateMachineProcessDefinitionReaderInterface;
use Spryker\Zed\Workflow\Business\Registry\PluginRegistryInterface;
use Spryker\Zed\Workflow\Persistence\WorkflowEntityManagerInterface;
use Spryker\Zed\Workflow\Persistence\WorkflowRepositoryInterface;
use Spryker\Zed\Workflow\WorkflowConfig;

class PersistentStateMachineHandler
{
    public function __construct(
        protected PluginRegistryInterface $pluginRegistry,
        protected WorkflowRepositoryInterface $workflowRepository,
        protected WorkflowEntityManagerInterface $workflowEntityManager,
        protected StateMachineProcessDefinitionReaderInterface $stateMachineProcessDefinitionReader,
        protected WorkflowConfig $workflowConfig
    ) {
    }

    /**
     * @return array<\Spryker\Zed\StateMachine\Dependency\Plugin\CommandPluginInterface>
     */
    public function getCommandPlugins(StateMachineProcessTransfer $stateMachineProcessTransfer): array
    {
        return $this->pluginRegistry->getCommandPluginsBySubjectType(
            $stateMachineProcessTransfer->getSubjectTypeOrFail(),
        );
    }

    /**
     * @return array<\Spryker\Zed\StateMachine\Dependency\Plugin\ConditionPluginInterface>
     */
    public function getConditionPlugins(StateMachineProcessTransfer $stateMachineProcessTransfer): array
    {
        return $this->pluginRegistry->getConditionPluginsBySubjectType(
            $stateMachineProcessTransfer->getSubjectTypeOrFail(),
        );
    }

    /**
     * @return array<\Generated\Shared\Transfer\StateMachineProcessTransfer>
     */
    public function getProcessesForConditionCheck(StateMachineProcessTransfer $stateMachineProcessTransfer): array
    {
        $stateMachineProcessDefinitionCollectionTransfer = $this->workflowRepository
            ->getStateMachineProcessDefinitionCollection(
                (new StateMachineProcessDefinitionCriteriaTransfer())
                    ->setStateMachineProcessDefinitionConditions(
                        (new StateMachineProcessDefinitionConditionsTransfer())
                            ->addIdStateMachineProcess($stateMachineProcessTransfer->getIdStateMachineProcessOrFail())
                            ->setHasConditionTransitions(true)
                            ->setHasUnfinishedInstances(true),
                    ),
            );

        $stateMachineProcessTransfers = [];

        foreach ($stateMachineProcessDefinitionCollectionTransfer->getStateMachineProcessDefinitions() as $stateMachineProcessDefinitionTransfer) {
            $stateMachineProcessTransfers[] = (new StateMachineProcessTransfer())
                ->setStateMachineName($stateMachineProcessTransfer->getStateMachineNameOrFail())
                ->setProcessName($stateMachineProcessTransfer->getProcessNameOrFail())
                ->setVersion($stateMachineProcessDefinitionTransfer->getVersionOrFail());
        }

        return $stateMachineProcessTransfers;
    }

    public function getInitialStateForPersistentProcess(StateMachineProcessTransfer $stateMachineProcessTransfer): string
    {
        return $this->getDefinitionOrFail($stateMachineProcessTransfer, $stateMachineProcessTransfer->getVersionOrFail())->getInitialStateOrFail();
    }

    public function getDefinitionXmlForPersistentProcess(StateMachineProcessTransfer $stateMachineProcessTransfer): string
    {
        return $this->getDefinitionOrFail($stateMachineProcessTransfer, $stateMachineProcessTransfer->getVersionOrFail())->getDefinitionOrFail();
    }

    public function itemStateUpdated(
        StateMachineProcessTransfer $stateMachineProcessTransfer,
        StateMachineItemTransfer $stateMachineItemTransfer
    ): bool {
        $stateMachineProcessDefinitionTransfer = $this->getDefinitionOrFail(
            $stateMachineProcessTransfer,
            (int)$stateMachineItemTransfer->getVersion(),
        );

        $stateMachineProcessDefinitionInstanceTransfer = (new StateMachineProcessDefinitionInstanceTransfer())
            ->setStateMachineProcessDefinition($stateMachineProcessDefinitionTransfer)
            ->setIdentifier($stateMachineItemTransfer->getIdentifierOrFail())
            ->setStateMachineItemState(
                (new StateMachineItemStateTransfer())->setIdItemState($stateMachineItemTransfer->getIdItemState()),
            )
            ->setFinishedAt($this->resolveFinishedAt($stateMachineProcessDefinitionTransfer, $stateMachineItemTransfer));

        $this->workflowEntityManager->saveStateMachineProcessDefinitionInstance($stateMachineProcessDefinitionInstanceTransfer);

        return true;
    }

    /**
     * @param array<int> $stateIds
     *
     * @return array<\Generated\Shared\Transfer\StateMachineItemTransfer>
     */
    public function getStateMachineItemsForPersistentProcessByStateIds(
        StateMachineProcessTransfer $stateMachineProcessTransfer,
        array $stateIds = []
    ): array {
        if ($stateIds === []) {
            return [];
        }

        $idStateMachineProcessDefinition = $this->getDefinitionOrFail($stateMachineProcessTransfer, $stateMachineProcessTransfer->getVersionOrFail())->getIdStateMachineProcessDefinitionOrFail();

        $stateMachineProcessDefinitionInstanceCollectionTransfer = $this->workflowRepository->getStateMachineProcessDefinitionInstanceCollection(
            (new StateMachineProcessDefinitionInstanceCriteriaTransfer())
                ->setStateMachineProcessDefinitionInstanceConditions(
                    (new StateMachineProcessDefinitionInstanceConditionsTransfer())
                        ->setStateMachineItemStateIds($stateIds)
                        ->addIdStateMachineProcessDefinition($idStateMachineProcessDefinition)
                        ->setWithStateMachineItemState(true)
                        ->setWithStateMachineProcessDefinition(true),
                ),
        );

        $stateMachineItemTransfers = [];
        foreach ($stateMachineProcessDefinitionInstanceCollectionTransfer->getStateMachineProcessDefinitionInstances() as $instanceTransfer) {
            $stateMachineItemStateTransfer = $instanceTransfer->getStateMachineItemStateOrFail();
            $stateMachineItemTransfers[] = (new StateMachineItemTransfer())
                ->setIdentifier($instanceTransfer->getIdentifier())
                ->setStateMachineName($stateMachineProcessTransfer->getStateMachineNameOrFail())
                ->setProcessName($stateMachineProcessTransfer->getProcessNameOrFail())
                ->setIdStateMachineProcess($stateMachineProcessTransfer->getIdStateMachineProcessOrFail())
                ->setStateMachineProcessDefinitionInstance($instanceTransfer)
                ->setVersion($instanceTransfer->getStateMachineProcessDefinitionOrFail()->getVersion())
                ->setIdItemState($stateMachineItemStateTransfer->getIdItemState())
                ->setStateName($stateMachineItemStateTransfer->getName());
        }

        return $stateMachineItemTransfers;
    }

    /**
     * @param array<\Generated\Shared\Transfer\StateMachineItemTransfer> $stateMachineItemTransfers
     *
     * @return array<\Generated\Shared\Transfer\StateMachineItemTransfer>
     */
    public function expandTimeoutItemsWithVersion(array $stateMachineItemTransfers): array
    {
        if ($stateMachineItemTransfers === []) {
            return [];
        }

        $identifiers = [];
        $expandedStateMachineItemTransfers = [];

        foreach ($stateMachineItemTransfers as $stateMachineItemTransfer) {
            $identifiers[] = $stateMachineItemTransfer->getIdentifierOrFail();
        }

        $versionByIdentifier = $this->getInstanceVersionsIndexedByIdentifier($identifiers);

        foreach ($stateMachineItemTransfers as $stateMachineItemTransfer) {
            $version = $versionByIdentifier[$stateMachineItemTransfer->getIdentifierOrFail()] ?? null;
            if ($version === null) {
                continue;
            }

            $expandedStateMachineItemTransfers[] = $stateMachineItemTransfer->setVersion($version);
        }

        return $expandedStateMachineItemTransfers;
    }

    /**
     * @param array<int> $identifiers
     *
     * @return array<int, int> Version keyed by instance identifier.
     */
    protected function getInstanceVersionsIndexedByIdentifier(array $identifiers): array
    {
        $stateMachineProcessDefinitionInstanceCollectionTransfer = $this->workflowRepository
            ->getStateMachineProcessDefinitionInstanceCollection(
                (new StateMachineProcessDefinitionInstanceCriteriaTransfer())
                    ->setStateMachineProcessDefinitionInstanceConditions(
                        (new StateMachineProcessDefinitionInstanceConditionsTransfer())
                            ->setIdentifiers($identifiers)
                            ->setWithStateMachineProcessDefinition(true),
                    ),
            );

        $versionByIdentifier = [];
        foreach ($stateMachineProcessDefinitionInstanceCollectionTransfer->getStateMachineProcessDefinitionInstances() as $instanceTransfer) {
            $versionByIdentifier[$instanceTransfer->getIdentifierOrFail()] = $instanceTransfer
                ->getStateMachineProcessDefinitionOrFail()
                ->getVersionOrFail();
        }

        return $versionByIdentifier;
    }

    /**
     * @throws \Spryker\Zed\StateMachine\Business\Exception\StateMachineException
     */
    protected function getDefinitionOrFail(StateMachineProcessTransfer $stateMachineProcessTransfer, int $version): StateMachineProcessDefinitionTransfer
    {
        $stateMachineProcessDefinitionTransfer = $this->workflowRepository
            ->getStateMachineProcessDefinitionCollection(
                (new StateMachineProcessDefinitionCriteriaTransfer())
                    ->setStateMachineProcessDefinitionConditions(
                        (new StateMachineProcessDefinitionConditionsTransfer())
                            ->addIdStateMachineProcess($stateMachineProcessTransfer->getIdStateMachineProcessOrFail())
                            ->addVersion($version),
                    ),
            )
            ->getStateMachineProcessDefinitions()
            ->getIterator()
            ->current();

        if ($stateMachineProcessDefinitionTransfer === null) {
            throw new StateMachineException(
                sprintf(
                    'No definition found for process "%s" version "%s".',
                    $stateMachineProcessTransfer->getProcessNameOrFail(),
                    (string)$version,
                ),
            );
        }

        return $stateMachineProcessDefinitionTransfer;
    }

    /**
     * Marks the instance finished (timestamp) the moment it enters a final state, so condition/timeout
     * scans can skip it via a plain column filter instead of recomputing final states. Clears back to
     * null if a later transition leaves the final state.
     */
    protected function resolveFinishedAt(
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer,
        StateMachineItemTransfer $stateMachineItemTransfer
    ): ?string {
        $finalStateNames = $this->stateMachineProcessDefinitionReader->getStateMachineProcessDefinitionFinalStateNames($stateMachineProcessDefinitionTransfer);

        if (in_array($stateMachineItemTransfer->getStateNameOrFail(), $finalStateNames, true)) {
            return date('Y-m-d H:i:s');
        }

        return null;
    }
}

<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Persistence;

use ArrayObject;
use Generated\Shared\Transfer\PaginationTransfer;
use Generated\Shared\Transfer\SortTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessCollectionTransfer;
use Generated\Shared\Transfer\StateMachineProcessCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceCollectionTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceCriteriaTransfer;
use Orm\Zed\StateMachine\Persistence\Map\SpyStateMachineProcessTableMap;
use Orm\Zed\Workflow\Persistence\Map\SpyStateMachineProcessDefinitionTableMap;
use Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionInstanceQuery;
use Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Spryker\Zed\Kernel\Persistence\AbstractRepository;
use Spryker\Zed\Workflow\WorkflowConfig;

/**
 * @method \Spryker\Zed\Workflow\Persistence\WorkflowPersistenceFactory getFactory()
 */
class WorkflowRepository extends AbstractRepository implements WorkflowRepositoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @module StateMachine
     */
    public function getStateMachineProcessCollection(
        StateMachineProcessCriteriaTransfer $stateMachineProcessCriteriaTransfer
    ): StateMachineProcessCollectionTransfer {
        $stateMachineProcessCollectionTransfer = new StateMachineProcessCollectionTransfer();

        $stateMachineProcessQuery = $this->getFactory()
            ->getStateMachineProcessQuery()
            ->filterByType(WorkflowConfig::PROCESS_TYPE_DATABASE);

        $stateMachineProcessQuery = $this->applyProcessConditions(
            $stateMachineProcessQuery,
            $stateMachineProcessCriteriaTransfer,
        );

        $sortTransfers = $stateMachineProcessCriteriaTransfer->getSortCollection();
        $this->applySorting($stateMachineProcessQuery, $sortTransfers);
        $this->applyCollectionPagination(
            $stateMachineProcessQuery,
            $stateMachineProcessCriteriaTransfer->getPagination(),
            $stateMachineProcessCollectionTransfer,
        );

        return $this->getFactory()
            ->createWorkflowMapper()
            ->mapProcessEntitiesToStateMachineProcessCollectionTransfer(
                $stateMachineProcessQuery->find(),
                $stateMachineProcessCollectionTransfer,
            );
    }

    /**
     * @module StateMachine
     */
    public function getStateMachineProcessDefinitionCollection(
        StateMachineProcessDefinitionCriteriaTransfer $stateMachineProcessDefinitionCriteriaTransfer
    ): StateMachineProcessDefinitionCollectionTransfer {
        $stateMachineProcessDefinitionCollectionTransfer = new StateMachineProcessDefinitionCollectionTransfer();

        $stateMachineProcessDefinitionQuery = $this->getFactory()
            ->getStateMachineProcessDefinitionQuery()
            ->leftJoinWithStateMachineProcess();
        $stateMachineProcessDefinitionQuery = $this->applyDefinitionConditions(
            $stateMachineProcessDefinitionQuery,
            $stateMachineProcessDefinitionCriteriaTransfer,
        );

        $sortTransfers = $stateMachineProcessDefinitionCriteriaTransfer->getSortCollection();
        $this->applySorting($stateMachineProcessDefinitionQuery, $sortTransfers);
        $this->applyCollectionPagination(
            $stateMachineProcessDefinitionQuery,
            $stateMachineProcessDefinitionCriteriaTransfer->getPagination(),
            $stateMachineProcessDefinitionCollectionTransfer,
        );

        /** @var \Propel\Runtime\Collection\ObjectCollection<\Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinition> $stateMachineProcessDefinitionEntities */
        $stateMachineProcessDefinitionEntities = $stateMachineProcessDefinitionQuery->find();

        return $this->getFactory()
            ->createWorkflowMapper()
            ->mapProcessDefinitionEntitiesToStateMachineProcessDefinitionCollectionTransfer(
                $stateMachineProcessDefinitionEntities,
                $stateMachineProcessDefinitionCollectionTransfer,
            );
    }

    /**
     * @module StateMachine
     */
    public function getStateMachineProcessDefinitionInstanceCollection(
        StateMachineProcessDefinitionInstanceCriteriaTransfer $stateMachineProcessDefinitionInstanceCriteriaTransfer
    ): StateMachineProcessDefinitionInstanceCollectionTransfer {
        $stateMachineProcessDefinitionInstanceCollectionTransfer = new StateMachineProcessDefinitionInstanceCollectionTransfer();

        $stateMachineProcessDefinitionInstanceConditionsTransfer = $stateMachineProcessDefinitionInstanceCriteriaTransfer
            ->getStateMachineProcessDefinitionInstanceConditions()
            ?? new StateMachineProcessDefinitionInstanceConditionsTransfer();

        $stateMachineProcessDefinitionInstanceQuery = $this->getFactory()->getStateMachineProcessDefinitionInstanceQuery();
        $this->applyInstanceRelationJoins(
            $stateMachineProcessDefinitionInstanceQuery,
            $stateMachineProcessDefinitionInstanceConditionsTransfer,
        );
        $stateMachineProcessDefinitionInstanceQuery = $this->applyInstanceConditions(
            $stateMachineProcessDefinitionInstanceQuery,
            $stateMachineProcessDefinitionInstanceCriteriaTransfer,
        );

        $sortTransfers = $stateMachineProcessDefinitionInstanceCriteriaTransfer->getSortCollection();
        $this->applySorting($stateMachineProcessDefinitionInstanceQuery, $sortTransfers);
        $this->applyCollectionPagination(
            $stateMachineProcessDefinitionInstanceQuery,
            $stateMachineProcessDefinitionInstanceCriteriaTransfer->getPagination(),
            $stateMachineProcessDefinitionInstanceCollectionTransfer,
        );

        return $this->getFactory()
            ->createWorkflowMapper()
            ->mapProcessDefinitionInstanceEntitiesToStateMachineProcessDefinitionInstanceCollectionTransfer(
                $stateMachineProcessDefinitionInstanceQuery->find(),
                $stateMachineProcessDefinitionInstanceCollectionTransfer,
                $stateMachineProcessDefinitionInstanceConditionsTransfer,
            );
    }

    public function getStateMachineDefinitionTriggerCollection(
        StateMachineDefinitionTriggerCriteriaTransfer $stateMachineDefinitionTriggerCriteriaTransfer
    ): StateMachineDefinitionTriggerCollectionTransfer {
        $stateMachineDefinitionTriggerCollectionTransfer = new StateMachineDefinitionTriggerCollectionTransfer();

        $stateMachineProcessDefinitionTriggerQuery = $this->getFactory()->getStateMachineProcessDefinitionTriggerQuery();
        $stateMachineProcessDefinitionTriggerQuery = $this->applyTriggerConditions(
            $stateMachineProcessDefinitionTriggerQuery,
            $stateMachineDefinitionTriggerCriteriaTransfer,
        );

        $sortTransfers = $stateMachineDefinitionTriggerCriteriaTransfer->getSortCollection();
        $this->applySorting($stateMachineProcessDefinitionTriggerQuery, $sortTransfers);
        $this->applyCollectionPagination(
            $stateMachineProcessDefinitionTriggerQuery,
            $stateMachineDefinitionTriggerCriteriaTransfer->getPagination(),
            $stateMachineDefinitionTriggerCollectionTransfer,
        );

        return $this->getFactory()
            ->createWorkflowMapper()
            ->mapProcessDefinitionTriggerEntitiesToStateMachineDefinitionTriggerCollectionTransfer(
                $stateMachineProcessDefinitionTriggerQuery->find(),
                $stateMachineDefinitionTriggerCollectionTransfer,
            );
    }

    /**
     * {@inheritDoc}
     */
    public function getDatabaseStateMachineNames(): array
    {
        return $this->getFactory()
            ->getStateMachineProcessQuery()
            ->filterByType(WorkflowConfig::PROCESS_TYPE_DATABASE)
            ->filterByStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE)
            ->select([SpyStateMachineProcessTableMap::COL_STATE_MACHINE_NAME])
            ->distinct()
            ->find()
            ->getData();
    }

    public function findMaxVersionStateMachineProcessDefinition(
        StateMachineProcessCriteriaTransfer $stateMachineProcessCriteriaTransfer
    ): ?int {
        $stateMachineProcessConditionsTransfer = $stateMachineProcessCriteriaTransfer->getStateMachineProcessConditionsOrFail();

        $stateMachineProcessDefinitionCollectionTransfer = $this->getStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCriteriaTransfer())
                ->setStateMachineProcessDefinitionConditions(
                    (new StateMachineProcessDefinitionConditionsTransfer())
                        ->setStateMachineProcessIds($stateMachineProcessConditionsTransfer->getStateMachineProcessIds()),
                )
                ->addSort((new SortTransfer())->setField(SpyStateMachineProcessDefinitionTableMap::COL_VERSION)->setIsAscending(false)),
        );

        $stateMachineProcessDefinitionTransfer = $stateMachineProcessDefinitionCollectionTransfer
            ->getStateMachineProcessDefinitions()
            ->getIterator()
            ->current();

        return $stateMachineProcessDefinitionTransfer !== null ? $stateMachineProcessDefinitionTransfer->getVersion() : null;
    }

    public function hasRunningInstanceForProcessAndIdentifier(int $idStateMachineProcess, int $identifier): bool
    {
        return $this->getFactory()
            ->getStateMachineProcessDefinitionInstanceQuery()
            ->filterByIdentifier($identifier)
            ->useStateMachineProcessDefinitionQuery()
                ->filterByFkStateMachineProcess($idStateMachineProcess)
            ->endUse()
            ->exists();
    }

    public function hasInstancesForProcessDefinition(int $idStateMachineProcessDefinition): bool
    {
        return $this->getFactory()
            ->getStateMachineProcessDefinitionInstanceQuery()
            ->filterByFkStateMachineProcessDefinition($idStateMachineProcessDefinition)
            ->exists();
    }

    /**
     * {@inheritDoc}
     *
     * @module StateMachine
     */
    public function findIdStateMachineItemStateByProcessAndStateName(int $idStateMachineProcess, string $stateName): ?int
    {
        $stateMachineItemStateEntity = $this->getFactory()
            ->getStateMachineItemStateQuery()
            ->filterByName($stateName)
            ->filterByFkStateMachineProcess($idStateMachineProcess)
            ->findOne();

        if ($stateMachineItemStateEntity === null) {
            return null;
        }

        return $stateMachineItemStateEntity->getIdStateMachineItemState();
    }

    protected function applyProcessConditions(
        ModelCriteria $modelCriteria,
        StateMachineProcessCriteriaTransfer $stateMachineProcessCriteriaTransfer
    ): ModelCriteria {
        $stateMachineProcessConditionsTransfer = $stateMachineProcessCriteriaTransfer->getStateMachineProcessConditions();
        if ($stateMachineProcessConditionsTransfer === null) {
            return $modelCriteria;
        }

        if ($stateMachineProcessConditionsTransfer->getStateMachineProcessIds() !== []) {
            $modelCriteria->filterBy('IdStateMachineProcess', $stateMachineProcessConditionsTransfer->getStateMachineProcessIds(), Criteria::IN);
        }

        if ($stateMachineProcessConditionsTransfer->getStateMachineNames() !== []) {
            $modelCriteria->filterBy('StateMachineName', $stateMachineProcessConditionsTransfer->getStateMachineNames(), Criteria::IN);
        }

        if ($stateMachineProcessConditionsTransfer->getSubjectTypes() !== []) {
            $modelCriteria->filterBy('SubjectType', $stateMachineProcessConditionsTransfer->getSubjectTypes(), Criteria::IN);
        }

        if ($stateMachineProcessConditionsTransfer->getStatuses() !== []) {
            $modelCriteria->filterBy('Status', $stateMachineProcessConditionsTransfer->getStatuses(), Criteria::IN);
        }

        return $modelCriteria;
    }

    protected function applyDefinitionConditions(
        SpyStateMachineProcessDefinitionQuery $stateMachineProcessDefinitionQuery,
        StateMachineProcessDefinitionCriteriaTransfer $stateMachineProcessDefinitionCriteriaTransfer
    ): SpyStateMachineProcessDefinitionQuery {
        $stateMachineProcessDefinitionConditionsTransfer = $stateMachineProcessDefinitionCriteriaTransfer->getStateMachineProcessDefinitionConditions();
        if ($stateMachineProcessDefinitionConditionsTransfer === null) {
            return $stateMachineProcessDefinitionQuery;
        }

        if ($stateMachineProcessDefinitionConditionsTransfer->getStateMachineProcessDefinitionIds() !== []) {
            $stateMachineProcessDefinitionQuery->filterBy('IdStateMachineProcessDefinition', $stateMachineProcessDefinitionConditionsTransfer->getStateMachineProcessDefinitionIds(), Criteria::IN);
        }

        if ($stateMachineProcessDefinitionConditionsTransfer->getStateMachineProcessIds() !== []) {
            $stateMachineProcessDefinitionQuery->filterBy('FkStateMachineProcess', $stateMachineProcessDefinitionConditionsTransfer->getStateMachineProcessIds(), Criteria::IN);
        }

        if ($stateMachineProcessDefinitionConditionsTransfer->getStatuses() !== []) {
            $stateMachineProcessDefinitionQuery->filterBy('Status', $stateMachineProcessDefinitionConditionsTransfer->getStatuses(), Criteria::IN);
        }

        if ($stateMachineProcessDefinitionConditionsTransfer->getVersions() !== []) {
            $stateMachineProcessDefinitionQuery->filterBy('Version', $stateMachineProcessDefinitionConditionsTransfer->getVersions(), Criteria::IN);
        }

        return $this->applyDefinitionBooleanConditions($stateMachineProcessDefinitionQuery, $stateMachineProcessDefinitionCriteriaTransfer);
    }

    protected function applyDefinitionBooleanConditions(
        SpyStateMachineProcessDefinitionQuery $stateMachineProcessDefinitionQuery,
        StateMachineProcessDefinitionCriteriaTransfer $stateMachineProcessDefinitionCriteriaTransfer
    ): SpyStateMachineProcessDefinitionQuery {
        $stateMachineProcessDefinitionConditionsTransfer = $stateMachineProcessDefinitionCriteriaTransfer->getStateMachineProcessDefinitionConditions();
        if ($stateMachineProcessDefinitionConditionsTransfer === null) {
            return $stateMachineProcessDefinitionQuery;
        }

        if ($stateMachineProcessDefinitionConditionsTransfer->getHasConditionTransitions() !== null) {
            $stateMachineProcessDefinitionQuery->filterBy('HasConditionTransitions', $stateMachineProcessDefinitionConditionsTransfer->getHasConditionTransitions(), Criteria::EQUAL);
        }

        if ($stateMachineProcessDefinitionConditionsTransfer->getHasTimeoutTransitions() !== null) {
            $stateMachineProcessDefinitionQuery->filterBy('HasTimeoutTransitions', $stateMachineProcessDefinitionConditionsTransfer->getHasTimeoutTransitions(), Criteria::EQUAL);
        }

        if ($stateMachineProcessDefinitionConditionsTransfer->getHasInstances()) {
            $stateMachineProcessDefinitionQuery
                ->useSpyStateMachineProcessDefinitionInstanceExistsQuery()
                ->endUse();
        }

        if ($stateMachineProcessDefinitionConditionsTransfer->getHasUnfinishedInstances()) {
            $stateMachineProcessDefinitionQuery
                ->useSpyStateMachineProcessDefinitionInstanceExistsQuery()
                    ->filterByFinishedAt(null, Criteria::ISNULL)
                ->endUse();
        }

        return $stateMachineProcessDefinitionQuery;
    }

    /**
     * Eager-joins only the relations the caller asked for (with* flags). The process join lives inside the
     * definition join, so it implies the definition is joined too.
     *
     * @module StateMachine
     */
    protected function applyInstanceRelationJoins(
        SpyStateMachineProcessDefinitionInstanceQuery $stateMachineProcessDefinitionInstanceQuery,
        StateMachineProcessDefinitionInstanceConditionsTransfer $stateMachineProcessDefinitionInstanceConditionsTransfer
    ): void {
        if ($stateMachineProcessDefinitionInstanceConditionsTransfer->getWithStateMachineItemState()) {
            $stateMachineProcessDefinitionInstanceQuery->leftJoinWithStateMachineItemState();
        }

        if (!$stateMachineProcessDefinitionInstanceConditionsTransfer->getWithStateMachineProcessDefinition()) {
            return;
        }

        $stateMachineProcessDefinitionInstanceQuery->leftJoinWithStateMachineProcessDefinition();

        if ($stateMachineProcessDefinitionInstanceConditionsTransfer->getWithStateMachineProcess()) {
            $stateMachineProcessDefinitionInstanceQuery
                ->useStateMachineProcessDefinitionQuery()
                    ->leftJoinWithStateMachineProcess()
                ->endUse();
        }
    }

    protected function applyInstanceConditions(
        ModelCriteria $modelCriteria,
        StateMachineProcessDefinitionInstanceCriteriaTransfer $stateMachineProcessDefinitionInstanceCriteriaTransfer
    ): ModelCriteria {
        $conditionsTransfer = $stateMachineProcessDefinitionInstanceCriteriaTransfer->getStateMachineProcessDefinitionInstanceConditions();
        if ($conditionsTransfer === null) {
            return $modelCriteria;
        }

        if ($conditionsTransfer->getStateMachineProcessDefinitionInstanceIds() !== []) {
            $modelCriteria->filterBy('IdStateMachineProcessDefinitionInstance', $conditionsTransfer->getStateMachineProcessDefinitionInstanceIds(), Criteria::IN);
        }

        if ($conditionsTransfer->getStateMachineProcessDefinitionIds() !== []) {
            $modelCriteria->filterBy('FkStateMachineProcessDefinition', $conditionsTransfer->getStateMachineProcessDefinitionIds(), Criteria::IN);
        }

        if ($conditionsTransfer->getIdentifiers() !== []) {
            $modelCriteria->filterBy('Identifier', $conditionsTransfer->getIdentifiers(), Criteria::IN);
        }

        if ($conditionsTransfer->getStateMachineItemStateIds() !== []) {
            $modelCriteria->filterBy('FkStateMachineItemState', $conditionsTransfer->getStateMachineItemStateIds(), Criteria::IN);
        }

        return $modelCriteria;
    }

    protected function applyTriggerConditions(
        ModelCriteria $modelCriteria,
        StateMachineDefinitionTriggerCriteriaTransfer $stateMachineDefinitionTriggerCriteriaTransfer
    ): ModelCriteria {
        $conditionsTransfer = $stateMachineDefinitionTriggerCriteriaTransfer->getStateMachineDefinitionTriggerConditions();
        if ($conditionsTransfer === null) {
            return $modelCriteria;
        }

        if ($conditionsTransfer->getStateMachineProcessIds() !== []) {
            $modelCriteria->filterBy('FkStateMachineProcess', $conditionsTransfer->getStateMachineProcessIds(), Criteria::IN);
        }

        if ($conditionsTransfer->getEventNames() !== []) {
            $modelCriteria->filterBy('EventName', $conditionsTransfer->getEventNames(), Criteria::IN);
        }

        return $modelCriteria;
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\ModelCriteria $modelCriteria
     * @param \ArrayObject<array-key, \Generated\Shared\Transfer\SortTransfer> $sortTransfers
     */
    protected function applySorting(ModelCriteria $modelCriteria, ArrayObject $sortTransfers): ModelCriteria
    {
        foreach ($sortTransfers as $sortTransfer) {
            $modelCriteria->orderBy(
                $sortTransfer->getFieldOrFail(),
                $sortTransfer->getIsAscending() ? Criteria::ASC : Criteria::DESC,
            );
        }

        return $modelCriteria;
    }

    /**
     * @param \Generated\Shared\Transfer\StateMachineProcessCollectionTransfer|\Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionTransfer|\Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceCollectionTransfer|\Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionTransfer $collectionTransfer
     */
    protected function applyCollectionPagination(
        ModelCriteria $modelCriteria,
        ?PaginationTransfer $paginationTransfer,
        object $collectionTransfer
    ): ModelCriteria {
        if ($paginationTransfer === null) {
            return $modelCriteria;
        }

        if ($paginationTransfer->getOffset() !== null && $paginationTransfer->getLimit() !== null) {
            $paginationTransfer->setNbResults($modelCriteria->count());
            $modelCriteria->offset($paginationTransfer->getOffsetOrFail())->setLimit($paginationTransfer->getLimitOrFail());
            $collectionTransfer->setPagination($paginationTransfer);

            return $modelCriteria;
        }

        $collectionTransfer->setPagination($paginationTransfer);

        return $modelCriteria;
    }
}

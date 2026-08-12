<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Table;

use Generated\Shared\Transfer\StateMachineItemTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Orm\Zed\StateMachine\Persistence\Map\SpyStateMachineItemStateTableMap;
use Orm\Zed\Workflow\Persistence\Map\SpyStateMachineProcessDefinitionInstanceTableMap;
use Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionInstanceQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Spryker\Service\UtilText\Model\Url\Url;
use Spryker\Zed\Gui\Communication\Table\AbstractTable;
use Spryker\Zed\Gui\Communication\Table\TableConfiguration;
use Spryker\Zed\StateMachine\Business\StateMachineFacadeInterface;
use Spryker\Zed\Workflow\Business\WorkflowFacadeInterface;

class InstanceTable extends AbstractTable
{
    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\InstanceController::viewAction()
     *
     * @var string
     */
    protected const string URL_VIEW = '/workflow/instance/view';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\InstanceController::triggerAction()
     *
     * @var string
     */
    protected const string URL_TRIGGER = '/workflow/instance/trigger';

    /**
     * @var string
     */
    protected const string COL_VERSION = 'version';

    /**
     * @var string
     */
    protected const string COL_CURRENT_STATE = 'current_state';

    /**
     * @var string
     */
    protected const string COL_STATUS = 'status';

    /**
     * @var string
     */
    protected const string COL_MANUAL_ACTIONS = 'manual_actions';

    /**
     * @var string
     */
    protected const string STATUS_FINISHED = 'Finished';

    /**
     * @var string
     */
    protected const string STATUS_IN_PROGRESS = 'In progress';

    /**
     * Displayed in a cell when the underlying value is absent (no resolved state / no pinned version).
     *
     * @var string
     */
    protected const string PLACEHOLDER_EMPTY_VALUE = '-';

    /**
     * @var string
     */
    protected const string REQUEST_PARAM_ID_DEFINITION = 'id-definition';

    /**
     * @var string
     */
    protected const string REQUEST_PARAM_IDENTIFIER = 'identifier';

    /**
     * @var string
     */
    protected const string REQUEST_PARAM_EVENT = 'event';

    protected ?string $stateMachineName = null;

    protected ?string $processName = null;

    protected ?int $version = null;

    protected bool $isSupportingDataResolved = false;

    public function __construct(
        protected SpyStateMachineProcessDefinitionInstanceQuery $stateMachineProcessDefinitionInstanceQuery,
        protected StateMachineFacadeInterface $stateMachineFacade,
        protected WorkflowFacadeInterface $workflowFacade,
        protected int $idStateMachineProcessDefinition
    ) {
    }

    /**
     * Resolves the version, owning process (name / state machine name) and final-state names for the
     * definition once per request, so the controller does not need to gather them up front.
     */
    protected function resolveSupportingData(): void
    {
        if ($this->isSupportingDataResolved) {
            return;
        }

        $this->isSupportingDataResolved = true;

        $stateMachineProcessDefinitionTransfer = $this->findStateMachineProcessDefinition();
        if ($stateMachineProcessDefinitionTransfer === null) {
            return;
        }

        $this->version = $stateMachineProcessDefinitionTransfer->getVersion();
        $stateMachineProcessTransfer = $stateMachineProcessDefinitionTransfer->getStateMachineProcessOrFail();

        $this->stateMachineName = $stateMachineProcessTransfer->getStateMachineName();
        $this->processName = $stateMachineProcessTransfer->getProcessName();
    }

    protected function findStateMachineProcessDefinition(): ?StateMachineProcessDefinitionTransfer
    {
        return $this->workflowFacade->getStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCriteriaTransfer())
                ->setStateMachineProcessDefinitionConditions(
                    (new StateMachineProcessDefinitionConditionsTransfer())
                        ->addIdStateMachineProcessDefinition($this->idStateMachineProcessDefinition),
                ),
        )->getStateMachineProcessDefinitions()->getIterator()->current() ?: null;
    }

    protected function configure(TableConfiguration $config): TableConfiguration
    {
        $config->setUrl(sprintf('table?%s=%d', static::REQUEST_PARAM_ID_DEFINITION, $this->idStateMachineProcessDefinition));

        $config->setHeader([
            SpyStateMachineProcessDefinitionInstanceTableMap::COL_IDENTIFIER => 'Subject Identifier',
            static::COL_VERSION => 'Version',
            static::COL_CURRENT_STATE => 'Current State',
            static::COL_STATUS => 'Status',
            static::COL_MANUAL_ACTIONS => 'Manual Actions',
        ]);

        $config->setSortable([
            SpyStateMachineProcessDefinitionInstanceTableMap::COL_IDENTIFIER,
            static::COL_CURRENT_STATE,
        ]);

        $config->setRawColumns([
            static::COL_VERSION,
            static::COL_CURRENT_STATE,
            static::COL_STATUS,
            static::COL_MANUAL_ACTIONS,
        ]);

        $config->setSearchable([
            SpyStateMachineProcessDefinitionInstanceTableMap::COL_IDENTIFIER,
            SpyStateMachineItemStateTableMap::COL_NAME,
        ]);

        $config->setDefaultSortField(SpyStateMachineProcessDefinitionInstanceTableMap::COL_IDENTIFIER, TableConfiguration::SORT_ASC);

        return $config;
    }

    /**
     * @module StateMachine
     */
    protected function prepareQuery(): SpyStateMachineProcessDefinitionInstanceQuery
    {
        return $this->stateMachineProcessDefinitionInstanceQuery
            ->filterByFkStateMachineProcessDefinition($this->idStateMachineProcessDefinition)
            ->leftJoinStateMachineItemState()
            ->withColumn(SpyStateMachineItemStateTableMap::COL_NAME, static::COL_CURRENT_STATE)
            ->orderByCreatedAt(Criteria::DESC);
    }

    /**
     * @param \Spryker\Zed\Gui\Communication\Table\TableConfiguration $config
     *
     * @return array<array<string, mixed>>
     */
    protected function prepareData(TableConfiguration $config): array
    {
        $this->resolveSupportingData();

        $results = [];
        foreach ($this->runQuery($this->prepareQuery(), $config) as $item) {
            $identifier = (int)$item[SpyStateMachineProcessDefinitionInstanceTableMap::COL_IDENTIFIER];
            $currentState = $item[static::COL_CURRENT_STATE];
            $isInstanceFinished = $item[SpyStateMachineProcessDefinitionInstanceTableMap::COL_FINISHED_AT] !== null;

            $results[] = [
                SpyStateMachineProcessDefinitionInstanceTableMap::COL_IDENTIFIER => $identifier,
                static::COL_VERSION => $this->createVersionLink($identifier),
                static::COL_CURRENT_STATE => $this->wrapWithTestSelector('workflow-instance-state', $currentState ?? static::PLACEHOLDER_EMPTY_VALUE),
                static::COL_STATUS => $this->wrapWithTestSelector('workflow-instance-status', $this->createStatusLabel($isInstanceFinished)),
                static::COL_MANUAL_ACTIONS => $this->wrapWithTestSelector('workflow-instance-manual-actions', $this->createManualActionButtons($identifier, (string)$currentState)),
            ];
        }

        return $results;
    }

    protected function createVersionLink(int $identifier): string
    {
        $label = $this->version !== null ? (string)$this->version : static::PLACEHOLDER_EMPTY_VALUE;

        return $this->generateViewButton(
            Url::generate(static::URL_VIEW, [
                static::REQUEST_PARAM_ID_DEFINITION => $this->idStateMachineProcessDefinition,
                static::REQUEST_PARAM_IDENTIFIER => $identifier,
            ]),
            $label,
        );
    }

    protected function wrapWithTestSelector(string $testSelector, string $content): string
    {
        return sprintf('<span data-qa="%s">%s</span>', $testSelector, $content);
    }

    protected function createStatusLabel(bool $isFinished): string
    {
        if ($isFinished) {
            return $this->generateLabel(static::STATUS_FINISHED, 'label-info');
        }

        return $this->generateLabel(static::STATUS_IN_PROGRESS, 'label-default');
    }

    protected function createManualActionButtons(int $identifier, string $currentState): string
    {
        $manualEventNames = $this->findManualEventNames($currentState);
        if ($manualEventNames === []) {
            return '-';
        }

        $buttons = [];
        foreach ($manualEventNames as $eventName) {
            $buttons[] = $this->generateButton(
                Url::generate(static::URL_TRIGGER, [
                    static::REQUEST_PARAM_ID_DEFINITION => $this->idStateMachineProcessDefinition,
                    static::REQUEST_PARAM_IDENTIFIER => $identifier,
                    static::REQUEST_PARAM_EVENT => $eventName,
                ]),
                $eventName,
                ['class' => 'btn-create', 'icon' => 'fa-play'],
                ['data-qa' => sprintf('workflow-instance-manual-action %s', $eventName)],
            );
        }

        return implode(' ', $buttons);
    }

    /**
     * @return array<string> Manual events available from the given current state.
     */
    protected function findManualEventNames(string $currentState): array
    {
        if ($this->processName === null || $this->stateMachineName === null || $currentState === '') {
            return [];
        }

        $stateMachineItemTransfer = (new StateMachineItemTransfer())
            ->setStateMachineName($this->stateMachineName)
            ->setProcessName($this->processName)
            ->setStateName($currentState)
            ->setVersion($this->version);

        return $this->stateMachineFacade->getManualEventsForStateMachineItem($stateMachineItemTransfer);
    }
}

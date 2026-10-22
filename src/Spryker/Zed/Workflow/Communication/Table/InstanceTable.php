<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Table;

use Generated\Shared\Transfer\StateMachineItemTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Orm\Zed\StateMachine\Persistence\Map\SpyStateMachineItemStateTableMap;
use Orm\Zed\Workflow\Persistence\Map\SpyStateMachineProcessDefinitionInstanceTableMap;
use Orm\Zed\Workflow\Persistence\Map\SpyStateMachineProcessDefinitionTableMap;
use Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionInstanceQuery;
use Spryker\Service\UtilText\Model\Url\Url;
use Spryker\Zed\Gui\Communication\Table\AbstractTable;
use Spryker\Zed\Gui\Communication\Table\TableConfiguration;
use Spryker\Zed\StateMachine\Business\StateMachineFacadeInterface;
use Spryker\Zed\Workflow\Communication\Form\InstanceEventTriggerForm;

class InstanceTable extends AbstractTable
{
    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\InstanceController::tableAction()
     */
    protected const string URL_TABLE = 'table';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\InstanceController::viewAction()
     */
    protected const string URL_VIEW = '/workflow/instance/view';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\InstanceController::triggerAction()
     */
    protected const string URL_TRIGGER = '/workflow/instance/trigger';

    protected const string COL_SUBJECT_TYPE = 'subject_type';

    protected const string COL_VERSION = 'version';

    protected const string COL_CURRENT_STATE = 'current_state';

    protected const string COL_STATUS = 'status';

    protected const string COL_ACTIONS = 'actions';

    protected const string STATUS_FINISHED = 'Finished';

    protected const string STATUS_IN_PROGRESS = 'In progress';

    protected const string PLACEHOLDER_EMPTY_VALUE = '-';

    protected const string PARAM_ID_PROCESS = 'id-process';

    protected const string PARAM_ID_DEFINITION = 'id-definition';

    protected const string PARAM_IDENTIFIER = 'identifier';

    protected const string PARAM_BACK_ID_DEFINITION = 'back-id-definition';

    protected const string PARAM_EVENT = 'event';

    protected const string PARAM_REDIRECT_TARGET = 'redirect-target';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\InstanceController::REDIRECT_TARGET_VERSION_VIEW
     */
    protected const string REDIRECT_TARGET_VERSION_VIEW = 'version-view';

    /**
     * @var string
     */
    protected $baseUrl = '/workflow/instance';

    /**
     * @var array<int, array<string, array<string>>>
     */
    protected array $manualEventNamesIndexedByVersionAndState = [];

    public function __construct(
        protected SpyStateMachineProcessDefinitionInstanceQuery $stateMachineProcessDefinitionInstanceQuery,
        protected StateMachineFacadeInterface $stateMachineFacade,
        protected StateMachineProcessTransfer $stateMachineProcessTransfer,
        protected int $idBackStateMachineProcessDefinition
    ) {
    }

    protected function configure(TableConfiguration $config): TableConfiguration
    {
        $config->setUrl(Url::generate(static::URL_TABLE, [
            static::PARAM_ID_PROCESS => $this->stateMachineProcessTransfer->getIdStateMachineProcessOrFail(),
            static::PARAM_BACK_ID_DEFINITION => $this->idBackStateMachineProcessDefinition,
        ])->build());

        $config->setHeader([
            SpyStateMachineProcessDefinitionInstanceTableMap::COL_IDENTIFIER => 'Subject Identifier',
            static::COL_SUBJECT_TYPE => 'Subject Type',
            static::COL_VERSION => 'Version',
            static::COL_CURRENT_STATE => 'Current State',
            static::COL_STATUS => 'Status',
            SpyStateMachineProcessDefinitionInstanceTableMap::COL_CREATED_AT => 'Creation Date',
            static::COL_ACTIONS => 'Actions',
        ]);

        $config->setSortable([
            SpyStateMachineProcessDefinitionInstanceTableMap::COL_IDENTIFIER,
            static::COL_VERSION,
            static::COL_CURRENT_STATE,
            SpyStateMachineProcessDefinitionInstanceTableMap::COL_CREATED_AT,
        ]);

        $config->setRawColumns([
            static::COL_CURRENT_STATE,
            static::COL_STATUS,
            static::COL_ACTIONS,
        ]);

        $config->setSearchable([
            SpyStateMachineProcessDefinitionInstanceTableMap::COL_IDENTIFIER,
            SpyStateMachineItemStateTableMap::COL_NAME,
        ]);

        $config->setDefaultSortField(SpyStateMachineProcessDefinitionInstanceTableMap::COL_CREATED_AT, TableConfiguration::SORT_DESC);
        $config->setUseSorting(true);

        return $config;
    }

    /**
     * @module StateMachine
     */
    protected function prepareQuery(): SpyStateMachineProcessDefinitionInstanceQuery
    {
        $stateMachineProcessDefinitionInstanceQuery = $this->stateMachineProcessDefinitionInstanceQuery
            ->leftJoinStateMachineItemState()
            ->withColumn(SpyStateMachineItemStateTableMap::COL_NAME, static::COL_CURRENT_STATE)
            ->withColumn(SpyStateMachineProcessDefinitionTableMap::COL_VERSION, static::COL_VERSION);

        $stateMachineProcessDefinitionInstanceQuery
            ->useStateMachineProcessDefinitionQuery()
                ->filterByFkStateMachineProcess($this->stateMachineProcessTransfer->getIdStateMachineProcessOrFail())
            ->endUse();

        return $stateMachineProcessDefinitionInstanceQuery;
    }

    /**
     * @return array<array<string, mixed>>
     */
    protected function prepareData(TableConfiguration $config): array
    {
        $results = [];
        foreach ($this->runQuery($this->prepareQuery(), $config) as $item) {
            $identifier = (int)$item[SpyStateMachineProcessDefinitionInstanceTableMap::COL_IDENTIFIER];
            $idStateMachineProcessDefinition = (int)$item[SpyStateMachineProcessDefinitionInstanceTableMap::COL_FK_STATE_MACHINE_PROCESS_DEFINITION];
            $version = (int)$item[static::COL_VERSION];
            $currentState = (string)$item[static::COL_CURRENT_STATE];
            $isInstanceFinished = $item[SpyStateMachineProcessDefinitionInstanceTableMap::COL_FINISHED_AT] !== null;

            $results[] = [
                SpyStateMachineProcessDefinitionInstanceTableMap::COL_IDENTIFIER => $identifier,
                static::COL_SUBJECT_TYPE => $this->stateMachineProcessTransfer->getSubjectType() ?? static::PLACEHOLDER_EMPTY_VALUE,
                static::COL_VERSION => $version,
                static::COL_CURRENT_STATE => $this->wrapWithTestSelector('workflow-instance-state', $currentState !== '' ? htmlspecialchars($currentState, ENT_QUOTES) : static::PLACEHOLDER_EMPTY_VALUE),
                static::COL_STATUS => $this->wrapWithTestSelector('workflow-instance-status', $this->createStatusLabel($isInstanceFinished)),
                SpyStateMachineProcessDefinitionInstanceTableMap::COL_CREATED_AT => $item[SpyStateMachineProcessDefinitionInstanceTableMap::COL_CREATED_AT],
                static::COL_ACTIONS => $this->createActionButtons($idStateMachineProcessDefinition, $identifier, $version, $currentState),
            ];
        }

        return $results;
    }

    protected function createActionButtons(int $idStateMachineProcessDefinition, int $identifier, int $version, string $currentState): string
    {
        return implode(' ', [
            $this->createViewButton($idStateMachineProcessDefinition, $identifier),
            ...$this->createManualActionButtons($idStateMachineProcessDefinition, $identifier, $version, $currentState),
        ]);
    }

    protected function createViewButton(int $idStateMachineProcessDefinition, int $identifier): string
    {
        return $this->generateViewButton(
            Url::generate(static::URL_VIEW, [
                static::PARAM_ID_DEFINITION => $idStateMachineProcessDefinition,
                static::PARAM_IDENTIFIER => $identifier,
                static::PARAM_BACK_ID_DEFINITION => $this->idBackStateMachineProcessDefinition,
            ]),
            'View',
            ['data-qa' => 'workflow-instance-view'],
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

    /**
     * @return array<string>
     */
    protected function createManualActionButtons(int $idStateMachineProcessDefinition, int $identifier, int $version, string $currentState): array
    {
        $buttons = [];
        foreach ($this->findManualEventNames($version, $currentState) as $eventName) {
            $buttons[] = $this->generateFormButton(
                Url::generate(static::URL_TRIGGER, [
                    static::PARAM_ID_DEFINITION => $idStateMachineProcessDefinition,
                    static::PARAM_IDENTIFIER => $identifier,
                    static::PARAM_EVENT => $eventName,
                    static::PARAM_BACK_ID_DEFINITION => $this->idBackStateMachineProcessDefinition,
                    static::PARAM_REDIRECT_TARGET => static::REDIRECT_TARGET_VERSION_VIEW,
                ]),
                $eventName,
                InstanceEventTriggerForm::class,
                [
                    static::BUTTON_CLASS => 'btn-create',
                    static::BUTTON_ICON => 'fa-play',
                    'data-qa' => sprintf('workflow-instance-manual-action %s', $eventName),
                ],
            );
        }

        return $buttons;
    }

    /**
     * @return array<string>
     */
    protected function findManualEventNames(int $version, string $currentState): array
    {
        if ($currentState === '') {
            return [];
        }

        if (!isset($this->manualEventNamesIndexedByVersionAndState[$version][$currentState])) {
            $this->manualEventNamesIndexedByVersionAndState[$version][$currentState] = $this->getManualEventNames($version, $currentState);
        }

        return $this->manualEventNamesIndexedByVersionAndState[$version][$currentState];
    }

    /**
     * @return array<string>
     */
    protected function getManualEventNames(int $version, string $currentState): array
    {
        return $this->stateMachineFacade->getManualEventsForStateMachineItem(
            (new StateMachineItemTransfer())
                ->setStateMachineName($this->stateMachineProcessTransfer->getStateMachineNameOrFail())
                ->setProcessName($this->stateMachineProcessTransfer->getProcessNameOrFail())
                ->setStateName($currentState)
                ->setVersion($version),
        );
    }
}

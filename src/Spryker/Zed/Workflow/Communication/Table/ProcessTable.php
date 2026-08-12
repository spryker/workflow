<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Table;

use Generated\Shared\Transfer\StateMachineTriggerEventConditionsTransfer;
use Generated\Shared\Transfer\StateMachineTriggerEventCriteriaTransfer;
use Orm\Zed\StateMachine\Persistence\Map\SpyStateMachineProcessTableMap;
use Orm\Zed\StateMachine\Persistence\SpyStateMachineProcessQuery;
use Orm\Zed\Workflow\Persistence\Map\SpyStateMachineProcessDefinitionTriggerTableMap;
use Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionQuery;
use Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionTriggerQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Spryker\Service\UtilText\Model\Url\Url;
use Spryker\Zed\Gui\Communication\Table\AbstractTable;
use Spryker\Zed\Gui\Communication\Table\TableConfiguration;
use Spryker\Zed\Workflow\Business\WorkflowFacadeInterface;
use Spryker\Zed\Workflow\Communication\Form\ProcessActivationForm;
use Spryker\Zed\Workflow\WorkflowConfig;

class ProcessTable extends AbstractTable
{
    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\VersionController::indexAction()
     *
     * @var string
     */
    protected const string URL_VERSION = '/workflow/version';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\VersionController::viewAction()
     *
     * @var string
     */
    protected const string URL_VERSION_VIEW = '/workflow/version/view';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\TriggerController::indexAction()
     *
     * @var string
     */
    protected const string URL_TRIGGER = '/workflow/trigger';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\ProcessController::activateAction()
     *
     * @var string
     */
    protected const string URL_ACTIVATE = '/workflow/process/activate';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\ProcessController::deactivateAction()
     *
     * @var string
     */
    protected const string URL_DEACTIVATE = '/workflow/process/deactivate';

    /**
     * @var string
     */
    protected const string COL_ACTIVE_VERSION = 'active_version';

    /**
     * @var string
     */
    protected const string COL_TRIGGERS = 'triggers';

    /**
     * @var string
     */
    protected const string COL_ACTIONS = 'actions';

    /**
     * @var string
     */
    protected const string REQUEST_PARAM_ID_PROCESS = 'id-process';

    /**
     * @var string
     */
    protected const string REQUEST_PARAM_ID_DEFINITION = 'id-definition';

    /**
     * @var string
     */
    protected const string REQUEST_PARAM_BACK = 'back';

    /**
     * @var string
     */
    protected const string BACK_ORIGIN_PROCESS = 'process';

    public function __construct(
        protected SpyStateMachineProcessQuery $stateMachineProcessQuery,
        protected SpyStateMachineProcessDefinitionTriggerQuery $stateMachineProcessDefinitionTriggerQuery,
        protected SpyStateMachineProcessDefinitionQuery $stateMachineProcessDefinitionQuery,
        protected WorkflowFacadeInterface $workflowFacade
    ) {
    }

    protected function configure(TableConfiguration $config): TableConfiguration
    {
        $config->setHeader([
            SpyStateMachineProcessTableMap::COL_NAME => 'Workflow',
            SpyStateMachineProcessTableMap::COL_SUBJECT_TYPE => 'Subject Type',
            SpyStateMachineProcessTableMap::COL_STATUS => 'Status',
            static::COL_ACTIVE_VERSION => 'Active Version',
            static::COL_TRIGGERS => 'Triggers',
            static::COL_ACTIONS => 'Actions',
        ]);

        $config->setSortable([
            SpyStateMachineProcessTableMap::COL_NAME,
            SpyStateMachineProcessTableMap::COL_SUBJECT_TYPE,
            SpyStateMachineProcessTableMap::COL_STATUS,
        ]);

        $config->setSearchable([
            SpyStateMachineProcessTableMap::COL_NAME,
            SpyStateMachineProcessTableMap::COL_SUBJECT_TYPE,
            SpyStateMachineProcessTableMap::COL_STATUS,
        ]);

        $config->setRawColumns([
            SpyStateMachineProcessTableMap::COL_STATUS,
            static::COL_ACTIVE_VERSION,
            static::COL_TRIGGERS,
            static::COL_ACTIONS,
        ]);

        $config->setDefaultSortField(SpyStateMachineProcessTableMap::COL_NAME, TableConfiguration::SORT_ASC);

        return $config;
    }

    protected function prepareQuery(): SpyStateMachineProcessQuery
    {
        return $this->stateMachineProcessQuery
            ->filterByType(WorkflowConfig::PROCESS_TYPE_DATABASE)
            ->orderByCreatedAt(Criteria::DESC);
    }

    /**
     * @param \Spryker\Zed\Gui\Communication\Table\TableConfiguration $config
     *
     * @return array<array<string, mixed>>
     */
    protected function prepareData(TableConfiguration $config): array
    {
        $results = [];
        foreach ($this->runQuery($this->prepareQuery(), $config) as $item) {
            $results[] = $this->formatRow($item);
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    protected function formatRow(array $item): array
    {
        $idStateMachineProcess = (int)$item[SpyStateMachineProcessTableMap::COL_ID_STATE_MACHINE_PROCESS];

        return [
            SpyStateMachineProcessTableMap::COL_NAME => $item[SpyStateMachineProcessTableMap::COL_NAME],
            SpyStateMachineProcessTableMap::COL_SUBJECT_TYPE => $item[SpyStateMachineProcessTableMap::COL_SUBJECT_TYPE],
            SpyStateMachineProcessTableMap::COL_STATUS => $this->wrapWithTestSelector('workflow-process-status', $this->createStatusLabel((string)$item[SpyStateMachineProcessTableMap::COL_STATUS])),
            static::COL_ACTIVE_VERSION => $this->wrapWithTestSelector('workflow-process-active-version', $this->findActiveVersion($idStateMachineProcess)),
            static::COL_TRIGGERS => $this->wrapWithTestSelector('workflow-process-triggers', $this->createTriggersLabels($idStateMachineProcess)),
            static::COL_ACTIONS => implode(' ', $this->buildLinks($item)),
        ];
    }

    protected function wrapWithTestSelector(string $testSelector, string $content): string
    {
        return sprintf('<span data-qa="%s">%s</span>', $testSelector, $content);
    }

    protected function createStatusLabel(string $status): string
    {
        $labelClass = $status === WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE ? 'label-info' : 'label-default';

        return $this->generateLabel($status, $labelClass);
    }

    protected function findActiveVersion(int $idStateMachineProcess): string
    {
        $stateMachineProcessDefinitionEntity = (clone $this->stateMachineProcessDefinitionQuery)
            ->filterByFkStateMachineProcess($idStateMachineProcess)
            ->filterByStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE)
            ->findOne();

        if ($stateMachineProcessDefinitionEntity === null) {
            return '-';
        }

        return $this->generateViewButton(
            Url::generate(static::URL_VERSION_VIEW, [
                static::REQUEST_PARAM_ID_DEFINITION => $stateMachineProcessDefinitionEntity->getIdStateMachineProcessDefinition(),
                static::REQUEST_PARAM_BACK => static::BACK_ORIGIN_PROCESS,
            ]),
            (string)$stateMachineProcessDefinitionEntity->getVersion(),
        );
    }

    protected function createTriggersLabels(int $idStateMachineProcess): string
    {
        $eventNames = $this->findAttachedEventNames($idStateMachineProcess);
        if ($eventNames === []) {
            return '-';
        }

        $friendlyNamesByEventName = $this->getFriendlyNamesIndexedByEventName($idStateMachineProcess);

        $labels = [];
        foreach ($eventNames as $eventName) {
            $label = $friendlyNamesByEventName[$eventName] ?? $eventName;
            $labels[] = sprintf(
                '<span title="%s">%s</span>',
                htmlspecialchars($eventName, ENT_QUOTES),
                $this->generateLabel($label, 'label-default'),
            );
        }

        return implode(' ', $labels);
    }

    /**
     * @return array<string, string> Friendly name keyed by technical event name.
     */
    protected function getFriendlyNamesIndexedByEventName(int $idStateMachineProcess): array
    {
        $stateMachineTriggerEventCollectionTransfer = $this->workflowFacade->getStateMachineTriggerEventCollection(
            (new StateMachineTriggerEventCriteriaTransfer())
                ->setStateMachineTriggerEventConditions(
                    (new StateMachineTriggerEventConditionsTransfer())->addIdStateMachineProcess($idStateMachineProcess),
                ),
        );

        $friendlyNamesIndexedByEventName = [];
        foreach ($stateMachineTriggerEventCollectionTransfer->getStateMachineTriggerEvents() as $stateMachineTriggerEventTransfer) {
            $name = (string)$stateMachineTriggerEventTransfer->getName();

            if ($name === '') {
                continue;
            }

            $friendlyNamesIndexedByEventName[$stateMachineTriggerEventTransfer->getEventNameOrFail()] = $name;
        }

        return $friendlyNamesIndexedByEventName;
    }

    /**
     * @return array<string>
     */
    protected function findAttachedEventNames(int $idStateMachineProcess): array
    {
        $eventNamesCollection = (clone $this->stateMachineProcessDefinitionTriggerQuery)
            ->filterByFkStateMachineProcess($idStateMachineProcess)
            ->select([SpyStateMachineProcessDefinitionTriggerTableMap::COL_EVENT_NAME])
            ->find();

        $eventNames = [];
        foreach ($eventNamesCollection as $eventName) {
            $eventNames[] = (string)$eventName;
        }

        return $eventNames;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string>
     */
    protected function buildLinks(array $item): array
    {
        $idStateMachineProcess = $item[SpyStateMachineProcessTableMap::COL_ID_STATE_MACHINE_PROCESS];

        $buttons = [];
        $buttons[] = $this->generateViewButton(
            Url::generate(static::URL_VERSION, [static::REQUEST_PARAM_ID_PROCESS => $idStateMachineProcess]),
            'Versions',
        );
        $buttons[] = $this->generateViewButton(
            Url::generate(static::URL_TRIGGER, [static::REQUEST_PARAM_ID_PROCESS => $idStateMachineProcess]),
            'Triggers',
        );

        if ($item[SpyStateMachineProcessTableMap::COL_STATUS] === WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE) {
            $buttons[] = $this->generateFormButton(
                Url::generate(static::URL_DEACTIVATE, [static::REQUEST_PARAM_ID_PROCESS => $idStateMachineProcess]),
                'Deactivate',
                ProcessActivationForm::class,
                [static::BUTTON_CLASS => 'btn-danger'],
            );

            return $buttons;
        }

        $buttons[] = $this->generateFormButton(
            Url::generate(static::URL_ACTIVATE, [static::REQUEST_PARAM_ID_PROCESS => $idStateMachineProcess]),
            'Activate',
            ProcessActivationForm::class,
            [static::BUTTON_CLASS => 'btn-create'],
        );

        return $buttons;
    }
}

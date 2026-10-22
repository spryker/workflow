<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Table;

use Orm\Zed\StateMachine\Persistence\Map\SpyStateMachineProcessTableMap;
use Orm\Zed\StateMachine\Persistence\SpyStateMachineProcessQuery;
use Orm\Zed\Workflow\Persistence\Map\SpyStateMachineProcessDefinitionTableMap;
use Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Spryker\Service\UtilText\Model\Url\Url;
use Spryker\Zed\Gui\Communication\Table\AbstractTable;
use Spryker\Zed\Gui\Communication\Table\TableConfiguration;
use Spryker\Zed\Workflow\Communication\Builder\ProcessStatusConfirmationBuilderInterface;
use Spryker\Zed\Workflow\Communication\Controller\VersionController;
use Spryker\Zed\Workflow\Communication\Form\ProcessActivationForm;
use Spryker\Zed\Workflow\Communication\Reader\TriggerLabelReaderInterface;
use Spryker\Zed\Workflow\WorkflowConfig;

class ProcessTable extends AbstractTable
{
    protected const string BUTTON_TITLE_ACTIVATE = 'Activate';

    protected const string BUTTON_TITLE_DEACTIVATE = 'Deactivate';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\VersionController::viewAction()
     *
     * @var string
     */
    protected const string URL_VERSION_VIEW = '/workflow/version/view';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\VersionController::createAction()
     *
     * @var string
     */
    protected const string URL_VERSION_EDIT = '/workflow/version/create';

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

    protected const string VERSION_LABEL_PREFIX = 'V';

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
    protected const string PARAM_ID_PROCESS = 'id-process';

    /**
     * @var string
     */
    protected const string PARAM_ID_DEFINITION = 'id-definition';

    protected const int DESCRIPTION_MAX_LENGTH = 100;

    /**
     * @var string
     */
    protected const string DESCRIPTION_TRUNCATION_SUFFIX = '...';

    /**
     * @var array<int, array<array<string, mixed>>>
     */
    protected array $definitionsGroupedByIdStateMachineProcess = [];

    /**
     * @var array<int, array<string, string>>
     */
    protected array $triggerLabelsGroupedByIdStateMachineProcess = [];

    public function __construct(
        protected SpyStateMachineProcessQuery $stateMachineProcessQuery,
        protected SpyStateMachineProcessDefinitionQuery $stateMachineProcessDefinitionQuery,
        protected TriggerLabelReaderInterface $triggerLabelReader,
        protected ProcessStatusConfirmationBuilderInterface $processStatusConfirmationBuilder
    ) {
    }

    protected function configure(TableConfiguration $config): TableConfiguration
    {
        $config->setHeader([
            SpyStateMachineProcessTableMap::COL_NAME => 'Workflow',
            SpyStateMachineProcessTableMap::COL_SUBJECT_TYPE => 'Subject Type',
            SpyStateMachineProcessTableMap::COL_DESCRIPTION => 'Description',
            SpyStateMachineProcessTableMap::COL_STATUS => 'Status',
            static::COL_ACTIVE_VERSION => 'Active version',
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
            SpyStateMachineProcessTableMap::COL_DESCRIPTION,
            SpyStateMachineProcessTableMap::COL_STATUS,
        ]);

        $config->setRawColumns([
            SpyStateMachineProcessTableMap::COL_DESCRIPTION,
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
        $items = $this->runQuery($this->prepareQuery(), $config);

        $stateMachineProcessIds = [];
        foreach ($items as $item) {
            $stateMachineProcessIds[] = (int)$item[SpyStateMachineProcessTableMap::COL_ID_STATE_MACHINE_PROCESS];
        }

        $this->loadPageRelations($stateMachineProcessIds);

        $results = [];
        foreach ($items as $item) {
            $results[] = $this->formatRow($item);
        }

        return $results;
    }

    /**
     * @param array<int> $stateMachineProcessIds
     */
    protected function loadPageRelations(array $stateMachineProcessIds): void
    {
        $this->definitionsGroupedByIdStateMachineProcess = $this->getDefinitionsGroupedByIdStateMachineProcess($stateMachineProcessIds);
        $this->triggerLabelsGroupedByIdStateMachineProcess = $this->triggerLabelReader->getTriggerLabelsGroupedByIdStateMachineProcess($stateMachineProcessIds);
    }

    /**
     * @param array<int> $stateMachineProcessIds
     *
     * @return array<int, array<array<string, mixed>>>
     */
    protected function getDefinitionsGroupedByIdStateMachineProcess(array $stateMachineProcessIds): array
    {
        if ($stateMachineProcessIds === []) {
            return [];
        }

        $definitions = (clone $this->stateMachineProcessDefinitionQuery)
            ->filterByFkStateMachineProcess_In($stateMachineProcessIds)
            ->orderByVersion(Criteria::DESC)
            ->select([
                SpyStateMachineProcessDefinitionTableMap::COL_ID_STATE_MACHINE_PROCESS_DEFINITION,
                SpyStateMachineProcessDefinitionTableMap::COL_FK_STATE_MACHINE_PROCESS,
                SpyStateMachineProcessDefinitionTableMap::COL_VERSION,
                SpyStateMachineProcessDefinitionTableMap::COL_STATUS,
            ])
            ->find();

        $definitionsGroupedByIdStateMachineProcess = [];
        foreach ($definitions as $definition) {
            $definitionsGroupedByIdStateMachineProcess[(int)$definition[SpyStateMachineProcessDefinitionTableMap::COL_FK_STATE_MACHINE_PROCESS]][] = $definition;
        }

        return $definitionsGroupedByIdStateMachineProcess;
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
            SpyStateMachineProcessTableMap::COL_DESCRIPTION => $this->wrapWithTestSelector('workflow-process-description', $this->createDescription((string)$item[SpyStateMachineProcessTableMap::COL_DESCRIPTION])),
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

    protected function createDescription(string $description): string
    {
        if ($description === '') {
            return '-';
        }

        $truncatedDescription = $description;

        if (mb_strlen($description) > static::DESCRIPTION_MAX_LENGTH) {
            $truncatedDescription = rtrim(mb_substr($description, 0, static::DESCRIPTION_MAX_LENGTH)) . static::DESCRIPTION_TRUNCATION_SUFFIX;
        }

        return sprintf(
            '<span title="%s">%s</span>',
            htmlspecialchars($description, ENT_QUOTES),
            htmlspecialchars($truncatedDescription, ENT_QUOTES),
        );
    }

    protected function createStatusLabel(string $status): string
    {
        $labelClass = $status === WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE ? 'label-info' : 'label-default';

        return $this->generateLabel($status, $labelClass);
    }

    protected function findActiveVersion(int $idStateMachineProcess): string
    {
        $activeDefinition = $this->findActiveDefinition($idStateMachineProcess);

        if ($activeDefinition === null) {
            return '-';
        }

        return static::VERSION_LABEL_PREFIX . $activeDefinition[SpyStateMachineProcessDefinitionTableMap::COL_VERSION];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function findActiveDefinition(int $idStateMachineProcess): ?array
    {
        foreach ($this->definitionsGroupedByIdStateMachineProcess[$idStateMachineProcess] ?? [] as $definition) {
            if ($definition[SpyStateMachineProcessDefinitionTableMap::COL_STATUS] === WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function findDefaultDefinition(int $idStateMachineProcess): ?array
    {
        return $this->findActiveDefinition($idStateMachineProcess)
            ?? $this->definitionsGroupedByIdStateMachineProcess[$idStateMachineProcess][0]
            ?? null;
    }

    /**
     * @return array<string>
     */
    protected function buildDefinitionLinks(int $idStateMachineProcess): array
    {
        $defaultDefinition = $this->findDefaultDefinition($idStateMachineProcess);

        if ($defaultDefinition === null) {
            return [];
        }

        $idStateMachineProcessDefinition = (int)$defaultDefinition[SpyStateMachineProcessDefinitionTableMap::COL_ID_STATE_MACHINE_PROCESS_DEFINITION];

        return [
            $this->generateViewButton(
                Url::generate(static::URL_VERSION_VIEW, [
                    static::PARAM_ID_DEFINITION => $idStateMachineProcessDefinition,
                ]),
                'View',
            ),

            $this->generateEditButton(
                Url::generate(static::URL_VERSION_EDIT, [
                    static::PARAM_ID_PROCESS => $idStateMachineProcess,
                    static::PARAM_ID_DEFINITION => $idStateMachineProcessDefinition,
                    VersionController::PARAM_BACK => VersionController::BACK_ORIGIN_WORKFLOW_LIST,
                ]),
                'New version',
            ),
        ];
    }

    protected function createTriggersLabels(int $idStateMachineProcess): string
    {
        $triggerLabelsIndexedByEventName = $this->triggerLabelsGroupedByIdStateMachineProcess[$idStateMachineProcess] ?? [];
        if ($triggerLabelsIndexedByEventName === []) {
            return '-';
        }

        $labels = [];
        foreach ($triggerLabelsIndexedByEventName as $eventName => $label) {
            $labels[] = sprintf(
                '<span title="%s">%s</span>',
                htmlspecialchars($eventName, ENT_QUOTES),
                $this->generateLabel($label, 'label-default'),
            );
        }

        return implode(' ', $labels);
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string>
     */
    protected function buildLinks(array $item): array
    {
        $idStateMachineProcess = (int)$item[SpyStateMachineProcessTableMap::COL_ID_STATE_MACHINE_PROCESS];
        $isProcessActive = $item[SpyStateMachineProcessTableMap::COL_STATUS] === WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE;

        $buttons = $this->buildDefinitionLinks($idStateMachineProcess);
        $buttons[] = $this->generateFormButton(
            Url::generate($isProcessActive ? static::URL_DEACTIVATE : static::URL_ACTIVATE, [static::PARAM_ID_PROCESS => $idStateMachineProcess]),
            $isProcessActive ? static::BUTTON_TITLE_DEACTIVATE : static::BUTTON_TITLE_ACTIVATE,
            ProcessActivationForm::class,
            [static::BUTTON_CLASS => $isProcessActive ? 'btn-danger' : 'btn-create'] + $this->buildProcessStatusConfirmationAttributes(
                $idStateMachineProcess,
                (string)$item[SpyStateMachineProcessTableMap::COL_NAME],
                $isProcessActive,
            ),
        );

        return $buttons;
    }

    /**
     * @return array<string, string>
     */
    protected function buildProcessStatusConfirmationAttributes(int $idStateMachineProcess, string $processName, bool $isProcessActive): array
    {
        $actionConfirmationTransfer = $this->processStatusConfirmationBuilder->buildActionConfirmationTransfer(
            $processName,
            $this->triggerLabelsGroupedByIdStateMachineProcess[$idStateMachineProcess] ?? [],
            $isProcessActive,
            $this->findActiveDefinition($idStateMachineProcess) !== null,
        );

        return $this->buildConfirmationAttributes($actionConfirmationTransfer);
    }
}

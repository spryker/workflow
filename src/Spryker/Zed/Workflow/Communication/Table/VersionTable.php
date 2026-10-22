<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Table;

use DateTime;
use Generated\Shared\Transfer\ActionConfirmationTransfer;
use Orm\Zed\StateMachine\Persistence\Map\SpyStateMachineProcessTableMap;
use Orm\Zed\User\Persistence\Map\SpyUserTableMap;
use Orm\Zed\Workflow\Persistence\Map\SpyStateMachineProcessDefinitionInstanceTableMap;
use Orm\Zed\Workflow\Persistence\Map\SpyStateMachineProcessDefinitionTableMap;
use Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionInstanceQuery;
use Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Spryker\Service\UtilText\Model\Url\Url;
use Spryker\Zed\Gui\Communication\Table\AbstractTable;
use Spryker\Zed\Gui\Communication\Table\TableConfiguration;
use Spryker\Zed\Workflow\Communication\Controller\VersionController;
use Spryker\Zed\Workflow\Communication\Form\VersionActivationForm;
use Spryker\Zed\Workflow\Communication\Form\VersionDeleteForm;
use Spryker\Zed\Workflow\WorkflowConfig;

class VersionTable extends AbstractTable
{
    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\VersionController::viewAction()
     *
     * @var string
     */
    protected const string URL_VIEW = '/workflow/version/view';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\VersionController::createAction()
     *
     * @var string
     */
    protected const string URL_EDIT = '/workflow/version/create';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\VersionStatusController::activateAction()
     *
     * @var string
     */
    protected const string URL_ACTIVATE = '/workflow/version-status/activate';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\VersionController::deleteAction()
     *
     * @var string
     */
    protected const string URL_DELETE = '/workflow/version/delete';

    /**
     * @var string
     */
    protected const string COL_ACTIONS = 'actions';

    protected const string COL_AUTHOR = 'author';

    protected const string COL_AUTHOR_FIRST_NAME = 'author_first_name';

    protected const string COL_AUTHOR_LAST_NAME = 'author_last_name';

    protected const string COL_HAS_INSTANCES = 'has_instances';

    protected const string COL_PROCESS_NAME = 'process_name';

    protected const string VERSION_LABEL_PREFIX = 'V';

    protected const string EMPTY_VALUE = '—';

    protected const string DATE_FORMAT = 'Y-m-d H:i:s';

    protected const string ACTIVATE_CONFIRMATION_TITLE = 'Make version %version% the active version?';

    protected const string ACTIVATE_CONFIRMATION_MESSAGE = 'Version %version% replaces version %activeVersion% as the active version of %name%';

    protected const string ACTIVATE_CONFIRMATION_MESSAGE_WITHOUT_ACTIVE_VERSION = 'Version %version% becomes the active version of %name%';

    protected const string ACTIVATE_CONFIRMATION_BUTTON = 'Activate';

    protected const string ACTIVATE_DISABLED_TOOLTIP = 'This is already the active version';

    protected const string DELETE_DISABLED_ACTIVE_VERSION_TOOLTIP = 'The active version cannot be deleted.';

    protected const string DELETE_DISABLED_WITH_INSTANCES_TOOLTIP = 'Versions containing instances cannot be deleted.';

    protected const string TRANSLATION_PARAMETER_VERSION = '%version%';

    protected const string TRANSLATION_PARAMETER_ACTIVE_VERSION = '%activeVersion%';

    protected const string TRANSLATION_PARAMETER_NAME = '%name%';

    protected const string DELETE_CONFIRMATION_TITLE = 'Delete version';

    protected const string DELETE_CONFIRMATION_MESSAGE = 'This permanently deletes this version. It has no instances, so nothing running is affected. Delete it?';

    protected const string DELETE_CONFIRMATION_BUTTON = 'Delete';

    /**
     * @var string
     */
    protected const string PARAM_ID_PROCESS = 'id-process';

    /**
     * @var string
     */
    protected const string PARAM_ID_DEFINITION = 'id-definition';

    /**
     * The active version is constant per table render; resolved once and reused across rows.
     *
     * @var int|null
     */
    protected ?int $activeVersion = null;

    /**
     * @var bool
     */
    protected bool $isActiveVersionResolved = false;

    public function __construct(
        protected SpyStateMachineProcessDefinitionQuery $stateMachineProcessDefinitionQuery,
        protected SpyStateMachineProcessDefinitionInstanceQuery $stateMachineProcessDefinitionInstanceQuery,
        protected int $idStateMachineProcess
    ) {
    }

    protected function configure(TableConfiguration $config): TableConfiguration
    {
        $config->setUrl(sprintf('table?%s=%d', static::PARAM_ID_PROCESS, $this->idStateMachineProcess));

        $config->setHeader([
            SpyStateMachineProcessDefinitionTableMap::COL_VERSION => 'Version',
            static::COL_AUTHOR => 'Author',
            SpyStateMachineProcessDefinitionTableMap::COL_CREATED_AT => 'Date',
            static::COL_ACTIONS => 'Actions',
        ]);

        $config->setSortable([
            SpyStateMachineProcessDefinitionTableMap::COL_VERSION,
            SpyStateMachineProcessDefinitionTableMap::COL_CREATED_AT,
        ]);

        $config->setSearchable([
            SpyStateMachineProcessDefinitionTableMap::COL_VERSION,
            SpyUserTableMap::COL_FIRST_NAME,
            SpyUserTableMap::COL_LAST_NAME,
        ]);

        $config->setRawColumns([
            static::COL_ACTIONS,
        ]);

        $config->setDefaultSortField(SpyStateMachineProcessDefinitionTableMap::COL_CREATED_AT, TableConfiguration::SORT_DESC);

        return $config;
    }

    /**
     * @module User
     * @module StateMachine
     */
    protected function prepareQuery(): SpyStateMachineProcessDefinitionQuery
    {
        return $this->stateMachineProcessDefinitionQuery
            ->filterByFkStateMachineProcess($this->idStateMachineProcess)
            ->joinStateMachineProcess()
            ->withColumn(SpyStateMachineProcessTableMap::COL_NAME, static::COL_PROCESS_NAME)
            ->addJoin(SpyStateMachineProcessDefinitionTableMap::COL_FK_USER, SpyUserTableMap::COL_ID_USER, Criteria::LEFT_JOIN)
            ->withColumn(SpyUserTableMap::COL_FIRST_NAME, static::COL_AUTHOR_FIRST_NAME)
            ->withColumn(SpyUserTableMap::COL_LAST_NAME, static::COL_AUTHOR_LAST_NAME)
            ->withColumn($this->buildHasInstancesExpression(), static::COL_HAS_INSTANCES);
    }

    protected function buildHasInstancesExpression(): string
    {
        return 'EXISTS (SELECT 1 FROM ' . SpyStateMachineProcessDefinitionInstanceTableMap::TABLE_NAME
            . ' WHERE ' . SpyStateMachineProcessDefinitionInstanceTableMap::COL_FK_STATE_MACHINE_PROCESS_DEFINITION
            . ' = ' . SpyStateMachineProcessDefinitionTableMap::COL_ID_STATE_MACHINE_PROCESS_DEFINITION . ')';
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
        return [
            SpyStateMachineProcessDefinitionTableMap::COL_VERSION => $this->formatVersion($item),
            static::COL_AUTHOR => $this->formatAuthor($item),
            SpyStateMachineProcessDefinitionTableMap::COL_CREATED_AT => $this->formatDate($item[SpyStateMachineProcessDefinitionTableMap::COL_CREATED_AT]),
            static::COL_ACTIONS => implode(' ', $this->buildLinks($item)),
        ];
    }

    /**
     * @param array<string, mixed> $item
     */
    protected function formatVersion(array $item): string
    {
        return static::VERSION_LABEL_PREFIX . $item[SpyStateMachineProcessDefinitionTableMap::COL_VERSION];
    }

    /**
     * @param array<string, mixed> $item
     */
    protected function formatAuthor(array $item): string
    {
        $authorName = trim($item[static::COL_AUTHOR_FIRST_NAME] . ' ' . $item[static::COL_AUTHOR_LAST_NAME]);

        return $authorName !== '' ? $authorName : static::EMPTY_VALUE;
    }

    protected function formatDate(?string $createdAt): string
    {
        if ($createdAt === null) {
            return static::EMPTY_VALUE;
        }

        return (new DateTime($createdAt))->format(static::DATE_FORMAT);
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string>
     */
    protected function buildLinks(array $item): array
    {
        $idStateMachineProcessDefinition = $item[SpyStateMachineProcessDefinitionTableMap::COL_ID_STATE_MACHINE_PROCESS_DEFINITION];

        $buttons = [];
        $buttons[] = $this->generateViewButton(
            Url::generate(static::URL_VIEW, [static::PARAM_ID_DEFINITION => $idStateMachineProcessDefinition]),
            'View',
        );

        $buttons[] = $this->generateEditButton(
            Url::generate(static::URL_EDIT, [
                static::PARAM_ID_PROCESS => $this->idStateMachineProcess,
                static::PARAM_ID_DEFINITION => $idStateMachineProcessDefinition,
                VersionController::PARAM_BACK => VersionController::BACK_ORIGIN_VERSION_LIST,
            ]),
            'New version',
        );

        if ($item[SpyStateMachineProcessDefinitionTableMap::COL_STATUS] === WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE) {
            $buttons[] = $this->generateDisabledActivateButton((int)$idStateMachineProcessDefinition);
            $buttons[] = $this->generateDisabledDeleteButton((int)$idStateMachineProcessDefinition, static::DELETE_DISABLED_ACTIVE_VERSION_TOOLTIP);

            return $buttons;
        }

        $buttons[] = $this->generateFormButton(
            Url::generate(static::URL_ACTIVATE, [static::PARAM_ID_DEFINITION => $idStateMachineProcessDefinition]),
            'Activate',
            VersionActivationForm::class,
            [
                static::BUTTON_CLASS => 'btn-create',
                'data-qa' => 'workflow-version-activate',
            ] + $this->buildActivateConfirmationAttributes($item),
        );

        if ($item[static::COL_HAS_INSTANCES]) {
            $buttons[] = $this->generateDisabledDeleteButton((int)$idStateMachineProcessDefinition, static::DELETE_DISABLED_WITH_INSTANCES_TOOLTIP);

            return $buttons;
        }

        $buttons[] = $this->generateDeleteButton(
            (int)$idStateMachineProcessDefinition,
            [
                'data-qa' => 'workflow-version-delete',
            ] + $this->buildConfirmationAttributes(
                (new ActionConfirmationTransfer())
                    ->setTitle(static::DELETE_CONFIRMATION_TITLE)
                    ->setMessage(static::DELETE_CONFIRMATION_MESSAGE)
                    ->setConfirmButtonLabel(static::DELETE_CONFIRMATION_BUTTON),
            ),
        );

        return $buttons;
    }

    protected function generateDisabledDeleteButton(int $idStateMachineProcessDefinition, string $tooltip): string
    {
        return $this->generateDeleteButton($idStateMachineProcessDefinition, [
            'data-qa' => 'workflow-version-delete-disabled',
            'disabled' => 'disabled',
            'title' => $this->translateConfirmationAttribute($tooltip),
        ]);
    }

    /**
     * @param array<string, string> $buttonOptions
     */
    protected function generateDeleteButton(int $idStateMachineProcessDefinition, array $buttonOptions): string
    {
        return $this->generateFormButton(
            Url::generate(static::URL_DELETE, [
                static::PARAM_ID_PROCESS => $this->idStateMachineProcess,
                static::PARAM_ID_DEFINITION => $idStateMachineProcessDefinition,
            ]),
            'Delete',
            VersionDeleteForm::class,
            [
                static::BUTTON_ICON => 'fa-trash',
                static::BUTTON_CLASS => 'btn-danger',
            ] + $buttonOptions,
        );
    }

    protected function generateDisabledActivateButton(int $idStateMachineProcessDefinition): string
    {
        return $this->generateFormButton(
            Url::generate(static::URL_ACTIVATE, [static::PARAM_ID_DEFINITION => $idStateMachineProcessDefinition]),
            'Activate',
            VersionActivationForm::class,
            [
                static::BUTTON_CLASS => 'btn-create',
                'data-qa' => 'workflow-version-activate-disabled',
                'disabled' => 'disabled',
                'title' => $this->translateConfirmationAttribute(static::ACTIVATE_DISABLED_TOOLTIP),
            ],
        );
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, string>
     */
    protected function buildActivateConfirmationAttributes(array $item): array
    {
        $activeVersion = $this->findActiveVersion();
        $translationParameters = [
            static::TRANSLATION_PARAMETER_VERSION => (int)$item[SpyStateMachineProcessDefinitionTableMap::COL_VERSION],
            static::TRANSLATION_PARAMETER_ACTIVE_VERSION => (int)$activeVersion,
            static::TRANSLATION_PARAMETER_NAME => (string)$item[static::COL_PROCESS_NAME],
        ];

        return $this->buildConfirmationAttributes(
            (new ActionConfirmationTransfer())
                ->setTitle(static::ACTIVATE_CONFIRMATION_TITLE)
                ->setMessage($activeVersion !== null ? static::ACTIVATE_CONFIRMATION_MESSAGE : static::ACTIVATE_CONFIRMATION_MESSAGE_WITHOUT_ACTIVE_VERSION)
                ->setConfirmButtonLabel(static::ACTIVATE_CONFIRMATION_BUTTON)
                ->setType(static::CONFIRMATION_TYPE_PRIMARY)
                ->setTranslationParameters($translationParameters),
        );
    }

    protected function findActiveVersion(): ?int
    {
        if ($this->isActiveVersionResolved) {
            return $this->activeVersion;
        }

        $stateMachineProcessDefinitionEntity = (clone $this->stateMachineProcessDefinitionQuery)
            ->filterByFkStateMachineProcess($this->idStateMachineProcess)
            ->filterByStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE)
            ->findOne();

        $this->activeVersion = $stateMachineProcessDefinitionEntity !== null ? $stateMachineProcessDefinitionEntity->getVersion() : null;
        $this->isActiveVersionResolved = true;

        return $this->activeVersion;
    }
}

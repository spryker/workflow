<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Table;

use Orm\Zed\Workflow\Persistence\Map\SpyStateMachineProcessDefinitionTableMap;
use Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionInstanceQuery;
use Orm\Zed\Workflow\Persistence\SpyStateMachineProcessDefinitionQuery;
use Spryker\Service\UtilText\Model\Url\Url;
use Spryker\Zed\Gui\Communication\Table\AbstractTable;
use Spryker\Zed\Gui\Communication\Table\TableConfiguration;
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
     * @uses \Spryker\Zed\Workflow\Communication\Controller\InstanceController::indexAction()
     *
     * @var string
     */
    protected const string URL_INSTANCE = '/workflow/instance';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\VersionController::createAction()
     *
     * @var string
     */
    protected const string URL_EDIT = '/workflow/version/create';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\VersionController::activateAction()
     *
     * @var string
     */
    protected const string URL_ACTIVATE = '/workflow/version/activate';

    /**
     * @uses \Spryker\Zed\Workflow\Communication\Controller\VersionController::deactivateAction()
     *
     * @var string
     */
    protected const string URL_DEACTIVATE = '/workflow/version/deactivate';

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

    /**
     * @uses \Spryker\Zed\Workflow\Presentation\_partial\action-confirmation-modal.twig
     */
    protected const string ATTRIBUTE_CONFIRM = 'data-confirm';

    protected const string ATTRIBUTE_CONFIRM_TITLE = 'data-confirm-title';

    protected const string ATTRIBUTE_CONFIRM_MESSAGE = 'data-confirm-message';

    protected const string ATTRIBUTE_CONFIRM_BUTTON = 'data-confirm-button';

    protected const string EDIT_CONFIRMATION_TITLE = 'Create an editable copy';

    protected const string EDIT_CONFIRMATION_MESSAGE = 'You are about to create an editable copy of this workflow. Your changes are saved as a new version - the current one keeps running. Continue?';

    protected const string EDIT_CONFIRMATION_BUTTON = 'Continue';

    protected const string DELETE_CONFIRMATION_TITLE = 'Delete version';

    protected const string DELETE_CONFIRMATION_MESSAGE = 'This permanently deletes this version. It has no instances, so nothing running is affected. Delete it?';

    protected const string DELETE_CONFIRMATION_BUTTON = 'Delete';

    /**
     * @var string
     */
    protected const string REQUEST_PARAM_ID_PROCESS = 'id-process';

    /**
     * @var string
     */
    protected const string REQUEST_PARAM_ID_DEFINITION = 'id-definition';

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
        $config->setUrl(sprintf('table?%s=%d', static::REQUEST_PARAM_ID_PROCESS, $this->idStateMachineProcess));

        $config->setHeader([
            SpyStateMachineProcessDefinitionTableMap::COL_VERSION => 'Version',
            SpyStateMachineProcessDefinitionTableMap::COL_INITIAL_STATE => 'Initial State',
            SpyStateMachineProcessDefinitionTableMap::COL_STATUS => 'Status',
            static::COL_ACTIONS => 'Actions',
        ]);

        $config->setSortable([
            SpyStateMachineProcessDefinitionTableMap::COL_VERSION,
            SpyStateMachineProcessDefinitionTableMap::COL_INITIAL_STATE,
            SpyStateMachineProcessDefinitionTableMap::COL_STATUS,
        ]);

        $config->setSearchable([
            SpyStateMachineProcessDefinitionTableMap::COL_INITIAL_STATE,
            SpyStateMachineProcessDefinitionTableMap::COL_STATUS,
        ]);

        $config->setRawColumns([
            SpyStateMachineProcessDefinitionTableMap::COL_STATUS,
            static::COL_ACTIONS,
        ]);

        $config->setDefaultSortField(SpyStateMachineProcessDefinitionTableMap::COL_VERSION, TableConfiguration::SORT_DESC);

        return $config;
    }

    protected function prepareQuery(): SpyStateMachineProcessDefinitionQuery
    {
        return $this->stateMachineProcessDefinitionQuery
            ->filterByFkStateMachineProcess($this->idStateMachineProcess);
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
            SpyStateMachineProcessDefinitionTableMap::COL_VERSION => $item[SpyStateMachineProcessDefinitionTableMap::COL_VERSION],
            SpyStateMachineProcessDefinitionTableMap::COL_INITIAL_STATE => $item[SpyStateMachineProcessDefinitionTableMap::COL_INITIAL_STATE],
            SpyStateMachineProcessDefinitionTableMap::COL_STATUS => $this->createStatusLabel((string)$item[SpyStateMachineProcessDefinitionTableMap::COL_STATUS]),
            static::COL_ACTIONS => implode(' ', $this->buildLinks($item)),
        ];
    }

    protected function createStatusLabel(string $status): string
    {
        $labelClass = $status === WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE ? 'label-info' : 'label-default';

        return $this->generateLabel($status, $labelClass);
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
            Url::generate(static::URL_VIEW, [static::REQUEST_PARAM_ID_DEFINITION => $idStateMachineProcessDefinition]),
            'View',
        );
        $buttons[] = $this->generateViewButton(
            Url::generate(static::URL_INSTANCE, [static::REQUEST_PARAM_ID_DEFINITION => $idStateMachineProcessDefinition]),
            'Instances',
        );
        // Edit clones this version into the create form; saving always produces a NEW version (existing versions remain immutable).
        // The confirmation modal makes that explicit so users don't expect an in-place edit.
        $buttons[] = $this->generateEditButton(
            Url::generate(static::URL_EDIT, [
                static::REQUEST_PARAM_ID_PROCESS => $this->idStateMachineProcess,
                static::REQUEST_PARAM_ID_DEFINITION => $idStateMachineProcessDefinition,
            ]),
            'Edit',
            [
                static::ATTRIBUTE_CONFIRM => '1',
                static::ATTRIBUTE_CONFIRM_TITLE => $this->translateForAttribute(static::EDIT_CONFIRMATION_TITLE),
                static::ATTRIBUTE_CONFIRM_MESSAGE => $this->translateForAttribute(static::EDIT_CONFIRMATION_MESSAGE),
                static::ATTRIBUTE_CONFIRM_BUTTON => $this->translateForAttribute(static::EDIT_CONFIRMATION_BUTTON),
            ],
        );

        if ($item[SpyStateMachineProcessDefinitionTableMap::COL_STATUS] === WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE) {
            $buttons[] = $this->generateFormButton(
                Url::generate(static::URL_DEACTIVATE, [static::REQUEST_PARAM_ID_DEFINITION => $idStateMachineProcessDefinition]),
                'Deactivate',
                VersionActivationForm::class,
                [static::BUTTON_CLASS => 'btn-danger'],
            );

            return $buttons;
        }

        $activeVersion = $this->findActiveVersion();
        $buttons[] = $this->generateFormButton(
            Url::generate(static::URL_ACTIVATE, [static::REQUEST_PARAM_ID_DEFINITION => $idStateMachineProcessDefinition]),
            'Activate',
            VersionActivationForm::class,
            [
                static::BUTTON_CLASS => 'btn-create',
                'data-qa' => 'workflow-version-activate',
                'data-activate-confirm' => '1',
                'data-target-version' => (int)$item[SpyStateMachineProcessDefinitionTableMap::COL_VERSION],
                'data-active-version' => $activeVersion ?? '',
            ],
        );

        // Only inactive versions with no instances can be deleted; the button is hidden otherwise.
        if (!$this->hasInstances((int)$idStateMachineProcessDefinition)) {
            $buttons[] = $this->generateFormButton(
                Url::generate(static::URL_DELETE, [
                    static::REQUEST_PARAM_ID_PROCESS => $this->idStateMachineProcess,
                    static::REQUEST_PARAM_ID_DEFINITION => $idStateMachineProcessDefinition,
                ]),
                'Delete',
                VersionDeleteForm::class,
                [
                    static::BUTTON_ICON => 'fa-trash',
                    static::BUTTON_CLASS => 'btn-danger',
                    static::ATTRIBUTE_CONFIRM => '1',
                    static::ATTRIBUTE_CONFIRM_TITLE => $this->translateForAttribute(static::DELETE_CONFIRMATION_TITLE),
                    static::ATTRIBUTE_CONFIRM_MESSAGE => $this->translateForAttribute(static::DELETE_CONFIRMATION_MESSAGE),
                    static::ATTRIBUTE_CONFIRM_BUTTON => $this->translateForAttribute(static::DELETE_CONFIRMATION_BUTTON),
                ],
            );
        }

        return $buttons;
    }

    /**
     * The button template injects attributes with `| raw`, so the translated value is escaped here to
     * keep quotes in a translation from breaking out of the attribute.
     */
    protected function translateForAttribute(string $key): string
    {
        $translator = $this->getTranslator();
        $message = $translator !== null ? $translator->trans($key) : $key;

        return htmlspecialchars($message, \ENT_QUOTES, 'UTF-8');
    }

    protected function hasInstances(int $idStateMachineProcessDefinition): bool
    {
        return (clone $this->stateMachineProcessDefinitionInstanceQuery)
            ->filterByFkStateMachineProcessDefinition($idStateMachineProcessDefinition)
            ->exists();
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

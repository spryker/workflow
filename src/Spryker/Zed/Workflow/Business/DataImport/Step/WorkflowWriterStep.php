<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\DataImport\Step;

use Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerConditionsTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerTransfer;
use Generated\Shared\Transfer\StateMachineProcessCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use Spryker\Zed\DataImport\Business\Exception\DataImportException;
use Spryker\Zed\DataImport\Business\Model\DataImportStep\DataImportStepInterface;
use Spryker\Zed\DataImport\Business\Model\DataSet\DataSetInterface;
use Spryker\Zed\Workflow\Business\Writer\DefinitionWriterInterface;
use Spryker\Zed\Workflow\Business\Writer\ProcessWriterInterface;
use Spryker\Zed\Workflow\Business\Writer\TriggerWriterInterface;
use Spryker\Zed\Workflow\Persistence\WorkflowRepositoryInterface;
use Spryker\Zed\Workflow\WorkflowConfig;

/**
 * Imports a full workflow from one CSV row: creates (or reuses) the process, upserts the definition keyed by
 * the CSV-supplied version (so re-imports are idempotent - re-importing the same version updates that row in
 * place, a new version number authors a new version), optionally activates it, and syncs the process' start
 * triggers. The "definition" column is a project-root-relative path to the definition XML file, which is read
 * at import time. Reuses the same business writers as the Back Office so identical validation and
 * exclusive-activation logic applies.
 */
class WorkflowWriterStep implements DataImportStepInterface
{
    /**
     * @var string
     */
    public const COL_NAME = 'name';

    /**
     * @var string
     */
    public const COL_SUBJECT_TYPE = 'subject_type';

    /**
     * @var string
     */
    public const COL_DESCRIPTION = 'description';

    /**
     * @var string
     */
    public const COL_INITIAL_STATE = 'initial_state';

    /**
     * @var string
     */
    public const COL_VERSION = 'version';

    /**
     * @var string
     */
    public const COL_DEFINITION = 'definition';

    /**
     * @var string
     */
    public const COL_TRIGGER_EVENTS = 'trigger_events';

    /**
     * @var string
     */
    public const COL_IS_ACTIVE = 'is_active';

    /**
     * @var string
     */
    protected const IS_ACTIVE_TRUE = '1';

    public function __construct(
        protected ProcessWriterInterface $processWriter,
        protected DefinitionWriterInterface $definitionWriter,
        protected TriggerWriterInterface $triggerWriter,
        protected WorkflowRepositoryInterface $workflowRepository
    ) {
    }

    /**
     * @throws \Spryker\Zed\DataImport\Business\Exception\DataImportException
     */
    public function execute(DataSetInterface $dataSet): void
    {
        $name = trim((string)$dataSet[static::COL_NAME]);
        $subjectType = trim((string)$dataSet[static::COL_SUBJECT_TYPE]);
        $initialState = trim((string)$dataSet[static::COL_INITIAL_STATE]);
        $definitionPath = trim((string)$dataSet[static::COL_DEFINITION]);
        $version = trim((string)$dataSet[static::COL_VERSION]);

        if ($name === '' || $subjectType === '' || $initialState === '' || $definitionPath === '' || $version === '') {
            throw new DataImportException('Workflow import requires non-empty "name", "subject_type", "initial_state", "definition" and "version" columns.');
        }

        $definition = $this->readDefinition($definitionPath);

        $idStateMachineProcess = $this->getOrCreateProcess($name, $subjectType, (string)$dataSet[static::COL_DESCRIPTION]);

        $idStateMachineProcessDefinition = $this->createDefinition($idStateMachineProcess, (int)$version, $definition, $initialState);

        if (trim((string)$dataSet[static::COL_IS_ACTIVE]) === static::IS_ACTIVE_TRUE) {
            $this->activateProcess($idStateMachineProcess);
            $this->activateDefinition($idStateMachineProcessDefinition);
        }

        $this->syncTriggers($idStateMachineProcess, (string)$dataSet[static::COL_TRIGGER_EVENTS]);
    }

    /**
     * @throws \Spryker\Zed\DataImport\Business\Exception\DataImportException
     */
    protected function readDefinition(string $definitionPath): string
    {
        $absoluteDefinitionPath = sprintf('%s/%s', rtrim(APPLICATION_ROOT_DIR, '/'), ltrim($definitionPath, '/'));

        if (!is_file($absoluteDefinitionPath)) {
            throw new DataImportException(sprintf('Workflow definition file "%s" was not found.', $definitionPath));
        }

        $definition = file_get_contents($absoluteDefinitionPath);

        if ($definition === false || trim($definition) === '') {
            throw new DataImportException(sprintf('Workflow definition file "%s" is empty or could not be read.', $definitionPath));
        }

        return $definition;
    }

    protected function getOrCreateProcess(string $name, string $subjectType, string $description): int
    {
        $stateMachineProcessCollectionTransfer = $this->workflowRepository->getStateMachineProcessCollection(
            (new StateMachineProcessCriteriaTransfer())
                ->setStateMachineProcessConditions(
                    (new StateMachineProcessConditionsTransfer())->addStateMachineName($name),
                ),
        );

        $stateMachineProcessTransfer = $stateMachineProcessCollectionTransfer
            ->getStateMachineProcesses()
            ->getIterator()
            ->current();

        $idStateMachineProcess = $stateMachineProcessTransfer?->getIdStateMachineProcess();

        if ($idStateMachineProcess !== null) {
            return $idStateMachineProcess;
        }

        $stateMachineProcessCollectionResponseTransfer = $this->processWriter->createStateMachineProcessCollection(
            (new StateMachineProcessCollectionRequestTransfer())
                ->addStateMachineProcess(
                    (new StateMachineProcessTransfer())
                        ->setProcessName($name)
                        ->setStateMachineName($name)
                        ->setSubjectType($subjectType)
                        ->setDescription($description !== '' ? $description : null),
                ),
        );

        $this->assertNoErrors($stateMachineProcessCollectionResponseTransfer->getErrors(), sprintf('create process "%s"', $name));

        return $stateMachineProcessCollectionResponseTransfer
            ->getStateMachineProcesses()
            ->getIterator()
            ->current()
            ->getIdStateMachineProcessOrFail();
    }

    protected function createDefinition(int $idStateMachineProcess, int $version, string $definition, string $initialState): int
    {
        $stateMachineProcessDefinitionCollectionResponseTransfer = $this->definitionWriter->createStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCollectionRequestTransfer())
                ->addStateMachineProcessDefinition(
                    (new StateMachineProcessDefinitionTransfer())
                        ->setStateMachineProcess(
                            (new StateMachineProcessTransfer())->setIdStateMachineProcess($idStateMachineProcess),
                        )
                        ->setVersion($version)
                        ->setDefinition($definition)
                        ->setInitialState($initialState),
                ),
        );

        $this->assertNoErrors($stateMachineProcessDefinitionCollectionResponseTransfer->getErrors(), 'create definition');

        return $stateMachineProcessDefinitionCollectionResponseTransfer
            ->getStateMachineProcessDefinitions()
            ->getIterator()
            ->current()
            ->getIdStateMachineProcessDefinitionOrFail();
    }

    protected function activateProcess(int $idStateMachineProcess): void
    {
        $stateMachineProcessCollectionResponseTransfer = $this->processWriter->updateStateMachineProcessCollection(
            (new StateMachineProcessCollectionRequestTransfer())
                ->addStateMachineProcess(
                    (new StateMachineProcessTransfer())
                        ->setIdStateMachineProcess($idStateMachineProcess)
                        ->setStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE),
                )
                ->setIsTransactional(true),
        );

        $this->assertNoErrors($stateMachineProcessCollectionResponseTransfer->getErrors(), 'activate process');
    }

    protected function activateDefinition(int $idStateMachineProcessDefinition): void
    {
        $stateMachineProcessDefinitionCollectionResponseTransfer = $this->definitionWriter->updateStateMachineProcessDefinitionCollection(
            (new StateMachineProcessDefinitionCollectionRequestTransfer())
                ->addStateMachineProcessDefinition(
                    (new StateMachineProcessDefinitionTransfer())
                        ->setIdStateMachineProcessDefinition($idStateMachineProcessDefinition)
                        ->setStatus(WorkflowConfig::PROCESS_DEFINITION_STATUS_ACTIVE),
                )
                ->setIsTransactional(true),
        );

        $this->assertNoErrors($stateMachineProcessDefinitionCollectionResponseTransfer->getErrors(), 'activate definition');
    }

    protected function syncTriggers(int $idStateMachineProcess, string $triggerEvents): void
    {
        $eventNames = $this->parseTriggerEvents($triggerEvents);
        $persistedEventNames = $this->getPersistedTriggerEventNames($idStateMachineProcess);

        $stateMachineDefinitionTriggerCollectionRequestTransfer = new StateMachineDefinitionTriggerCollectionRequestTransfer();

        foreach (array_diff($eventNames, $persistedEventNames) as $eventName) {
            $stateMachineDefinitionTriggerCollectionRequestTransfer->addStateMachineDefinitionTriggerToAdd(
                $this->createTriggerTransfer($idStateMachineProcess, $eventName),
            );
        }

        foreach (array_diff($persistedEventNames, $eventNames) as $eventName) {
            $stateMachineDefinitionTriggerCollectionRequestTransfer->addStateMachineDefinitionTriggerToRemove(
                $this->createTriggerTransfer($idStateMachineProcess, $eventName),
            );
        }

        if (
            $stateMachineDefinitionTriggerCollectionRequestTransfer->getStateMachineDefinitionTriggersToAdd()->count() === 0
            && $stateMachineDefinitionTriggerCollectionRequestTransfer->getStateMachineDefinitionTriggersToRemove()->count() === 0
        ) {
            return;
        }

        $stateMachineDefinitionTriggerCollectionResponseTransfer = $this->triggerWriter->updateStateMachineDefinitionTriggerCollection(
            $stateMachineDefinitionTriggerCollectionRequestTransfer,
        );

        $this->assertNoErrors($stateMachineDefinitionTriggerCollectionResponseTransfer->getErrors(), 'sync triggers');
    }

    protected function createTriggerTransfer(int $idStateMachineProcess, string $eventName): StateMachineDefinitionTriggerTransfer
    {
        return (new StateMachineDefinitionTriggerTransfer())
            ->setEventName($eventName)
            ->setStateMachineProcess(
                (new StateMachineProcessTransfer())->setIdStateMachineProcess($idStateMachineProcess),
            );
    }

    /**
     * @return array<string>
     */
    protected function getPersistedTriggerEventNames(int $idStateMachineProcess): array
    {
        $stateMachineDefinitionTriggerCollectionTransfer = $this->workflowRepository->getStateMachineDefinitionTriggerCollection(
            (new StateMachineDefinitionTriggerCriteriaTransfer())
                ->setStateMachineDefinitionTriggerConditions(
                    (new StateMachineDefinitionTriggerConditionsTransfer())->addIdStateMachineProcess($idStateMachineProcess),
                ),
        );

        $eventNames = [];
        foreach ($stateMachineDefinitionTriggerCollectionTransfer->getStateMachineDefinitionTriggers() as $stateMachineDefinitionTriggerTransfer) {
            $eventNames[] = $stateMachineDefinitionTriggerTransfer->getEventNameOrFail();
        }

        return $eventNames;
    }

    /**
     * @return array<string>
     */
    protected function parseTriggerEvents(string $triggerEvents): array
    {
        if (trim($triggerEvents) === '') {
            return [];
        }

        // Comma is a plain, non-semantic value separator for the trigger_events column.
        $eventNames = array_map('trim', explode(',', $triggerEvents));

        return array_values(array_filter(array_unique($eventNames), static fn (string $eventName): bool => $eventName !== ''));
    }

    /**
     * @param iterable<\Generated\Shared\Transfer\ErrorTransfer> $errorTransfers
     *
     * @throws \Spryker\Zed\DataImport\Business\Exception\DataImportException
     */
    protected function assertNoErrors(iterable $errorTransfers, string $context): void
    {
        $messages = [];
        foreach ($errorTransfers as $errorTransfer) {
            $messages[] = (string)$errorTransfer->getMessage();
        }

        if ($messages !== []) {
            throw new DataImportException(sprintf('Workflow import failed to %s: %s', $context, implode('; ', $messages)));
        }
    }
}

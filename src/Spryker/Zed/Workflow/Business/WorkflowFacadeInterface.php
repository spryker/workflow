<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business;

use Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionResponseTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionTriggerCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionValidationResponseTransfer;
use Generated\Shared\Transfer\StateMachineEventTriggerRequestTransfer;
use Generated\Shared\Transfer\StateMachineEventTriggerResponseTransfer;
use Generated\Shared\Transfer\StateMachineProcessCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessCollectionResponseTransfer;
use Generated\Shared\Transfer\StateMachineProcessCollectionTransfer;
use Generated\Shared\Transfer\StateMachineProcessCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionRequestTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionResponseTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceCollectionTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Generated\Shared\Transfer\StateMachineTriggerEventCollectionTransfer;
use Generated\Shared\Transfer\StateMachineTriggerEventCriteriaTransfer;

interface WorkflowFacadeInterface
{
    /**
     * Specification:
     * - Retrieves state machine processes filtered by the provided criteria.
     * - Supports sorting and pagination.
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineProcessCriteriaTransfer $stateMachineProcessCriteriaTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineProcessCollectionTransfer
     */
    public function getStateMachineProcessCollection(
        StateMachineProcessCriteriaTransfer $stateMachineProcessCriteriaTransfer
    ): StateMachineProcessCollectionTransfer;

    /**
     * Specification:
     * - Creates state machine processes.
     * - Persists the metadata (type, subject_type, status, description) on the core state machine process row.
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineProcessCollectionRequestTransfer $stateMachineProcessCollectionRequestTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineProcessCollectionResponseTransfer
     */
    public function createStateMachineProcessCollection(
        StateMachineProcessCollectionRequestTransfer $stateMachineProcessCollectionRequestTransfer
    ): StateMachineProcessCollectionResponseTransfer;

    /**
     * Specification:
     * - Updates the editable metadata (status, description) of each process in the request.
     * - Setting status to inactive blocks new event/manual starts and excludes the process from
     *   condition/timeout discovery; running instances finish on their pinned version.
     * - Supports transactional manipulation (defaulted to true); on failure returns the offending
     *   error identifying the process.
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineProcessCollectionRequestTransfer $stateMachineProcessCollectionRequestTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineProcessCollectionResponseTransfer
     */
    public function updateStateMachineProcessCollection(
        StateMachineProcessCollectionRequestTransfer $stateMachineProcessCollectionRequestTransfer
    ): StateMachineProcessCollectionResponseTransfer;

    /**
     * Specification:
     * - Retrieves process definition versions filtered by the provided criteria.
     * - Supports sorting and pagination.
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineProcessDefinitionCriteriaTransfer $stateMachineProcessDefinitionCriteriaTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionTransfer
     */
    public function getStateMachineProcessDefinitionCollection(
        StateMachineProcessDefinitionCriteriaTransfer $stateMachineProcessDefinitionCriteriaTransfer
    ): StateMachineProcessDefinitionCollectionTransfer;

    /**
     * Specification:
     * - Persists process definition versions from the request.
     * - Validates each definition.
     * - Activating a version deactivates all other versions of the same process.
     * - Auto populates the condition/timeout transition flags from the compiled definition.
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionRequestTransfer $stateMachineProcessDefinitionCollectionRequestTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionResponseTransfer
     */
    public function createStateMachineProcessDefinitionCollection(
        StateMachineProcessDefinitionCollectionRequestTransfer $stateMachineProcessDefinitionCollectionRequestTransfer
    ): StateMachineProcessDefinitionCollectionResponseTransfer;

    /**
     * Specification:
     * - Validates a single definition (raw XML + initial state + owning process id); subject type and
     *   process name are resolved from the process id.
     * - Reports invalid XML, unknown command/condition refs, missing/invalid initial state, unreachable
     *   states, undefined target states, duplicate state/event names and process-name mismatches.
     * - Does NOT persist anything. Used both for the pre-save AJAX "validate" action and as the hard
     *   gate inside createStateMachineProcessDefinitionCollection (invalid definitions are never saved).
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineDefinitionValidationResponseTransfer
     */
    public function validateStateMachineProcessDefinition(
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
    ): StateMachineDefinitionValidationResponseTransfer;

    /**
     * Specification:
     * - Updates the status of each process definition version in the request.
     * - Activating a version is exclusive (single-active): all sibling versions of the same process
     *   are deactivated, then this one is activated.
     * - Deactivating a version blocks new starts only, running instances finish on their pinned version.
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionRequestTransfer $stateMachineProcessDefinitionCollectionRequestTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionResponseTransfer
     */
    public function updateStateMachineProcessDefinitionCollection(
        StateMachineProcessDefinitionCollectionRequestTransfer $stateMachineProcessDefinitionCollectionRequestTransfer
    ): StateMachineProcessDefinitionCollectionResponseTransfer;

    /**
     * Specification:
     * - Deletes each process definition version in the request.
     * - A version is deleted only when it is inactive AND has no instances
     * - Otherwise an error is returned for that version and it is left untouched.
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionRequestTransfer $stateMachineProcessDefinitionCollectionRequestTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineProcessDefinitionCollectionResponseTransfer
     */
    public function deleteStateMachineProcessDefinitionCollection(
        StateMachineProcessDefinitionCollectionRequestTransfer $stateMachineProcessDefinitionCollectionRequestTransfer
    ): StateMachineProcessDefinitionCollectionResponseTransfer;

    /**
     * Specification:
     * - Retrieves running instances filtered by the provided criteria.
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceCriteriaTransfer $stateMachineProcessDefinitionInstanceCriteriaTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineProcessDefinitionInstanceCollectionTransfer
     */
    public function getStateMachineProcessDefinitionInstanceCollection(
        StateMachineProcessDefinitionInstanceCriteriaTransfer $stateMachineProcessDefinitionInstanceCriteriaTransfer
    ): StateMachineProcessDefinitionInstanceCollectionTransfer;

    /**
     * Specification:
     * - Retrieves per-process triggers filtered by the provided criteria.
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineDefinitionTriggerCriteriaTransfer $stateMachineDefinitionTriggerCriteriaTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionTransfer
     */
    public function getStateMachineDefinitionTriggerCollection(
        StateMachineDefinitionTriggerCriteriaTransfer $stateMachineDefinitionTriggerCriteriaTransfer
    ): StateMachineDefinitionTriggerCollectionTransfer;

    /**
     * Specification:
     * - Returns the trigger events registered for the subject type of the matched process(es).
     * - Resolves the subject type per process id in the conditions, or filters directly by subject types,
     *   trigger events are collected from the registered StateMachineProcessTriggerPlugin stack and filtered by subject type.
     * - Returns an empty collection when no process matches or no trigger plugins match the subject type.
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineTriggerEventCriteriaTransfer $stateMachineTriggerEventCriteriaTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineTriggerEventCollectionTransfer
     */
    public function getStateMachineTriggerEventCollection(
        StateMachineTriggerEventCriteriaTransfer $stateMachineTriggerEventCriteriaTransfer
    ): StateMachineTriggerEventCollectionTransfer;

    /**
     * Specification:
     * - Persists the triggers in `stateMachineDefinitionTriggersToAdd`.
     * - Deletes the triggers in `stateMachineDefinitionTriggersToRemove`.
     * - Returns the added triggers on the response.
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionRequestTransfer $stateMachineDefinitionTriggerCollectionRequestTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineDefinitionTriggerCollectionResponseTransfer
     */
    public function updateStateMachineDefinitionTriggerCollection(
        StateMachineDefinitionTriggerCollectionRequestTransfer $stateMachineDefinitionTriggerCollectionRequestTransfer
    ): StateMachineDefinitionTriggerCollectionResponseTransfer;

    /**
     * Specification:
     * - Resolves every process whose per-process trigger matches the request event name.
     * - Resolves each matched process's active version and, unless an instance already exists for the
     *   subject identifier, starts a new state machine instance on that version.
     * - Matched state machines run sequentially, independently and failure-isolated.
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineEventTriggerRequestTransfer $stateMachineEventTriggerRequestTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineEventTriggerResponseTransfer
     */
    public function startStateMachineInstance(
        StateMachineEventTriggerRequestTransfer $stateMachineEventTriggerRequestTransfer
    ): StateMachineEventTriggerResponseTransfer;

    /**
     * Specification:
     * - Fires a named transition event on the running instance of the subject (scoped by subject type).
     * - Resolves the instance's current-state pointer and delegates to the stock engine to transition it.
     * - Fails cleanly with an error when no running instance with a current state exists for the subject.
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\StateMachineEventTriggerRequestTransfer $stateMachineEventTriggerRequestTransfer
     *
     * @return \Generated\Shared\Transfer\StateMachineEventTriggerResponseTransfer
     */
    public function triggerStateMachineInstanceEvent(
        StateMachineEventTriggerRequestTransfer $stateMachineEventTriggerRequestTransfer
    ): StateMachineEventTriggerResponseTransfer;

    /**
     * Specification:
     * - Discovery step for the scheduled condition check (event-less, conditional transitions).
     * - Enumerates every ACTIVE DB-authored state machine that has any version declaring condition
     *   transitions and runs the engine condition check for each.
     * - Per state machine, only versions with non-finished instances are scanned (spans active and
     *   inactive versions).
     * - Returns the total number of affected items.
     *
     * @api
     *
     * @return int
     */
    public function checkDynamicConditions(): int;

    /**
     * Specification:
     * - Discovery step for the scheduled timeout check.
     * - Enumerates every ACTIVE DB-authored state machine that has any version declaring timeout
     *   transitions and runs the engine timeout check for each.
     * - Returns the total number of affected items.
     *
     * @api
     *
     * @return int
     */
    public function checkDynamicTimeouts(): int;
}

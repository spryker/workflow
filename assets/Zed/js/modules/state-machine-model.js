'use strict';

/**
 * State machine domain model — the single source of truth for the visual builder.
 *
 * The canvas, the inspector and the serialized XML are all projections of this model; they never
 * own data. Entities are referenced by stable internal ids, not by names, so renaming or deleting
 * a state cannot leave dangling references (the initial state and transitions point at ids).
 *
 * Shape:
 *   states:      [ { id, name, display } ]
 *   events:      [ { id, name, eventType: ''|'onEnter'|'manual'|'timeout', timeout, command } ]
 *   transitions: [ { id, sourceId, targetId, eventId: string|null, condition, happy } ]
 *   initialStateId: string|null
 *
 * Every mutating operation records an undo snapshot (consecutive edits of the same field are
 * coalesced so typing does not flood the history) and notifies subscribers afterwards. A snapshot
 * carries the operation name and the entity the change concerned, so undo can report back what it
 * reverted instead of silently swapping the model out from under the user.
 */

const HISTORY_LIMIT = 50;
const COALESCE_WINDOW_IN_MILLISECONDS = 800;

/**
 * Builds the human-readable display name shown in the Back Office for a generated state name:
 * `state 8` becomes `State 8`.
 *
 * @param {string} name
 *
 * @returns {string}
 */
function toDisplayName(name) {
    return name.replace(/(^|\s)(\S)/g, (match, separator, character) => separator + character.toUpperCase());
}

class StateMachineModel {
    constructor() {
        this.processName = 'Workflow';
        this.states = [];
        this.events = [];
        this.transitions = [];
        this.initialStateId = null;

        this.listeners = [];
        this.history = [];
        this.lastSnapshotKey = '';
        this.lastSnapshotTime = 0;
        this.idCounter = 0;
    }

    subscribe(listener) {
        this.listeners.push(listener);
    }

    notify() {
        this.listeners.forEach((listener) => listener());
    }

    nextId(prefix) {
        this.idCounter += 1;

        return `${prefix}${this.idCounter}`;
    }

    /**
     * Records an undo snapshot. `key` identifies the logical edit (e.g. `renameState:s1`) so a
     * burst of keystrokes on the same field collapses into a single history entry; the part before
     * the colon is the operation name undo reports back.
     *
     * A coalesced snapshot deliberately keeps the entry already on the history: it holds the state
     * from before the burst started, which is what the user expects one undo to restore.
     *
     * @param {string} key
     * @param {{kind: string, id: string}|null} focus the entity the change concerns, so undo can
     *   point the user at it; null for operations that have no single subject
     */
    snapshot(key, focus) {
        const now = Date.now();

        if (key === this.lastSnapshotKey && now - this.lastSnapshotTime < COALESCE_WINDOW_IN_MILLISECONDS) {
            this.lastSnapshotTime = now;

            return;
        }

        this.history.push({
            serialized: this.serializeState(),
            operation: key.split(':')[0],
            focus: focus || null,
        });

        if (this.history.length > HISTORY_LIMIT) {
            this.history.shift();
        }

        this.lastSnapshotKey = key;
        this.lastSnapshotTime = now;
    }

    canUndo() {
        return this.history.length > 0;
    }

    /**
     * Rolls the model back one step.
     *
     * @returns {{operation: string, focus: {kind: 'state'|'transition', id: string}|null}|null}
     *   or null when there was nothing left to undo
     */
    undo() {
        if (!this.history.length) {
            return null;
        }

        const entry = this.history.pop();
        this.restoreState(entry.serialized);
        this.lastSnapshotKey = '';
        this.notify();

        return { operation: entry.operation, focus: entry.focus };
    }

    serializeState() {
        return JSON.stringify({
            processName: this.processName,
            states: this.states,
            events: this.events,
            transitions: this.transitions,
            initialStateId: this.initialStateId,
        });
    }

    restoreState(serialized) {
        const data = JSON.parse(serialized);
        this.processName = data.processName;
        this.states = data.states;
        this.events = data.events;
        this.transitions = data.transitions;
        this.initialStateId = data.initialStateId;
    }

    getState(id) {
        return this.states.find((state) => state.id === id) || null;
    }

    getTransition(id) {
        return this.transitions.find((transition) => transition.id === id) || null;
    }

    getEvent(id) {
        return this.events.find((event) => event.id === id) || null;
    }

    stateName(id) {
        const state = this.getState(id);

        return state ? state.name : '';
    }

    countTransitionsUsingEvent(eventId) {
        return this.transitions.filter((transition) => transition.eventId === eventId).length;
    }

    addState() {
        const state = this.createState();

        // Keyed by the new id so consecutive adds stay separately undoable instead of coalescing.
        this.snapshot(`addState:${state.id}`, { kind: 'state', id: state.id });
        this.states.push(state);
        this.notify();

        return state;
    }

    /**
     * Builds a state entity without touching the model, so composite operations can snapshot with
     * the final id and push several entities in one history step.
     *
     * @returns {{id: string, name: string, display: string}}
     */
    createState() {
        const name = this.uniqueStateName();

        return { id: this.nextId('s'), name: name, display: toDisplayName(name) };
    }

    /**
     * Adds a state connected to the given one — the canvas "+" affordance. State, transition and
     * (when it does not exist yet) the default event are created as a single undoable operation.
     *
     * @param {string} sourceId
     *
     */
    addConnectedState(sourceId) {
        if (!this.getState(sourceId)) {
            return null;
        }

        const state = this.createState();
        this.snapshot(`addConnectedState:${state.id}`, { kind: 'state', id: state.id });

        const event = this.createEvent(this.uniqueEventName());
        this.events.push(event);
        const transition = this.createTransition(sourceId, state.id, event.id);

        this.states.push(state);
        this.transitions.push(transition);
        this.notify();

        return { state: state, transition: transition };
    }

    uniqueStateName() {
        const existing = this.states.map((state) => state.name);
        let index = this.states.length + 1;
        let candidate = `state ${index}`;

        while (existing.indexOf(candidate) !== -1) {
            index += 1;
            candidate = `state ${index}`;
        }

        return candidate;
    }

    isStateNameTaken(id, name) {
        return this.states.some((state) => state.id !== id && state.name === name);
    }

    renameState(id, name) {
        this.snapshot(`renameState:${id}`, { kind: 'state', id: id });
        const state = this.getState(id);

        if (state) {
            state.name = name;
        }

        this.notify();
    }

    setStateDisplay(id, display) {
        this.snapshot(`setStateDisplay:${id}`, { kind: 'state', id: id });
        const state = this.getState(id);

        if (state) {
            state.display = display;
        }

        this.notify();
    }

    setInitialState(id) {
        this.snapshot('setInitialState', id ? { kind: 'state', id: id } : null);
        this.initialStateId = id;
        this.notify();
    }

    deleteState(id) {
        this.snapshot('deleteState', null);
        this.removeStates([id]);
        this.pruneUnusedEvents();
        this.notify();
    }

    /**
     * Deletes a whole selection — states, standalone transitions, and the transitions attached to
     * those states — as ONE undoable operation, so bringing a multi-select delete back takes a
     * single undo rather than one per entity.
     *
     * @param {{stateIds: Array<string>, transitionIds: Array<string>}} entityIds
     */
    deleteEntities(entityIds) {
        const stateIds = entityIds.stateIds || [];
        const transitionIds = entityIds.transitionIds || [];

        if (!stateIds.length && !transitionIds.length) {
            return;
        }

        // The selected ids are part of the key so deleting one selection after another never
        // coalesces two deletes into a single history entry.
        this.snapshot(`deleteEntities:${stateIds.join(',')}|${transitionIds.join(',')}`, null);

        this.transitions = this.transitions.filter((transition) => transitionIds.indexOf(transition.id) === -1);
        this.removeStates(stateIds);
        this.pruneUnusedEvents();
        this.notify();
    }

    /**
     * Drops the given states along with every transition that touches one of them. Shared by the
     * single- and multi-state deletes so the cascade cannot drift apart between them.
     *
     * @param {Array<string>} stateIds
     */
    removeStates(stateIds) {
        if (!stateIds.length) {
            return;
        }

        this.states = this.states.filter((state) => stateIds.indexOf(state.id) === -1);
        this.transitions = this.transitions.filter(
            (transition) =>
                stateIds.indexOf(transition.sourceId) === -1 && stateIds.indexOf(transition.targetId) === -1,
        );

        if (stateIds.indexOf(this.initialStateId) !== -1) {
            this.initialStateId = null;
        }
    }

    /**
     * Connects two states — drawing a link on the canvas. The transition starts out carrying the
     * default event rather than none, because an event-less transition is only meaningful together
     * with a condition.
     *
     * @param {string} sourceId
     * @param {string} targetId
     *
     */
    addTransition(sourceId, targetId) {
        const id = this.nextId('t');
        this.snapshot(`addTransition:${id}`, { kind: 'transition', id: id });

        const event = this.createEvent(this.uniqueEventName());
        this.events.push(event);
        const transition = this.createTransition(sourceId, targetId, event.id, id);
        this.transitions.push(transition);
        this.notify();

        return transition;
    }

    /**
     * Builds a transition entity without touching the model.
     *
     * @param {string} sourceId
     * @param {string} targetId
     * @param {string|null} eventId
     * @param {string|null} id reuse an id already allocated for the undo snapshot key
     */
    createTransition(sourceId, targetId, eventId, id) {
        return {
            id: id || this.nextId('t'),
            sourceId: sourceId,
            targetId: targetId,
            eventId: eventId,
            condition: '',
            happy: false,
        };
    }

    /**
     * Builds an event entity without touching the model.
     *
     * @param {string} name
     *
     * @returns {Object}
     */
    createEvent(name) {
        return { id: this.nextId('e'), name: name, eventType: '', timeout: '', command: '' };
    }

    deleteTransition(id) {
        this.snapshot('deleteTransition', null);
        this.transitions = this.transitions.filter((transition) => transition.id !== id);
        this.pruneUnusedEvents();
        this.notify();
    }

    setTransitionEvent(transitionId, eventId) {
        this.snapshot(`setTransitionEvent:${transitionId}`, { kind: 'transition', id: transitionId });
        const transition = this.getTransition(transitionId);

        if (transition) {
            transition.eventId = eventId;
        }

        this.pruneUnusedEvents();
        this.notify();
    }

    setTransitionCondition(transitionId, condition) {
        this.snapshot(`setTransitionCondition:${transitionId}`, { kind: 'transition', id: transitionId });
        const transition = this.getTransition(transitionId);

        if (transition) {
            transition.condition = condition;
        }

        this.notify();
    }

    setTransitionHappy(transitionId, isHappy) {
        this.snapshot(`setTransitionHappy:${transitionId}`, { kind: 'transition', id: transitionId });
        const transition = this.getTransition(transitionId);

        if (transition) {
            transition.happy = isHappy;
        }

        this.notify();
    }

    /**
     * @param {string} transitionId
     * @param {string} name
     *
     * @returns {void}
     */
    setTransitionEventName(transitionId, name) {
        this.snapshot(`setTransitionEventName:${transitionId}`, { kind: 'transition', id: transitionId });
        const transition = this.getTransition(transitionId);

        if (!transition) {
            return;
        }

        const trimmedName = name.trim();

        if (trimmedName === '') {
            transition.eventId = null;
            this.pruneUnusedEvents();
            this.notify();

            return;
        }

        const existingEvent = transition.eventId ? this.getEvent(transition.eventId) : null;

        if (existingEvent && this.countTransitionsUsingEvent(existingEvent.id) === 1) {
            existingEvent.name = trimmedName;
            this.notify();

            return;
        }

        const event = existingEvent ? { ...existingEvent, id: this.nextId('e') } : this.createEvent('');
        event.name = trimmedName;
        this.events.push(event);
        transition.eventId = event.id;
        this.notify();
    }

    linkTransitionEventByName(transitionId) {
        const transition = this.getTransition(transitionId);
        const transitionEvent = transition && transition.eventId ? this.getEvent(transition.eventId) : null;

        if (!transitionEvent) {
            return;
        }

        const namesakeEvent = this.events.find(
            (event) => event.id !== transitionEvent.id && event.name === transitionEvent.name,
        );

        if (!namesakeEvent) {
            return;
        }

        this.snapshot(`linkTransitionEvent:${transitionId}`, { kind: 'transition', id: transitionId });
        transition.eventId = namesakeEvent.id;
        this.pruneUnusedEvents();
        this.notify();
    }

    uniqueEventName() {
        const existing = this.events.map((event) => event.name);
        let index = this.events.length + 1;
        let candidate = `event ${index}`;

        while (existing.indexOf(candidate) !== -1) {
            index += 1;
            candidate = `event ${index}`;
        }

        return candidate;
    }

    updateEvent(id, key, value) {
        this.snapshot(`updateEvent:${id}:${key}`, this.transitionFocusForEvent(id));
        const event = this.getEvent(id);

        if (event) {
            event[key] = value;

            // A duration belongs to a timeout event only: kept past a switch to manual or on-enter
            // it stayed on the canvas label (and reappeared in the inspector) although the
            // definition XML no longer carried it.
            if (key === 'eventType' && value !== 'timeout') {
                event.timeout = '';
            }
        }

        this.notify();
    }

    /**
     * @param {string} eventId
     *
     * @returns {{kind: string, id: string}|null}
     */
    transitionFocusForEvent(eventId) {
        const transition = this.transitions.find((candidate) => candidate.eventId === eventId);

        return transition ? { kind: 'transition', id: transition.id } : null;
    }

    /**
     * Drops event entities no transition references any more. Called after operations that can
     * orphan an event, so the event dropdown never offers stale entries.
     */
    pruneUnusedEvents() {
        this.events = this.events.filter((event) => this.countTransitionsUsingEvent(event.id) > 0);
    }

    /**
     * Maps the id-based model to the name-based shape the codec serializes. Ids exist only inside
     * the builder; the `state-machine-01` XML speaks names.
     *
     * @returns {Object}
     */
    toDefinitionModel() {
        const states = this.states
            .filter((state) => state.name)
            .map((state) => {
                const node = { name: state.name };

                if (state.display) {
                    node.display = state.display;
                }

                return node;
            });

        const transitions = [];
        const referencedEventIds = {};

        this.transitions.forEach((transition) => {
            const source = this.stateName(transition.sourceId);
            const target = this.stateName(transition.targetId);

            if (!source || !target) {
                return;
            }

            const node = { source: source, target: target };
            const event = transition.eventId ? this.getEvent(transition.eventId) : null;

            if (event && event.name) {
                node.event = event.name;
                referencedEventIds[event.id] = true;
            }

            if (transition.condition) {
                node.condition = transition.condition;
            }

            if (transition.happy) {
                node.happy = true;
            }

            transitions.push(node);
        });

        const events = this.events
            .filter((event) => referencedEventIds[event.id])
            .map((event) => {
                const node = { name: event.name };

                if (event.eventType === 'onEnter') {
                    node.onEnter = true;
                }

                if (event.eventType === 'manual') {
                    node.manual = true;
                }

                if (event.eventType === 'timeout' && event.timeout) {
                    node.timeout = event.timeout;
                }

                if (event.command) {
                    node.command = event.command;
                }

                return node;
            });

        return {
            process: { name: this.processName, main: true },
            states: states,
            transitions: transitions,
            events: events,
        };
    }

    /**
     * Loads a codec-parsed (name-based) definition into the model — hydration of an existing
     * version. Not an undoable user edit, so the history is reset.
     *
     * @param {Object} definitionModel parsed by the codec
     * @param {string} initialStateName
     */
    loadDefinitionModel(definitionModel, initialStateName) {
        this.history = [];
        this.replaceFromDefinitionModel(definitionModel, initialStateName);
    }

    /**
     * Applies a manually edited definition (the raw-XML panel) as an undoable operation. The
     * initial state carries over by name; entities whose names survive the edit keep their ids, so
     * view state keyed by id (canvas positions) survives too.
     *
     * @param {Object} definitionModel parsed by the codec
     */
    applyDefinitionModel(definitionModel) {
        this.snapshot('applyDefinitionModel', null);
        this.replaceFromDefinitionModel(definitionModel, this.stateName(this.initialStateId));
    }

    /**
     * Replaces the model content with a name-based definition, resolving all references by name.
     * First occurrence wins on duplicate names (invalid input, e.g. restored after a rejected
     * save): references then stay on the original entity instead of silently jumping to the last.
     * An entity whose name already existed keeps its previous id.
     */
    replaceFromDefinitionModel(definitionModel, initialStateName) {
        const previousStateIdByName = this.firstWinsIdByName(this.states);
        const previousEventIdByName = this.firstWinsIdByName(this.events);

        this.states = [];
        this.events = [];
        this.transitions = [];
        this.initialStateId = null;

        if (definitionModel.process && definitionModel.process.name) {
            this.processName = definitionModel.process.name;
        }

        const stateIdByName = {};
        (definitionModel.states || []).forEach((state) => {
            const isFirstWithName = !(state.name in stateIdByName);
            const node = {
                id:
                    isFirstWithName && previousStateIdByName[state.name]
                        ? previousStateIdByName[state.name]
                        : this.nextId('s'),
                name: state.name,
                display: state.display || '',
            };
            this.states.push(node);

            if (isFirstWithName) {
                stateIdByName[state.name] = node.id;
            }
        });

        const eventIdByName = {};
        (definitionModel.events || []).forEach((event) => {
            let eventType = '';

            if (event.onEnter) {
                eventType = 'onEnter';
            }

            if (event.manual) {
                eventType = 'manual';
            }

            if (event.timeout) {
                eventType = 'timeout';
            }

            const isFirstWithName = !(event.name in eventIdByName);
            const node = {
                id:
                    isFirstWithName && previousEventIdByName[event.name]
                        ? previousEventIdByName[event.name]
                        : this.nextId('e'),
                name: event.name,
                eventType: eventType,
                timeout: event.timeout || '',
                command: event.command || '',
            };
            this.events.push(node);

            if (isFirstWithName) {
                eventIdByName[event.name] = node.id;
            }
        });

        (definitionModel.transitions || []).forEach((transition) => {
            const sourceId = stateIdByName[transition.source];
            const targetId = stateIdByName[transition.target];

            if (!sourceId || !targetId) {
                return;
            }

            this.transitions.push({
                id: this.nextId('t'),
                sourceId: sourceId,
                targetId: targetId,
                eventId: transition.event ? eventIdByName[transition.event] || null : null,
                condition: transition.condition || '',
                happy: !!transition.happy,
            });
        });

        this.initialStateId = stateIdByName[initialStateName] || null;
        this.pruneUnusedEvents();
        this.notify();
    }

    /**
     * @param {Array<{id: string, name: string}>} entities
     *
     * @returns {Object} name to id, first occurrence winning on duplicates
     */
    firstWinsIdByName(entities) {
        const idByName = {};
        entities.forEach((entity) => {
            if (!(entity.name in idByName)) {
                idByName[entity.name] = entity.id;
            }
        });

        return idByName;
    }
}

module.exports = { StateMachineModel };

'use strict';

/**
 * Inspector adapter — binds the side-panel form controls to the state machine model.
 *
 * Pure projection: `render()` fills the controls from the model and the current selection, and
 * every input dispatches a model operation. All user-facing text lives in the Twig template
 * (translated there); this module only moves values.
 *
 * The panel shows an entity's fields only when exactly ONE entity is selected. A multi-selection
 * gets the summary panel instead: the fields of several entities cannot be shown in one set of
 * controls, but deleting the whole selection is offered there.
 */

function qs(root, selector) {
    return root.querySelector(selector);
}

/**
 * Keeps the (translated) placeholder option from the template and appends the given values after
 * it, preserving the current value when still available.
 *
 * @param {HTMLSelectElement} select
 * @param {Array<string>} values
 */
function replacePluginOptions(select, values) {
    [...select.options].filter((option) => option.value !== '').forEach((option) => option.remove());
    appendPluginOptions(select, values);
}

function appendPluginOptions(select, values) {
    values.forEach((value) => {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = value;
        select.appendChild(option);
    });
}

/**
 * Rewriting a focused input's value would jump the caret to the end on every model notification,
 * so the value is only pushed when the user is not currently typing into that control.
 *
 * @param {HTMLInputElement} input
 * @param {string} value
 * @param {boolean} isSameEntity
 */
function setValueUnlessEditing(input, value, isSameEntity) {
    if (!isSameEntity || document.activeElement !== input) {
        input.value = value;
    }
}

/**
 * Selects the given value, adding an option for it when the dropdown does not offer it. A saved
 * definition may name a command or condition plugin that is no longer registered for the subject
 * type; showing it keeps the definition readable and stops a re-render from silently rewriting the
 * value to the placeholder.
 *
 * @param {HTMLSelectElement} select
 * @param {string} value
 */
function setSelectValue(select, value) {
    if (value && !select.querySelector(`option[value="${CSS.escape(value)}"]`)) {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = value;
        select.appendChild(option);
    }

    select.value = value;
}

/**
 * Binds a click handler to a control the template renders only in edit mode — the read-only view
 * page omits the delete buttons entirely.
 *
 * @param {HTMLElement|null} button
 * @param {Function} handler
 */
function bindOptionalButton(button, handler) {
    if (button) {
        button.addEventListener('click', handler);
    }
}

class StateMachineInspector {
    /**
     * @param {HTMLElement} root
     * @param {Object} model StateMachineModel
     * @param {{commands: Array<string>, conditions: Array<string>}} plugins
     * @param {{getSelection: Function, onDeleteSelection: Function}} callbacks `getSelection`
     *   returns the StateMachineSelection, read-only here
     */
    constructor(root, model, plugins, callbacks) {
        this.root = root;
        this.model = model;
        this.callbacks = callbacks;
        this.renderedEntityKey = null;

        appendPluginOptions(qs(root, '.js-sm-event-command'), plugins.commands);
        appendPluginOptions(qs(root, '.js-sm-transition-condition'), plugins.conditions);

        this.bindStateControls();
        this.bindTransitionControls();
    }

    /**
     * @param {{commands: Array<string>, conditions: Array<string>}} plugins
     */
    setAvailablePlugins(plugins) {
        replacePluginOptions(qs(this.root, '.js-sm-event-command'), plugins.commands);
        replacePluginOptions(qs(this.root, '.js-sm-transition-condition'), plugins.conditions);
        this.render();
    }

    bindStateControls() {
        qs(this.root, '.js-sm-state-name').addEventListener('input', (event) => {
            const selection = this.selectedState();

            if (selection) {
                this.model.renameState(selection.id, event.target.value);
            }
        });

        qs(this.root, '.js-sm-state-name').addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.target.blur();
            }
        });

        qs(this.root, '.js-sm-state-display').addEventListener('input', (event) => {
            const selection = this.selectedState();

            if (selection) {
                this.model.setStateDisplay(selection.id, event.target.value);
            }
        });

        qs(this.root, '.js-sm-state-initial').addEventListener('change', (event) => {
            const selection = this.selectedState();

            if (selection) {
                this.model.setInitialState(event.target.checked ? selection.id : null);
            }
        });

        bindOptionalButton(qs(this.root, '.js-sm-delete-state'), () => this.callbacks.onDeleteSelection());
    }

    bindTransitionControls() {
        qs(this.root, '.js-sm-transition-event-name').addEventListener('input', (event) => {
            const selection = this.selectedTransition();

            if (selection) {
                this.model.setTransitionEventName(selection.id, event.target.value);
            }
        });

        qs(this.root, '.js-sm-transition-event-name').addEventListener('change', () => {
            const selection = this.selectedTransition();

            if (selection) {
                this.model.linkTransitionEventByName(selection.id);
            }
        });

        qs(this.root, '.js-sm-transition-event-name').addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.target.blur();
            }
        });

        qs(this.root, '.js-sm-event-type').addEventListener('change', (event) => {
            const sharedEvent = this.selectedTransitionEvent();

            if (sharedEvent) {
                this.model.updateEvent(sharedEvent.id, 'eventType', event.target.value);
            }
        });

        qs(this.root, '.js-sm-event-timeout').addEventListener('input', (event) => {
            const sharedEvent = this.selectedTransitionEvent();

            if (sharedEvent) {
                this.model.updateEvent(sharedEvent.id, 'timeout', event.target.value);
            }
        });

        qs(this.root, '.js-sm-event-command').addEventListener('change', (event) => {
            const sharedEvent = this.selectedTransitionEvent();

            if (sharedEvent) {
                this.model.updateEvent(sharedEvent.id, 'command', event.target.value);
            }
        });

        qs(this.root, '.js-sm-transition-condition').addEventListener('change', (event) => {
            const selection = this.selectedTransition();

            if (selection) {
                this.model.setTransitionCondition(selection.id, event.target.value);
            }
        });

        qs(this.root, '.js-sm-transition-happy').addEventListener('change', (event) => {
            const selection = this.selectedTransition();

            if (selection) {
                this.model.setTransitionHappy(selection.id, event.target.checked);
            }
        });

        bindOptionalButton(qs(this.root, '.js-sm-delete-transition'), () => this.callbacks.onDeleteSelection());
        bindOptionalButton(qs(this.root, '.js-sm-delete-selection'), () => this.callbacks.onDeleteSelection());
    }

    /**
     * @returns {{kind: string, id: string}|null} the selected state, or null when the selection is
     *   empty, a transition, or more than one entity
     */
    selectedState() {
        const single = this.callbacks.getSelection().single();

        return single && single.kind === 'state' ? single : null;
    }

    /**
     * @returns {{kind: string, id: string}|null}
     */
    selectedTransition() {
        const single = this.callbacks.getSelection().single();

        return single && single.kind === 'transition' ? single : null;
    }

    selectedTransitionEvent() {
        const selection = this.selectedTransition();

        if (!selection) {
            return null;
        }

        const transition = this.model.getTransition(selection.id);

        return transition && transition.eventId ? this.model.getEvent(transition.eventId) : null;
    }

    render() {
        const selection = this.callbacks.getSelection();
        const single = selection.single();
        const entityKey = single ? `${single.kind}:${single.id}` : null;
        const isSameEntity = entityKey === this.renderedEntityKey;
        this.renderedEntityKey = entityKey;
        const emptyBox = qs(this.root, '.js-sm-inspector-empty');
        const stateBox = qs(this.root, '.js-sm-state-inspector');
        const transitionBox = qs(this.root, '.js-sm-transition-inspector');
        const multiBox = qs(this.root, '.js-sm-multi-inspector');

        emptyBox.classList.add('hidden');
        stateBox.classList.add('hidden');
        transitionBox.classList.add('hidden');

        if (multiBox) {
            multiBox.classList.add('hidden');
        }

        if (multiBox && selection.size() > 1) {
            this.renderMultiSelection(selection);
            multiBox.classList.remove('hidden');

            return;
        }

        const state = this.selectedState();

        if (state && this.model.getState(state.id)) {
            this.renderState(this.model.getState(state.id), isSameEntity);
            stateBox.classList.remove('hidden');

            return;
        }

        const transition = this.selectedTransition();

        if (transition && this.model.getTransition(transition.id)) {
            this.renderTransition(this.model.getTransition(transition.id), isSameEntity);
            transitionBox.classList.remove('hidden');

            return;
        }

        emptyBox.classList.remove('hidden');
    }

    /**
     * Fills the multi-selection summary. The counts are written into elements the template owns, so
     * the surrounding wording stays translatable.
     *
     * @param {Object} selection StateMachineSelection
     */
    renderMultiSelection(selection) {
        const countNode = qs(this.root, '.js-sm-multi-count');
        const stateCountNode = qs(this.root, '.js-sm-multi-state-count');
        const transitionCountNode = qs(this.root, '.js-sm-multi-transition-count');

        if (countNode) {
            countNode.textContent = String(selection.size());
        }

        if (stateCountNode) {
            stateCountNode.textContent = String(selection.stateIds().length);
        }

        if (transitionCountNode) {
            transitionCountNode.textContent = String(selection.transitionIds().length);
        }
    }

    renderState(state, isSameEntity) {
        setValueUnlessEditing(qs(this.root, '.js-sm-state-name'), state.name, isSameEntity);
        qs(this.root, '.js-sm-state-name').classList.toggle(
            'is-invalid',
            this.model.isStateNameTaken(state.id, state.name),
        );
        setValueUnlessEditing(qs(this.root, '.js-sm-state-display'), state.display, isSameEntity);
        qs(this.root, '.js-sm-state-initial').checked = this.model.initialStateId === state.id;
    }

    renderTransition(transition, isSameEntity) {
        const transitionEvent = transition.eventId ? this.model.getEvent(transition.eventId) : null;

        setValueUnlessEditing(
            qs(this.root, '.js-sm-transition-event-name'),
            transitionEvent ? transitionEvent.name : '',
            isSameEntity,
        );

        const eventDetails = qs(this.root, '.js-sm-event-details');
        eventDetails.classList.toggle('hidden', !transitionEvent);

        const transitionCount = transitionEvent ? this.model.countTransitionsUsingEvent(transitionEvent.id) : 0;
        qs(this.root, '.js-sm-event-transition-count').textContent = String(transitionCount);
        qs(this.root, '.js-sm-event-shared-hint').classList.toggle('hidden', transitionCount < 2);

        if (transitionEvent) {
            qs(this.root, '.js-sm-event-type').value = transitionEvent.eventType;
            setValueUnlessEditing(qs(this.root, '.js-sm-event-timeout'), transitionEvent.timeout, isSameEntity);
            setSelectValue(qs(this.root, '.js-sm-event-command'), transitionEvent.command);
            qs(this.root, '.js-sm-event-timeout-group').classList.toggle(
                'hidden',
                transitionEvent.eventType !== 'timeout',
            );
        }

        setSelectValue(qs(this.root, '.js-sm-transition-condition'), transition.condition);
        qs(this.root, '.js-sm-transition-happy').checked = transition.happy;
    }
}

module.exports = { StateMachineInspector };

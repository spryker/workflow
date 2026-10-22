'use strict';

import { findWorkflowSummary, getSelectedValues } from './workflow-summary';

const FORM_SELECTOR = '.js-workflow-process-form';
const NAME_SELECTOR = '.js-workflow-process-name';
const SUBJECT_TYPE_SELECTOR = '.js-workflow-process-subject-type';
const DESCRIPTION_SELECTOR = '.js-workflow-process-description';
const TRIGGER_EVENTS_SELECTOR = '.js-workflow-process-trigger-events';
const AVAILABLE_PLUGINS_SELECTOR = '.js-workflow-available-plugins-by-subject-type';
const PROCESS_NAME_CHANGE_EVENT = 'workflow:process-name-change';
const AVAILABLE_PLUGINS_CHANGE_EVENT = 'workflow:available-plugins-change';
// The builder serializes an empty name as <process name="">, which the definition parser rejects.
const FALLBACK_PROCESS_NAME = 'Workflow';

function readAvailablePluginsBySubjectType(form) {
    const node = form.querySelector(AVAILABLE_PLUGINS_SELECTOR);

    try {
        return JSON.parse(node ? node.textContent.trim() || '{}' : '{}');
    } catch {
        return {};
    }
}

/**
 * Select2 renders its own widget and only re-reads the native select on a jQuery `change`.
 *
 * @param {HTMLSelectElement} select
 */
function notifySelectChanged(select) {
    if (window.jQuery) {
        window.jQuery(select).trigger('change');
    }
}

/**
 * Select2 announces a new value with a jQuery `change`, which native listeners never receive.
 *
 * @param {HTMLSelectElement} select
 * @param {Function} callback
 */
function onSelectChanged(select, callback) {
    if (window.jQuery) {
        window.jQuery(select).on('change', callback);

        return;
    }

    select.addEventListener('change', callback);
}

class WorkflowCreateForm {
    constructor(form) {
        this.form = form;
        this.nameInput = form.querySelector(NAME_SELECTOR);
        this.subjectTypeSelect = form.querySelector(SUBJECT_TYPE_SELECTOR);
        this.descriptionInput = form.querySelector(DESCRIPTION_SELECTOR);
        this.triggerEventsSelect = form.querySelector(TRIGGER_EVENTS_SELECTOR);
        this.summary = findWorkflowSummary(form);
        this.availablePluginsBySubjectType = readAvailablePluginsBySubjectType(form);
        this.triggerEventOptions = this.triggerEventsSelect ? [...this.triggerEventsSelect.options] : [];

        this.bindName();
        this.bindSubjectType();
        this.bindSummary();
    }

    bindName() {
        if (!this.nameInput) {
            return;
        }

        this.nameInput.addEventListener('input', () => this.announceProcessName());
        this.announceProcessName();
    }

    announceProcessName() {
        const processName = this.nameInput.value.trim() || FALLBACK_PROCESS_NAME;

        document.dispatchEvent(new CustomEvent(PROCESS_NAME_CHANGE_EVENT, { detail: { processName } }));
    }

    bindSubjectType() {
        if (!this.subjectTypeSelect) {
            return;
        }

        onSelectChanged(this.subjectTypeSelect, () => this.applySubjectType());
        this.applySubjectType();
    }

    applySubjectType() {
        const subjectType = this.subjectTypeSelect.value;
        const plugins = this.availablePluginsBySubjectType[subjectType] || {};

        this.filterTriggerEvents(subjectType);

        document.dispatchEvent(
            new CustomEvent(AVAILABLE_PLUGINS_CHANGE_EVENT, {
                detail: {
                    commands: Array.isArray(plugins.commands) ? plugins.commands : [],
                    conditions: Array.isArray(plugins.conditions) ? plugins.conditions : [],
                },
            }),
        );
    }

    filterTriggerEvents(subjectType) {
        if (!this.triggerEventsSelect) {
            return;
        }

        const matchingOptions = this.triggerEventOptions.filter((option) => option.dataset.subjectType === subjectType);

        this.triggerEventOptions
            .filter((option) => !matchingOptions.includes(option))
            .forEach((option) => {
                option.selected = false;
            });

        this.triggerEventsSelect.replaceChildren(...matchingOptions);
        this.triggerEventsSelect.disabled = subjectType === '';
        notifySelectChanged(this.triggerEventsSelect);
    }

    bindSummary() {
        if (!this.summary) {
            return;
        }

        const renderSummary = () => this.renderSummary();

        this.nameInput?.addEventListener('input', renderSummary);
        this.descriptionInput?.addEventListener('input', renderSummary);
        [this.subjectTypeSelect, this.triggerEventsSelect]
            .filter(Boolean)
            .forEach((select) => $(select).on('change', renderSummary));
        this.renderSummary();
    }

    renderSummary() {
        this.summary.render({
            name: this.nameInput?.value ?? '',
            subjectType: this.subjectTypeSelect?.value ?? '',
            description: this.descriptionInput?.value ?? '',
            eventNames: getSelectedValues(this.triggerEventsSelect),
        });
    }
}

function init() {
    const form = document.querySelector(FORM_SELECTOR);

    if (!form) {
        return;
    }

    new WorkflowCreateForm(form);
}

init();

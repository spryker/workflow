'use strict';

import { findWorkflowSummary, getSelectedValues } from './workflow-summary';

// Saved values come from data attributes, not form.reset(): after a failed save the form is rendered
// with the submitted values.

const PANEL_SELECTOR = '.js-workflow-process-edit';
const FORM_SELECTOR = '.js-workflow-process-edit-form';
const DESCRIPTION_SELECTOR = '.js-workflow-process-edit-description';
const TRIGGER_EVENTS_SELECTOR = '.js-workflow-process-edit-trigger-events';
const PANEL_SHOWN_EVENT = 'shown.bs.collapse';
const PANEL_HIDDEN_EVENT = 'hidden.bs.collapse';
const PANEL_SHOWN_CLASS_NAME = 'show';

class WorkflowProcessEditForm {
    constructor(panel, form, summary) {
        this.panel = panel;
        this.form = form;
        this.summary = summary;
        this.descriptionInput = form.querySelector(DESCRIPTION_SELECTOR);
        this.triggerEventsSelect = form.querySelector(TRIGGER_EVENTS_SELECTOR);

        this.bindSummary();
        this.bindPanel();
    }

    bindSummary() {
        const renderSummary = () => this.renderSummary();

        this.descriptionInput?.addEventListener('input', renderSummary);

        if (this.triggerEventsSelect) {
            $(this.triggerEventsSelect).on('change', renderSummary);
        }

        // A failed save re-opens the panel with the submitted values, which the summary should already show.
        if (this.panel.classList.contains(PANEL_SHOWN_CLASS_NAME)) {
            this.renderSummary();
        }
    }

    bindPanel() {
        this.panel.addEventListener(PANEL_SHOWN_EVENT, () => this.renderSummary());
        this.panel.addEventListener(PANEL_HIDDEN_EVENT, () => this.discardChanges());
    }

    renderSummary() {
        this.summary.render({
            description: this.descriptionInput?.value ?? '',
            eventNames: getSelectedValues(this.triggerEventsSelect),
        });
    }

    discardChanges() {
        if (this.descriptionInput) {
            this.descriptionInput.value = this.form.dataset.savedDescription ?? '';
        }

        if (this.triggerEventsSelect) {
            const triggerEventNames = JSON.parse(this.form.dataset.triggerEventNames || '[]');

            Array.from(this.triggerEventsSelect.options).forEach((option) => {
                option.selected = triggerEventNames.includes(option.value);
            });

            // Select2 only re-reads the native select on a jQuery `change`.
            $(this.triggerEventsSelect).trigger('change');
        }

        this.summary.restore();
    }
}

function init() {
    const panel = document.querySelector(PANEL_SELECTOR);
    const form = panel?.querySelector(FORM_SELECTOR);
    const summary = findWorkflowSummary();

    if (!panel || !form || !summary) {
        return;
    }

    new WorkflowProcessEditForm(panel, form, summary);
}

init();

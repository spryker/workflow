'use strict';

const bootstrap = require('bootstrap');
const MODAL_SELECTOR = '#action-confirmation-modal';
const TITLE_SELECTOR = '.modal-title';
const MESSAGE_SELECTOR = '.js-action-confirmation-message';
const CONFIRM_BUTTON_SELECTOR = '.js-action-confirmation-confirm-button';
const TRIGGER_SELECTOR = 'button[type="submit"][data-confirm], a[data-confirm]';

function init() {
    const modalElement = document.querySelector(MODAL_SELECTOR);

    if (!modalElement) {
        return;
    }

    const title = modalElement.querySelector(TITLE_SELECTOR);
    const message = modalElement.querySelector(MESSAGE_SELECTOR);
    const confirmButton = modalElement.querySelector(CONFIRM_BUTTON_SELECTOR);

    if (!title || !message || !confirmButton) {
        return;
    }

    const modal = new bootstrap.Modal(modalElement);
    let pendingTrigger = null;

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest(TRIGGER_SELECTOR);

        if (!trigger) {
            return;
        }

        event.preventDefault();

        pendingTrigger = trigger;

        title.textContent = trigger.dataset.confirmTitle ?? '';
        message.textContent = trigger.dataset.confirmMessage ?? '';
        confirmButton.textContent = trigger.dataset.confirmButton ?? '';

        modal.show();
    });

    confirmButton.addEventListener('click', () => {
        if (!pendingTrigger) {
            return;
        }

        modal.hide();
        submitTrigger(pendingTrigger);
    });
}

function submitTrigger(trigger) {
    const form = trigger.closest('form');

    if (form) {
        form.requestSubmit ? form.requestSubmit(trigger) : form.submit();

        return;
    }

    window.location.assign(trigger.href);
}

init();

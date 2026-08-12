'use strict';

const bootstrap = require('bootstrap');

const MODAL_SELECTOR = '#version-activation-confirmation-modal';
const QUESTION_SELECTOR = '.js-version-activation-question';
const WARNING_SELECTOR = '.js-version-activation-warning';
const CONFIRM_BUTTON_SELECTOR = '.js-version-activation-confirm-button';
// The confirmable control is the form submit button, so confirming submits a CSRF-protected POST form
// instead of navigating to a GET URL.
const TRIGGER_SELECTOR = 'button[type="submit"][data-activate-confirm], a[data-activate-confirm]';

function renderTemplate(element, placeholders) {
    return Object.entries(placeholders).reduce(
        (message, [placeholder, value]) => message.replaceAll(placeholder, value),
        element.dataset.messageTemplate ?? '',
    );
}

function init() {
    const modalElement = document.querySelector(MODAL_SELECTOR);

    if (!modalElement) {
        return;
    }

    const question = modalElement.querySelector(QUESTION_SELECTOR);
    const warning = modalElement.querySelector(WARNING_SELECTOR);
    const confirmButton = modalElement.querySelector(CONFIRM_BUTTON_SELECTOR);

    if (!question || !warning || !confirmButton) {
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

        const targetVersion = trigger.dataset.targetVersion ?? '';
        const activeVersion = trigger.dataset.activeVersion ?? '';

        question.textContent = renderTemplate(question, { '%target%': targetVersion });
        warning.textContent = activeVersion ? renderTemplate(warning, { '%active%': activeVersion }) : '';
        warning.hidden = !activeVersion;

        modal.show();
    });

    confirmButton.addEventListener('click', () => {
        if (!pendingTrigger) {
            return;
        }

        modal.hide();

        const form = pendingTrigger.closest('form');

        if (form) {
            form.requestSubmit ? form.requestSubmit(pendingTrigger) : form.submit();

            return;
        }

        window.location.assign(pendingTrigger.href);
    });
}

init();

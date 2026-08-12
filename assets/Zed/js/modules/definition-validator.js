'use strict';

const FORM_SELECTOR = '.js-version-form';
const BUTTON_SELECTOR = '.js-validate-definition-button';
const RESULT_SELECTOR = '.js-definition-validation-result';

function getMessages(button) {
    return {
        validating: button.dataset.messageValidating ?? '',
        valid: button.dataset.messageValid ?? '',
        failed: button.dataset.messageFailed ?? '',
        heading: button.dataset.messageHeading ?? '',
    };
}

function createAlert(cssClass, text) {
    const alert = document.createElement('div');
    alert.className = `alert d-block ${cssClass}`;
    alert.textContent = text;

    return alert;
}

function createErrorAlert(heading, errors) {
    const alert = createAlert('alert-danger', '');

    const title = document.createElement('strong');
    title.textContent = heading;
    alert.appendChild(title);

    const list = document.createElement('ul');
    errors.forEach((error) => {
        const item = document.createElement('li');
        item.textContent = error.message ?? '';
        list.appendChild(item);
    });
    alert.appendChild(list);

    return alert;
}

function render(result, alert) {
    result.replaceChildren(alert);
}

async function requestValidation(form, url) {
    const response = await fetch(url, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new FormData(form),
    });

    if (!response.ok) {
        throw new Error(`Validation request failed with status ${response.status}.`);
    }

    return response.json();
}

function init() {
    const form = document.querySelector(FORM_SELECTOR);
    const button = document.querySelector(BUTTON_SELECTOR);
    const result = document.querySelector(RESULT_SELECTOR);

    if (!form || !button || !result) {
        return;
    }

    const messages = getMessages(button);
    const url = button.dataset.validateUrl;

    if (!url) {
        return;
    }

    button.addEventListener('click', async () => {
        render(result, createAlert('alert-info', messages.validating));

        try {
            const data = await requestValidation(form, url);

            if (data.isValid) {
                render(result, createAlert('alert-success', messages.valid));

                return;
            }

            render(result, createErrorAlert(messages.heading, data.errors ?? []));
        } catch {
            render(result, createAlert('alert-danger', messages.failed));
        }
    });
}

init();

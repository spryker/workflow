import { renderValidationErrors, renderValidationMessage } from './definition-validation-result';

const FORM_SELECTOR = '.js-version-form';
const BUTTON_SELECTOR = '.js-validate-definition-button';

function getMessages(button) {
    return {
        validating: button.dataset.messageValidating ?? '',
        valid: button.dataset.messageValid ?? '',
        failed: button.dataset.messageFailed ?? '',
    };
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

    if (!form || !button) {
        return;
    }

    const messages = getMessages(button);
    const url = button.dataset.validateUrl;

    if (!url) {
        return;
    }

    button.addEventListener('click', async () => {
        renderValidationMessage('alert-info', messages.validating);

        try {
            const data = await requestValidation(form, url);

            if (data.isValid) {
                renderValidationMessage('alert-success', messages.valid);

                return;
            }

            renderValidationErrors((data.errors ?? []).map((error) => error.message ?? ''));
        } catch {
            renderValidationMessage('alert-danger', messages.failed);
        }
    });
}

init();

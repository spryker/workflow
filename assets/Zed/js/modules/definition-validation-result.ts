const RESULT_SELECTOR = '.js-definition-validation-result';
const MESSAGE_TEMPLATE_SELECTOR = '.js-definition-validation-message-template';
const MESSAGE_SELECTOR = '.js-definition-validation-message';
const ERRORS_TEMPLATE_SELECTOR = '.js-definition-validation-errors-template';
const ERRORS_LIST_SELECTOR = '.js-definition-validation-errors-list';

export function renderValidationMessage(alertClass: string, message: string): void {
    const alert = cloneTemplate(MESSAGE_TEMPLATE_SELECTOR);
    const messageElement = alert?.querySelector(MESSAGE_SELECTOR);

    if (!alert || !messageElement) {
        return;
    }

    messageElement.classList.add(alertClass);
    messageElement.textContent = message;
    renderResult(alert);
}

export function renderValidationErrors(errorMessages: string[]): void {
    const alert = cloneTemplate(ERRORS_TEMPLATE_SELECTOR);
    const list = alert?.querySelector(ERRORS_LIST_SELECTOR);

    if (!alert || !list) {
        return;
    }

    list.append(...errorMessages.map(createErrorItem));
    renderResult(alert);
}

export function scrollToValidationResult(): void {
    document.querySelector(RESULT_SELECTOR)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function cloneTemplate(selector: string): DocumentFragment | null {
    const template = document.querySelector<HTMLTemplateElement>(selector);

    return template ? (template.content.cloneNode(true) as DocumentFragment) : null;
}

function createErrorItem(errorMessage: string): HTMLLIElement {
    const item = document.createElement('li');
    item.textContent = errorMessage;

    return item;
}

function renderResult(alert: DocumentFragment): void {
    document.querySelector(RESULT_SELECTOR)?.replaceChildren(alert);
}

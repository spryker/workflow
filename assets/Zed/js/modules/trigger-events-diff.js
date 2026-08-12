'use strict';

const FORM_SELECTOR = '.js-trigger-form';
const SELECT_SELECTOR = '[data-qa="trigger-events-select"]';

function getSelectedValues(select) {
    return [...select.selectedOptions].map((option) => option.value);
}

function appendCollectionInputs(form, formName, field, eventNames) {
    eventNames.forEach((eventName) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = `${formName}[${field}][]`;
        input.value = eventName;
        form.appendChild(input);
    });
}

function init() {
    const form = document.querySelector(FORM_SELECTOR);
    const select = form?.querySelector(SELECT_SELECTOR);

    if (!form || !select) {
        return;
    }

    const [formName] = (select.getAttribute('name') ?? '').split('[');

    if (!formName) {
        return;
    }

    const initiallySelected = getSelectedValues(select);

    form.addEventListener('submit', () => {
        const currentlySelected = getSelectedValues(select);

        const toBeAdded = currentlySelected.filter((eventName) => !initiallySelected.includes(eventName));
        const toBeRemoved = initiallySelected.filter((eventName) => !currentlySelected.includes(eventName));

        appendCollectionInputs(form, formName, 'eventNamesToBeAdded', toBeAdded);
        appendCollectionInputs(form, formName, 'eventNamesToBeRemoved', toBeRemoved);
    });
}

init();

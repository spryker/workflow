import type { StateMachineModel } from './state-machine-model';
import { renderValidationErrors, scrollToValidationResult } from './definition-validation-result';

const SAVE_SELECTOR = '.js-sm-save';
const VALIDATE_SELECTOR = '.js-validate-definition-button';

// The listener is in the capture phase on purpose: the Validate button's own click handler in
// definition-validator.js runs later, and stopImmediatePropagation only blocks the request if this runs first.
export function bindSubmitGuard(collectProblems: () => string[]): void {
    document.addEventListener(
        'click',
        (event) => {
            const button = findSubmitButton(event.target);

            if (!button) {
                return;
            }

            const problems = collectProblems();

            if (problems.length === 0) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            renderValidationErrors(problems);

            if (!reportInvalidFields(button)) {
                scrollToValidationResult();
            }
        },
        true,
    );
}

export function collectSubmitProblems(model: StateMachineModel, messages: DOMStringMap): string[] {
    const problems: string[] = [];

    if (!model.states.some((state: { name: string }) => state.name)) {
        problems.push(messages.messageNoStates ?? '');
    }

    if (!model.initialStateId) {
        problems.push(messages.messageNoInitialState ?? '');
    }

    if (model.states.some((state: { id: string; name: string }) => model.isStateNameTaken(state.id, state.name))) {
        problems.push(messages.messageDuplicateStateName ?? '');
    }

    return problems;
}

// The event target can be a text node, which has no closest().
function findSubmitButton(target: EventTarget | null): HTMLButtonElement | null {
    if (!(target instanceof Element)) {
        return null;
    }

    return target.closest<HTMLButtonElement>(`${SAVE_SELECTOR}, ${VALIDATE_SELECTOR}`);
}

// Blocking the Save click also skips the browser's required-field check, so it is run here.
function reportInvalidFields(button: HTMLButtonElement): boolean {
    const form = button.matches(SAVE_SELECTOR) ? button.form : null;

    return form ? !form.reportValidity() : false;
}

import { serialize, deserialize } from './state-machine-codec';
import type { StateMachineModel } from './state-machine-model';

const XML_APPLY_DEBOUNCE_IN_MILLISECONDS = 600;

export function bindXmlEditorPanel(root: HTMLElement, model: StateMachineModel, isReadOnly: boolean): void {
    const toggle = root.querySelector<HTMLElement>('.js-sm-advanced-toggle');

    toggle?.addEventListener('click', () => {
        const isHidden = root.querySelector('.js-sm-advanced-body')?.classList.toggle('hidden') ?? true;
        toggle.setAttribute('aria-expanded', String(!isHidden));
    });

    if (isReadOnly) {
        return;
    }

    const editor = root.querySelector<HTMLTextAreaElement>('.js-sm-xml-editor');
    let debounceTimer: ReturnType<typeof setTimeout> | undefined;

    editor?.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => applyManualDefinition(root, model), XML_APPLY_DEBOUNCE_IN_MILLISECONDS);
    });

    editor?.addEventListener('blur', () => {
        clearTimeout(debounceTimer);
        applyManualDefinition(root, model);
    });
}

function applyManualDefinition(root: HTMLElement, model: StateMachineModel): void {
    const editor = root.querySelector<HTMLTextAreaElement>('.js-sm-xml-editor');
    const errorBox = root.querySelector<HTMLElement>('.js-sm-xml-error');

    if (!editor || !errorBox) {
        return;
    }

    const xml = editor.value;
    const definitionModel = deserialize(xml);

    // Mid-edit input has no states yet; applying it would wipe the diagram.
    if (!definitionModel.states.length) {
        errorBox.textContent = (xml.trim() ? root.dataset.messageInvalidXml : root.dataset.messageIncompleteXml) ?? '';
        errorBox.classList.remove('hidden');

        return;
    }

    errorBox.classList.add('hidden');

    // Compare canonical forms: formatting-only edits must not create undo history entries.
    const canonicalXml = serialize(model.toDefinitionModel());

    if (serialize(definitionModel) === canonicalXml) {
        if (document.activeElement !== editor) {
            editor.value = canonicalXml;
        }

        return;
    }

    model.applyDefinitionModel(definitionModel);
}

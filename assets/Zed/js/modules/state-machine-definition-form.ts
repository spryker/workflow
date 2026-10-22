import { serialize, deserialize } from './state-machine-codec';
import type { StateMachineModel } from './state-machine-model';

interface DefinitionSource {
    xml: string;
    initialStateName: string;
}

export class StateMachineDefinitionForm {
    private readonly definitionField: HTMLInputElement | null;
    private readonly initialStateField: HTMLInputElement | null;

    constructor(
        private readonly root: HTMLElement,
        private readonly readOnly: boolean,
    ) {
        this.definitionField = document.querySelector('[name$="[stateMachineDefinition][definition]"]');
        this.initialStateField = document.querySelector('[name$="[stateMachineDefinition][initialState]"]');
    }

    hydrate(model: StateMachineModel): void {
        const source = this.readSource();

        if (!source.xml) {
            return;
        }

        const definitionModel = deserialize(source.xml);

        if (!definitionModel.states.length && !definitionModel.transitions.length) {
            return;
        }

        model.loadDefinitionModel(definitionModel, source.initialStateName);
    }

    sync(model: StateMachineModel): void {
        // The view page shows the definition as it is stored, comments and hand formatting included;
        // overwriting it with the model's canonical serialization would misreport what is saved.
        if (this.readOnly) {
            return;
        }

        const xml = serialize(model.toDefinitionModel());

        if (this.definitionField) {
            this.definitionField.value = xml;
        }

        if (this.initialStateField) {
            this.initialStateField.value = model.initialStateId ? model.stateName(model.initialStateId) : '';
        }

        const editor = this.findXmlEditor();

        // The canonical XML must not clobber the text while the user is typing in the editor.
        if (editor && document.activeElement !== editor) {
            editor.value = xml;
        }
    }

    private readSource(): DefinitionSource {
        if (this.readOnly) {
            return {
                xml: this.findXmlEditor()?.defaultValue.trim() ?? '',
                initialStateName: (this.root.dataset.initialState || '').trim(),
            };
        }

        return {
            xml: this.readPristineValue(this.definitionField),
            initialStateName: this.readPristineValue(this.initialStateField),
        };
    }

    // Reads defaultValue: it stays pristine even after an earlier sync overwrote the live value.
    private readPristineValue(field: HTMLInputElement | null): string {
        return field ? (field.defaultValue || field.value || '').trim() : '';
    }

    private findXmlEditor(): HTMLTextAreaElement | null {
        return this.root.querySelector<HTMLTextAreaElement>('.js-sm-xml-editor');
    }
}

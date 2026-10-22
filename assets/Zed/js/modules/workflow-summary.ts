const SUMMARY_SELECTOR = '.js-workflow-summary';
const NAME_SELECTOR = '.js-workflow-summary-name';
const SUBJECT_TYPE_SELECTOR = '.js-workflow-summary-subject-type';
const DESCRIPTION_SELECTOR = '.js-workflow-summary-description';
const TRIGGERS_SELECTOR = '.js-workflow-summary-triggers';
const TRIGGER_BADGE_CLASS_NAME = 'badge text-bg-light';
const TRIGGER_BADGE_QA = 'workflow-version-trigger';

export interface WorkflowSummaryValues {
    name?: string;
    subjectType?: string;
    description?: string;
    eventNames?: string[];
}

interface SavedContent {
    element: HTMLElement;
    content: Node;
}

export class WorkflowSummary {
    private readonly nameElement: HTMLElement | null;
    private readonly subjectTypeElement: HTMLElement | null;
    private readonly descriptionElement: HTMLElement | null;
    private readonly triggersElement: HTMLElement | null;
    private readonly noTriggersMessage: string;
    private readonly savedContent: SavedContent[];

    constructor(element: HTMLElement) {
        this.nameElement = element.querySelector<HTMLElement>(NAME_SELECTOR);
        this.subjectTypeElement = element.querySelector<HTMLElement>(SUBJECT_TYPE_SELECTOR);
        this.descriptionElement = element.querySelector<HTMLElement>(DESCRIPTION_SELECTOR);
        this.triggersElement = element.querySelector<HTMLElement>(TRIGGERS_SELECTOR);
        this.noTriggersMessage = this.triggersElement?.dataset.messageNoTriggers || '';
        this.savedContent = this.snapshot();
    }

    render(values: WorkflowSummaryValues): void {
        if (values.name !== undefined) {
            this.setText(this.nameElement, values.name);
        }

        if (values.subjectType !== undefined) {
            this.setText(this.subjectTypeElement, values.subjectType);
        }

        if (values.description !== undefined) {
            this.setText(this.descriptionElement, values.description);
        }

        if (values.eventNames !== undefined) {
            this.renderTriggers(values.eventNames);
        }
    }

    restore(): void {
        this.savedContent.forEach(({ element, content }) => {
            element.replaceChildren(...content.cloneNode(true).childNodes);
        });
    }

    private snapshot(): SavedContent[] {
        return [this.nameElement, this.subjectTypeElement, this.descriptionElement, this.triggersElement]
            .filter((element): element is HTMLElement => element !== null)
            .map((element) => ({ element, content: element.cloneNode(true) }));
    }

    private setText(element: HTMLElement | null, text: string): void {
        if (element) {
            element.textContent = text.trim() || element.dataset.messageEmpty || '';
        }
    }

    private renderTriggers(eventNames: string[]): void {
        if (!this.triggersElement) {
            return;
        }

        if (!eventNames.length) {
            const message = document.createElement('span');
            message.textContent = this.noTriggersMessage;
            this.triggersElement.replaceChildren(message);

            return;
        }

        this.triggersElement.replaceChildren(...eventNames.map((eventName) => createTriggerBadge(eventName)));
    }
}

function createTriggerBadge(eventName: string): HTMLSpanElement {
    const badge = document.createElement('span');
    badge.className = TRIGGER_BADGE_CLASS_NAME;
    badge.dataset.qa = TRIGGER_BADGE_QA;
    badge.textContent = eventName;

    return badge;
}

export function findWorkflowSummary(root: ParentNode = document): WorkflowSummary | null {
    const element = root.querySelector<HTMLElement>(SUMMARY_SELECTOR);

    return element ? new WorkflowSummary(element) : null;
}

export function getSelectedValues(select: HTMLSelectElement | null): string[] {
    return select ? [...select.selectedOptions].map((option) => option.value) : [];
}

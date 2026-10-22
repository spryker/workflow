import { STATE_HEIGHT, STATE_LABEL_FONT_SIZE } from './state-machine-canvas-shared';

const INLINE_EDITOR_INSET_RATIO = 0.07;
const INLINE_EDITOR_FONT_RATIO = STATE_LABEL_FONT_SIZE / STATE_HEIGHT;
const INLINE_EDITOR_LINE_HEIGHT = 1.4;
const INLINE_EDITOR_MINIMUM_FONT_SIZE = 9;

export class JointInlineRename {
    /**
     * @param {Object} paper dia.Paper
     * @param {HTMLElement} mountElement
     * @param {Function} findElement
     * @param {{onRenameState: Function, onRenameStart: Function, onRenameEnd: Function}} callbacks
     */
    constructor(paper, mountElement, findElement, callbacks) {
        this.paper = paper;
        this.mountElement = mountElement;
        this.findElement = findElement;
        this.callbacks = callbacks;
        this.editing = null;
        this.onPointerDownOutside = null;
    }

    /**
     * @param {Object} elementView dia.ElementView
     */
    open(elementView) {
        this.close(false);

        const element = elementView.model;
        const box = this.paper.localToClientRect(element.getBBox());
        const bounds = this.mountElement.getBoundingClientRect();

        const inset = box.width * INLINE_EDITOR_INSET_RATIO;
        const fontSize = Math.max(box.height * INLINE_EDITOR_FONT_RATIO, INLINE_EDITOR_MINIMUM_FONT_SIZE);
        const height = fontSize * INLINE_EDITOR_LINE_HEIGHT;

        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'workflow-canvas-inline-editor js-sm-inline-editor';
        input.value = element.get('stateName') || '';
        input.style.position = 'absolute';
        input.style.left = `${box.x - bounds.left + inset}px`;
        input.style.top = `${box.y - bounds.top + (box.height - height) / 2}px`;
        input.style.width = `${Math.max(box.width - inset * 2, height)}px`;
        input.style.height = `${height}px`;
        input.style.fontSize = `${fontSize}px`;

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                this.close(true);
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                this.close(false);
            }
        });
        input.addEventListener('blur', () => this.close(true));

        // Clicking away commits too. Blur alone does not carry it: pressing on the SVG canvas moves
        // no focus, so the field would stay open behind the click. Registered on the document now
        // that the opening click has already passed its capture phase, so it cannot close itself.
        this.onPointerDownOutside = (event) => {
            if (event.target !== input) {
                this.close(true);
            }
        };
        document.addEventListener('pointerdown', this.onPointerDownOutside, true);

        element.attr('label/opacity', 0);

        this.mountElement.appendChild(input);
        this.editing = { input: input, stateId: element.get('modelId') };
        input.focus();
        input.select();
        this.callbacks.onRenameStart();
    }

    /**
     * An empty name is never committed: a state without a name is dropped from the serialized definition.
     *
     * @param {boolean} shouldCommit
     */
    close(shouldCommit) {
        if (!this.editing) {
            return;
        }

        const { input, stateId } = this.editing;
        // Cleared first: removing the input fires its own blur, which lands back in here.
        this.editing = null;
        document.removeEventListener('pointerdown', this.onPointerDownOutside, true);

        const name = input.value.trim();
        const element = this.findElement(stateId);
        input.remove();
        this.callbacks.onRenameEnd();

        if (element) {
            element.attr('label/opacity', 1);
        }

        if (shouldCommit && name && (!element || name !== element.get('stateName'))) {
            this.callbacks.onRenameState(stateId, name);
        }
    }
}

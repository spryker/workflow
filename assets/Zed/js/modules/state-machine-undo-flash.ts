// Floats over the canvas, not in the layout's flash container: a message there pushes the whole diagram down.

import { type Labels } from './state-machine-page-data';

const UNDO_FLASH_CLASS = 'js-sm-undo-flash';
const UNDO_FLASH_TEXT_SELECTOR = '.js-sm-undo-flash-text';
const UNDO_FLASH_VISIBLE_IN_MILLISECONDS = 5000;

let hideTimer: ReturnType<typeof setTimeout> | undefined;

/**
 * The per-operation wording comes translated from the template.
 */
export function announceUndo(root: HTMLElement, undoLabels: Labels, operation: string): void {
    const template = root.querySelector<HTMLTemplateElement>('.js-sm-undo-flash-template');

    if (!template) {
        return;
    }

    showUndoFlash(
        root.querySelector<HTMLElement>('.js-sm-canvas') ?? document.body,
        template,
        undoLabels[operation] || undoLabels.genericUndo || '',
    );
}

export function showUndoFlash(container: HTMLElement, template: HTMLTemplateElement, message: string): void {
    removeUndoFlash();

    const flash = template.content.cloneNode(true) as DocumentFragment;
    const text = flash.querySelector(UNDO_FLASH_TEXT_SELECTOR);

    if (text) {
        text.textContent = message;
    }

    container.appendChild(flash);

    hideTimer = setTimeout(removeUndoFlash, UNDO_FLASH_VISIBLE_IN_MILLISECONDS);
}

export function removeUndoFlash(): void {
    clearTimeout(hideTimer);
    hideTimer = undefined;

    document.querySelectorAll(`.${UNDO_FLASH_CLASS}`).forEach((flash) => flash.remove());
}

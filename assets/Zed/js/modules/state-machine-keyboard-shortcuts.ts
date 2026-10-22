export interface KeyboardShortcutHandlers {
    onUndo: () => void;
    onDelete: () => void;
    hasSelection: () => boolean;
}

export function bindKeyboardShortcuts({ onUndo, onDelete, hasSelection }: KeyboardShortcutHandlers): void {
    document.addEventListener('keydown', (event) => {
        if (isTextEntryTarget(event.target)) {
            return;
        }

        if (isUndoShortcut(event)) {
            event.preventDefault();
            onUndo();

            return;
        }

        if (isDeleteKey(event) && hasSelection()) {
            event.preventDefault();
            onDelete();
        }
    });
}

export function isUndoShortcut(event: KeyboardEvent): boolean {
    if (event.key !== 'z' && event.key !== 'Z') {
        return false;
    }

    return (event.ctrlKey || event.metaKey) && !event.altKey && !event.shiftKey;
}

export function isDeleteKey(event: KeyboardEvent): boolean {
    return event.key === 'Delete' || event.key === 'Backspace';
}

export function isTextEntryTarget(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    return ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) || target.isContentEditable;
}

export interface AvailablePlugins {
    commands: string[];
    conditions: string[];
}

export type Labels = Record<string, string>;

export function readAvailablePlugins(): AvailablePlugins {
    const node = document.querySelector('.js-workflow-available-plugins');

    if (!node) {
        return { commands: [], conditions: [] };
    }

    try {
        const parsed = JSON.parse(node.textContent?.trim() || '{}');

        return {
            commands: Array.isArray(parsed.commands) ? parsed.commands : [],
            conditions: Array.isArray(parsed.conditions) ? parsed.conditions : [],
        };
    } catch {
        return { commands: [], conditions: [] };
    }
}

export function readLabels(root: HTMLElement, selector: string): Labels {
    const node = root.querySelector(selector);

    if (!node) {
        return {};
    }

    try {
        const parsed = JSON.parse(node.textContent?.trim() || '{}');

        return parsed && typeof parsed === 'object' ? parsed : {};
    } catch {
        return {};
    }
}

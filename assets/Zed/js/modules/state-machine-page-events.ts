import { type AvailablePlugins } from './state-machine-page-data';

// Dispatched on `document` by the workflow create page, whose process name and subject type are
// edited on the same page as the definition.
const PROCESS_NAME_CHANGE_EVENT = 'workflow:process-name-change';
const AVAILABLE_PLUGINS_CHANGE_EVENT = 'workflow:available-plugins-change';

export interface PageEventHandlers {
    onProcessNameChange: (processName: string) => void;
    onAvailablePluginsChange: (availablePlugins: AvailablePlugins) => void;
}

export function bindPageEvents({ onProcessNameChange, onAvailablePluginsChange }: PageEventHandlers): void {
    document.addEventListener(PROCESS_NAME_CHANGE_EVENT, (event) => {
        onProcessNameChange((event as CustomEvent<{ processName: string }>).detail.processName);
    });

    document.addEventListener(AVAILABLE_PLUGINS_CHANGE_EVENT, (event) => {
        onAvailablePluginsChange((event as CustomEvent<AvailablePlugins>).detail);
    });
}

export function preventSubmitOnEnter(root: HTMLElement): void {
    root.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target instanceof HTMLInputElement) {
            event.preventDefault();
        }
    });
}

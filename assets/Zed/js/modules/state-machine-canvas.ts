import type { StateMachineModel } from './state-machine-model';
import type { StateMachineSelection } from './state-machine-selection';

export type SelectableKind = 'state' | 'transition';

export interface SelectionRef {
    kind: SelectableKind;
    id: string;
}

export interface StateMachineCanvas {
    render(model: StateMachineModel, selection: StateMachineSelection): void;
    renderSelection?(model: StateMachineModel, selection: StateMachineSelection): void;
    zoomIn(): void;
    zoomOut(): void;
    zoomToFit(): void;
    resetZoom?(): void;
    revealState?(stateId: string): void;
}

export function placeInitialViewport(canvas: StateMachineCanvas, model: StateMachineModel): void {
    if (!model.states.length) {
        return;
    }

    if (canvas.resetZoom) {
        canvas.resetZoom();
    } else {
        canvas.zoomToFit();
    }

    if (model.initialStateId) {
        canvas.revealState?.(model.initialStateId);
    }
}

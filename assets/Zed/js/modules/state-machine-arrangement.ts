import { ensureStatePositions, labelsOutgrowEdges } from './state-machine-layout';
import type { StateMachineModel } from './state-machine-model';

export interface StatePosition {
    x: number;
    y: number;
}

export type PositionByStateId = Record<string, StatePosition>;

interface Viewport {
    width: number;
    height: number;
}

type Arrangement = Record<string, string>;

/**
 * Positions are view state, never part of the definition. The renderer writes dragged positions back into
 * the very same maps, so they are only ever mutated IN PLACE, never replaced.
 */
export class StateMachineArrangement {
    readonly positionByStateId: PositionByStateId = {};
    readonly edgeRouteByPair: Record<string, unknown> = {};
    private layoutArrangement: Arrangement | null = null;

    arrange(model: StateMachineModel, viewport: Viewport): void {
        this.relayoutWhenNeeded(model);

        if (ensureStatePositions(model, this.positionByStateId, viewport, this.edgeRouteByPair)) {
            this.layoutArrangement = this.capture();
        }
    }

    reset(): void {
        Object.keys(this.positionByStateId).forEach((stateId) => {
            delete this.positionByStateId[stateId];
        });
    }

    /**
     * Adding or removing states never re-lays out an arranged diagram: a new state is placed beside what it
     * connects to, which is what keeps the user's arrangement intact.
     */
    private relayoutWhenNeeded(model: StateMachineModel): void {
        if (this.isLayoutArrangement() && labelsOutgrowEdges(model, this.positionByStateId)) {
            this.reset();
        }
    }

    private isLayoutArrangement(): boolean {
        const rememberedArrangement = this.layoutArrangement;

        if (!rememberedArrangement) {
            return true;
        }

        const currentArrangement = this.capture();

        return Object.keys(rememberedArrangement).every(
            (stateId) =>
                !(stateId in currentArrangement) || currentArrangement[stateId] === rememberedArrangement[stateId],
        );
    }

    private capture(): Arrangement {
        const arrangement: Arrangement = {};

        Object.entries(this.positionByStateId).forEach(([stateId, position]) => {
            arrangement[stateId] = `${position.x},${position.y}`;
        });

        return arrangement;
    }
}

/**
 * JointJS canvas adapter — renders the state machine model with `@joint/core` and reports user
 * intents back.
 *
 * Implements the renderer contract in state-machine-canvas-contract.js. The adapter owns no domain
 * data; element positions are view state kept in the position map the builder passes in, shared
 * across renderer implementations so switching the renderer keeps the arrangement.
 *
 * Editing happens directly on the diagram: every state carries an inbound and an outbound
 * connection handle (drag the outbound one onto another state to connect them; the drawn edges
 * themselves attach to whichever side of a box faces the other state), and hovering a
 * state reveals two round affordances — "+" adds a connected state, the bin deletes it. In
 * read-only mode none of that is offered: the handles lose their magnets, the affordances are not
 * drawn and states cannot be moved, while selection, panning and zooming keep working.
 *
 * Selection is reported on pointer DOWN, not on click: a click is suppressed by the slightest
 * pointer movement, so on a draggable state it fires only sometimes — which is what made selecting
 * feel unreliable. Because selection repaints through `renderSelection()` and never rebuilds the
 * graph, selecting on pointer down does not destroy the cell the user has just started dragging.
 *
 * Gestures: click selects, a modifier-click (shift/ctrl/cmd) adds to or removes from the selection,
 * dragging blank canvas pans, and shift-dragging blank canvas rubber-band selects. Dragging any
 * state of a multi-state selection moves the whole selection.
 */

import { dia, shapes } from '@joint/core';
import { AbstractStateMachineCanvas } from './state-machine-canvas-contract';
import { getRendererRegistry } from './state-machine-renderer-registry';
import { transitionLabelLines } from './state-machine-transition-label';
import { GRID_SIZE, THEME } from './state-machine-canvas-shared';
import {
    createStateElement,
    createLink,
    createLinkLabel,
    isOutboundPort,
    paintStateSelection,
    paintLinkSelection,
} from './state-machine-joint-cells';
import { JointEdgeRouter, DEFAULT_ROUTER } from './state-machine-joint-edge-router';
import { JointInlineRename } from './state-machine-joint-inline-rename';
import { JointHoverAffordances } from './state-machine-joint-affordances';
import { JointBlankDrag } from './state-machine-joint-blank-drag';

const SCALE_MIN = 0.3;
const SCALE_MAX = 2.5;
const SCALE_STEP = 1.2;

// Padding kept around a state that `revealState()` scrolls into view, so it does not end up flush
// against the container edge with its handles clipped.
const REVEAL_PADDING = 40;

/**
 * Whether the event asks to extend the selection rather than replace it.
 *
 * @param {Object} event
 *
 * @returns {boolean}
 */
function isAdditiveEvent(event) {
    return Boolean(event && (event.shiftKey || event.ctrlKey || event.metaKey));
}

export class StateMachineCanvasJoint extends AbstractStateMachineCanvas {
    constructor(container, callbacks, positionByStateId, options) {
        super(container, callbacks, positionByStateId, options);
        this.scale = 1;
        this.stateDrag = null;
        this.selection = null;
        this.routeFrame = null;
        this.hasMovedSincePress = false;

        this.elementByStateId = {};
        this.linkByTransitionId = {};

        // The paper mounts into an inner element: dia.Paper#remove() removes its own el from the
        // DOM, and it must never take the shared builder container with it on a renderer switch.
        // Positioned, because the rubber-band overlay is placed against it.
        this.mountElement = document.createElement('div');
        this.mountElement.style.width = '100%';
        this.mountElement.style.height = '100%';
        this.mountElement.style.position = 'relative';
        container.appendChild(this.mountElement);

        this.graph = new dia.Graph({}, { cellNamespace: shapes });
        this.paper = new dia.Paper({
            el: this.mountElement,
            model: this.graph,
            width: '100%',
            height: '100%',
            cellViewNamespace: shapes,
            gridSize: GRID_SIZE,
            drawGrid: { name: 'dot', color: THEME.grid, thickness: 1 },
            background: { color: THEME.canvasBackground },
            // Labels go to the dedicated layer above the cells: drawn inside their own link they
            // were painted in link order, so a link added later crossed over an earlier label.
            labelsLayer: dia.Paper.Layers.LABELS,
            linkPinning: false,
            // Edges meet a state on whichever side faces the other end — left or right when it lies
            // beside the box, top or bottom when it lies above or below — so an arrow into a state
            // dragged over its source is not still glued to the box's left edge with the line
            // reaching around to it. A left-to-right layout is unchanged: those sides are exactly
            // where the connection handles sit. The router is left free to leave and enter on any
            // side for the same reason.
            defaultAnchor: { name: 'midSide', args: { mode: 'prefer-horizontal' } },
            defaultRouter: DEFAULT_ROUTER,
            defaultConnector: { name: 'rounded', args: { radius: 6 } },
            markAvailable: true,
            defaultLink: () => createLink(),
            // Only an outbound handle may start a link, and it must land on a state.
            validateMagnet: (elementView, magnet) => !this.options.readOnly && isOutboundPort(magnet),
            validateConnection: (sourceView, sourceMagnet, targetView) =>
                !this.options.readOnly && targetView.model.isElement(),
            // States stay draggable; links are display-only (no vertex editing, no re-wiring).
            interactive: (cellView) =>
                this.options.readOnly || cellView.model.isLink() ? false : { elementMove: true },
        });
        this.edgeRouter = new JointEdgeRouter(this.paper, positionByStateId, this.options.edgeRouteByPair || {});
        this.renameEditor = new JointInlineRename(
            this.paper,
            this.mountElement,
            (stateId) => this.elementByStateId[stateId],
            callbacks,
        );
        this.affordances = new JointHoverAffordances(callbacks);
        this.blankDrag = new JointBlankDrag(this.paper, this.mountElement, container, (area) =>
            this.callbacks.onSelectMany(this.#refsWithinArea(area)),
        );

        this.#bindPaperEvents();

        if (!this.options.readOnly) {
            this.#bindEditingIntents();
        }
    }

    destroy() {
        this.#cancelScheduledRoute();
        this.affordances.forget();
        this.renameEditor.close(false);
        this.blankDrag.destroy();
        this.paper.remove();
        this.mountElement.remove();
    }

    #bindPaperEvents() {
        // Pointer DOWN, not click: see the note on selection reliability in the module docblock.
        this.paper.on('element:pointerdown', (elementView, event) => {
            const stateId = elementView.model.get('modelId');

            // Pressing a state that is already part of a multi-selection must not collapse that
            // selection down to it — that press is how the user grabs the group to move it.
            if (!this.#isPressOnMultiSelectedState(stateId, event)) {
                this.callbacks.onSelect('state', stateId, isAdditiveEvent(event));
            }

            this.hasMovedSincePress = false;
            this.#beginStateDrag(stateId);
        });

        this.paper.on('link:pointerdown', (linkView, event) => {
            this.callbacks.onSelect('transition', linkView.model.get('modelId'), isAdditiveEvent(event));
        });

        this.paper.on('blank:pointerclick', () => {
            this.callbacks.onBlankClick();
        });

        // Blank-canvas drags do one of two things: plain drag pans the diagram, shift-drag sweeps a
        // rubber band to select everything under it. Shift is already the "extend the selection"
        // modifier for clicks, so the same key selecting in bulk keeps the gesture coherent — and
        // panning, the older behaviour, is left exactly as it was.
        this.paper.on('blank:pointerdown', (event) => {
            if (!this.options.readOnly && isAdditiveEvent(event)) {
                this.blankDrag.beginRubberBand(event);

                return;
            }

            this.blankDrag.beginPanning(event);
        });

        // Edges with their own bends are computed from where the boxes stand, so they must follow the drag.
        // Routed once per frame, however many pointer moves arrive in it.
        this.paper.on('element:pointermove', (elementView) => {
            this.hasMovedSincePress = true;
            this.#dragSelectionWithLeader(elementView.model.get('modelId'));
            this.#scheduleRoute();
        });

        this.paper.on('element:pointerup', (elementView) => {
            this.#finishStateDrag(elementView.model.get('modelId'));
            this.#cancelScheduledRoute();
            this.edgeRouter.route({ full: this.hasMovedSincePress });
        });
    }

    #scheduleRoute() {
        if (this.routeFrame !== null) {
            return;
        }

        this.routeFrame = window.requestAnimationFrame(() => {
            this.routeFrame = null;
            this.edgeRouter.route();
        });
    }

    #cancelScheduledRoute() {
        if (this.routeFrame !== null) {
            window.cancelAnimationFrame(this.routeFrame);
            this.routeFrame = null;
        }
    }

    /**
     * @param {string} stateId
     * @param {Object} event
     *
     * @returns {boolean} whether this is a plain press on one state of a multi-state selection
     */
    #isPressOnMultiSelectedState(stateId, event) {
        return (
            !isAdditiveEvent(event) &&
            Boolean(this.selection) &&
            this.selection.size() > 1 &&
            this.selection.has('state', stateId)
        );
    }

    /**
     * Remembers where every selected state stood when the drag started, so the rest of the
     * selection can follow the state under the pointer by the same delta.
     *
     * @param {string} leaderStateId the state the user grabbed
     */
    #beginStateDrag(leaderStateId) {
        this.stateDrag = null;

        if (this.options.readOnly || !this.selection || !this.selection.has('state', leaderStateId)) {
            return;
        }

        const leaderElement = this.elementByStateId[leaderStateId];
        const followerIds = this.selection.stateIds().filter((stateId) => stateId !== leaderStateId);

        if (!leaderElement || !followerIds.length) {
            return;
        }

        this.stateDrag = {
            leaderStateId: leaderStateId,
            leaderStart: leaderElement.position(),
            followers: followerIds
                .filter((stateId) => this.elementByStateId[stateId])
                .map((stateId) => ({
                    stateId: stateId,
                    element: this.elementByStateId[stateId],
                    start: this.elementByStateId[stateId].position(),
                })),
        };
    }

    /**
     * Moves the rest of the selection along with the dragged state. The delta is taken from the
     * dragged element's own position rather than from the pointer, so grid snapping applies to the
     * whole selection identically.
     *
     * @param {string} movedStateId
     */
    #dragSelectionWithLeader(movedStateId) {
        if (!this.stateDrag || this.stateDrag.leaderStateId !== movedStateId) {
            return;
        }

        const leaderPosition = this.elementByStateId[movedStateId].position();
        const deltaX = leaderPosition.x - this.stateDrag.leaderStart.x;
        const deltaY = leaderPosition.y - this.stateDrag.leaderStart.y;

        this.stateDrag.followers.forEach((follower) => {
            follower.element.position(follower.start.x + deltaX, follower.start.y + deltaY);
        });
    }

    /**
     * Writes the settled positions of the states that moved back into the shared position map. The
     * builder holds the same object, so nothing has to be reported.
     *
     * @param {string} draggedStateId
     */
    #finishStateDrag(draggedStateId) {
        const movedStateIds = [draggedStateId].concat(
            this.stateDrag ? this.stateDrag.followers.map((follower) => follower.stateId) : [],
        );

        movedStateIds.forEach((stateId) => {
            const element = this.elementByStateId[stateId];

            if (element) {
                const position = element.position();
                this.positionByStateId[stateId] = { x: position.x, y: position.y };
            }
        });

        this.stateDrag = null;
    }

    /**
     * Wires the three editing intents: the finished link drag, and the two hover affordances shown
     * next to the state under the pointer.
     */
    #bindEditingIntents() {
        // The link the user dropped is a drawing artefact, not model data: report the intent and
        // discard it, so the transition appears only through the model's own re-render.
        this.paper.on('link:connect', (linkView) => {
            const sourceStateId = linkView.model.getSourceElement().get('modelId');
            const targetStateId = linkView.model.getTargetElement().get('modelId');
            linkView.model.remove();

            this.callbacks.onConnect(sourceStateId, targetStateId);
        });

        // A clean click (no drag — JointJS withholds the click when the pointer moved) opens the
        // name for editing right inside the box; pressing and dragging still just moves the state.
        // A modifier-click is a selection gesture (add to / remove from the selection), not a
        // request to edit the name; nor is a click on one state of a multi-selection, which keeps
        // the selection for a group move. Opening the editor on those made shift-clicking several
        // states end up typing into one of them.
        this.paper.on('element:pointerclick', (elementView, event) => {
            if (isAdditiveEvent(event) || (this.selection && this.selection.size() > 1)) {
                return;
            }

            this.renameEditor.open(elementView);
        });

        this.paper.on('element:mouseenter', (elementView) => {
            this.affordances.show(elementView);
        });

        this.paper.on('element:mouseleave', () => {
            this.affordances.scheduleHide();
        });
    }

    /**
     * @param {Object} area g.Rect in model coordinates
     *
     * @returns {Array<{kind: string, id: string}>}
     */
    #refsWithinArea(area) {
        const stateIds = Object.keys(this.elementByStateId).filter((stateId) =>
            Boolean(this.elementByStateId[stateId].getBBox().intersect(area)),
        );

        const sweptStateIds = new Set(stateIds);
        const refs = stateIds.map((stateId) => ({ kind: 'state', id: stateId }));

        Object.keys(this.linkByTransitionId).forEach((transitionId) => {
            const link = this.linkByTransitionId[transitionId];

            if (sweptStateIds.has(link.get('sourceStateId')) && sweptStateIds.has(link.get('targetStateId'))) {
                refs.push({ kind: 'transition', id: transitionId });
            }
        });

        return refs;
    }

    zoomIn() {
        this.#setScale(this.scale * SCALE_STEP);
    }

    zoomOut() {
        this.#setScale(this.scale / SCALE_STEP);
    }

    #setScale(scale) {
        this.scale = Math.min(SCALE_MAX, Math.max(SCALE_MIN, scale));
        this.paper.scale(this.scale, this.scale);
    }

    zoomToFit() {
        if (!this.graph.getCells().length) {
            this.scale = 1;
            this.paper.scale(1, 1);
            this.paper.translate(0, 0);

            return;
        }

        this.paper.transformToFitContent({
            padding: 30,
            minScale: SCALE_MIN,
            maxScale: 1.5,
            verticalAlign: 'middle',
            horizontalAlign: 'middle',
        });
        this.scale = this.paper.scale().sx;
    }

    resetZoom() {
        this.#setScale(1);
        this.paper.translate(0, 0);
    }

    /**
     * Pans the diagram so the given state is on screen. A state already in view is left where it
     * is — yanking the viewport for something the user can already see is worse than not moving.
     *
     * @param {string} stateId
     */
    revealState(stateId) {
        const element = this.elementByStateId[stateId];

        if (!element) {
            return;
        }

        const stateBox = element.getBBox();
        const visibleArea = this.paper.getArea();

        // Shrunk by the padding, so a state flush against the edge still counts as needing a reveal.
        if (visibleArea.clone().inflate(-REVEAL_PADDING, -REVEAL_PADDING).containsRect(stateBox)) {
            return;
        }

        // Centre the state: it is off screen, so the shortest correction is rarely worth the extra
        // arithmetic, and landing it in the middle makes clear where the diagram jumped to.
        const translate = this.paper.translate();
        const targetCenterX = stateBox.x + stateBox.width / 2;
        const targetCenterY = stateBox.y + stateBox.height / 2;
        const visibleCenterX = visibleArea.x + visibleArea.width / 2;
        const visibleCenterY = visibleArea.y + visibleArea.height / 2;

        this.paper.translate(
            translate.tx - (targetCenterX - visibleCenterX) * this.scale,
            translate.ty - (targetCenterY - visibleCenterY) * this.scale,
        );
    }

    /**
     * Rebuilds the diagram from the model. Placement is not computed here — the builder tops up
     * the shared position map (dagre/grid) before every render, so this adapter only reads it.
     *
     * @param {Object} model StateMachineModel
     * @param {Object} selection StateMachineSelection
     */
    render(model, selection) {
        // The rebuild disposes of the hovered view and its buttons; drop the stale references. An
        // open inline editor is anchored to a box that is about to be replaced, so it goes too.
        this.#cancelScheduledRoute();
        this.affordances.forget();
        this.renameEditor.close(false);
        this.graph.clear();

        this.selection = selection;
        this.elementByStateId = {};
        this.linkByTransitionId = {};

        model.states.forEach((state) => {
            const element = createStateElement(state, {
                position: this.positionByStateId[state.id] || { x: 0, y: 0 },
                isInitial: model.initialStateId === state.id,
                isCurrent: this.#isCurrentState(state),
                readOnly: this.options.readOnly,
            });
            element.addTo(this.graph);
            this.elementByStateId[state.id] = element;
        });

        model.transitions.forEach((transition) => {
            const sourceElement = this.elementByStateId[transition.sourceId];
            const targetElement = this.elementByStateId[transition.targetId];

            if (!sourceElement || !targetElement) {
                return;
            }

            const link = createLink();
            link.set('modelId', transition.id);
            // Kept on the cell so the rubber band can tell which transitions run between two swept
            // states without reaching back into the model.
            link.set('sourceStateId', transition.sourceId);
            link.set('targetStateId', transition.targetId);
            // Attached to the box itself, not to a handle: the handles are for starting and dropping
            // a drag, while a drawn edge picks its side from where the other state is (defaultAnchor).
            link.source({ id: sourceElement.id });
            link.target({ id: targetElement.id });

            const lines = transitionLabelLines(model, transition);

            if (lines.length) {
                link.appendLabel(createLinkLabel(lines));
            }

            link.addTo(this.graph);
            this.linkByTransitionId[transition.id] = link;
        });

        this.edgeRouter.setCells(model, this.elementByStateId, this.linkByTransitionId);
        this.edgeRouter.route();
        this.renderSelection(model, selection);
    }

    /**
     * Repaints selection without touching the graph, so a drag in progress survives it.
     *
     * @param {Object} model StateMachineModel
     * @param {Object} selection StateMachineSelection
     */
    renderSelection(model, selection) {
        this.selection = selection;

        Object.keys(this.elementByStateId).forEach((stateId) => {
            paintStateSelection(this.paper, this.elementByStateId[stateId], selection.has('state', stateId));
        });

        Object.keys(this.linkByTransitionId).forEach((transitionId) => {
            paintLinkSelection(this.linkByTransitionId[transitionId], selection.has('transition', transitionId));
        });
    }

    #isCurrentState(state) {
        const currentStateName = this.options.currentStateName;

        return Boolean(currentStateName) && state.name === currentStateName;
    }
}

// The module's default renderer, always available as the fallback strategy.
getRendererRegistry().register('joint', StateMachineCanvasJoint);

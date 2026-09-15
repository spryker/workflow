'use strict';

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

const { dia, shapes, elementTools, highlighters } = require('@joint/core');
const { AbstractStateMachineCanvas } = require('./state-machine-canvas-contract');
const { getRendererRegistry } = require('./state-machine-renderer-registry');
const {
    FAN_OUT_TURN_BASE,
    FAN_OUT_TURN_STEP,
    PARALLEL_EDGE_GAP,
    buildFanOutIndex,
    buildParallelEdgeIndex,
    isForwardInPair,
    parallelGroupKey,
    parallelOffset,
    perpendicularMidpointVertex,
    selfLoopVertices,
} = require('./state-machine-edge-geometry');
const { transitionLabelLines, transitionLabelSize } = require('./state-machine-transition-label');
const {
    STATE_WIDTH,
    STATE_HORIZONTAL_PADDING,
    INITIAL_STATE_MARKER,
    stateBoxWidth,
    STATE_HEIGHT,
    STATE_CORNER_RADIUS,
    STATE_BORDER_WIDTH,
    HANDLE_RADIUS,
    AFFORDANCE_RADIUS,
    AFFORDANCE_OFFSET,
    GRID_SIZE,
    LABEL_LINE_HEIGHT,
    RUBBER_BAND_MINIMUM_SIZE,
    THEME,
} = require('./state-machine-canvas-shared');

const SCALE_MIN = 0.3;
const SCALE_MAX = 2.5;
const SCALE_STEP = 1.2;

const PORT_IN = 'in';
const PORT_OUT = 'out';

const SELECTION_HIGHLIGHTER_ID = 'selection';

const LABEL_HORIZONTAL_PADDING = 4;
const LABEL_VERTICAL_PADDING = 3;

// Shifts along an edge (as a ratio of its length) tried, in order, when a label would otherwise
// sit on a state box: the preferred spot first, then progressively further from it either way.
const LABEL_ESCAPE_SHIFTS = [0, -0.08, 0.08, -0.16, 0.16, -0.24, 0.24, -0.32, 0.32];

// A label is never slid right up to an end of its edge, where it would sit on the state itself.
const LABEL_DISTANCE_MINIMUM = 0.05;
const LABEL_DISTANCE_MAXIMUM = 0.95;

// Room kept between the labels of neighbouring parallel edges, on top of the labels' own size.
const PARALLEL_LABEL_CLEARANCE = 12;

// Padding kept around a state that `revealState()` scrolls into view, so it does not end up flush
// against the container edge with its handles clipped.
const REVEAL_PADDING = 40;

// The inline name editor drawn over a state box on double-click.
// The inline editor is sized from the state box AS DRAWN and carries the drawn label's own font
// size (14px against a 56px box), so at any zoom it reads as the label itself turning editable
// rather than as a control laid over it. The floor keeps it legible when zoomed far out.
const INLINE_EDITOR_INSET_RATIO = 0.07;
const INLINE_EDITOR_FONT_RATIO = 14 / 56;
const INLINE_EDITOR_LINE_HEIGHT = 1.4;
const INLINE_EDITOR_MINIMUM_FONT_SIZE = 9;

// Grace period before the hover affordances go away. The buttons are drawn outside the state's own
// bounding box, so reaching for one leaves the state and fires its mouseleave; without the delay the
// buttons would vanish while the pointer is still crossing the gap towards them.
const AFFORDANCE_HIDE_DELAY_IN_MILLISECONDS = 400;

// Glyphs for the hover affordances, drawn centred on the tool's own origin.
const GLYPH_PLUS = 'M -5 0 H 5 M 0 -5 V 5';
const GLYPH_BIN = 'M -5 -4 H 5 M -4 -4 V 5 H 4 V -4 M -2 -4 V -6 H 2 V -4';

/**
 * Markup for a round affordance button: a filled circle with a white glyph on top.
 *
 * @param {string} fillColor
 * @param {string} glyphPath
 *
 * @returns {Array<Object>} JointJS markup JSON
 */
function affordanceMarkup(fillColor, glyphPath) {
    return [
        {
            tagName: 'circle',
            selector: 'button',
            attributes: {
                r: AFFORDANCE_RADIUS,
                fill: fillColor,
                cursor: 'pointer',
            },
        },
        {
            tagName: 'path',
            selector: 'icon',
            attributes: {
                d: glyphPath,
                fill: 'none',
                stroke: '#ffffff',
                'stroke-width': 2,
                'stroke-linecap': 'round',
                'pointer-events': 'none',
            },
        },
    ];
}

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

/**
 * @param {{x: number, y: number, width: number, height: number}} first
 * @param {{x: number, y: number, width: number, height: number}} second
 *
 * @returns {boolean}
 */
function rectanglesIntersect(first, second) {
    return (
        first.x < second.x + second.width &&
        first.x + first.width > second.x &&
        first.y < second.y + second.height &&
        first.y + first.height > second.y
    );
}

class StateMachineCanvasJoint extends AbstractStateMachineCanvas {
    constructor(container, callbacks, positionByStateId, options) {
        super(container, callbacks, positionByStateId, options);
        this.scale = 1;
        this.panning = null;
        this.rubberBand = null;
        this.rubberBandElement = null;
        this.stateDrag = null;
        this.inlineRename = null;
        this.onPointerDownOutsideRename = null;
        this.selection = null;
        // The model last rendered, kept so a drag can re-route edges without a re-render.
        this.model = null;

        this.elementByStateId = {};
        this.linkByTransitionId = {};

        this.affordanceElementView = null;
        this.affordanceToolsElement = null;
        this.affordanceHideTimer = null;
        this.onAffordanceMouseEnter = () => this.cancelAffordanceHide();
        this.onAffordanceMouseLeave = () => this.scheduleAffordanceHide();

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
            defaultRouter: { name: 'manhattan', args: { padding: 20, step: 10 } },
            defaultConnector: { name: 'rounded', args: { radius: 6 } },
            markAvailable: true,
            defaultLink: () => this.createLink(),
            // Only an outbound handle may start a link, and it must land on a state.
            validateMagnet: (elementView, magnet) => !this.options.readOnly && this.isOutboundPort(magnet),
            validateConnection: (sourceView, sourceMagnet, targetView) =>
                !this.options.readOnly && targetView.model.isElement(),
            // States stay draggable; links are display-only (no vertex editing, no re-wiring).
            interactive: (cellView) =>
                this.options.readOnly || cellView.model.isLink() ? false : { elementMove: true },
        });

        this.bindPaperEvents();
        this.bindPanningAndRubberBand();

        if (!this.options.readOnly) {
            this.bindEditingIntents();
        }
    }

    destroy() {
        this.cancelAffordanceHide();
        this.finishInlineRename(false);
        this.removeRubberBandOverlay();
        document.removeEventListener('mousemove', this.onDocumentMouseMove);
        document.removeEventListener('mouseup', this.onDocumentMouseUp);
        this.paper.remove();
        this.mountElement.remove();
    }

    /**
     * JointJS puts the `port` attribute on the port markup's root node, which is the handle circle
     * itself here; `closest()` keeps the check correct should the markup ever gain a wrapper.
     *
     * @param {SVGElement|null} magnet
     *
     * @returns {boolean}
     */
    isOutboundPort(magnet) {
        const portNode = magnet ? magnet.closest('[port]') : null;

        return Boolean(portNode) && portNode.getAttribute('port') === PORT_OUT;
    }

    bindPaperEvents() {
        // Pointer DOWN, not click: see the note on selection reliability in the module docblock.
        this.paper.on('element:pointerdown', (elementView, event) => {
            const stateId = elementView.model.get('modelId');

            // Pressing a state that is already part of a multi-selection must not collapse that
            // selection down to it — that press is how the user grabs the group to move it.
            if (!this.isPressOnMultiSelectedState(stateId, event)) {
                this.callbacks.onSelect('state', stateId, isAdditiveEvent(event));
            }

            this.beginStateDrag(stateId);
        });

        this.paper.on('link:pointerdown', (linkView, event) => {
            this.callbacks.onSelect('transition', linkView.model.get('modelId'), isAdditiveEvent(event));
        });

        this.paper.on('blank:pointerclick', () => {
            this.callbacks.onBlankClick();
        });

        // Edges with their own bends (parallel bows, fan-out corridors) are computed from where the
        // boxes stand, so they follow the drag; left as rendered, a moved state's edge ran straight
        // to a bend that was still where the state used to be.
        this.paper.on('element:pointermove', (elementView) => {
            this.dragSelectionWithLeader(elementView.model.get('modelId'));
            this.routeEdges();
        });

        // Dragging states only updates the view-state position map — no model change, no re-render.
        this.paper.on('element:pointerup', (elementView) => {
            this.finishStateDrag(elementView.model.get('modelId'));
            this.routeEdges();
        });
    }

    /**
     * @param {string} stateId
     * @param {Object} event
     *
     * @returns {boolean} whether this is a plain press on one state of a multi-state selection
     */
    isPressOnMultiSelectedState(stateId, event) {
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
    beginStateDrag(leaderStateId) {
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
    dragSelectionWithLeader(movedStateId) {
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
    finishStateDrag(draggedStateId) {
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
    bindEditingIntents() {
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

            this.startInlineRename(elementView);
        });

        this.paper.on('element:mouseenter', (elementView) => {
            this.showAffordances(elementView);
        });

        this.paper.on('element:mouseleave', () => {
            this.scheduleAffordanceHide();
        });
    }

    /**
     * Opens a text field over the state so its name can be changed without going to the inspector.
     * JointJS core has no text editing of its own, so the editor is a plain input positioned over
     * the state box; Enter or leaving the field commits, Escape discards. Committing the unchanged
     * name is a no-op, so merely clicking a state never leaves an undo entry behind.
     *
     * @param {Object} elementView dia.ElementView
     */
    startInlineRename(elementView) {
        this.finishInlineRename(false);

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
                this.finishInlineRename(true);
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                this.finishInlineRename(false);
            }
        });
        input.addEventListener('blur', () => this.finishInlineRename(true));

        // Clicking away commits too. Blur alone does not carry it: pressing on the SVG canvas moves
        // no focus, so the field would stay open behind the click. Registered on the document now
        // that the opening click has already passed its capture phase, so it cannot close itself.
        this.onPointerDownOutsideRename = (event) => {
            if (event.target !== input) {
                this.finishInlineRename(true);
            }
        };
        document.addEventListener('pointerdown', this.onPointerDownOutsideRename, true);

        element.attr('label/opacity', 0);

        this.mountElement.appendChild(input);
        this.inlineRename = { input: input, stateId: element.get('modelId') };
        input.focus();
        input.select();
        this.callbacks.onRenameStart();
    }

    /**
     * Closes the inline editor. An empty name is never committed — a state without a name is
     * dropped from the serialized definition, which is not what deleting the text asks for.
     *
     * @param {boolean} shouldCommit
     */
    finishInlineRename(shouldCommit) {
        if (!this.inlineRename) {
            return;
        }

        const { input, stateId } = this.inlineRename;
        // Cleared first: removing the input fires its own blur, which lands back in here.
        this.inlineRename = null;
        document.removeEventListener('pointerdown', this.onPointerDownOutsideRename, true);

        const name = input.value.trim();
        const element = this.elementByStateId[stateId];
        input.remove();
        this.callbacks.onRenameEnd();

        if (element) {
            element.attr('label/opacity', 1);
        }

        if (shouldCommit && name && (!element || name !== element.get('stateName'))) {
            this.callbacks.onRenameState(stateId, name);
        }
    }

    /**
     * Mounts the affordance buttons on the hovered state, replacing the previously hovered state's.
     *
     * @param {Object} elementView dia.ElementView
     */
    showAffordances(elementView) {
        this.cancelAffordanceHide();

        if (this.affordanceElementView === elementView) {
            return;
        }

        this.hideAffordances();

        const tools = this.createAffordanceTools(elementView.model.get('modelId'));
        elementView.addTools(tools);

        this.affordanceElementView = elementView;
        this.affordanceToolsElement = tools.el;

        // The buttons render in the paper's tool layer, not inside the state, so hovering them has
        // to cancel the hide the state's own mouseleave just scheduled.
        tools.el.addEventListener('mouseenter', this.onAffordanceMouseEnter);
        tools.el.addEventListener('mouseleave', this.onAffordanceMouseLeave);
    }

    hideAffordances() {
        if (!this.affordanceElementView) {
            return;
        }

        this.affordanceToolsElement.removeEventListener('mouseenter', this.onAffordanceMouseEnter);
        this.affordanceToolsElement.removeEventListener('mouseleave', this.onAffordanceMouseLeave);
        this.affordanceElementView.removeTools();
        this.forgetAffordances();
    }

    scheduleAffordanceHide() {
        this.cancelAffordanceHide();
        this.affordanceHideTimer = setTimeout(() => this.hideAffordances(), AFFORDANCE_HIDE_DELAY_IN_MILLISECONDS);
    }

    cancelAffordanceHide() {
        clearTimeout(this.affordanceHideTimer);
        this.affordanceHideTimer = null;
    }

    /**
     * Drops the references without touching the views — for when the graph rebuild already disposed
     * of them.
     */
    forgetAffordances() {
        this.affordanceElementView = null;
        this.affordanceToolsElement = null;
    }

    /**
     * @param {string} stateId
     *
     * @returns {Object} dia.ToolsView with the "add connected state" and "delete state" buttons
     */
    createAffordanceTools(stateId) {
        return new dia.ToolsView({
            tools: [
                new elementTools.Button({
                    x: '100%',
                    y: '50%',
                    offset: { x: AFFORDANCE_OFFSET, y: 0 },
                    markup: affordanceMarkup(THEME.accent, GLYPH_PLUS),
                    action: () => this.callbacks.onAddConnectedState(stateId),
                }),
                new elementTools.Button({
                    x: '50%',
                    y: 0,
                    offset: { x: 0, y: -AFFORDANCE_OFFSET },
                    markup: affordanceMarkup(THEME.danger, GLYPH_BIN),
                    action: () => this.callbacks.onDeleteState(stateId),
                }),
            ],
        });
    }

    /**
     * Blank-canvas drags do one of two things: plain drag pans the diagram, shift-drag sweeps a
     * rubber band to select everything under it. Shift is already the "extend the selection"
     * modifier for clicks, so the same key selecting in bulk keeps the gesture coherent — and
     * panning, the older behaviour, is left exactly as it was.
     */
    bindPanningAndRubberBand() {
        this.paper.on('blank:pointerdown', (event) => {
            if (!this.options.readOnly && isAdditiveEvent(event)) {
                this.beginRubberBand(event);

                return;
            }

            this.beginPanning(event);
        });

        this.onDocumentMouseMove = (event) => {
            if (this.rubberBand) {
                this.updateRubberBand(event);

                return;
            }

            if (!this.panning) {
                return;
            }

            this.paper.translate(
                this.panning.translateX + (event.clientX - this.panning.clientX),
                this.panning.translateY + (event.clientY - this.panning.clientY),
            );
        };

        this.onDocumentMouseUp = () => {
            if (this.rubberBand) {
                this.finishRubberBand();

                return;
            }

            this.panning = null;
            this.container.style.cursor = '';
        };

        document.addEventListener('mousemove', this.onDocumentMouseMove);
        document.addEventListener('mouseup', this.onDocumentMouseUp);
    }

    beginPanning(event) {
        const translate = this.paper.translate();
        this.panning = {
            clientX: event.clientX,
            clientY: event.clientY,
            translateX: translate.tx,
            translateY: translate.ty,
        };
        this.container.style.cursor = 'grabbing';
    }

    beginRubberBand(event) {
        this.rubberBand = { startClientX: event.clientX, startClientY: event.clientY };
        this.rubberBandElement = document.createElement('div');
        this.rubberBandElement.style.position = 'absolute';
        this.rubberBandElement.style.pointerEvents = 'none';
        this.rubberBandElement.style.background = THEME.rubberBandFill;
        this.rubberBandElement.style.border = `1px solid ${THEME.rubberBandStroke}`;
        this.mountElement.appendChild(this.rubberBandElement);
        this.updateRubberBand(event);
    }

    updateRubberBand(event) {
        const bounds = this.mountElement.getBoundingClientRect();
        const rectangle = this.currentRubberBandClientRectangle(event);

        this.rubberBandElement.style.left = `${rectangle.x - bounds.left}px`;
        this.rubberBandElement.style.top = `${rectangle.y - bounds.top}px`;
        this.rubberBandElement.style.width = `${rectangle.width}px`;
        this.rubberBandElement.style.height = `${rectangle.height}px`;
    }

    /**
     * @param {Object} event
     *
     * @returns {{x: number, y: number, width: number, height: number}} in client coordinates
     */
    currentRubberBandClientRectangle(event) {
        return {
            x: Math.min(this.rubberBand.startClientX, event.clientX),
            y: Math.min(this.rubberBand.startClientY, event.clientY),
            width: Math.abs(event.clientX - this.rubberBand.startClientX),
            height: Math.abs(event.clientY - this.rubberBand.startClientY),
        };
    }

    /**
     * Reports everything the band swept over. A band too small to be a deliberate drag is dropped
     * without reporting: that gesture was a click, and `blank:pointerclick` already handles it by
     * clearing the selection.
     */
    finishRubberBand() {
        const bounds = this.rubberBandElement.getBoundingClientRect();
        this.removeRubberBandOverlay();
        this.rubberBand = null;

        if (bounds.width < RUBBER_BAND_MINIMUM_SIZE && bounds.height < RUBBER_BAND_MINIMUM_SIZE) {
            return;
        }

        const area = this.paper.clientToLocalRect(bounds.left, bounds.top, bounds.width, bounds.height);
        this.callbacks.onSelectMany(this.refsWithinArea(area));
    }

    removeRubberBandOverlay() {
        if (this.rubberBandElement) {
            this.rubberBandElement.remove();
            this.rubberBandElement = null;
        }
    }

    /**
     * The states the area touches, plus the transitions running between two of those states — a
     * band swept over part of the diagram selects that subgraph, edges included, which is what
     * makes "move these five states" and "delete this branch" one gesture.
     *
     * @param {{x: number, y: number, width: number, height: number}} area in model coordinates
     *
     * @returns {Array<{kind: string, id: string}>}
     */
    refsWithinArea(area) {
        const stateIds = Object.keys(this.elementByStateId).filter((stateId) =>
            rectanglesIntersect(this.elementByStateId[stateId].getBBox(), area),
        );

        const refs = stateIds.map((stateId) => ({ kind: 'state', id: stateId }));

        Object.keys(this.linkByTransitionId).forEach((transitionId) => {
            const link = this.linkByTransitionId[transitionId];

            if (
                stateIds.indexOf(link.get('sourceStateId')) !== -1 &&
                stateIds.indexOf(link.get('targetStateId')) !== -1
            ) {
                refs.push({ kind: 'transition', id: transitionId });
            }
        });

        return refs;
    }

    zoomIn() {
        this.setScale(this.scale * SCALE_STEP);
    }

    zoomOut() {
        this.setScale(this.scale / SCALE_STEP);
    }

    setScale(scale) {
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
        this.cancelAffordanceHide();
        this.finishInlineRename(false);
        this.forgetAffordances();
        this.graph.clear();

        this.model = model;
        this.selection = selection;
        this.elementByStateId = {};
        this.linkByTransitionId = {};

        model.states.forEach((state) => {
            const element = this.createStateElement(state, model.initialStateId === state.id);
            element.addTo(this.graph);
            this.elementByStateId[state.id] = element;
        });

        model.transitions.forEach((transition) => {
            const sourceElement = this.elementByStateId[transition.sourceId];
            const targetElement = this.elementByStateId[transition.targetId];

            if (!sourceElement || !targetElement) {
                return;
            }

            const link = this.createLink();
            link.set('modelId', transition.id);
            // Kept on the cell so the rubber band can tell which transitions run between two swept
            // states without reaching back into the model.
            link.set('sourceStateId', transition.sourceId);
            link.set('targetStateId', transition.targetId);
            // Attached to the box itself, not to a handle: the handles are for starting and dropping
            // a drag, while a drawn edge picks its side from where the other state is (defaultAnchor).
            link.source({ id: sourceElement.id });
            link.target({ id: targetElement.id });

            const lines = this.transitionLabelLines(model, transition);

            if (lines.length) {
                link.appendLabel(this.createLinkLabel(lines));
            }

            link.addTo(this.graph);
            this.linkByTransitionId[transition.id] = link;
        });

        this.routeEdges();
        this.renderSelection(model, selection);
    }

    /**
     * Gives every edge its route for where the boxes stand right now, then places the labels on
     * the routed paths. Run after a render and on every step of a drag: the bends are geometry of
     * the current positions, not of the model, so they cannot be computed once and kept.
     */
    routeEdges() {
        const model = this.model;

        if (!model) {
            return;
        }

        const parallelEdgeIndex = buildParallelEdgeIndex(model.transitions);
        const parallelGapByPair = this.measureParallelGaps(model);
        const fanOutCorridorIndex = this.buildFanOutCorridorIndex(model.transitions);

        model.transitions.forEach((transition) => {
            const link = this.linkByTransitionId[transition.id];
            const sourceElement = this.elementByStateId[transition.sourceId];
            const targetElement = this.elementByStateId[transition.targetId];

            if (!link || !sourceElement || !targetElement) {
                return;
            }

            // Back to the paper's default route first: an edge that no longer needs its bends
            // (its target dragged onto the source's row, say) must not keep the old ones.
            if (link.get('router')) {
                link.unset('router');
            }

            if (link.vertices().length) {
                link.vertices([]);
            }

            this.separateParallelEdge(link, sourceElement, targetElement, parallelEdgeIndex[transition.id], {
                isForward: isForwardInPair(transition),
                gap: parallelGapByPair[parallelGroupKey(transition)] || PARALLEL_EDGE_GAP,
            });
            this.routeFanOutEdge(link, sourceElement, targetElement, fanOutCorridorIndex[transition.id]);
        });

        this.placeLabels(model, fanOutCorridorIndex);
    }

    /**
     * How far apart the bowed edges of each state pair must be for their labels not to touch. The
     * labels sit side by side across the pair's line: across a horizontal line they stack, so it
     * is their height that matters; across a vertical one — a state dragged above its neighbour —
     * they sit next to each other, and the widest label sets the gap.
     *
     * @param {Object} model StateMachineModel
     *
     * @returns {Object} pair key to gap in pixels, never below PARALLEL_EDGE_GAP
     */
    measureParallelGaps(model) {
        const gapByPair = {};

        model.transitions.forEach((transition) => {
            const sourceElement = this.elementByStateId[transition.sourceId];
            const targetElement = this.elementByStateId[transition.targetId];

            if (!sourceElement || !targetElement || transition.sourceId === transition.targetId) {
                return;
            }

            const sourceCenter = sourceElement.getBBox().center();
            const targetCenter = targetElement.getBBox().center();
            const isVertical = Math.abs(targetCenter.y - sourceCenter.y) > Math.abs(targetCenter.x - sourceCenter.x);
            const size = transitionLabelSize(transitionLabelLines(model, transition));
            const needed = (isVertical ? size.width : size.height) + PARALLEL_LABEL_CLEARANCE;
            const key = parallelGroupKey(transition);

            gapByPair[key] = Math.max(gapByPair[key] || PARALLEL_EDGE_GAP, needed);
        });

        return gapByPair;
    }

    /**
     * Puts every label ON its own line, where the eye attributes it to that transition and no other.
     *
     * A fan-out edge carries its label on the final run into its target: the one stretch of its
     * route no sibling shares, on the target's own row. Halfway along the whole path it landed on
     * the vertical corridor next to the source, among the siblings' corridors, and pushing sibling
     * labels sideways off their lines to separate them there put a label visibly nearer another
     * state's edge than its own. Every other edge keeps the middle of its path.
     *
     * From that preferred spot a label is slid along its edge only when it would sit on a state
     * box: the layout reserves room between the ranks, but a long edge's midpoint can still fall
     * squarely over a state two ranks along. A label with no clear spot keeps its preferred one
     * rather than being hidden.
     *
     * @param {Object} model StateMachineModel
     * @param {Object} fanOutCorridorIndex from buildFanOutCorridorIndex
     */
    placeLabels(model, fanOutCorridorIndex) {
        const stateBoxes = Object.keys(this.elementByStateId).map((stateId) =>
            this.elementByStateId[stateId].getBBox(),
        );

        model.transitions.forEach((transition) => {
            const link = this.linkByTransitionId[transition.id];

            if (!link || !link.labels().length) {
                return;
            }

            const linkView = link.findView(this.paper);
            const size = transitionLabelSize(transitionLabelLines(model, transition));

            if (!linkView || !size.width) {
                return;
            }

            const preferred =
                fanOutCorridorIndex[transition.id] && link.vertices().length
                    ? this.finalRunMidpointRatio(linkView, link.vertices())
                    : 0.5;
            const candidates = LABEL_ESCAPE_SHIFTS.map((shift) =>
                Math.min(LABEL_DISTANCE_MAXIMUM, Math.max(LABEL_DISTANCE_MINIMUM, preferred + shift)),
            );
            const clearDistance = candidates.find((distance) =>
                this.isLabelSpotClear(linkView, distance, size, stateBoxes),
            );

            link.label(0, { position: { distance: clearDistance === undefined ? preferred : clearDistance } });
        });
    }

    /**
     * @param {Object} linkView dia.LinkView of a routed edge
     * @param {Array<{x: number, y: number}>} vertices the edge's route, the last one where its
     *   final run into the target begins
     *
     * @returns {number} the ratio along the whole path at the middle of that final run
     */
    finalRunMidpointRatio(linkView, vertices) {
        const lastTurnRatio = linkView.getClosestPointRatio(vertices[vertices.length - 1]);

        return lastTurnRatio + (1 - lastTurnRatio) / 2;
    }

    /**
     * @param {Object} linkView dia.LinkView
     * @param {number} distance ratio along the path
     * @param {{width: number, height: number}} size the label's own size
     * @param {Array<Object>} stateBoxes g.Rect per state
     *
     * @returns {boolean} whether a label of that size centred there touches no state box
     */
    isLabelSpotClear(linkView, distance, size, stateBoxes) {
        const point = linkView.getPointAtRatio(distance);

        if (!point) {
            return false;
        }

        const labelBox = {
            x: point.x - size.width / 2,
            y: point.y - size.height / 2,
            width: size.width,
            height: size.height,
        };

        return !stateBoxes.some((box) => rectanglesIntersect(labelBox, box));
    }

    /**
     * Bows an edge off the direct line when it shares its state pair with others, and arcs a
     * self-loop above its state. Routed by the shortest path alone they would all take the same
     * line, hiding every edge but the topmost and leaving it the only clickable one.
     *
     * @param {Object} link
     * @param {Object} sourceElement
     * @param {Object} targetElement
     * @param {{index: number, count: number, isSelfLoop: boolean}} edgePosition
     * @param {{isForward: boolean, gap: number}} bow whether the edge runs in its pair's canonical
     *   direction (the offsets are measured in that direction for the whole group), and how far
     *   apart the group's edges are
     */
    separateParallelEdge(
        link,
        sourceElement,
        targetElement,
        edgePosition,
        bow = { isForward: true, gap: PARALLEL_EDGE_GAP },
    ) {
        if (!edgePosition) {
            return;
        }

        if (edgePosition.isSelfLoop) {
            link.router('normal');
            link.vertices(selfLoopVertices(sourceElement.getBBox().center(), edgePosition.index));

            return;
        }

        if (edgePosition.count < 2) {
            return;
        }

        const sourceCenter = sourceElement.getBBox().center();
        const targetCenter = targetElement.getBBox().center();
        const offset = parallelOffset(edgePosition.index, edgePosition.count, bow.gap);
        const vertex = bow.isForward
            ? perpendicularMidpointVertex(sourceCenter, targetCenter, offset)
            : perpendicularMidpointVertex(targetCenter, sourceCenter, offset);

        if (!vertex) {
            return;
        }

        // The default (manhattan) router keeps the bow: it routes through the vertex, so each edge
        // steps out to its own offset and back — orthogonally, with the same rounded bends as every
        // other edge. Drawn as a straight polyline through the vertex the pair read as two
        // diagonals that did not bend at all once a state had been dragged off the row.
        link.vertices([vertex]);
    }

    /**
     * Assigns every edge of a fan-out (several transitions leaving one state to different targets) a
     * corridor: the vertical run in which it turns towards its target. The edge to the target
     * farthest away vertically turns FIRST, closest to the source; the others turn progressively
     * further out. That ordering is what keeps the fan from crossing itself — the general-purpose
     * router picks its turns by obstacle avoidance and happened to draw two edges of a fan across
     * each other.
     *
     * Which edges form a fan is decided by `buildFanOutIndex`, shared with the layout so that the
     * room the corridors take is reserved for exactly the edges that get one.
     *
     * @param {Array<Object>} transitions
     *
     * @returns {Object} transition id to {rank} — the corridor's ordinal, 0 nearest the source
     */
    buildFanOutCorridorIndex(transitions) {
        const fanOutIndex = buildFanOutIndex(transitions);
        const corridorByTransitionId = {};

        Object.keys(fanOutIndex).forEach((sourceId) => {
            const sourceElement = this.elementByStateId[sourceId];

            if (!sourceElement) {
                return;
            }

            const sourceCenterY = sourceElement.getBBox().center().y;

            fanOutIndex[sourceId]
                .map((transitionId) => {
                    const transition = transitions.find((candidate) => candidate.id === transitionId);
                    const targetElement = transition && this.elementByStateId[transition.targetId];

                    return targetElement
                        ? {
                              id: transitionId,
                              verticalDistance: Math.abs(targetElement.getBBox().center().y - sourceCenterY),
                          }
                        : null;
                })
                .filter(Boolean)
                .sort((first, second) => second.verticalDistance - first.verticalDistance)
                .forEach((sibling, rank) => {
                    corridorByTransitionId[sibling.id] = { rank: rank };
                });
        });

        return corridorByTransitionId;
    }

    /**
     * Routes a fan-out edge through its corridor: out of the source, along the corridor to the
     * target's row, then into the target. A target on the source's own row keeps the default
     * router — there is nothing to turn for. So does a target that no longer lies beyond the
     * corridor (dragged under its source, say): the corridor's turn would fall inside the target's
     * own box, with the arrow ending in the middle of it, where the default router finds a way in
     * from whichever side faces the source.
     *
     * @param {Object} link
     * @param {Object} sourceElement
     * @param {Object} targetElement
     * @param {{rank: number}|undefined} corridor
     */
    routeFanOutEdge(link, sourceElement, targetElement, corridor) {
        if (!corridor) {
            return;
        }

        const sourceBox = sourceElement.getBBox();
        const targetBox = targetElement.getBBox();
        const targetCenterY = targetBox.center().y;
        const sourceCenterY = sourceBox.center().y;
        const turnX = sourceBox.x + sourceBox.width + FAN_OUT_TURN_BASE + corridor.rank * FAN_OUT_TURN_STEP;

        if (Math.round(targetCenterY) === Math.round(sourceCenterY) || targetBox.x <= turnX + FAN_OUT_TURN_BASE) {
            return;
        }

        link.router('normal');
        link.vertices([
            { x: turnX, y: sourceCenterY },
            { x: turnX, y: targetCenterY },
        ]);
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
            this.paintStateSelection(this.elementByStateId[stateId], selection.has('state', stateId));
        });

        Object.keys(this.linkByTransitionId).forEach((transitionId) => {
            this.paintLinkSelection(this.linkByTransitionId[transitionId], selection.has('transition', transitionId));
        });
    }

    /**
     * Draws (or clears) the accent halo around a state — the border alone reads too weakly at the
     * canvas scale.
     *
     * @param {Object} element
     * @param {boolean} isSelected
     */
    paintStateSelection(element, isSelected) {
        element.attr('body/stroke', isSelected ? THEME.accent : THEME.stateStroke);

        const elementView = element.findView(this.paper);

        if (!elementView) {
            return;
        }

        if (!isSelected) {
            highlighters.stroke.remove(elementView, SELECTION_HIGHLIGHTER_ID);

            return;
        }

        highlighters.stroke.add(elementView, 'body', SELECTION_HIGHLIGHTER_ID, {
            padding: 3,
            rx: STATE_CORNER_RADIUS + 3,
            ry: STATE_CORNER_RADIUS + 3,
            attrs: { stroke: THEME.accent, strokeWidth: 2, strokeOpacity: 0.4 },
        });
    }

    /**
     * @param {Object} link
     * @param {boolean} isSelected
     */
    paintLinkSelection(link, isSelected) {
        const color = isSelected ? THEME.accent : THEME.link;

        link.attr('line/stroke', color);
        link.attr('line/strokeWidth', isSelected ? 2.5 : 1.5);
        link.attr('line/targetMarker/fill', color);

        // The label is outlined in the same accent, so which chip belongs to the selected line is
        // never in doubt even where neighbouring labels sit close together.
        if (link.labels().length) {
            link.label(0, { attrs: { rect: { stroke: isSelected ? THEME.accent : 'none' } } });
        }
    }

    /**
     * @param {Object} state
     * @param {boolean} isInitial
     *
     * @returns {Object} shapes.standard.Rectangle
     */
    createStateElement(state, isInitial) {
        const width = stateBoxWidth(state, isInitial);
        const element = new shapes.standard.Rectangle({
            position: this.positionByStateId[state.id] || { x: 0, y: 0 },
            size: { width: width, height: STATE_HEIGHT },
            attrs: {
                body: {
                    fill: THEME.stateFill,
                    stroke: THEME.stateStroke,
                    strokeWidth: STATE_BORDER_WIDTH,
                    rx: STATE_CORNER_RADIUS,
                    ry: STATE_CORNER_RADIUS,
                    cursor: this.options.readOnly ? 'default' : 'pointer',
                },
                label: {
                    text: (isInitial ? INITIAL_STATE_MARKER : '') + state.name,
                    fill: THEME.stateText,
                    fontSize: 14,
                    textWrap: {
                        width: width - STATE_HORIZONTAL_PADDING,
                        maxLineCount: 2,
                        ellipsis: true,
                    },
                },
            },
            ports: {
                groups: this.createPortGroups(),
                items: [
                    { id: PORT_IN, group: PORT_IN },
                    { id: PORT_OUT, group: PORT_OUT },
                ],
            },
        });
        element.set('modelId', state.id);
        // The drawn label is prefixed with the initial-state marker, so the plain name is kept
        // alongside it for the inline editor to start from.
        element.set('stateName', state.name);

        return element;
    }

    /**
     * The inbound handle accepts a dropped link, the outbound one starts a drag. Read-only drops
     * both magnets, so the handles stay visible as connection points without being interactive.
     *
     * @returns {Object} JointJS port group definitions
     */
    createPortGroups() {
        const handleAttributes = {
            r: HANDLE_RADIUS,
            fill: THEME.handleFill,
            stroke: THEME.handleStroke,
            strokeWidth: 2,
        };

        return {
            [PORT_IN]: {
                position: { name: 'left' },
                markup: [{ tagName: 'circle', selector: 'portBody' }],
                attrs: {
                    portBody: Object.assign({}, handleAttributes, {
                        magnet: this.options.readOnly ? false : 'passive',
                    }),
                },
            },
            [PORT_OUT]: {
                position: { name: 'right' },
                markup: [{ tagName: 'circle', selector: 'portBody' }],
                attrs: {
                    portBody: Object.assign({}, handleAttributes, {
                        magnet: !this.options.readOnly,
                        cursor: this.options.readOnly ? 'default' : 'crosshair',
                    }),
                },
            },
        };
    }

    /**
     * @returns {Object} shapes.standard.Link
     */
    createLink() {
        return new shapes.standard.Link({
            attrs: {
                line: {
                    stroke: THEME.link,
                    strokeWidth: 1.5,
                    targetMarker: { type: 'path', d: 'M 8 -4 0 0 8 4 z', fill: THEME.link, stroke: 'none' },
                },
            },
        });
    }

    /**
     * The label chip: white background behind the text, so it stays readable where it crosses the
     * line or the grid. The event name is the first line and is emphasised; the transition's
     * command, event type and condition follow it in a muted tone, which is what lets a reader tell
     * two transitions apart without clicking each one. Placed at the middle of the path until
     * `placeLabels()` finds it its own spot.
     *
     * @param {Array<string>} lines the event name first, detail lines after
     *
     * @returns {Object} JointJS label definition
     */
    createLinkLabel(lines) {
        return {
            attrs: {
                text: {
                    text: lines.join('\n'),
                    fill: THEME.linkLabelDetailText,
                    fontSize: 11,
                    lineHeight: LABEL_LINE_HEIGHT,
                    // Left transparent to the pointer so a click lands on the plate behind it,
                    // which is the whole label's hit area.
                    pointerEvents: 'none',
                    // Annotations index into the joined text, so this range is exactly the first line.
                    annotations: [
                        {
                            start: 0,
                            end: lines[0].length,
                            attrs: { fill: THEME.linkLabelText, fontSize: 12, fontWeight: 600 },
                        },
                    ],
                },
                rect: {
                    fill: THEME.linkLabelFill,
                    // Outlined in the accent while its transition is selected (paintLinkSelection).
                    stroke: 'none',
                    strokeWidth: 1.5,
                    rx: 2,
                    ry: 2,
                    x: `calc(x - ${LABEL_HORIZONTAL_PADDING})`,
                    y: `calc(y - ${LABEL_VERTICAL_PADDING})`,
                    width: `calc(w + ${LABEL_HORIZONTAL_PADDING * 2})`,
                    height: `calc(h + ${LABEL_VERTICAL_PADDING * 2})`,
                    // The label is the easiest part of a transition to aim at, so it selects it.
                    pointerEvents: 'auto',
                    cursor: 'pointer',
                },
            },
            position: { distance: 0.5 },
        };
    }

    /**
     * @param {Object} model StateMachineModel
     * @param {Object} transition
     *
     * @returns {Array<string>}
     */
    transitionLabelLines(model, transition) {
        return transitionLabelLines(model, transition);
    }
}

// The module's default renderer, always available as the fallback strategy.
getRendererRegistry().register('joint', StateMachineCanvasJoint);

module.exports = { StateMachineCanvasJoint };

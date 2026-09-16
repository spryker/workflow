'use strict';

/**
 * Renderer-independent placement of states. Positions are view state (the `state-machine-01` XML
 * carries no coordinates): the builder tops them up here before every render, so canvas adapters
 * only ever read the position map and never compute placement themselves.
 *
 * Two strategies. A diagram with no positions at all — just opened, or after "Auto-arrange" — gets
 * a dagre directed-graph layout over the whole graph. Once it is on screen the arrangement is the
 * user's: a state added afterwards is placed next to what it is connected to, in the first free
 * row, and nothing already placed moves. Laying the whole diagram out again after every addition
 * threw away every box the user had just dragged into place. Nothing is persisted: the diagram is
 * laid out afresh on opening.
 */

const dagre = require('@dagrejs/dagre');
const { STATE_HEIGHT, stateBoxWidth } = require('./state-machine-canvas-shared');
const { buildFanOutIndex, fanOutCorridorAllowance, edgeRouteKey } = require('./state-machine-edge-geometry');
const { transitionLabelLines, transitionLabelSize } = require('./state-machine-transition-label');

// The dagre spacing, reused for states placed one at a time so they line up with a laid-out
// diagram: a new state sits a rank's gap beside the state it is connected to, and rows of states
// are a node's gap apart — which is also the room a spot must have around it to count as free.
const RANK_SEPARATION = 120;
const NODE_SEPARATION = 50;
const CANVAS_MARGIN = 40;

const FALLBACK_VIEWPORT = { width: 1000, height: 800 };

function usableViewport(viewport) {
    if (!viewport || !viewport.width || !viewport.height) {
        return FALLBACK_VIEWPORT;
    }

    return viewport;
}

/**
 * @param {Object} model StateMachineModel
 * @param {Object} positionByStateId shared view-state map of state id to {x, y}, filled in place
 *
 * @returns {boolean} whether the whole diagram was laid out afresh (as opposed to nothing, or only
 *   the new states, being placed)
 */
function ensureStatePositions(model, positionByStateId, viewport, edgeRouteByPair) {
    const unplacedStates = model.states.filter((state) => !positionByStateId[state.id]);

    if (!unplacedStates.length) {
        return false;
    }

    if (unplacedStates.length === model.states.length) {
        applyDirectedGraphLayout(model, positionByStateId, usableViewport(viewport), edgeRouteByPair);

        return true;
    }

    unplacedStates.forEach((state) => {
        positionByStateId[state.id] = freePositionFor(model, state, positionByStateId);
    });

    return false;
}

/**
 * Where a state added to an arranged diagram goes: beside the placed state it is connected to —
 * to the right of the one it comes from (under that state's other targets when it already has
 * some, so a fan keeps growing as a column), or to the left of the one it leads to — and failing
 * any connection, below everything at the diagram's left edge. From there it moves down a row at a
 * time until it touches nothing already placed.
 *
 * @param {Object} model StateMachineModel
 * @param {Object} state the state without a position
 * @param {Object} positionByStateId
 *
 * @returns {{x: number, y: number}}
 */
function freePositionFor(model, state, positionByStateId) {
    const placedBoxes = model.states
        .filter((candidate) => positionByStateId[candidate.id])
        .map((candidate) => placedBox(model, candidate, positionByStateId));
    const width = stateBoxWidth(state, model.initialStateId === state.id);
    const source = connectedPlacedState(
        model,
        positionByStateId,
        (transition) => transition.targetId === state.id,
        'sourceId',
    );
    const target = connectedPlacedState(
        model,
        positionByStateId,
        (transition) => transition.sourceId === state.id,
        'targetId',
    );
    const candidate = source
        ? positionBesideSource(model, state, source, positionByStateId)
        : target
          ? {
                x: placedBox(model, target, positionByStateId).x - RANK_SEPARATION - width,
                y: positionByStateId[target.id].y,
            }
          : positionBelowEverything(placedBoxes);

    while (
        placedBoxes.some((box) =>
            boxesTooClose({ x: candidate.x, y: candidate.y, width: width, height: STATE_HEIGHT }, box),
        )
    ) {
        candidate.y += STATE_HEIGHT + NODE_SEPARATION;
    }

    return candidate;
}

/**
 * @param {Object} model StateMachineModel
 * @param {Object} positionByStateId
 * @param {function(Object): boolean} isRelevantTransition
 * @param {string} endKey the transition property naming the state at the other end
 *
 * @returns {Object|null} the first placed state at the other end of a matching transition
 */
function connectedPlacedState(model, positionByStateId, isRelevantTransition, endKey) {
    const transition = model.transitions.find(
        (candidate) =>
            isRelevantTransition(candidate) &&
            candidate.sourceId !== candidate.targetId &&
            positionByStateId[candidate[endKey]] &&
            model.getState(candidate[endKey]),
    );

    return transition ? model.getState(transition[endKey]) : null;
}

function positionBesideSource(model, state, source, positionByStateId) {
    const sourceBox = placedBox(model, source, positionByStateId);
    const siblingBoxes = model.transitions
        .filter(
            (transition) =>
                transition.sourceId === source.id &&
                transition.targetId !== state.id &&
                transition.targetId !== source.id &&
                positionByStateId[transition.targetId] &&
                model.getState(transition.targetId),
        )
        .map((transition) => placedBox(model, model.getState(transition.targetId), positionByStateId));

    if (!siblingBoxes.length) {
        return { x: sourceBox.x + sourceBox.width + RANK_SEPARATION, y: sourceBox.y };
    }

    // The column is where most of the siblings stand; one dragged elsewhere neither moves the
    // column nor pushes the new state down below itself.
    const columnX = mostCommonValue(siblingBoxes.map((box) => box.x));
    const columnBoxes = siblingBoxes.filter((box) => box.x === columnX);

    return {
        x: columnX,
        y: Math.max(...columnBoxes.map((box) => box.y + box.height)) + NODE_SEPARATION,
    };
}

/**
 * @param {Array<number>} values
 *
 * @returns {number} the value occurring most often; the smallest of those on a tie
 */
function mostCommonValue(values) {
    const countByValue = {};

    values.forEach((value) => {
        countByValue[value] = (countByValue[value] || 0) + 1;
    });

    return values.slice().sort((first, second) => countByValue[second] - countByValue[first] || first - second)[0];
}

function positionBelowEverything(placedBoxes) {
    if (!placedBoxes.length) {
        return { x: CANVAS_MARGIN, y: CANVAS_MARGIN };
    }

    return {
        x: Math.min(...placedBoxes.map((box) => box.x)),
        y: Math.max(...placedBoxes.map((box) => box.y + box.height)) + NODE_SEPARATION,
    };
}

function placedBox(model, state, positionByStateId) {
    const position = positionByStateId[state.id];

    return {
        x: position.x,
        y: position.y,
        width: stateBoxWidth(state, model.initialStateId === state.id),
        height: STATE_HEIGHT,
    };
}

/**
 * @returns {boolean} whether two boxes are closer than a node's gap to each other — exactly a
 *   node's gap apart, as laid-out rows are, still counts as clear
 */
function boxesTooClose(first, second) {
    const clearance = NODE_SEPARATION / 2;

    return (
        first.x - clearance < second.x + second.width + clearance &&
        first.x + first.width + clearance > second.x - clearance &&
        first.y - clearance < second.y + second.height + clearance &&
        first.y + first.height + clearance > second.y - clearance
    );
}

function applyDirectedGraphLayout(model, positionByStateId, viewport, edgeRouteByPair) {
    const candidates = [layoutInDirection(model, 'LR'), layoutInDirection(model, 'TB')];
    const best = candidates.reduce((winner, candidate) =>
        fitScale(candidate, viewport) > fitScale(winner, viewport) ? candidate : winner,
    );

    Object.keys(best.positionByStateId).forEach((stateId) => {
        positionByStateId[stateId] = best.positionByStateId[stateId];
    });

    if (!edgeRouteByPair) {
        return;
    }

    Object.keys(edgeRouteByPair).forEach((pair) => delete edgeRouteByPair[pair]);
    Object.keys(best.edgeRouteByPair).forEach((pair) => {
        edgeRouteByPair[pair] = best.edgeRouteByPair[pair];
    });
}

function fitScale(layout, viewport) {
    if (!layout.width || !layout.height) {
        return 0;
    }

    return Math.min(viewport.width / layout.width, viewport.height / layout.height);
}

function layoutInDirection(model, rankdir) {
    const graph = new dagre.graphlib.Graph();
    // `align: 'UL'` stacks a fork's children downward from the fork's own row. dagre's default
    // centres a parent between its children instead, which puts the first child ABOVE the parent —
    // a side branch then grows up into the main flow's rows, and the edge router has to weave its
    // way through, which is what read as tangled. Measured on the reported graph: 2 branch states
    // intruding into the main rows by default, none with 'UL', same overall size.
    graph.setGraph({
        rankdir: rankdir,
        align: 'UL',
        nodesep: NODE_SEPARATION,
        ranksep: RANK_SEPARATION,
        edgesep: 30,
        marginx: CANVAS_MARGIN,
        marginy: CANVAS_MARGIN,
    });
    graph.setDefaultEdgeLabel(() => ({}));

    model.states.forEach((state) => {
        graph.setNode(state.id, {
            width: stateBoxWidth(state, model.initialStateId === state.id),
            height: STATE_HEIGHT,
        });
    });

    const labelSizeByPair = measureEdgeLabels(model);

    Object.keys(labelSizeByPair).forEach((pair) => {
        const { sourceId, targetId, width, height } = labelSizeByPair[pair];
        // Handing dagre the label size makes it reserve that space between the ranks, so the
        // states end up far enough apart for the text to fit instead of being covered by it.
        graph.setEdge(sourceId, targetId, { width: width, height: height, labelpos: 'c' });
    });

    dagre.layout(graph);

    const placed = {};
    let left = Infinity;
    let top = Infinity;
    let right = -Infinity;
    let bottom = -Infinity;

    model.states.forEach((state) => {
        const node = graph.node(state.id);
        // dagre reports the node center; the renderers position by the top-left corner.
        const position = { x: node.x - node.width / 2, y: node.y - STATE_HEIGHT / 2 };

        placed[state.id] = position;
        left = Math.min(left, position.x);
        top = Math.min(top, position.y);
        right = Math.max(right, position.x + node.width);
        bottom = Math.max(bottom, position.y + STATE_HEIGHT);
    });

    const routes = {};

    graph.edges().forEach((edge) => {
        const points = graph.edge(edge).points || [];

        if (points.length < 3) {
            return;
        }

        routes[edgeRouteKey(edge.v, edge.w)] = {
            points: points.slice(1, -1).map((point) => ({ x: point.x, y: point.y })),
            sourcePosition: placed[edge.v],
            targetPosition: placed[edge.w],
        };
    });

    return {
        positionByStateId: placed,
        edgeRouteByPair: routes,
        width: model.states.length ? right - left : 0,
        height: model.states.length ? bottom - top : 0,
    };
}

/**
 * Total label size per state pair. Transitions sharing a pair are bowed apart perpendicular to the
 * connecting line, so their labels stack: the pair needs the widest of them and the sum of their
 * heights. Self-loops are left out — they are drawn as arcs over their own state and never sit
 * between two ranks.
 *
 * @param {Object} model StateMachineModel
 *
 * @returns {Object} pair key to {sourceId, targetId, width, height}
 */
function measureEdgeLabels(model) {
    const sizeByPair = {};
    const roomForLabel = labelRoomMeasurer(model);

    model.transitions.forEach((transition) => {
        if (transition.sourceId === transition.targetId) {
            return;
        }

        const key = `${transition.sourceId}|${transition.targetId}`;
        const size = transitionLabelSize(transitionLabelLines(model, transition));
        const known = sizeByPair[key];

        sizeByPair[key] = {
            sourceId: transition.sourceId,
            targetId: transition.targetId,
            width: Math.max(known ? known.width : 0, roomForLabel(transition, size.width)),
            height: (known ? known.height : 0) + size.height,
        };
    });

    return sizeByPair;
}

/**
 * The horizontal room a transition's label needs between its two ranks. A fan-out edge carries its
 * label on the final run into its target, past the corridors the fan turns in — so the corridors'
 * width is reserved on top of the label's own, or the run would be too short for it.
 *
 * @param {Object} model StateMachineModel
 *
 * @returns {function(Object, number): number} transition and its label width to the room needed
 */
function labelRoomMeasurer(model) {
    const fanOutIndex = buildFanOutIndex(model.transitions);
    const allowanceByTransitionId = {};

    Object.keys(fanOutIndex).forEach((sourceId) => {
        fanOutIndex[sourceId].forEach((transitionId) => {
            allowanceByTransitionId[transitionId] = fanOutCorridorAllowance(fanOutIndex[sourceId].length);
        });
    });

    return (transition, labelWidth) => labelWidth + (allowanceByTransitionId[transition.id] || 0);
}

/**
 * Whether any transition's label has outgrown the room between its two states — after the user
 * picked a long command or condition, say. Field edits leave the graph's structure alone, so this
 * is the other reason the diagram is laid out again: the layout then reserves the label's width
 * between the ranks and the edge stretches to carry it. Once that has happened the label fits, so
 * this stays false until the next change that makes it overflow — no oscillation.
 *
 * @param {Object} model StateMachineModel
 * @param {Object} positionByStateId shared view-state map of state id to {x, y}
 *
 * @returns {boolean}
 */
function labelsOutgrowEdges(model, positionByStateId) {
    const roomForLabel = labelRoomMeasurer(model);

    return model.transitions.some((transition) => {
        const sourceState = model.getState(transition.sourceId);
        const source = positionByStateId[transition.sourceId];
        const target = positionByStateId[transition.targetId];

        if (!sourceState || !source || !target || transition.sourceId === transition.targetId) {
            return false;
        }

        const sourceWidth = stateBoxWidth(sourceState, model.initialStateId === transition.sourceId);
        const span = target.x - (source.x + sourceWidth);

        // A backward or same-column edge rides its route; there is no gap for a label to outgrow.
        if (span <= 0) {
            return false;
        }

        const labelWidth = transitionLabelSize(transitionLabelLines(model, transition)).width;

        return roomForLabel(transition, labelWidth) > span;
    });
}

module.exports = { ensureStatePositions, labelsOutgrowEdges };

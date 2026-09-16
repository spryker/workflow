'use strict';

/**
 * Renderer-independent geometry for drawing several transitions between the same two states.
 *
 * A state machine routinely carries more than one transition between one pair of states — one per
 * event. Routed by the shortest path they all take the identical line, so only the topmost one is
 * visible and clickable and the rest are unreachable. Every edge therefore gets an index within its
 * group and is bowed off the direct line by a distinct offset.
 *
 * Grouping is by the UNORDERED state pair, so `a → b` and `b → a` share one group and separate from
 * each other as well. Self-loops form their own group per state and arc above the box at stacked
 * heights, since a perpendicular offset is meaningless when source and target coincide.
 *
 * The other grouping here is the fan-out: several transitions leaving one state for DIFFERENT
 * targets. A renderer routes each through its own vertical corridor next to the source; the
 * corridors take horizontal room, which the layout has to reserve the same way the renderer spends
 * it — hence the corridor metrics live here, next to the grouping both sides use.
 */

const { STATE_WIDTH } = require('./state-machine-canvas-shared');

// Distance between neighbouring edges of the same group, measured at the midpoint. Sized to clear
// a whole label (event name plus its command/condition lines) — at 34 the labels of two parallel
// transitions sat on top of each other.
const PARALLEL_EDGE_GAP = 56;

// Where the edges of a fan-out turn towards their targets, measured from the source's right edge:
// the first corridor sits FAN_OUT_TURN_BASE out, each further one FAN_OUT_TURN_STEP beyond it.
const FAN_OUT_TURN_BASE = 24;
const FAN_OUT_TURN_STEP = 18;

const SELF_LOOP_BASE_HEIGHT = 70;
const SELF_LOOP_HEIGHT_STEP = 32;
const SELF_LOOP_HALF_WIDTH = STATE_WIDTH / 3;

/**
 * Indexes every transition within its group of transitions sharing the same state pair.
 *
 * @param {Array<Object>} transitions model transitions
 *
 * @returns {Object} transition id to {index, count, isSelfLoop}
 */
function buildParallelEdgeIndex(transitions) {
    const groupMembers = {};

    transitions.forEach((transition) => {
        const key = groupKey(transition);
        groupMembers[key] = groupMembers[key] || [];
        groupMembers[key].push(transition.id);
    });

    const indexByTransitionId = {};

    transitions.forEach((transition) => {
        const members = groupMembers[groupKey(transition)];

        indexByTransitionId[transition.id] = {
            index: members.indexOf(transition.id),
            count: members.length,
            isSelfLoop: transition.sourceId === transition.targetId,
        };
    });

    return indexByTransitionId;
}

/**
 * Self-loops are keyed apart from ordinary transitions on the same state: they are drawn by a
 * different mechanism, so sharing a group with them would waste offsets and misplace the arcs.
 *
 * @param {Object} transition
 *
 * @returns {string}
 */
function groupKey(transition) {
    if (transition.sourceId === transition.targetId) {
        return `loop:${transition.sourceId}`;
    }

    return `pair:${[transition.sourceId, transition.targetId].sort().join('|')}`;
}

/**
 * @param {number} index the edge's position within its group
 * @param {number} count the size of the group
 * @param {number} gap distance between neighbouring edges of the group; PARALLEL_EDGE_GAP unless
 *   the group's labels need more
 *
 * @returns {number} the perpendicular displacement, spread symmetrically around the direct line so
 *   a group of one is not displaced at all and the middle edge of an odd group keeps the direct path
 */
function parallelOffset(index, count, gap = PARALLEL_EDGE_GAP) {
    return (index - (count - 1) / 2) * gap;
}

/**
 * Whether the transition runs in its pair's canonical direction. The bow offsets of a group are
 * measured in that one direction for every member: measured in each edge's own direction, a
 * reversed edge (`b → a` among `a → b`s) landed on the same side as a forward one.
 *
 * @param {Object} transition
 *
 * @returns {boolean}
 */
function isForwardInPair(transition) {
    return [transition.sourceId, transition.targetId].sort()[0] === transition.sourceId;
}

/**
 * The point a bowed edge is routed through: the midpoint between the two state centres, pushed off
 * the connecting line at a right angle.
 *
 * @param {{x: number, y: number}} sourceCenter
 * @param {{x: number, y: number}} targetCenter
 * @param {number} offset
 *
 * @returns {{x: number, y: number}|null} null when the two centres coincide, which leaves no line to
 *   be perpendicular to — the caller draws a self-loop instead
 */
function perpendicularMidpointVertex(sourceCenter, targetCenter, offset) {
    const deltaX = targetCenter.x - sourceCenter.x;
    const deltaY = targetCenter.y - sourceCenter.y;
    const length = Math.hypot(deltaX, deltaY);

    if (length === 0) {
        return null;
    }

    const midpointX = (sourceCenter.x + targetCenter.x) / 2;
    const midpointY = (sourceCenter.y + targetCenter.y) / 2;

    return {
        x: midpointX + (-deltaY / length) * offset,
        y: midpointY + (deltaX / length) * offset,
    };
}

/**
 * The two points a self-loop is routed through, arcing above the state box. Loops beyond the first
 * arc progressively higher so several loops on one state stay distinguishable.
 *
 * @param {{x: number, y: number}} center the state's centre
 * @param {number} index the loop's position within the state's group of self-loops
 *
 * @returns {Array<{x: number, y: number}>}
 */
function selfLoopVertices(center, index) {
    const height = SELF_LOOP_BASE_HEIGHT + index * SELF_LOOP_HEIGHT_STEP;

    return [
        { x: center.x - SELF_LOOP_HALF_WIDTH, y: center.y - height },
        { x: center.x + SELF_LOOP_HALF_WIDTH, y: center.y - height },
    ];
}

/**
 * Groups the edges of every fan-out by their source. Parallel edges (a shared pair, bowed apart
 * instead) and self-loops are not fan-out edges, and a state with a single such edge has no fan.
 *
 * @param {Array<Object>} transitions model transitions
 *
 * @returns {Object} source state id to the ids of its fan-out transitions, two or more per entry
 */
function buildFanOutIndex(transitions) {
    const parallelEdgeIndex = buildParallelEdgeIndex(transitions);
    const bySource = {};

    transitions.forEach((transition) => {
        const position = parallelEdgeIndex[transition.id];

        if (position.isSelfLoop || position.count > 1) {
            return;
        }

        bySource[transition.sourceId] = bySource[transition.sourceId] || [];
        bySource[transition.sourceId].push(transition.id);
    });

    Object.keys(bySource).forEach((sourceId) => {
        if (bySource[sourceId].length < 2) {
            delete bySource[sourceId];
        }
    });

    return bySource;
}

/**
 * Horizontal room a fan-out's corridors take past the source's right edge — where its outermost
 * corridor turns. The layout adds it to the labels of that fan, so the final run into every target
 * still has room for the label after the corridors have taken theirs.
 *
 * @param {number} count fan-out edges leaving the state
 *
 * @returns {number}
 */
function fanOutCorridorAllowance(count) {
    return FAN_OUT_TURN_BASE + (count - 1) * FAN_OUT_TURN_STEP;
}

function edgeRouteKey(sourceId, targetId) {
    return `${sourceId}\u0000${targetId}`;
}

module.exports = {
    edgeRouteKey,
    FAN_OUT_TURN_BASE,
    FAN_OUT_TURN_STEP,
    PARALLEL_EDGE_GAP,
    buildParallelEdgeIndex,
    buildFanOutIndex,
    fanOutCorridorAllowance,
    isForwardInPair,
    parallelGroupKey: groupKey,
    parallelOffset,
    perpendicularMidpointVertex,
    selfLoopVertices,
};

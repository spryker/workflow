'use strict';

/**
 * Visual tokens every canvas renderer draws with: state box metrics and the colour theme.
 *
 * Keeping them in one module guarantees the diagram looks the same no matter which renderer is
 * active — the bundled JointJS one, or a strategy a project registers through the renderer
 * registry. The values mirror the approved workflow-builder design.
 */

// The narrowest a state box gets; a longer name widens it (see stateBoxWidth).
const STATE_WIDTH = 176;

// Advance width of the 14px state label, the padding kept either side of it, and the point past
// which a name is ellipsised instead of widening the box any further.
const STATE_CHARACTER_WIDTH = 7.6;
const STATE_HORIZONTAL_PADDING = 24;
const STATE_MAXIMUM_WIDTH = 460;

// Drawn before the initial state's name, and paid for in its width.
const INITIAL_STATE_MARKER = '▶ ';
const STATE_HEIGHT = 56;
const STATE_CORNER_RADIUS = 8;
const STATE_BORDER_WIDTH = 2;

const HANDLE_RADIUS = 4;

// Round hover affordances rendered next to a state box: "+" on the right edge, delete above.
const AFFORDANCE_RADIUS = 12;
const AFFORDANCE_OFFSET = 24;

const GRID_SIZE = 11;

// Line spacing inside a transition label, which stacks the event name over its command, condition
// and timeout detail lines.
const LABEL_LINE_HEIGHT = 14;

// A rubber-band drag shorter than this in both axes was a click that wobbled, not a drag.
const RUBBER_BAND_MINIMUM_SIZE = 4;

const THEME = {
    accent: '#2bbe9b',
    accentHover: '#00ab89',
    danger: '#ee343b',
    stateFill: '#ffffff',
    stateStroke: '#e5e5e5',
    stateText: '#0a0a0a',
    currentStateFill: '#e8f1fc',
    currentStateStroke: '#2f6fd0',
    handleStroke: '#a7c8ea',
    handleFill: '#ffffff',
    link: '#737373',
    linkLabelFill: '#ffffff',
    linkLabelText: '#0a0a0a',
    // The transition's command/condition/timeout lines, muted against the event name above them.
    linkLabelDetailText: '#525252',
    canvasBackground: '#f5f5f5',
    grid: '#d4d4d4',
    rubberBandFill: 'rgba(43, 190, 155, 0.12)',
    rubberBandStroke: '#2bbe9b',
};

/**
 * Width of the box a state is drawn in: wide enough for its own name, never narrower than
 * STATE_WIDTH and never wider than STATE_MAXIMUM_WIDTH. Shared so the graph layout reserves exactly
 * what the renderer will draw — a box measured one way and drawn another would overlap its
 * neighbours.
 *
 * @param {Object} state model state
 * @param {boolean} isInitial whether the initial-state marker is drawn before the name
 *
 * @returns {number}
 */
function stateBoxWidth(state, isInitial) {
    const text = (isInitial ? INITIAL_STATE_MARKER : '') + String((state && state.name) || '');
    const wanted = Math.ceil(text.length * STATE_CHARACTER_WIDTH) + STATE_HORIZONTAL_PADDING * 2;

    return Math.min(Math.max(STATE_WIDTH, wanted), STATE_MAXIMUM_WIDTH);
}

module.exports = {
    STATE_WIDTH,
    STATE_HORIZONTAL_PADDING,
    INITIAL_STATE_MARKER,
    stateBoxWidth,
    STATE_HEIGHT,
    LABEL_LINE_HEIGHT,
    STATE_CORNER_RADIUS,
    STATE_BORDER_WIDTH,
    HANDLE_RADIUS,
    AFFORDANCE_RADIUS,
    AFFORDANCE_OFFSET,
    GRID_SIZE,
    RUBBER_BAND_MINIMUM_SIZE,
    THEME,
};

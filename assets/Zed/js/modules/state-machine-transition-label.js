/**
 * What the diagram writes next to a transition — renderer-independent, so every renderer describes
 * a transition identically, and the layout can reserve room for it.
 *
 * The label is deliberately short: the event name, and under it the command, the event type and
 * the condition as bare values — no field captions. Those are what distinguish transitions that
 * would otherwise look alike; what is left (happy path) stays in the inspector, because putting
 * everything on the canvas made labels tall enough to cover their neighbours.
 *
 * The event type is written as the definition XML names it (`onEnter`, `manual`, `timeout`), and a
 * timeout carries its duration after a colon — `timeout: 3 second`. Values only means no UI wording
 * at all, so nothing here needs translating.
 */

import { LABEL_LINE_HEIGHT } from './state-machine-canvas-shared';

// Deliberately generous: reserving too little space puts the label on top of a state box.
const TITLE_CHARACTER_WIDTH = 7.2;
const DETAIL_CHARACTER_WIDTH = 6.6;
const TITLE_LINE_HEIGHT = LABEL_LINE_HEIGHT + 1;
const LABEL_PADDING = 12;

/**
 * @param {Object} model StateMachineModel, for resolving the transition's event
 * @param {Object} transition
 *
 * @returns {Array<string>} the event name, then its command and type, then the transition's
 *   condition — bare values, one per line; empty when the transition carries nothing worth drawing
 */
export function transitionLabelLines(model, transition) {
    const event = transition.eventId ? model.getEvent(transition.eventId) : null;
    const lines = [];

    if (event && event.name) {
        lines.push(event.name);
    }

    if (event && event.command) {
        lines.push(event.command);
    }

    if (event && event.eventType) {
        lines.push(eventTypeLine(event));
    }

    if (transition.condition) {
        lines.push(transition.condition);
    }

    return lines;
}

/**
 * @param {Object} event a model event with a non-empty eventType
 *
 * @returns {string} the type alone, or `timeout: <duration>` once a timeout has its duration —
 *   the duration is meaningful only for a timeout event, so no other type ever shows one
 */
function eventTypeLine(event) {
    if (event.eventType === 'timeout' && event.timeout) {
        return `${event.eventType}: ${event.timeout}`;
    }

    return event.eventType;
}

/**
 * Estimated size of the drawn label, so the graph layout can keep states far enough apart for it
 * to fit instead of letting it spill over its neighbours.
 *
 * @param {Array<string>} lines
 *
 * @returns {{width: number, height: number}} zero for a transition with no label
 */
export function transitionLabelSize(lines) {
    if (!lines.length) {
        return { width: 0, height: 0 };
    }

    const titleWidth = lines[0].length * TITLE_CHARACTER_WIDTH;
    const detailWidth = lines
        .slice(1)
        .reduce((widest, line) => Math.max(widest, line.length * DETAIL_CHARACTER_WIDTH), 0);

    return {
        width: Math.ceil(Math.max(titleWidth, detailWidth)) + LABEL_PADDING * 2,
        height: TITLE_LINE_HEIGHT + (lines.length - 1) * LABEL_LINE_HEIGHT + LABEL_PADDING,
    };
}

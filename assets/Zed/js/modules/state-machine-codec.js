'use strict';

/**
 * State machine codec — serializes a visual-builder model into a Spryker `state-machine-01` XML
 * definition string.
 *
 * This is the create-only (v1) direction: model -> XML. The reverse direction (XML -> model, for
 * editing existing workflows) is a later step and intentionally not implemented here.
 *
 * The model shape mirrors the `state-machine-01` grammar used by the shipped demo workflow
 * (CompanyOnboarding). It is deliberately semantics-only: comments and original whitespace from a
 * hand-authored XML are not represented, because they cannot be reconstructed from a diagram.
 *
 * Model:
 *   {
 *     process: { name: string, main?: boolean },
 *     states: [ { name: string, display?: string } ],
 *     transitions: [
 *       { source: string, target: string, event?: string, condition?: string, happy?: boolean }
 *     ],
 *     events: [
 *       { name: string, onEnter?: boolean, manual?: boolean, timeout?: string, command?: string }
 *     ]
 *   }
 *
 * Notes reflected from the real demo:
 * - A state may omit `display` (e.g. `created`); the serializer must not invent one.
 * - A transition may omit `event` entirely (event-less, condition-only transition advanced by the
 *   condition cron).
 * - Several transitions may share the same `event` name (a branch); order is preserved as given.
 * - An event may combine `onEnter` with a `command`, or carry a `timeout` duration string verbatim.
 */

const XML_HEADER = '<?xml version="1.0"?>';
const STATE_MACHINE_NAMESPACE = 'spryker:state-machine-01';
const XSI_NAMESPACE = 'http://www.w3.org/2001/XMLSchema-instance';
const SCHEMA_LOCATION = 'spryker:state-machine-01 http://static.spryker.com/state-machine-01.xsd';
const INDENT = '    ';

/**
 * Escapes the five XML special characters for use inside attribute values and text nodes.
 *
 * @param {string} value
 *
 * @returns {string}
 */
function escapeXml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&apos;');
}

/**
 * Renders a single attribute (` name="value"`) only when the value is meaningful. A missing or empty
 * value produces no attribute, so optional attributes such as `display` are omitted rather than
 * emitted empty.
 *
 * @param {string} name
 * @param {string|boolean|undefined|null} value
 *
 * @returns {string}
 */
function attribute(name, value) {
    if (value === undefined || value === null || value === '' || value === false) {
        return '';
    }

    if (value === true) {
        return ` ${name}="true"`;
    }

    return ` ${name}="${escapeXml(value)}"`;
}

/**
 * @param {Array} states
 *
 * @returns {string}
 */
function renderStates(states) {
    const lines = states.map(
        (state) => `${INDENT.repeat(2)}<state${attribute('name', state.name)}${attribute('display', state.display)}/>`,
    );

    return `${INDENT}<states>\n${lines.join('\n')}\n${INDENT}</states>`;
}

/**
 * @param {Object} transition
 *
 * @returns {string}
 */
function renderTransition(transition) {
    const open = `${INDENT.repeat(2)}<transition${attribute('happy', transition.happy)}${attribute(
        'condition',
        transition.condition,
    )}>`;

    const children = [
        `${INDENT.repeat(3)}<source>${escapeXml(transition.source)}</source>`,
        `${INDENT.repeat(3)}<target>${escapeXml(transition.target)}</target>`,
    ];

    // A transition may be event-less (condition-only), so the <event> node is emitted only when set.
    if (transition.event) {
        children.push(`${INDENT.repeat(3)}<event>${escapeXml(transition.event)}</event>`);
    }

    return `${open}\n${children.join('\n')}\n${INDENT.repeat(2)}</transition>`;
}

/**
 * @param {Array} transitions
 *
 * @returns {string}
 */
function renderTransitions(transitions) {
    const blocks = transitions.map(renderTransition);

    return `${INDENT}<transitions>\n${blocks.join('\n\n')}\n${INDENT}</transitions>`;
}

/**
 * @param {Array} events
 *
 * @returns {string}
 */
function renderEvents(events) {
    const lines = events.map(
        (event) =>
            `${INDENT.repeat(2)}<event${attribute('name', event.name)}${attribute('onEnter', event.onEnter)}${attribute(
                'timeout',
                event.timeout,
            )}${attribute('manual', event.manual)}${attribute('command', event.command)}/>`,
    );

    return `${INDENT}<events>\n${lines.join('\n')}\n${INDENT}</events>`;
}

/**
 * Serializes a visual-builder model into a `state-machine-01` XML definition string.
 *
 * @param {Object} model
 *
 * @returns {string}
 */
function serialize(model) {
    const process = model.process || {};

    const processOpen = `${INDENT}<process${attribute('name', process.name)}${attribute('main', process.main)}>`;

    const body = [
        renderStates(model.states || []),
        renderTransitions(model.transitions || []),
        renderEvents(model.events || []),
    ].join('\n\n');

    return [
        XML_HEADER,
        `<statemachine`,
        `${INDENT}xmlns="${STATE_MACHINE_NAMESPACE}"`,
        `${INDENT}xmlns:xsi="${XSI_NAMESPACE}"`,
        `${INDENT}xsi:schemaLocation="${SCHEMA_LOCATION}"`,
        `>`,
        ``,
        processOpen,
        ``,
        body,
        ``,
        `${INDENT}</process>`,
        ``,
        `</statemachine>`,
        ``,
    ].join('\n');
}

/**
 * Reads a boolean-ish XML attribute. `state-machine-01` uses the literal string "true".
 *
 * @param {Element} element
 * @param {string} name
 *
 * @returns {boolean}
 */
function readBooleanAttribute(element, name) {
    return element.getAttribute(name) === 'true';
}

/**
 * Parses a `state-machine-01` XML definition string back into a visual-builder model.
 *
 * This is the reverse of {@link serialize}, used to hydrate the builder when editing an existing
 * version (or re-rendering after a failed save). It is tolerant of hand-authored XML: comments and
 * original whitespace are ignored, and optional attributes/nodes may be absent.
 *
 * Events are returned as their own list, exactly as the grammar defines them; a transition carries
 * only the referencing event name. A transition with no `<event>` child is an event-less
 * (condition-only) transition. Resolving names to shared event entities is the model's job.
 *
 * @param {string} xml
 */
function deserialize(xml) {
    const emptyModel = { process: { name: '', main: true }, states: [], transitions: [], events: [] };

    if (!xml || !xml.trim()) {
        return emptyModel;
    }

    const doc = new DOMParser().parseFromString(xml, 'application/xml');

    if (doc.getElementsByTagName('parsererror').length > 0) {
        return emptyModel;
    }

    const processNode = doc.querySelector('process');

    if (!processNode) {
        return emptyModel;
    }

    const process = {
        name: processNode.getAttribute('name') || '',
        main: readBooleanAttribute(processNode, 'main'),
    };

    const states = Array.from(processNode.querySelectorAll('states > state')).map((stateNode) => {
        const state = { name: stateNode.getAttribute('name') || '' };
        const display = stateNode.getAttribute('display');

        if (display) {
            state.display = display;
        }

        return state;
    });

    const events = Array.from(processNode.querySelectorAll('events > event')).map((eventNode) => ({
        name: eventNode.getAttribute('name') || '',
        onEnter: readBooleanAttribute(eventNode, 'onEnter'),
        manual: readBooleanAttribute(eventNode, 'manual'),
        timeout: eventNode.getAttribute('timeout') || '',
        command: eventNode.getAttribute('command') || '',
    }));

    const transitions = Array.from(processNode.querySelectorAll('transitions > transition')).map((transitionNode) => {
        const sourceNode = transitionNode.querySelector('source');
        const targetNode = transitionNode.querySelector('target');
        const eventNode = transitionNode.querySelector('event');

        const transition = {
            source: sourceNode ? sourceNode.textContent.trim() : '',
            target: targetNode ? targetNode.textContent.trim() : '',
        };

        const condition = transitionNode.getAttribute('condition');
        if (condition) {
            transition.condition = condition;
        }
        if (readBooleanAttribute(transitionNode, 'happy')) {
            transition.happy = true;
        }

        if (eventNode) {
            transition.event = eventNode.textContent.trim();
        }

        return transition;
    });

    return { process: process, states: states, transitions: transitions, events: events };
}

module.exports = { serialize, deserialize };

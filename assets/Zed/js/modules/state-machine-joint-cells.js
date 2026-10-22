import { shapes, highlighters } from '@joint/core';
import {
    STATE_HORIZONTAL_PADDING,
    INITIAL_STATE_MARKER,
    stateBoxWidth,
    STATE_HEIGHT,
    STATE_LABEL_FONT_SIZE,
    STATE_CORNER_RADIUS,
    STATE_BORDER_WIDTH,
    STATE_ACCENT_WIDTH,
    HANDLE_RADIUS,
    LABEL_LINE_HEIGHT,
    LABEL_TITLE_FONT_SIZE,
    LABEL_DETAIL_FONT_SIZE,
    THEME,
} from './state-machine-canvas-shared';

const CURRENT_STATE_TEST_SELECTOR = 'workflow-current-state';

const PORT_IN = 'in';
const PORT_OUT = 'out';

const SELECTION_HIGHLIGHTER_ID = 'selection';
const SELECTION_HALO_PADDING = 3;

const LINK_STROKE_WIDTH = 1.5;
const SELECTED_LINK_STROKE_WIDTH = 2.5;

const LABEL_CORNER_RADIUS = 12;
const LABEL_HORIZONTAL_PADDING = 10;
const LABEL_VERTICAL_PADDING = 3;
const LABEL_STROKE_WIDTH = 1;
const SELECTED_LABEL_STROKE_WIDTH = 2;

/**
 * @returns {string} SVG path data
 */
function stateAccentPath() {
    const radius = STATE_CORNER_RADIUS;
    const inset = radius - Math.sqrt(radius * radius - (radius - STATE_ACCENT_WIDTH) ** 2);

    return [
        `M ${STATE_ACCENT_WIDTH} ${inset}`,
        `A ${radius} ${radius} 0 0 0 0 ${radius}`,
        `V ${STATE_HEIGHT - radius}`,
        `A ${radius} ${radius} 0 0 0 ${STATE_ACCENT_WIDTH} ${STATE_HEIGHT - inset}`,
        'Z',
    ].join(' ');
}

/**
 * @param {boolean} readOnly
 *
 * @returns {Object} JointJS port group definitions
 */
function createPortGroups(readOnly) {
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
                    magnet: readOnly ? false : 'passive',
                }),
            },
        },
        [PORT_OUT]: {
            position: { name: 'right' },
            markup: [{ tagName: 'circle', selector: 'portBody' }],
            attrs: {
                portBody: Object.assign({}, handleAttributes, {
                    magnet: !readOnly,
                    cursor: readOnly ? 'default' : 'crosshair',
                }),
            },
        },
    };
}

/**
 * @param {Object} state model state
 * @param {{position: {x: number, y: number}, isInitial: boolean, isCurrent: boolean, readOnly: boolean}} options
 *
 * @returns {Object} shapes.standard.Rectangle carrying the state's id as `modelId`
 */
export function createStateElement(state, { position, isInitial, isCurrent, readOnly }) {
    const width = stateBoxWidth(state, isInitial);
    const element = new shapes.standard.Rectangle({
        position: position,
        size: { width: width, height: STATE_HEIGHT },
        markup: [
            { tagName: 'rect', selector: 'body' },
            { tagName: 'path', selector: 'accent' },
            { tagName: 'text', selector: 'label' },
        ],
        attrs: {
            root: isCurrent ? { 'data-qa': CURRENT_STATE_TEST_SELECTOR } : {},
            body: {
                fill: isCurrent ? THEME.currentStateFill : THEME.stateFill,
                stroke: isCurrent ? THEME.currentStateStroke : THEME.stateStroke,
                strokeWidth: STATE_BORDER_WIDTH,
                rx: STATE_CORNER_RADIUS,
                ry: STATE_CORNER_RADIUS,
                cursor: readOnly ? 'default' : 'pointer',
            },
            accent: {
                d: stateAccentPath(),
                fill: THEME.accent,
                pointerEvents: 'none',
            },
            label: {
                text: (isInitial ? INITIAL_STATE_MARKER : '') + state.name,
                fill: THEME.stateText,
                fontSize: STATE_LABEL_FONT_SIZE,
                textWrap: {
                    width: width - STATE_HORIZONTAL_PADDING,
                    maxLineCount: 2,
                    ellipsis: true,
                },
            },
        },
        ports: {
            groups: createPortGroups(readOnly),
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
    element.set('isCurrentState', isCurrent);

    return element;
}

/**
 * JointJS puts the `port` attribute on the port markup's root node, which is the handle circle
 * itself here; `closest()` keeps the check correct should the markup ever gain a wrapper.
 *
 * @param {SVGElement|null} magnet
 *
 * @returns {boolean}
 */
export function isOutboundPort(magnet) {
    const portNode = magnet ? magnet.closest('[port]') : null;

    return Boolean(portNode) && portNode.getAttribute('port') === PORT_OUT;
}

/**
 * @returns {Object} shapes.standard.Link
 */
export function createLink() {
    return new shapes.standard.Link({
        attrs: {
            line: {
                stroke: THEME.link,
                strokeWidth: LINK_STROKE_WIDTH,
                targetMarker: { type: 'path', d: 'M 8 -4 0 0 8 4 z', fill: THEME.link, stroke: 'none' },
            },
        },
    });
}

/**
 * @param {Array<string>} lines the event name first, detail lines after
 *
 * @returns {Object} JointJS label definition
 */
export function createLinkLabel(lines) {
    return {
        attrs: {
            text: {
                text: lines.join('\n'),
                fill: THEME.linkLabelDetailText,
                fontSize: LABEL_DETAIL_FONT_SIZE,
                lineHeight: LABEL_LINE_HEIGHT,
                // Left transparent to the pointer so a click lands on the plate behind it,
                // which is the whole label's hit area.
                pointerEvents: 'none',
                annotations: [
                    {
                        start: 0,
                        end: lines[0].length,
                        attrs: { fill: THEME.linkLabelText, fontSize: LABEL_TITLE_FONT_SIZE, fontWeight: 600 },
                    },
                ],
            },
            rect: {
                fill: THEME.linkLabelFill,
                stroke: THEME.link,
                strokeWidth: LABEL_STROKE_WIDTH,
                rx: LABEL_CORNER_RADIUS,
                ry: LABEL_CORNER_RADIUS,
                x: `calc(x - ${LABEL_HORIZONTAL_PADDING})`,
                y: `calc(y - ${LABEL_VERTICAL_PADDING})`,
                width: `calc(w + ${LABEL_HORIZONTAL_PADDING * 2})`,
                height: `calc(h + ${LABEL_VERTICAL_PADDING * 2})`,
                pointerEvents: 'auto',
                cursor: 'pointer',
            },
        },
        position: { distance: 0.5 },
    };
}

/**
 * @param {Object} paper dia.Paper
 * @param {Object} element
 * @param {boolean} isSelected
 */
export function paintStateSelection(paper, element, isSelected) {
    const unselectedStroke = element.get('isCurrentState') ? THEME.currentStateStroke : THEME.stateStroke;

    element.attr('body/stroke', isSelected ? THEME.accent : unselectedStroke);

    const elementView = element.findView(paper);

    if (!elementView) {
        return;
    }

    if (!isSelected) {
        highlighters.stroke.remove(elementView, SELECTION_HIGHLIGHTER_ID);

        return;
    }

    highlighters.stroke.add(elementView, 'body', SELECTION_HIGHLIGHTER_ID, {
        padding: SELECTION_HALO_PADDING,
        rx: STATE_CORNER_RADIUS + SELECTION_HALO_PADDING,
        ry: STATE_CORNER_RADIUS + SELECTION_HALO_PADDING,
        attrs: { stroke: THEME.accent, strokeWidth: 2, strokeOpacity: 0.4 },
    });
}

/**
 * @param {Object} link
 * @param {boolean} isSelected
 */
export function paintLinkSelection(link, isSelected) {
    const color = isSelected ? THEME.accent : THEME.link;

    link.attr('line/stroke', color);
    link.attr('line/strokeWidth', isSelected ? SELECTED_LINK_STROKE_WIDTH : LINK_STROKE_WIDTH);
    link.attr('line/targetMarker/fill', color);

    if (link.labels().length) {
        link.label(0, {
            attrs: {
                rect: { stroke: color, strokeWidth: isSelected ? SELECTED_LABEL_STROKE_WIDTH : LABEL_STROKE_WIDTH },
            },
        });
    }
}

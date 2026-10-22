import { dia, elementTools } from '@joint/core';
import { AFFORDANCE_RADIUS, AFFORDANCE_OFFSET, THEME } from './state-machine-canvas-shared';

// Grace period before the hover affordances go away. The buttons are drawn outside the state's own
// bounding box, so reaching for one leaves the state and fires its mouseleave; without the delay the
// buttons would vanish while the pointer is still crossing the gap towards them.
const AFFORDANCE_HIDE_DELAY_IN_MILLISECONDS = 400;

const GLYPH_PLUS = 'M -5 0 H 5 M 0 -5 V 5';
const GLYPH_BIN = 'M -5 -4 H 5 M -4 -4 V 5 H 4 V -4 M -2 -4 V -6 H 2 V -4';

const ADD_CONNECTED_STATE_TEST_SELECTOR = 'workflow-canvas-add-connected-state';
const DELETE_STATE_TEST_SELECTOR = 'workflow-canvas-delete-state';

/**
 * @param {string} fillColor
 * @param {string} glyphPath
 * @param {string} testSelector
 *
 * @returns {Array<Object>} JointJS markup JSON
 */
function affordanceMarkup(fillColor, glyphPath, testSelector) {
    return [
        {
            tagName: 'circle',
            selector: 'button',
            attributes: {
                r: AFFORDANCE_RADIUS,
                fill: fillColor,
                cursor: 'pointer',
                'data-qa': testSelector,
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

export class JointHoverAffordances {
    /**
     * @param {{onAddConnectedState: Function, onDeleteState: Function}} callbacks
     */
    constructor(callbacks) {
        this.callbacks = callbacks;
        this.elementView = null;
        this.toolsElement = null;
        this.hideTimer = null;
        this.onMouseEnter = () => this.#cancelHide();
        this.onMouseLeave = () => this.scheduleHide();
    }

    /**
     * @param {Object} elementView dia.ElementView
     */
    show(elementView) {
        this.#cancelHide();

        if (this.elementView === elementView) {
            return;
        }

        this.#hide();

        const tools = this.#createTools(elementView.model.get('modelId'));
        elementView.addTools(tools);

        this.elementView = elementView;
        this.toolsElement = tools.el;

        // The buttons render in the paper's tool layer, not inside the state, so hovering them has
        // to cancel the hide the state's own mouseleave just scheduled.
        tools.el.addEventListener('mouseenter', this.onMouseEnter);
        tools.el.addEventListener('mouseleave', this.onMouseLeave);
    }

    #hide() {
        if (!this.elementView) {
            return;
        }

        this.toolsElement.removeEventListener('mouseenter', this.onMouseEnter);
        this.toolsElement.removeEventListener('mouseleave', this.onMouseLeave);
        this.elementView.removeTools();
        this.forget();
    }

    scheduleHide() {
        this.#cancelHide();
        this.hideTimer = setTimeout(() => this.#hide(), AFFORDANCE_HIDE_DELAY_IN_MILLISECONDS);
    }

    #cancelHide() {
        clearTimeout(this.hideTimer);
        this.hideTimer = null;
    }

    /**
     * Drops the pending hide and the references without touching the views — for when the graph
     * rebuild already disposed of them.
     */
    forget() {
        this.#cancelHide();
        this.elementView = null;
        this.toolsElement = null;
    }

    /**
     * @param {string} stateId
     *
     * @returns {Object} dia.ToolsView
     */
    #createTools(stateId) {
        return new dia.ToolsView({
            tools: [
                new elementTools.Button({
                    x: '100%',
                    y: '50%',
                    offset: { x: AFFORDANCE_OFFSET, y: 0 },
                    markup: affordanceMarkup(THEME.accent, GLYPH_PLUS, ADD_CONNECTED_STATE_TEST_SELECTOR),
                    action: () => this.callbacks.onAddConnectedState(stateId),
                }),
                new elementTools.Button({
                    x: '50%',
                    y: 0,
                    offset: { x: 0, y: -AFFORDANCE_OFFSET },
                    markup: affordanceMarkup(THEME.danger, GLYPH_BIN, DELETE_STATE_TEST_SELECTOR),
                    action: () => this.callbacks.onDeleteState(stateId),
                }),
            ],
        });
    }
}

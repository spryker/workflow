/**
 * Contract for canvas renderers.
 *
 * A renderer is a pure projection of the state machine model onto a drawing surface. It owns no
 * domain data and communicates back only through the intent callbacks. Any graph library (or none —
 * a plain HTML view is a valid renderer) can back the builder by implementing this contract and
 * registering the class in the renderer registry.
 *
 * Deliberately NOT part of the contract — these are renderer-independent and shared:
 *   - XML parsing/serialization (state-machine-codec.js),
 *   - domain operations and undo (state-machine-model.js),
 *   - state placement (state-machine-layout.js, fills the shared position map before render),
 *   - separating several transitions drawn between the same two states
 *     (state-machine-edge-geometry.js).
 *
 * Extend {@link AbstractStateMachineCanvas} to inherit the constructor storage, the fail-fast
 * abstract methods and working defaults for the optional methods below; extending is optional — the
 * registry validates only the required method surface on any class (JavaScript has no `abstract`
 * keyword, so throwing stubs are the enforcement idiom).
 */

export const CANVAS_RENDERER_METHODS = ['render', 'zoomIn', 'zoomOut', 'zoomToFit', 'destroy'];

/**
 * Methods a renderer MAY implement for a better editing experience. A renderer without them still
 * works: the builder checks for each and falls back to the behaviour documented on the method.
 */
export const CANVAS_RENDERER_OPTIONAL_METHODS = ['renderSelection', 'revealState', 'resetZoom'];

export class AbstractStateMachineCanvas {
    /**
     * @param {HTMLElement} container the shared builder container; renderers MUST mount into their
     *   own inner element and remove it in destroy(), never the container itself
     * @param {{
     *     onSelect: Function,
     *     onSelectMany: Function,
     *     onBlankClick: Function,
     *     onConnect: Function,
     *     onAddConnectedState: Function,
     *     onDeleteState: Function,
     *     onRenameState: Function,
     *     onRenameStart: Function,
     *     onRenameEnd: Function,
     * }} callbacks intent callbacks; report user interactions through these and nothing else — the
     *   ids passed are model entity ids (put them on drawn cells to map clicks back).
     *
     *   Selection intents: `onSelect(kind, id, isAdditive)` when the user picks a single entity —
     *   `isAdditive` is true when a modifier key was held, meaning "add to / remove from the
     *   selection" rather than "replace it"; `onSelectMany(refs)` with an array of `{kind, id}`
     *   after a rubber-band drag; `onBlankClick()` when empty canvas is clicked.
     *
     *   Editing intents — `onConnect(sourceStateId, targetStateId)` after the user draws a link,
     *   `onAddConnectedState(stateId)` / `onDeleteState(stateId)` from the affordances drawn next
     *   to a state, and `onRenameState(stateId, name)` when the user edits a state's name on the
     *   diagram itself — MUST NOT be offered when `options.readOnly` is set. `onRenameStart()` and
     *   `onRenameEnd()` bracket that on-diagram editing, so the builder can tell the user which keys
     *   commit it while the field is open.
     * @param {Object} positionByStateId shared view-state map of state id to {x, y}; read it for
     *   placement, write back when the user drags a state (in place — the builder shares this very
     *   object, so a moved state needs no callback); kept across renderer switches
     * @param {{readOnly: boolean, currentStateName: string, edgeRouteByPair: Object}} options
     *   `readOnly` renders an inspect-only diagram: no editing affordances, no link drawing, states
     *   not draggable; selection, panning and zooming stay. `currentStateName` names the state a
     *   workflow instance is in (empty outside the instance view), to be told apart on the diagram.
     *   `edgeRouteByPair` holds the routes the layout computed for its edges, keyed by
     *   `edgeRouteKey()` (state-machine-edge-geometry.js) — refilled in place by each directed-graph
     *   layout, so a renderer may follow them for as long as both states stand where it put them.
     */
    constructor(container, callbacks, positionByStateId, options) {
        this.container = container;
        this.callbacks = callbacks;
        this.positionByStateId = positionByStateId;
        this.options = options || { readOnly: false, currentStateName: '', edgeRouteByPair: {} };
    }

    /**
     * Redraws the whole diagram from the model. Called on every model change and after
     * initialization; a full rebuild is acceptable, incremental updates are an optimization the
     * renderer may choose. Must visualize: state boxes (name, the initial-state marker, selection),
     * transitions with their event/condition label and selection, using the shared THEME.
     *
     * Several transitions between the same two states MUST be drawn apart from each other — routed
     * naively they collapse onto one line and only the topmost stays reachable. See
     * state-machine-edge-geometry.js.
     *
     * @param {Object} model StateMachineModel — read-only access to states/events/transitions
     * @param {Object} selection StateMachineSelection — read-only; query it with `has(kind, id)`,
     *   `isEmpty()`, `size()`, `stateIds()`, `transitionIds()`
     */
    render(model, selection) {
        this.throwNotImplemented('render');
    }

    /**
     * Increases the canvas zoom by one step.
     */
    zoomIn() {
        this.throwNotImplemented('zoomIn');
    }

    /**
     * Decreases the canvas zoom by one step.
     */
    zoomOut() {
        this.throwNotImplemented('zoomOut');
    }

    /**
     * Scales and pans so the whole diagram is visible inside the container.
     */
    zoomToFit() {
        this.throwNotImplemented('zoomToFit');
    }

    /**
     * Tears the renderer down: releases library resources, removes document-level listeners and
     * removes the own mount element — the shared container must be left empty and reusable.
     */
    destroy() {
        this.throwNotImplemented('destroy');
    }

    /**
     * OPTIONAL. Repaints only what selection affects, leaving the drawn graph in place.
     *
     * Worth implementing: selection changes on every click, and a renderer that rebuilds its graph
     * to show them destroys the cell the user is in the middle of dragging — which reads as clicks
     * and drags being unreliable. The default falls back to a full `render()`.
     *
     * @param {Object} model StateMachineModel
     * @param {Object} selection StateMachineSelection
     */
    renderSelection(model, selection) {
        this.render(model, selection);
    }

    /**
     * OPTIONAL. Brings the given state into view if it is off screen, leaving the viewport alone
     * when it is already visible. The default does nothing, so the user may have to pan to find a
     * state that could not be placed inside the viewport.
     *
     * @param {string} stateId
     */
    revealState(stateId) {
        // Nothing to do: a renderer without a viewport of its own always shows every state.
    }

    /**
     * OPTIONAL. Returns the canvas to 100% zoom with the diagram origin in the top-left corner, so
     * states are drawn at their readable size however large the workflow is. The default falls back
     * to `zoomToFit()`.
     */
    resetZoom() {
        this.zoomToFit();
    }

    throwNotImplemented(methodName) {
        throw new Error(
            `Canvas renderer "${this.constructor.name}" does not implement "${methodName}()". ` +
                `A workflow canvas renderer must implement: ${CANVAS_RENDERER_METHODS.join(', ')}. ` +
                `Optionally also: ${CANVAS_RENDERER_OPTIONAL_METHODS.join(', ')}.`,
        );
    }
}

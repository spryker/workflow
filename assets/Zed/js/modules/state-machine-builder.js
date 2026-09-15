'use strict';

/**
 * Visual state machine builder — composition root.
 *
 * Wires the layers together and owns the UI-only state (the current selection):
 *
 *   StateMachineModel        the single source of truth (states, shared events, transitions)
 *   renderer registry        canvas projections of the model; a project registers its own
 *                            strategy through window.WorkflowStateMachineRenderers
 *   StateMachineInspector    form-control projection of the model
 *
 * Exactly one renderer is active per page: the strategy is resolved once at initialization from
 * the `data-renderer` attribute on the builder root (JointJS is the default; unknown names fall
 * back to it with a warning). A project changes the renderer by overriding the template attribute
 * and registering its strategy — there is no runtime switching.
 *
 * The same builder serves two pages. On the version editor it is fully interactive and, on every
 * model change, pushes the serialized `state-machine-01` XML into the hidden
 * `definition`/`initialState` form fields — saving uses the existing Create Version form, no new
 * endpoint. On the version view page `data-readonly` turns it into an inspect-only diagram whose
 * model is hydrated from the XML rendered into the raw-XML panel.
 *
 * Undo is a model snapshot, so it works uniformly for every operation. It also reports what it
 * reverted: the builder selects the entity the undone change concerned and announces it, because an
 * undo that silently swaps the model out reads as a button that does nothing.
 */

const { StateMachineModel } = require('./state-machine-model');
const { StateMachineSelection } = require('./state-machine-selection');
const { StateMachineInspector } = require('./state-machine-inspector');
const { serialize, deserialize } = require('./state-machine-codec');
const { ensureStatePositions, labelsOutgrowEdges } = require('./state-machine-layout');
const { getRendererRegistry } = require('./state-machine-renderer-registry');

// The bundled adapter registers itself in the renderer registry when loaded.
require('./state-machine-canvas-joint');

const ROOT_SELECTOR = '.js-state-machine-builder';
const SAVE_SELECTOR = '.js-sm-save';
// Undo announces itself with the Back Office's own flash markup, but floating inside the canvas:
// the layout's flash container sits above the page content, so a message appearing there pushes the
// whole diagram down, and one at the far corner of the window went unnoticed. Out of the flow, over
// the corner of the drawing the user is already looking at.
const UNDO_FLASH_CLASS = 'js-sm-undo-flash';
const UNDO_FLASH_VISIBLE_IN_MILLISECONDS = 5000;
const VALIDATE_SELECTOR = '.js-validate-definition-button';
const XML_APPLY_DEBOUNCE_IN_MILLISECONDS = 600;

function qs(root, selector) {
    return root.querySelector(selector);
}

/**
 * Escapes text for safe insertion into innerHTML.
 *
 * @param {string} value
 *
 * @returns {string}
 */
function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value);

    return div.innerHTML;
}

/**
 * Reads the inlined { commands: [...], conditions: [...] } lists for this process.
 *
 * @returns {{commands: Array<string>, conditions: Array<string>}}
 */
function readAvailablePlugins() {
    const node = document.querySelector('.js-workflow-available-plugins');

    if (!node) {
        return { commands: [], conditions: [] };
    }

    try {
        const parsed = JSON.parse(node.textContent.trim() || '{}');

        return {
            commands: Array.isArray(parsed.commands) ? parsed.commands : [],
            conditions: Array.isArray(parsed.conditions) ? parsed.conditions : [],
        };
    } catch (error) {
        return { commands: [], conditions: [] };
    }
}

/**
 * Reads a translated-strings blob the template inlines. Neither the canvas renderers nor the undo
 * announcements may hardcode user-facing text, and they sit too deep to reach Twig themselves.
 *
 * @param {HTMLElement} root
 * @param {string} selector
 *
 * @returns {Object} label key to translated string; empty when the blob is absent or malformed
 */
function readLabels(root, selector) {
    const node = root.querySelector(selector);

    if (!node) {
        return {};
    }

    try {
        const parsed = JSON.parse(node.textContent.trim() || '{}');

        return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (error) {
        return {};
    }
}

class StateMachineBuilder {
    constructor(root) {
        this.root = root;
        this.readOnly = root.dataset.readonly === '1';
        this.selection = new StateMachineSelection();
        this.undoLabels = readLabels(root, '.js-sm-undo-labels');
        this.undoFlashTimer = null;
        // The toolbar hint the page was rendered with; restored after a temporary one replaces it.
        this.defaultHint = (qs(root, '.js-sm-canvas-hint') || {}).textContent;
        // Where the last full layout put every state, to tell a diagram the user has since arranged
        // by hand from one that is still the layout's own.
        this.layoutArrangement = null;

        this.definitionField =
            document.querySelector('[name$="[definition]"]') || document.querySelector('#version_definition');
        this.initialStateField =
            document.querySelector('[name$="[initialState]"]') || document.querySelector('#version_initialState');

        this.model = new StateMachineModel();

        // Seed the process name from the parent context label if present.
        const contextLabel = document.querySelector('.workflow-parent-context .label');

        if (contextLabel) {
            this.model.processName = contextLabel.textContent.trim();
        }

        this.positionByStateId = {};
        this.canvas = this.createCanvas(root.dataset.renderer || '');

        this.inspector = new StateMachineInspector(root, this.model, readAvailablePlugins(), {
            getSelection: () => this.selection,
            onDeleteSelection: () => this.deleteSelection(),
        });

        this.bindToolbar();
        this.bindKeyboardShortcuts();
        this.bindAdvancedPanel();
        this.bindSubmitGuards();

        this.hydrateFromDefinition();

        this.model.subscribe(() => this.refresh());

        this.refresh();

        this.placeInitialViewport();
    }

    /**
     * Positions the diagram once the model is loaded: both pages open with the whole workflow in
     * view, scaled to fit and centred in the canvas. The toolbar's zoom controls take it from there.
     */
    placeInitialViewport() {
        if (!this.model.states.length) {
            return;
        }

        this.canvas.zoomToFit();
    }

    createCanvas(requestedRendererName) {
        const RendererClass = getRendererRegistry().resolve(requestedRendererName);
        const callbacks = {
            onSelect: (kind, id, isAdditive) => this.select(kind, id, isAdditive),
            onSelectMany: (refs) => this.selectMany(refs),
            onBlankClick: () => this.select(null, null, false),
            onConnect: (sourceId, targetId) => this.connectStates(sourceId, targetId),
            onAddConnectedState: (sourceId) => this.addConnectedState(sourceId),
            onDeleteState: (id) => this.model.deleteState(id),
            onRenameState: (id, name) => this.model.renameState(id, name),
            onRenameStart: () => this.showHint(this.root.dataset.messageRenameHint),
            onRenameEnd: () => this.showHint(this.defaultHint),
        };

        return new RendererClass(qs(this.root, '.js-sm-canvas'), callbacks, this.positionByStateId, {
            readOnly: this.readOnly,
        });
    }

    /**
     * Scrolls a state into view. Optional in the renderer contract; a renderer without it simply
     * does not scroll.
     *
     * @param {string} stateId
     */
    revealState(stateId) {
        if (typeof this.canvas.revealState === 'function') {
            this.canvas.revealState(stateId);
        }
    }

    /**
     * Re-renders every projection of the model. Runs on each model notification.
     */
    refresh() {
        this.selection.retainExisting(this.model);
        this.relayoutWhenNeeded();

        if (ensureStatePositions(this.model, this.positionByStateId)) {
            this.layoutArrangement = this.currentArrangement();
        }

        this.canvas.render(this.model, this.selection);
        this.inspector.render();
        this.syncFields();

        const undoButton = qs(this.root, '.js-sm-undo');

        if (undoButton) {
            undoButton.disabled = !this.model.canUndo();
        }
    }

    /**
     * Throws the arrangement away when a transition's label has outgrown its edge — the user
     * picked a long command or condition, say — so the layout runs again and stretches the edge to
     * carry it. Only while the arrangement is still the layout's own: once the user has dragged a
     * state, the boxes are where they were put, and no edit moves them. Adding or removing states
     * never re-lays out an arranged diagram either — a new state is placed beside what it connects
     * to (see the layout module), which is what keeps the user's arrangement intact.
     */
    relayoutWhenNeeded() {
        if (!this.isLayoutArrangement() || !labelsOutgrowEdges(this.model, this.positionByStateId)) {
            return;
        }

        Object.keys(this.positionByStateId).forEach((stateId) => {
            delete this.positionByStateId[stateId];
        });
    }

    /**
     * @returns {boolean} whether every state the last full layout placed still stands where it
     *   was put; states added since do not count, and a diagram never laid out counts as untouched
     */
    isLayoutArrangement() {
        const remembered = this.layoutArrangement;

        if (!remembered) {
            return true;
        }

        const current = this.currentArrangement();

        return Object.keys(remembered).every(
            (stateId) => !(stateId in current) || current[stateId] === remembered[stateId],
        );
    }

    /**
     * @returns {Object} state id to its position as one comparable string
     */
    currentArrangement() {
        const arrangement = {};

        Object.keys(this.positionByStateId).forEach((stateId) => {
            const position = this.positionByStateId[stateId];
            arrangement[stateId] = `${position.x},${position.y}`;
        });

        return arrangement;
    }

    /**
     * Repaints only what a selection change affects. Kept apart from `refresh()` on purpose: a full
     * re-render rebuilds the canvas graph, which would destroy the cell the user has just pressed
     * down on and is about to drag.
     */
    refreshSelection() {
        if (typeof this.canvas.renderSelection === 'function') {
            this.canvas.renderSelection(this.model, this.selection);
        } else {
            this.canvas.render(this.model, this.selection);
        }

        this.inspector.render();
    }

    syncFields() {
        // The view page shows the definition as it is stored, comments and hand formatting included;
        // overwriting it with the model's canonical serialization would misreport what is saved.
        if (this.readOnly) {
            return;
        }

        const xml = serialize(this.model.toDefinitionModel());

        if (this.definitionField) {
            this.definitionField.value = xml;
        }

        if (this.initialStateField) {
            this.initialStateField.value = this.model.initialStateId
                ? this.model.stateName(this.model.initialStateId)
                : '';
        }

        const editor = qs(this.root, '.js-sm-xml-editor');

        // The canonical XML must not clobber the text while the user is typing in the editor.
        if (editor && document.activeElement !== editor) {
            editor.value = xml;
        }
    }

    /**
     * Rebuilds the model from the definition the page was rendered with: the pre-filled
     * definition/initial-state form fields when editing (also after a failed save), or the XML the
     * read-only view page rendered into the raw-XML panel. `defaultValue` is read for both, because
     * it stays pristine even if an earlier sync already overwrote the live `value`.
     */
    hydrateFromDefinition() {
        const source = this.readDefinitionSource();

        if (!source.xml) {
            return;
        }

        const definitionModel = deserialize(source.xml);

        if (!definitionModel.states.length && !definitionModel.transitions.length) {
            return;
        }

        this.model.loadDefinitionModel(definitionModel, source.initialStateName);
    }

    /**
     * @returns {{xml: string, initialStateName: string}}
     */
    readDefinitionSource() {
        if (this.readOnly) {
            const editor = qs(this.root, '.js-sm-xml-editor');

            return {
                xml: editor ? editor.defaultValue.trim() : '',
                initialStateName: (this.root.dataset.initialState || '').trim(),
            };
        }

        return {
            xml: this.definitionField
                ? (this.definitionField.defaultValue || this.definitionField.value || '').trim()
                : '',
            initialStateName: this.initialStateField
                ? (this.initialStateField.defaultValue || this.initialStateField.value || '').trim()
                : '',
        };
    }

    /**
     * @param {string|null} kind
     * @param {string|null} id
     * @param {boolean} isAdditive whether to extend the selection instead of replacing it
     */
    select(kind, id, isAdditive) {
        if (isAdditive && kind && id) {
            this.selection.toggle(kind, id);
        } else {
            this.selection.replaceWith(kind, id);
        }

        this.refreshSelection();
    }

    /**
     * @param {Array<{kind: string, id: string}>} refs the rubber band's result
     */
    selectMany(refs) {
        this.selection.replaceWithRefs(refs);
        this.refreshSelection();
    }

    /**
     * Links two existing states — the user dropped a connection on the canvas.
     *
     * @param {string} sourceId
     * @param {string} targetId
     */
    connectStates(sourceId, targetId) {
        const transition = this.model.addTransition(sourceId, targetId);
        this.select('transition', transition.id, false);
    }

    /**
     * Adds a state connected to the given one and selects it — the canvas "+" affordance. Where it
     * lands is the layout's decision: the structural change re-runs it for the whole diagram.
     *
     * @param {string} sourceId
     */
    addConnectedState(sourceId) {
        const result = this.model.addConnectedState(sourceId);

        if (!result) {
            return;
        }

        this.selectAndReveal(result.state.id);
    }

    /**
     * @param {string} stateId a state the model notification has already laid out
     */
    selectAndReveal(stateId) {
        this.select('state', stateId, false);
        this.revealState(stateId);
    }

    /**
     * Deletes everything selected in one undoable operation — the inspector's delete buttons and
     * the Delete key both land here, whether one entity is selected or twenty.
     */
    deleteSelection() {
        if (this.readOnly || this.selection.isEmpty()) {
            return;
        }

        this.model.deleteEntities({
            stateIds: this.selection.stateIds(),
            transitionIds: this.selection.transitionIds(),
        });
    }

    bindToolbar() {
        const addStateButton = qs(this.root, '.js-sm-add-state');

        if (addStateButton) {
            addStateButton.addEventListener('click', () => this.addState());
        }

        const undoButton = qs(this.root, '.js-sm-undo');

        if (undoButton) {
            undoButton.addEventListener('click', () => this.undo());
        }

        qs(this.root, '.js-sm-zoom-in').addEventListener('click', () => this.canvas.zoomIn());
        qs(this.root, '.js-sm-zoom-out').addEventListener('click', () => this.canvas.zoomOut());
        qs(this.root, '.js-sm-zoom-fit').addEventListener('click', () => this.canvas.zoomToFit());

        const autoArrangeButton = qs(this.root, '.js-sm-auto-arrange');

        if (autoArrangeButton) {
            autoArrangeButton.addEventListener('click', () => this.autoArrange());
        }
    }

    /**
     * Undoes the last diagram operation and says what that was.
     *
     * The plain rollback is what made the button feel broken after a field edit: the model changed
     * back but nothing on screen pointed at it. So the reverted entity is selected — which opens
     * its inspector panel at the restored value — and the operation is announced in the toolbar.
     */
    undo() {
        const undone = this.model.undo();

        if (!undone) {
            return;
        }

        if (undone.focus && this.selection.existsInModel(this.model, undone.focus)) {
            this.select(undone.focus.kind, undone.focus.id, false);
        }

        this.announceUndo(undone.operation);
    }

    /**
     * Names the reverted change as a flash message, built with the Messenger partial's own markup
     * so it reads as an ordinary Back Office alert, and floated so that showing it never moves the
     * diagram. The per-operation wording comes translated from the template; an operation without
     * its own wording falls back to the generic one rather than showing a raw key. One at a time: a
     * new announcement replaces the previous one instead of stacking under it.
     *
     * @param {string} operation
     */
    announceUndo(operation) {
        this.removeUndoFlash();

        const flash = document.createElement('div');
        flash.className = `alert alert-info ${UNDO_FLASH_CLASS}`;
        flash.setAttribute('data-qa', 'workflow-undo-flash');
        flash.setAttribute('role', 'status');

        const icon = document.createElement('i');
        icon.className = 'fas fa-info-circle alert__icon';

        const text = document.createElement('div');
        text.className = 'alert__text';
        text.textContent = this.undoLabels[operation] || this.undoLabels.genericUndo || '';

        flash.appendChild(icon);
        flash.appendChild(text);
        (qs(this.root, '.js-sm-canvas') || document.body).appendChild(flash);

        this.undoFlashTimer = setTimeout(() => this.removeUndoFlash(), UNDO_FLASH_VISIBLE_IN_MILLISECONDS);
    }

    removeUndoFlash() {
        clearTimeout(this.undoFlashTimer);
        this.undoFlashTimer = null;

        document.querySelectorAll(`.${UNDO_FLASH_CLASS}`).forEach((flash) => flash.remove());
    }

    /**
     * Replaces the toolbar hint. Used to tell the user how to finish editing a state's name on the
     * diagram, and to put the standing hint back afterwards.
     *
     * @param {string|undefined} text
     */
    showHint(text) {
        const hint = qs(this.root, '.js-sm-canvas-hint');

        if (hint && text) {
            hint.textContent = text;
        }
    }

    /**
     * Adds a state from the toolbar and selects it. Placement is the layout's, as for every
     * structural change.
     */
    addState() {
        this.selectAndReveal(this.model.addState().id);
    }

    /**
     * Lays the whole diagram out again from scratch and fits it on screen.
     *
     * Positions are view state, never part of the definition, so this is not a model change and not
     * undoable — the arrangement it replaces was not saved anywhere either. The map is emptied IN
     * PLACE because the renderer holds the very same object; replacing it would leave the canvas
     * reading a map the builder no longer writes to.
     */
    autoArrange() {
        Object.keys(this.positionByStateId).forEach((stateId) => {
            delete this.positionByStateId[stateId];
        });

        this.refresh();
        this.canvas.zoomToFit();
    }

    /**
     * Ctrl+Z / Cmd+Z undoes the last diagram operation; Delete or Backspace removes the selection.
     * While the caret is in a text field the browser's own editing behaviour is what the user
     * expects, so the shortcuts stay out of the way there — the raw-XML editor included, where undo
     * belongs to the text, not to the diagram.
     */
    bindKeyboardShortcuts() {
        if (this.readOnly) {
            return;
        }

        document.addEventListener('keydown', (event) => {
            if (this.isTextEntryTarget(event.target)) {
                return;
            }

            if (this.isUndoShortcut(event)) {
                event.preventDefault();
                this.undo();

                return;
            }

            if ((event.key === 'Delete' || event.key === 'Backspace') && !this.selection.isEmpty()) {
                event.preventDefault();
                this.deleteSelection();
            }
        });
    }

    /**
     * @param {KeyboardEvent} event
     *
     * @returns {boolean}
     */
    isUndoShortcut(event) {
        if (event.key !== 'z' && event.key !== 'Z') {
            return false;
        }

        return (event.ctrlKey || event.metaKey) && !event.altKey && !event.shiftKey;
    }

    /**
     * @param {EventTarget|null} target
     *
     * @returns {boolean}
     */
    isTextEntryTarget(target) {
        if (!target || !target.tagName) {
            return false;
        }

        return ['INPUT', 'TEXTAREA', 'SELECT'].indexOf(target.tagName) !== -1 || target.isContentEditable === true;
    }

    bindAdvancedPanel() {
        const toggle = qs(this.root, '.js-sm-advanced-toggle');

        toggle.addEventListener('click', () => {
            const isHidden = qs(this.root, '.js-sm-advanced-body').classList.toggle('hidden');
            toggle.setAttribute('aria-expanded', String(!isHidden));
        });

        // Read-only keeps the collapsible panel but never applies edits: the textarea is a preview
        // of the stored definition, rendered with the readonly attribute.
        if (this.readOnly) {
            return;
        }

        const editor = qs(this.root, '.js-sm-xml-editor');
        let debounceTimer = null;

        editor.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => this.applyManualDefinition(), XML_APPLY_DEBOUNCE_IN_MILLISECONDS);
        });

        editor.addEventListener('blur', () => {
            clearTimeout(debounceTimer);
            this.applyManualDefinition();
        });
    }

    /**
     * Applies the hand-edited raw XML to the model. Broken XML shows an error and leaves the model
     * untouched; a successful apply re-renders the canvas and is a single undo step. States whose
     * names survive the edit keep their canvas positions (the model preserves their ids).
     */
    applyManualDefinition() {
        const editor = qs(this.root, '.js-sm-xml-editor');
        const errorBox = qs(this.root, '.js-sm-xml-error');
        const xml = editor.value;
        const definitionModel = deserialize(xml);

        // A definition without states is never applied. Mid-edit the editor passes through exactly
        // that — the user cleared it to retype, or has not closed the first tag yet — and applying
        // it replaced the whole diagram with nothing, which is how editing the XML "killed" the
        // canvas. deserialize() also yields an empty model for input it cannot parse, so the two
        // cases are told apart by whether there is any text at all.
        if (!definitionModel.states.length) {
            errorBox.textContent = xml.trim()
                ? this.root.dataset.messageInvalidXml
                : this.root.dataset.messageIncompleteXml;
            errorBox.classList.remove('hidden');

            return;
        }

        errorBox.classList.add('hidden');

        // Compare canon against canon: formatting-only differences in the hand-written XML must not
        // produce model operations (and undo history entries). Leaving the field still normalizes
        // the text to the canonical formatting.
        const canonicalXml = serialize(this.model.toDefinitionModel());

        if (serialize(definitionModel) === canonicalXml) {
            if (document.activeElement !== editor) {
                editor.value = canonicalXml;
            }

            return;
        }

        this.model.applyDefinitionModel(definitionModel);
    }

    /**
     * Guards BOTH submit paths — Save and Validate — against a graph the backend cannot make sense
     * of yet, because in both the failure is unhelpful. The definition and initial-state fields are
     * hidden, so Symfony's required-field error on Save would be invisible; Validate posts the whole
     * form, so an unset initial state arrives as an empty string and comes back as a complaint about
     * a state named "" that the user never created. Naming the actual problem here is what the user
     * can act on, and it keeps the backend from having to special-case an empty value.
     *
     * The listener sits on `document` in the CAPTURE phase on purpose: the Validate button's own
     * click handler lives in another module (definition-validator.js), capture runs ahead of it
     * regardless of which module bound first, and stopImmediatePropagation stops the request from
     * being sent at all.
     */
    bindSubmitGuards() {
        if (this.readOnly) {
            return;
        }

        document.addEventListener(
            'click',
            (event) => {
                // Duck-typed rather than `instanceof Element`: the event target can be a text node,
                // and this keeps the module free of a dependency on the DOM global.
                const target = event.target;
                const button =
                    target && typeof target.closest === 'function'
                        ? target.closest(`${SAVE_SELECTOR}, ${VALIDATE_SELECTOR}`)
                        : null;

                if (!button) {
                    return;
                }

                const problems = this.collectSubmitProblems();

                if (problems.length === 0) {
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();
                this.renderValidationErrors(problems);
            },
            true,
        );
    }

    /**
     * @returns {Array<string>} translated, human-readable reasons the graph cannot be submitted yet
     */
    collectSubmitProblems() {
        const problems = [];

        if (!this.model.states.filter((state) => state.name).length) {
            problems.push(this.root.dataset.messageNoStates);
        }

        if (!this.model.initialStateId) {
            problems.push(this.root.dataset.messageNoInitialState);
        }

        return problems;
    }

    /**
     * Renders client-side problems in the same banner the backend validator uses.
     *
     * @param {Array<string>} problems
     */
    renderValidationErrors(problems) {
        const container = document.querySelector('.js-definition-validation-result');

        if (!container) {
            return;
        }

        const validateButton = document.querySelector('.js-validate-definition-button');
        const heading = validateButton ? validateButton.dataset.messageHeading : '';
        const items = problems.map((problem) => `<li>${escapeHtml(problem)}</li>`).join('');
        container.innerHTML = `<div class="alert d-block alert-danger"><strong>${escapeHtml(
            heading,
        )}</strong><ul>${items}</ul></div>`;
    }
}

function init() {
    const root = document.querySelector(ROOT_SELECTOR);

    if (!root) {
        return;
    }

    new StateMachineBuilder(root);
}

init();

module.exports = { StateMachineBuilder };

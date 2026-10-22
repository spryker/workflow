/**
 * Undo reports what it reverted: the builder selects the entity the undone change concerned and announces it,
 * because an undo that silently swaps the model out reads as a button that does nothing.
 */

import { StateMachineModel } from './state-machine-model';
import { StateMachineSelection } from './state-machine-selection';
import { StateMachineInspector } from './state-machine-inspector';
import { getRendererRegistry } from './state-machine-renderer-registry';
import { type Labels, readAvailablePlugins, readLabels } from './state-machine-page-data';
import { announceUndo } from './state-machine-undo-flash';
import { bindPageEvents, preventSubmitOnEnter } from './state-machine-page-events';
import { bindKeyboardShortcuts } from './state-machine-keyboard-shortcuts';
import { bindSubmitGuard, collectSubmitProblems } from './state-machine-submit-guard';
import { bindXmlEditorPanel } from './state-machine-xml-editor';
import { StateMachineArrangement } from './state-machine-arrangement';
import { StateMachineDefinitionForm } from './state-machine-definition-form';
import {
    type SelectableKind,
    type SelectionRef,
    type StateMachineCanvas,
    placeInitialViewport,
} from './state-machine-canvas';

// The bundled adapter registers itself in the renderer registry when loaded.
import './state-machine-canvas-joint';

const ROOT_SELECTOR = '.js-state-machine-builder';

export class StateMachineBuilder {
    private readonly readOnly: boolean;
    private readonly selection = new StateMachineSelection();
    private readonly undoLabels: Labels;
    private readonly defaultHint: string | undefined;
    private readonly model = new StateMachineModel();
    private readonly arrangement = new StateMachineArrangement();
    private readonly definitionForm: StateMachineDefinitionForm;
    private readonly canvas: StateMachineCanvas;
    private readonly inspector: StateMachineInspector;

    constructor(private readonly root: HTMLElement) {
        this.readOnly = root.dataset.readonly === '1';
        this.undoLabels = readLabels(root, '.js-sm-undo-labels');
        this.defaultHint = this.find('.js-sm-canvas-hint')?.textContent ?? undefined;

        this.definitionForm = new StateMachineDefinitionForm(root, this.readOnly);

        if (root.dataset.processName) {
            this.model.processName = root.dataset.processName;
        }

        this.canvas = this.createCanvas(root.dataset.renderer || '');

        this.inspector = new StateMachineInspector(root, this.model, readAvailablePlugins(), {
            getSelection: () => this.selection,
            onDeleteSelection: () => this.deleteSelection(),
        });

        this.bindToolbar();
        bindXmlEditorPanel(root, this.model, this.readOnly);

        if (!this.readOnly) {
            bindKeyboardShortcuts({
                onUndo: () => this.undo(),
                onDelete: () => this.deleteSelection(),
                hasSelection: () => !this.selection.isEmpty(),
            });
            bindSubmitGuard(() => collectSubmitProblems(this.model, this.root.dataset));
            preventSubmitOnEnter(root);
            bindPageEvents({
                onProcessNameChange: (processName) => {
                    this.model.processName = processName;
                    this.definitionForm.sync(this.model);
                },
                onAvailablePluginsChange: (availablePlugins) => this.inspector.setAvailablePlugins(availablePlugins),
            });
        }

        this.definitionForm.hydrate(this.model);

        this.model.subscribe(() => this.refresh());

        this.refresh();

        placeInitialViewport(this.canvas, this.model);
    }

    private find<T extends HTMLElement = HTMLElement>(selector: string): T | null {
        return this.root.querySelector<T>(selector);
    }

    private canvasViewport(): { width: number; height: number } {
        const canvasElement = this.find('.js-sm-canvas');

        return { width: canvasElement?.clientWidth ?? 0, height: canvasElement?.clientHeight ?? 0 };
    }

    private createCanvas(requestedRendererName: string): StateMachineCanvas {
        const RendererClass = getRendererRegistry().resolve(requestedRendererName);
        const callbacks = {
            onSelect: (kind: SelectableKind | null, id: string | null, isAdditive: boolean) =>
                this.select(kind, id, isAdditive),
            onSelectMany: (refs: SelectionRef[]) => this.selectMany(refs),
            onBlankClick: () => this.select(null, null, false),
            onConnect: (sourceId: string, targetId: string) => this.connectStates(sourceId, targetId),
            onAddConnectedState: (sourceId: string) => this.addConnectedState(sourceId),
            onDeleteState: (id: string) => this.model.deleteState(id),
            onRenameState: (id: string, name: string) => this.renameStateFromCanvas(id, name),
            onRenameStart: () => this.showHint(this.root.dataset.messageRenameHint),
            onRenameEnd: () => this.showHint(this.defaultHint),
        };

        return new RendererClass(this.find('.js-sm-canvas'), callbacks, this.arrangement.positionByStateId, {
            readOnly: this.readOnly,
            currentStateName: (this.root.dataset.currentState || '').trim(),
            edgeRouteByPair: this.arrangement.edgeRouteByPair,
        });
    }

    private revealState(stateId: string): void {
        this.canvas.revealState?.(stateId);
    }

    private refresh(): void {
        this.selection.retainExisting(this.model);
        this.arrangement.arrange(this.model, this.canvasViewport());
        this.canvas.render(this.model, this.selection);
        this.inspector.render();
        this.definitionForm.sync(this.model);

        const undoButton = this.find<HTMLButtonElement>('.js-sm-undo');

        if (undoButton) {
            undoButton.disabled = !this.model.canUndo();
        }
    }

    /**
     * Repaints only what a selection change affects. Kept apart from `refresh()` on purpose: a full
     * re-render rebuilds the canvas graph, which would destroy the cell the user has just pressed
     * down on and is about to drag.
     */
    private refreshSelection(): void {
        if (this.canvas.renderSelection) {
            this.canvas.renderSelection(this.model, this.selection);
        } else {
            this.canvas.render(this.model, this.selection);
        }

        this.inspector.render();
    }

    private select(kind: SelectableKind | null, id: string | null, isAdditive: boolean): void {
        if (isAdditive && kind && id) {
            this.selection.toggle(kind, id);
        } else {
            this.selection.replaceWith(kind, id);
        }

        this.refreshSelection();
    }

    private selectMany(refs: SelectionRef[]): void {
        this.selection.replaceWithRefs(refs);
        this.refreshSelection();
    }

    private connectStates(sourceId: string, targetId: string): void {
        const transition = this.model.addTransition(sourceId, targetId);
        this.select('transition', transition.id, false);
    }

    /**
     * Where it lands is the layout's decision: the structural change re-runs it for the whole diagram.
     */
    private addConnectedState(sourceId: string): void {
        const result = this.model.addConnectedState(sourceId);

        if (!result) {
            return;
        }

        this.selectAndReveal(result.state.id);
    }

    /**
     * Expects a state the model notification has already laid out.
     */
    private selectAndReveal(stateId: string): void {
        this.select('state', stateId, false);
        this.revealState(stateId);
    }

    private deleteSelection(): void {
        if (this.readOnly || this.selection.isEmpty()) {
            return;
        }

        this.model.deleteEntities({
            stateIds: this.selection.stateIds(),
            transitionIds: this.selection.transitionIds(),
        });
    }

    private bindToolbar(): void {
        this.find('.js-sm-add-state')?.addEventListener('click', () => this.addState());
        this.find('.js-sm-undo')?.addEventListener('click', () => this.undo());
        this.find('.js-sm-zoom-in')?.addEventListener('click', () => this.canvas.zoomIn());
        this.find('.js-sm-zoom-out')?.addEventListener('click', () => this.canvas.zoomOut());
        this.find('.js-sm-zoom-fit')?.addEventListener('click', () => this.canvas.zoomToFit());
        this.find('.js-sm-auto-arrange')?.addEventListener('click', () => this.autoArrange());
    }

    /**
     * The plain rollback is what made the button feel broken after a field edit: the model changed
     * back but nothing on screen pointed at it. So the reverted entity is selected — which opens
     * its inspector panel at the restored value — and the operation is announced in the toolbar.
     */
    private undo(): void {
        const undone = this.model.undo();

        if (!undone) {
            return;
        }

        if (undone.focus && this.selection.existsInModel(this.model, undone.focus)) {
            this.select(undone.focus.kind, undone.focus.id, false);
        }

        announceUndo(this.root, this.undoLabels, undone.operation);
    }

    private renameStateFromCanvas(id: string, name: string): void {
        if (this.model.isStateNameTaken(id, name)) {
            this.showHint(this.root.dataset.messageDuplicateStateName);

            return;
        }

        this.model.renameState(id, name);
    }

    private showHint(text: string | undefined): void {
        const hint = this.find('.js-sm-canvas-hint');

        if (hint && text) {
            hint.textContent = text;
        }
    }

    /**
     * Placement is the layout's, as for every structural change.
     */
    private addState(): void {
        this.selectAndReveal(this.model.addState().id);
    }

    /**
     * Positions are view state, never part of the definition, so this is not a model change and not
     * undoable — the arrangement it replaces was not saved anywhere either.
     */
    private autoArrange(): void {
        this.arrangement.reset();
        this.refresh();
        this.canvas.zoomToFit();
    }
}

function init(): void {
    const root = document.querySelector<HTMLElement>(ROOT_SELECTOR);

    if (!root) {
        return;
    }

    new StateMachineBuilder(root);
}

init();

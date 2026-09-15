'use strict';

/**
 * The builder's selection — which model entities the user currently has picked.
 *
 * Selection is UI state, never model data: entities are referenced by kind and id, and a reference
 * to an entity an operation removed is dropped by `retainExisting()` on the next render rather than
 * kept as a dangling pointer.
 *
 * The set is ordered by the sequence the user picked the entities in, which is what the inspector
 * and the "delete selected" operation read back. Both the canvas renderers and the inspector get
 * the same instance and treat it as read-only — only the builder mutates it.
 */

const KIND_STATE = 'state';
const KIND_TRANSITION = 'transition';

class StateMachineSelection {
    constructor() {
        this.refs = [];
    }

    clear() {
        this.refs = [];
    }

    isEmpty() {
        return this.refs.length === 0;
    }

    size() {
        return this.refs.length;
    }

    /**
     * @param {string} kind
     * @param {string} id
     *
     * @returns {boolean}
     */
    has(kind, id) {
        return this.indexOfRef(kind, id) !== -1;
    }

    /**
     * Selects exactly the given entity, dropping everything else — a plain click. A null kind
     * clears the selection, which is what the click on empty canvas reports.
     *
     * @param {string|null} kind
     * @param {string|null} id
     */
    replaceWith(kind, id) {
        this.refs = kind && id ? [{ kind: kind, id: id }] : [];
    }

    /**
     * Selects exactly the given entities — the result of a rubber-band drag.
     *
     * @param {Array<{kind: string, id: string}>} refs
     */
    replaceWithRefs(refs) {
        this.refs = [];
        refs.forEach((ref) => this.add(ref.kind, ref.id));
    }

    /**
     * Adds or removes the entity, keeping the rest — a modifier-click.
     *
     * @param {string} kind
     * @param {string} id
     */
    toggle(kind, id) {
        const index = this.indexOfRef(kind, id);

        if (index === -1) {
            this.refs.push({ kind: kind, id: id });

            return;
        }

        this.refs.splice(index, 1);
    }

    /**
     * @param {string} kind
     * @param {string} id
     */
    add(kind, id) {
        if (!this.has(kind, id)) {
            this.refs.push({ kind: kind, id: id });
        }
    }

    /**
     * @returns {{kind: string, id: string}|null} the selected entity, or null unless exactly one is
     *   selected — the inspector shows an entity's fields only when there is no ambiguity
     */
    single() {
        return this.refs.length === 1 ? this.refs[0] : null;
    }

    /**
     * @returns {Array<string>} the distinct kinds present in the selection
     */
    kinds() {
        const kinds = [];
        this.refs.forEach((ref) => {
            if (kinds.indexOf(ref.kind) === -1) {
                kinds.push(ref.kind);
            }
        });

        return kinds;
    }

    /**
     * @returns {Array<string>}
     */
    stateIds() {
        return this.idsOfKind(KIND_STATE);
    }

    /**
     * @returns {Array<string>}
     */
    transitionIds() {
        return this.idsOfKind(KIND_TRANSITION);
    }

    /**
     * Drops references to entities the model no longer holds — after a delete, an undo, or a
     * raw-XML edit that removed them.
     *
     * @param {Object} model StateMachineModel
     *
     * @returns {boolean} whether anything was dropped, so the caller can skip a needless re-render
     */
    retainExisting(model) {
        const survivors = this.refs.filter((ref) => this.existsInModel(model, ref));

        if (survivors.length === this.refs.length) {
            return false;
        }

        this.refs = survivors;

        return true;
    }

    /**
     * @param {Object} model StateMachineModel
     * @param {{kind: string, id: string}} ref
     *
     * @returns {boolean}
     */
    existsInModel(model, ref) {
        if (ref.kind === KIND_STATE) {
            return Boolean(model.getState(ref.id));
        }

        if (ref.kind === KIND_TRANSITION) {
            return Boolean(model.getTransition(ref.id));
        }

        return false;
    }

    idsOfKind(kind) {
        return this.refs.filter((ref) => ref.kind === kind).map((ref) => ref.id);
    }

    indexOfRef(kind, id) {
        for (let index = 0; index < this.refs.length; index += 1) {
            if (this.refs[index].kind === kind && this.refs[index].id === id) {
                return index;
            }
        }

        return -1;
    }
}

module.exports = { StateMachineSelection, KIND_STATE, KIND_TRANSITION };

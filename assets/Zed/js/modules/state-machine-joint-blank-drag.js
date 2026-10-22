import { RUBBER_BAND_MINIMUM_SIZE, THEME } from './state-machine-canvas-shared';

export class JointBlankDrag {
    /**
     * @param {Object} paper dia.Paper
     * @param {HTMLElement} mountElement
     * @param {HTMLElement} cursorElement
     * @param {Function} onSweep
     */
    constructor(paper, mountElement, cursorElement, onSweep) {
        this.paper = paper;
        this.mountElement = mountElement;
        this.cursorElement = cursorElement;
        this.onSweep = onSweep;
        this.panning = null;
        this.rubberBand = null;
        this.rubberBandElement = null;

        this.onDocumentMouseMove = (event) => {
            if (this.rubberBand) {
                this.#updateRubberBand(event);

                return;
            }

            if (!this.panning) {
                return;
            }

            this.paper.translate(
                this.panning.translateX + (event.clientX - this.panning.clientX),
                this.panning.translateY + (event.clientY - this.panning.clientY),
            );
        };

        this.onDocumentMouseUp = () => {
            this.#stopListening();

            if (this.rubberBand) {
                this.#finishRubberBand();

                return;
            }

            this.panning = null;
            this.cursorElement.style.cursor = '';
        };
    }

    destroy() {
        this.#stopListening();
        this.#removeRubberBandOverlay();
    }

    /**
     * The document follows the pointer only while a drag is on: the release can land anywhere on
     * the page, outside the canvas included.
     */
    #listen() {
        document.addEventListener('mousemove', this.onDocumentMouseMove);
        document.addEventListener('mouseup', this.onDocumentMouseUp);
    }

    #stopListening() {
        document.removeEventListener('mousemove', this.onDocumentMouseMove);
        document.removeEventListener('mouseup', this.onDocumentMouseUp);
    }

    beginPanning(event) {
        this.#listen();

        const translate = this.paper.translate();
        this.panning = {
            clientX: event.clientX,
            clientY: event.clientY,
            translateX: translate.tx,
            translateY: translate.ty,
        };
        this.cursorElement.style.cursor = 'grabbing';
    }

    beginRubberBand(event) {
        this.#listen();

        this.rubberBand = { startClientX: event.clientX, startClientY: event.clientY };
        this.rubberBandElement = document.createElement('div');
        this.rubberBandElement.style.position = 'absolute';
        this.rubberBandElement.style.pointerEvents = 'none';
        this.rubberBandElement.style.background = THEME.rubberBandFill;
        this.rubberBandElement.style.border = `1px solid ${THEME.rubberBandStroke}`;
        this.mountElement.appendChild(this.rubberBandElement);
        this.#updateRubberBand(event);
    }

    #updateRubberBand(event) {
        const bounds = this.mountElement.getBoundingClientRect();
        const rectangle = this.#currentRubberBandClientRectangle(event);

        this.rubberBandElement.style.left = `${rectangle.x - bounds.left}px`;
        this.rubberBandElement.style.top = `${rectangle.y - bounds.top}px`;
        this.rubberBandElement.style.width = `${rectangle.width}px`;
        this.rubberBandElement.style.height = `${rectangle.height}px`;
    }

    /**
     * @param {Object} event
     *
     * @returns {{x: number, y: number, width: number, height: number}} in client coordinates
     */
    #currentRubberBandClientRectangle(event) {
        return {
            x: Math.min(this.rubberBand.startClientX, event.clientX),
            y: Math.min(this.rubberBand.startClientY, event.clientY),
            width: Math.abs(event.clientX - this.rubberBand.startClientX),
            height: Math.abs(event.clientY - this.rubberBand.startClientY),
        };
    }

    /**
     * Reports what the band swept over. A band too small to be a deliberate drag is dropped without
     * reporting: that gesture was a click, and `blank:pointerclick` already handles it by clearing
     * the selection.
     */
    #finishRubberBand() {
        const bounds = this.rubberBandElement.getBoundingClientRect();
        this.#removeRubberBandOverlay();
        this.rubberBand = null;

        if (bounds.width < RUBBER_BAND_MINIMUM_SIZE && bounds.height < RUBBER_BAND_MINIMUM_SIZE) {
            return;
        }

        this.onSweep(this.paper.clientToLocalRect(bounds.left, bounds.top, bounds.width, bounds.height));
    }

    #removeRubberBandOverlay() {
        if (this.rubberBandElement) {
            this.rubberBandElement.remove();
            this.rubberBandElement = null;
        }
    }
}

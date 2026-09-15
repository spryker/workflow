'use strict';

/**
 * Project-extendable canvas renderer registry.
 *
 * Lives on `window` on purpose: the core Workflow bundle and a project (Pyz) Zed bundle are
 * separate webpack builds with no shared module graph, so the global object is the only channel a
 * project can register its own renderer through — without forking the core module:
 *
 *     // in a project bundle, loaded after the core Workflow bundle:
 *     window.WorkflowStateMachineRenderers.register('my-renderer', MyRenderer);
 *
 * Exactly one renderer is active per page. The strategy is chosen once, at builder initialization,
 * by the `data-renderer` attribute on the builder root (set in Twig, overridable at project
 * level); an unknown name falls back to the default JointJS renderer with a console warning, so a
 * missing project bundle degrades instead of breaking the page.
 */

const { CANVAS_RENDERER_METHODS } = require('./state-machine-canvas-contract');

const GLOBAL_KEY = 'WorkflowStateMachineRenderers';
const DEFAULT_RENDERER_NAME = 'joint';

function createRegistry() {
    const rendererByName = {};

    return {
        /**
         * @param {string} name strategy key used in the `data-renderer` template attribute
         * @param {Function} RendererClass class implementing the canvas contract
         */
        register(name, RendererClass) {
            const missingMethods = CANVAS_RENDERER_METHODS.filter(
                (methodName) => typeof RendererClass.prototype[methodName] !== 'function',
            );

            if (missingMethods.length) {
                throw new Error(
                    `Cannot register workflow canvas renderer "${name}": the class is missing ` +
                        `${missingMethods.join(', ')}. Implement the full canvas contract ` +
                        `(${CANVAS_RENDERER_METHODS.join(', ')}) — see state-machine-canvas-contract.js ` +
                        `in the Workflow module.`,
                );
            }

            rendererByName[name] = RendererClass;
        },

        /**
         * @param {string} name requested strategy; empty or unknown falls back to the default
         *
         * @returns {Function} the renderer class to instantiate
         */
        resolve(name) {
            if (name && rendererByName[name]) {
                return rendererByName[name];
            }

            if (name && name !== DEFAULT_RENDERER_NAME) {
                console.warn(
                    `Unknown workflow canvas renderer "${name}" requested via data-renderer; ` +
                        `falling back to "${DEFAULT_RENDERER_NAME}". Registered renderers: ` +
                        `${Object.keys(rendererByName).join(', ')}. Register the missing one before ` +
                        `the Workflow builder initializes.`,
                );
            }

            return rendererByName[DEFAULT_RENDERER_NAME];
        },
    };
}

function getRendererRegistry() {
    if (!window[GLOBAL_KEY]) {
        window[GLOBAL_KEY] = createRegistry();
    }

    return window[GLOBAL_KEY];
}

module.exports = { getRendererRegistry, DEFAULT_RENDERER_NAME };

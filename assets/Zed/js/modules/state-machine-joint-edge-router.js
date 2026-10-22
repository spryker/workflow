import { g } from '@joint/core';
import {
    FAN_OUT_TURN_BASE,
    FAN_OUT_TURN_STEP,
    PARALLEL_EDGE_GAP,
    buildFanOutIndex,
    buildParallelEdgeIndex,
    isForwardInPair,
    parallelGroupKey,
    parallelOffset,
    edgeRouteKey,
    perpendicularMidpointVertex,
    selfLoopVertices,
} from './state-machine-edge-geometry';
import { transitionLabelLines, transitionLabelSize } from './state-machine-transition-label';

const MANHATTAN_PADDING = 20;
const MANHATTAN_STEP = 10;

export const DEFAULT_ROUTER = { name: 'manhattan', args: { padding: MANHATTAN_PADDING, step: MANHATTAN_STEP } };

// Room around a moved state within which an edge may have steered around it: the router's own
// padding, and a grid step more so a path running right along that padding is caught too.
const SWEPT_AREA_MARGIN = MANHATTAN_PADDING + MANHATTAN_STEP;

const DEFAULT_ROUTE = { router: null, vertices: [] };

const LABEL_ESCAPE_SHIFTS = [0, -0.08, 0.08, -0.16, 0.16, -0.24, 0.24, -0.32, 0.32];

const LABEL_DISTANCE_MINIMUM = 0.05;
const LABEL_DISTANCE_MAXIMUM = 0.95;

const PARALLEL_LABEL_CLEARANCE = 12;

/**
 * @param {Array<{x: number, y: number}>} first
 * @param {Array<{x: number, y: number}>} second
 *
 * @returns {boolean}
 */
function isSameVertices(first, second) {
    return (
        first.length === second.length &&
        first.every((vertex, index) => vertex.x === second[index].x && vertex.y === second[index].y)
    );
}

export class JointEdgeRouter {
    /**
     * @param {Object} paper dia.Paper
     * @param {Object} positionByStateId
     * @param {Object} edgeRouteByPair
     */
    constructor(paper, positionByStateId, edgeRouteByPair) {
        this.paper = paper;
        this.positionByStateId = positionByStateId;
        this.edgeRouteByPair = edgeRouteByPair;
        this.setCells(null, {}, {});
    }

    /**
     * @param {Object|null} model StateMachineModel
     * @param {Object} elementByStateId state id to the drawn element
     * @param {Object} linkByTransitionId transition id to the drawn link
     */
    setCells(model, elementByStateId, linkByTransitionId) {
        const transitions = model ? model.transitions : [];

        this.model = model;
        this.elementByStateId = elementByStateId;
        this.linkByTransitionId = linkByTransitionId;
        this.parallelEdgeIndex = buildParallelEdgeIndex(transitions);
        this.fanOutIndex = buildFanOutIndex(transitions);
        this.transitionById = {};
        this.labelSizeByTransitionId = {};
        this.routedBoxByStateId = {};

        transitions.forEach((transition) => {
            this.transitionById[transition.id] = transition;
            this.labelSizeByTransitionId[transition.id] = transitionLabelSize(transitionLabelLines(model, transition));
        });
    }

    /**
     * Only changed routes are written: each write re-runs JointJS's router for that edge, so
     * rewriting every edge on every step of a drag makes large workflows crawl.
     * @param {{full: boolean}} [options]
     *   states moved
     */
    route({ full = false } = {}) {
        const model = this.model;

        if (!model) {
            return;
        }

        const sweptAreas = this.#collectSweptAreas();
        const parallelGapByPair = this.#measureParallelGaps(model);
        const fanOutCorridorIndex = this.#buildFanOutCorridorIndex();

        model.transitions.forEach((transition) => {
            const link = this.linkByTransitionId[transition.id];
            const sourceElement = this.elementByStateId[transition.sourceId];
            const targetElement = this.elementByStateId[transition.targetId];

            if (!link || !sourceElement || !targetElement) {
                return;
            }

            const plannedRoute = this.#planRoute(transition, sourceElement, targetElement, {
                gap: parallelGapByPair[parallelGroupKey(transition)] || PARALLEL_EDGE_GAP,
                corridor: fanOutCorridorIndex[transition.id],
            });

            if (this.#applyRoute(link, plannedRoute) || !plannedRoute.vertices.length) {
                return;
            }

            if (full || this.#isCrossingAny(link, sweptAreas)) {
                const linkView = link.findView(this.paper);

                if (linkView) {
                    linkView.requestConnectionUpdate();
                }
            }
        });

        this.#placeLabels(model, fanOutCorridorIndex);
        this.#rememberRoutedBoxes();
    }

    /**
     * @param {Object} transition
     * @param {Object} sourceElement
     * @param {Object} targetElement
     * @param {{gap: number, corridor: ({rank: number}|undefined)}} spacing
     *
     * @returns {{router: (string|null), vertices: Array<{x: number, y: number}>}}
     */
    #planRoute(transition, sourceElement, targetElement, spacing) {
        const edgePosition = this.parallelEdgeIndex[transition.id];
        const layoutVertices = this.#layoutRouteVertices(transition, edgePosition);

        if (layoutVertices) {
            return { router: null, vertices: layoutVertices };
        }

        return (
            this.#parallelEdgeRoute(sourceElement, targetElement, edgePosition, {
                isForward: isForwardInPair(transition),
                gap: spacing.gap,
            }) ||
            this.#fanOutRoute(sourceElement, targetElement, spacing.corridor) ||
            DEFAULT_ROUTE
        );
    }

    /**
     * Router and bends go in one write, so JointJS routes the edge once.
     *
     * @param {Object} link
     * @param {{router: (string|null), vertices: Array<{x: number, y: number}>}} plannedRoute
     *
     * @returns {boolean} whether the route changed
     */
    #applyRoute(link, plannedRoute) {
        const router = link.get('router');

        if (
            (router ? router.name : null) === plannedRoute.router &&
            isSameVertices(link.vertices(), plannedRoute.vertices)
        ) {
            return false;
        }

        link.set({
            router: plannedRoute.router ? { name: plannedRoute.router } : null,
            vertices: plannedRoute.vertices,
        });

        return true;
    }

    /**
     * @returns {Array<Object>} g.Rect
     */
    #collectSweptAreas() {
        const areas = [];

        Object.keys(this.elementByStateId).forEach((stateId) => {
            const box = this.elementByStateId[stateId].getBBox();
            const routedBox = this.routedBoxByStateId[stateId];

            if (routedBox && !routedBox.equals(box)) {
                areas.push(routedBox.clone().inflate(SWEPT_AREA_MARGIN), box.inflate(SWEPT_AREA_MARGIN));
            }
        });

        return areas;
    }

    #rememberRoutedBoxes() {
        Object.keys(this.elementByStateId).forEach((stateId) => {
            this.routedBoxByStateId[stateId] = this.elementByStateId[stateId].getBBox();
        });
    }

    /**
     * @param {Object} link
     * @param {Array<Object>} areas g.Rect
     *
     * @returns {boolean}
     */
    #isCrossingAny(link, areas) {
        if (!areas.length) {
            return false;
        }

        const linkView = link.findView(this.paper);
        const connection = linkView && linkView.getConnection();

        if (!connection) {
            return false;
        }

        const pathBox = connection.bbox();

        return areas.some((area) => pathBox.intersect(area));
    }

    /**
     * @param {Object} transition
     * @param {{count: number, isSelfLoop: boolean}|undefined} edgePosition
     *
     * @returns {Array<{x: number, y: number}>|null}
     */
    #layoutRouteVertices(transition, edgePosition) {
        if (!edgePosition || edgePosition.count !== 1 || edgePosition.isSelfLoop) {
            return null;
        }

        const route = this.edgeRouteByPair[edgeRouteKey(transition.sourceId, transition.targetId)];

        if (!route || !route.points.length) {
            return null;
        }

        if (
            !this.#isWhereLayoutPutIt(transition.sourceId, route.sourcePosition) ||
            !this.#isWhereLayoutPutIt(transition.targetId, route.targetPosition)
        ) {
            return null;
        }

        return route.points.map((point) => ({ x: point.x, y: point.y }));
    }

    #isWhereLayoutPutIt(stateId, layoutPosition) {
        const position = this.positionByStateId[stateId];

        return Boolean(
            position &&
            layoutPosition &&
            Math.round(position.x) === Math.round(layoutPosition.x) &&
            Math.round(position.y) === Math.round(layoutPosition.y),
        );
    }

    /**
     * @param {Object} model StateMachineModel
     *
     * @returns {Object}
     */
    #measureParallelGaps(model) {
        const gapByPair = {};

        model.transitions.forEach((transition) => {
            const sourceElement = this.elementByStateId[transition.sourceId];
            const targetElement = this.elementByStateId[transition.targetId];

            if (!sourceElement || !targetElement || transition.sourceId === transition.targetId) {
                return;
            }

            const sourceCenter = sourceElement.getBBox().center();
            const targetCenter = targetElement.getBBox().center();
            const isVertical = Math.abs(targetCenter.y - sourceCenter.y) > Math.abs(targetCenter.x - sourceCenter.x);
            const size = this.labelSizeByTransitionId[transition.id];
            const needed = (isVertical ? size.width : size.height) + PARALLEL_LABEL_CLEARANCE;
            const key = parallelGroupKey(transition);

            gapByPair[key] = Math.max(gapByPair[key] || PARALLEL_EDGE_GAP, needed);
        });

        return gapByPair;
    }

    /**
     * A fan-out edge takes its label on the final run into its target. Halfway along the whole path
     * it would land among sibling corridors, nearer another state's edge than its own.
     *
     * @param {Object} model StateMachineModel
     * @param {Object} fanOutCorridorIndex
     */
    #placeLabels(model, fanOutCorridorIndex) {
        const stateBoxes = Object.keys(this.elementByStateId).map((stateId) =>
            this.elementByStateId[stateId].getBBox(),
        );

        model.transitions.forEach((transition) => {
            const link = this.linkByTransitionId[transition.id];

            if (!link || !link.labels().length) {
                return;
            }

            const linkView = link.findView(this.paper);
            const size = this.labelSizeByTransitionId[transition.id];

            if (!linkView || !size.width) {
                return;
            }

            const preferred =
                fanOutCorridorIndex[transition.id] && link.vertices().length
                    ? this.#finalRunMidpointRatio(linkView, link.vertices())
                    : 0.5;
            const candidates = LABEL_ESCAPE_SHIFTS.map((shift) =>
                Math.min(LABEL_DISTANCE_MAXIMUM, Math.max(LABEL_DISTANCE_MINIMUM, preferred + shift)),
            );
            const clearDistance = candidates.find((distance) =>
                this.#isLabelSpotClear(linkView, distance, size, stateBoxes),
            );
            const distance = clearDistance === undefined ? preferred : clearDistance;
            const position = link.label(0).position;

            if (!position || position.distance !== distance) {
                link.label(0, { position: { distance: distance } });
            }
        });
    }

    /**
     * @param {Object} linkView dia.LinkView of a routed edge
     * @param {Array<{x: number, y: number}>} vertices
     *
     * @returns {number}
     */
    #finalRunMidpointRatio(linkView, vertices) {
        const lastTurnRatio = linkView.getClosestPointRatio(vertices[vertices.length - 1]);

        return lastTurnRatio + (1 - lastTurnRatio) / 2;
    }

    /**
     * @param {Object} linkView dia.LinkView
     * @param {number} distance ratio along the path
     * @param {{width: number, height: number}} size
     * @param {Array<Object>} stateBoxes g.Rect per state
     *
     * @returns {boolean}
     */
    #isLabelSpotClear(linkView, distance, size, stateBoxes) {
        const point = linkView.getPointAtRatio(distance);

        if (!point) {
            return false;
        }

        const labelBox = new g.Rect(point.x - size.width / 2, point.y - size.height / 2, size.width, size.height);

        return !stateBoxes.some((box) => labelBox.intersect(box));
    }

    /**
     * Bows edges that share a state pair: routed by the shortest path alone they would all take the same line.
     *
     * @param {Object} sourceElement
     * @param {Object} targetElement
     * @param {{index: number, count: number, isSelfLoop: boolean}|undefined} edgePosition
     * @param {{isForward: boolean, gap: number}} bow
     *
     * @returns {{router: (string|null), vertices: Array<{x: number, y: number}>}|null}
     */
    #parallelEdgeRoute(sourceElement, targetElement, edgePosition, bow) {
        if (!edgePosition) {
            return null;
        }

        if (edgePosition.isSelfLoop) {
            return {
                router: 'normal',
                vertices: selfLoopVertices(sourceElement.getBBox().center(), edgePosition.index),
            };
        }

        if (edgePosition.count < 2) {
            return null;
        }

        const sourceCenter = sourceElement.getBBox().center();
        const targetCenter = targetElement.getBBox().center();
        const offset = parallelOffset(edgePosition.index, edgePosition.count, bow.gap);
        const vertex = bow.isForward
            ? perpendicularMidpointVertex(sourceCenter, targetCenter, offset)
            : perpendicularMidpointVertex(targetCenter, sourceCenter, offset);

        // Routed through the vertex by the default router, not as a straight polyline: that drew two
        // diagonals which did not bend at all once a state had been dragged off the row.
        return vertex ? { router: null, vertices: [vertex] } : null;
    }

    /**
     * The edge to the farthest target turns first, closest to the source, so the fan does not cross itself.
     *
     * Shares buildFanOutIndex with the layout, so the room for corridors goes to exactly the edges that get one.
     *
     * @returns {Object} transition id to {rank}
     */
    #buildFanOutCorridorIndex() {
        const corridorByTransitionId = {};

        Object.keys(this.fanOutIndex).forEach((sourceId) => {
            const sourceElement = this.elementByStateId[sourceId];

            if (!sourceElement) {
                return;
            }

            const sourceCenterY = sourceElement.getBBox().center().y;

            this.fanOutIndex[sourceId]
                .map((transitionId) => {
                    const transition = this.transitionById[transitionId];
                    const targetElement = transition && this.elementByStateId[transition.targetId];

                    return targetElement
                        ? {
                              id: transitionId,
                              verticalDistance: Math.abs(targetElement.getBBox().center().y - sourceCenterY),
                          }
                        : null;
                })
                .filter(Boolean)
                .sort((first, second) => second.verticalDistance - first.verticalDistance)
                .forEach((sibling, rank) => {
                    corridorByTransitionId[sibling.id] = { rank: rank };
                });
        });

        return corridorByTransitionId;
    }

    /**
     * A target on the source's own row, or one no longer beyond the corridor, keeps the default router:
     * the corridor's turn would fall inside the target's own box.
     *
     * @param {Object} sourceElement
     * @param {Object} targetElement
     * @param {{rank: number}|undefined} corridor
     *
     * @returns {{router: string, vertices: Array<{x: number, y: number}>}|null}
     */
    #fanOutRoute(sourceElement, targetElement, corridor) {
        if (!corridor) {
            return null;
        }

        const sourceBox = sourceElement.getBBox();
        const targetBox = targetElement.getBBox();
        const targetCenterY = targetBox.center().y;
        const sourceCenterY = sourceBox.center().y;
        const turnX = sourceBox.x + sourceBox.width + FAN_OUT_TURN_BASE + corridor.rank * FAN_OUT_TURN_STEP;

        if (Math.round(targetCenterY) === Math.round(sourceCenterY) || targetBox.x <= turnX + FAN_OUT_TURN_BASE) {
            return null;
        }

        return {
            router: 'normal',
            vertices: [
                { x: turnX, y: sourceCenterY },
                { x: turnX, y: targetCenterY },
            ],
        };
    }
}

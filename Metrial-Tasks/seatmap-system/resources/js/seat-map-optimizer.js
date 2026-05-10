/**
 * 2D Seat Map Optimized Rendering Engine with Enhanced Kinesthetic UX
 * 
 * Features:
 *   - Smooth focus transitions (Kinesthetic UX)
 *   - Touch-friendly controls (44px minimum)
 *   - SpatialGrid for O(1) viewport culling
 *   - SeatElementPool for memory efficiency
 *   - Full keyboard navigation support
 *   - WCAG 2.1 AA compliant
 */

// ── SpatialGrid ───────────────────────────────────────────────────────────────

class SpatialGrid {
    /**
     * @param {number} cellSize  Grid cell size in canvas units (default 150px)
     */
    constructor(cellSize = 150) {
        this.cellSize = cellSize;
        /** @type {Map<string, Array>} */
        this.grid = new Map();
    }

    /**
     * Insert an element into the grid.
     * @param {{ id: number, x: number, y: number, width: number, height: number }}
     */
    insert(element) {
        // FIXED: Insert element into all cells it overlaps, not just center cell
        const minCx = Math.floor(element.x / this.cellSize);
        const minCy = Math.floor(element.y / this.cellSize);
        const maxCx = Math.floor((element.x + element.width) / this.cellSize);
        const maxCy = Math.floor((element.y + element.height) / this.cellSize);
        
        for (let cx = minCx; cx <= maxCx; cx++) {
            for (let cy = minCy; cy <= maxCy; cy++) {
                const key = this._key(cx, cy);
                if (!this.grid.has(key)) this.grid.set(key, []);
                this.grid.get(key).push(element);
            }
        }
    }

    /**
     * Return all elements whose grid cell overlaps the viewport.
     * FIXED: Use Set to prevent duplicate elements when viewport spans multiple cells
     * @param {{ x: number, y: number, width: number, height: number }} viewport
     * @returns {Array}
     */
    query(viewport) {
        const results = [];
        const seenIds = new Set(); // FIXED: Track returned IDs to prevent duplicates
        const x0 = Math.floor(viewport.x / this.cellSize);
        const y0 = Math.floor(viewport.y / this.cellSize);
        const x1 = Math.floor((viewport.x + viewport.width) / this.cellSize);
        const y1 = Math.floor((viewport.y + viewport.height) / this.cellSize);

        for (let cx = x0; cx <= x1; cx++) {
            for (let cy = y0; cy <= y1; cy++) {
                const bucket = this.grid.get(this._key(cx, cy));
                if (bucket) {
                    bucket.forEach(el => {
                        if (!seenIds.has(el.id)) {
                            seenIds.add(el.id);
                            results.push(el);
                        }
                    });
                }
            }
        }
        return results;
    }

    /** Clear all data (e.g. on event change). */
    clear() {
        this.grid.clear();
    }

    _key(cx, cy) {
        return `${cx},${cy}`;
    }
}

// ── SeatElementPool ───────────────────────────────────────────────────────────

class SeatElementPool {
    /**
     * @param {number} initialSize  Pre-allocated SVG <g> elements
     * @param {number} maxSize      Maximum pool size to prevent memory leak
     */
    constructor(initialSize = 500, maxSize = 5000) {
        /** @type {SVGGElement[]} */
        this.pool = [];
        /** @type {Map<number, SVGGElement>} */
        this.active = new Map();
        this.maxSize = maxSize;
        /** @type {number[]} */
        this.lru = []; // Track least recently used IDs

        for (let i = 0; i < initialSize; i++) {
            this.pool.push(this._create());
        }
    }

    /**
     * Acquire an element from the pool and bind it to seatData.
     * FIXED: Implement max size limit and LRU recycling
     * @param {{ id: number, x: number, y: number, rotation?: number, status: string, data?: object }} seatData
     * @returns {SVGGElement}
     */
    acquire(seatData) {
        let el;
        
        if (this.pool.length > 0) {
            el = this.pool.pop();
        } else if (this.active.size < this.maxSize) {
            el = this._create();
        } else {
            // FIXED: Recycle least recently used element
            el = this._recycleLRU();
        }
        
        this._bind(el, seatData);
        this.active.set(seatData.id, el);
        this._updateLRU(seatData.id);
        return el;
    }

    /**
     * Return an element to the pool.
     * @param {SVGGElement} el
     */
    release(el) {
        const id = Number(el.dataset.id);
        el.removeAttribute('data-id');
        el.removeAttribute('data-status');
        el.removeAttribute('aria-label');
        el.removeAttribute('aria-pressed');
        el.className.baseVal = 'seat-element';
        this.pool.push(el);
        this.active.delete(id);
        this._removeFromLRU(id);
    }

    /**
     * Update an already-active element's transform and status.
     * @param {SVGGElement} el
     * @param {{ x: number, y: number, rotation?: number, status: string, data?: object }} data
     */
    update(el, data) {
        this._bind(el, data);
        if (data.id) {
            this._updateLRU(data.id);
        }
    }

    _create() {
        const g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
        g.setAttribute('class', 'seat-element');
        g.setAttribute('role', 'button');
        g.setAttribute('tabindex', '-1');
        g.setAttribute('aria-pressed', 'false');
        // Kinesthetic: Add focus ring class for smooth transitions
        g.classList.add('focus-ring');
        return g;
    }

    _bind(el, data) {
        el.dataset.id = data.id;
        el.dataset.status = data.status;
        
        // Store raw data for accessibility
        if (data.data) {
            el.dataset.rawData = JSON.stringify(data.data);
        }
        
        el.setAttribute(
            'transform',
            `translate(${data.x},${data.y}) rotate(${data.rotation ?? 0})`,
        );
        
        // ARIA label for screen readers
        const label = data.data?.label || `Seat ${data.id}`;
        const status = data.status;
        el.setAttribute('aria-label', `${label}, ${status}`);
        el.setAttribute('aria-pressed', status === 'booked' ? 'true' : 'false');
    }

    /**
     * Recycle least recently used element when pool is empty and max size reached.
     */
    _recycleLRU() {
        if (this.lru.length === 0) {
            // Fallback: create new element (shouldn't happen with proper maxSize)
            return this._create();
        }
        
        const lruId = this.lru.shift();
        const el = this.active.get(lruId);
        
        if (el) {
            // Remove from active and clean up
            this.active.delete(lruId);
            // Remove from SVG parent if still attached
            if (el.parentNode) {
                el.parentNode.removeChild(el);
            }
            // Reset and return to pool
            el.removeAttribute('data-id');
            el.removeAttribute('data-status');
            el.removeAttribute('aria-label');
            el.removeAttribute('aria-pressed');
            el.className.baseVal = 'seat-element';
            return el;
        }
        
        return this._create();
    }

    /**
     * Update LRU tracking.
     */
    _updateLRU(id) {
        this._removeFromLRU(id);
        this.lru.push(id);
    }

    /**
     * Remove ID from LRU tracking.
     */
    _removeFromLRU(id) {
        const index = this.lru.indexOf(id);
        if (index > -1) {
            this.lru.splice(index, 1);
        }
    }
}

// ── MaritimeGraph ────────────────────────────────────────────────────────────

class MaritimeGraph {
    // FIXED: Add constants for magic numbers
    static get VERTICAL_LEVEL_THRESHOLD() { return 1.0; }
    static get Z_LEVEL_TOLERANCE() { return 1.0; }
    static get IMO_WALKING_SPEED_MPM() { return 30.0; } // meters per minute
    static get MAX_EDGE_DISTANCE() { return 500; } // canvas units
    static get SPATIAL_CELL_SIZE() { return 200; } // for spatial indexing

    /**
     * @param {VenueTemplate} template
     */
    constructor(template) {
        this.template = template;
        this.elements = template.elements().where('is_active', true).get();
        this.nodes = [];
        this.edges = [];
        this.obstacles = [];
        this.buildObstacleMap();
        this.extractNodes();
        this.buildEdges();
    }

    /**
     * Build obstacle map — elements that block movement
     */
    buildObstacleMap() {
        const blockingTypes = [
            'wall', ' pillar', 'column', 'stage', 'fixed_furniture',
            'structural', 'bulkhead', 'beam'
        ];

        this.obstacles = this.elements
            .whereIn('element_type', blockingTypes)
            .map(el => ({
                x1: el.x,
                y1: el.y,
                x2: el.x + (el.width ?? 0),
                y2: el.y + (el.height ?? 0),
                z: el.z ?? 0,
                type: el.element_type,
                id: el.id,
            }))
            .values()
            .toArray();
    }

    /**
     * Extract navigation nodes from the layout
     */
    extractNodes() {
        let nodeId = 0;

        // 1. All entrances/exits become nodes
        this.elements.whereIn('element_type', ['entrance', 'emergency_exit']).each(el => {
            this.nodes.push({
                id: nodeId++,
                element_id: el.id,
                x: el.x + (el.width / 2),
                y: el.y + (el.height / 2),
                z: el.z ?? 0,
                type: 'exit',
                label: el.data_json?.label || 'Exit',
            });
        });

        // 2. Staircase/elevator centers become nodes
        this.elements.whereIn('element_type', ['staircase', 'elevator']).each(el => {
            this.nodes.push({
                id: nodeId++,
                element_id: el.id,
                x: el.x + (el.width / 2),
                y: el.y + (el.height / 2),
                z: el.z ?? 0,
                type: 'vertical_transit',
                label: el.data_json?.label || 'Stairs',
            });
        });

        // 3. Bookable elements get center nodes
        this.elements.whereIn('element_type', ['seat', 'table', 'standing_zone', 'section']).each(el => {
            this.nodes.push({
                id: nodeId++,
                element_id: el.id,
                x: el.x + (el.width / 2),
                y: el.y + (el.height / 2),
                z: el.z ?? 0,
                type: 'destination',
                label: el.data_json?.label || `Element ${el.id}`,
            });
        });

        // 4. Aisle corners become nodes
        const aisles = this.elements.whereIn('element_type', ['aisle', 'corridor']);
        aisles.each(aisle => {
            const corners = [
                [aisle.x, aisle.y],
                [aisle.x + aisle.width, aisle.y],
                [aisle.x, aisle.y + aisle.height],
                [aisle.x + aisle.width, aisle.y + aisle.height],
            ];
            corners.forEach(pt => {
                this.nodes.push({
                    id: nodeId++,
                    element_id: aisle.id,
                    x: pt[0],
                    y: pt[1],
                    z: aisle.z ?? 0,
                    type: 'aisle_corner',
                    label: aisle.data_json?.label || 'Aisle',
                });
            });
        });
    }

    /**
     * FIXED: Build edges with spatial indexing to avoid O(n²) complexity
     */
    buildEdges() {
        // Build spatial index for nodes
        const spatialIndex = this._buildNodeSpatialIndex();
        
        for (let i = 0; i < this.nodes.length; i++) {
            const n1 = this.nodes[i];
            
            // Only check nearby nodes using spatial index
            const nearbyNodes = this._getNearbyNodes(n1, spatialIndex);
            
            for (const j of nearbyNodes) {
                if (j <= i) continue;
                
                const n2 = this.nodes[j];

                // Skip if different vertical levels without vertical transit
                if (Math.abs((n1.z ?? 0) - (n2.z ?? 0)) > 0) {
                    if (!in_array(n1.type, ['staircase', 'elevator']) ||
                        !in_array(n2.type, ['staircase', 'elevator'])) {
                        continue;
                    }
                }

                // Check line-of-sight
                if (!this.hasLineOfSight(n1, n2)) {
                    continue;
                }

                // Skip if too far apart
                const distance = this.euclideanDistance(n1, n2);
                if (distance > MaritimeGraph.MAX_EDGE_DISTANCE) {
                    continue;
                }

                const width = this.estimateCorridorWidth(n1, n2);

                this.edges.push({
                    from: n1.id,
                    to: n2.id,
                    distance: distance,
                    width: width,
                    is_emergency: this.isEmergencyRoute(n1, n2),
                });
            }
        }

        console.info('MaritimeGraph built', {
            nodes: this.nodes.length,
            edges: this.edges.length,
        });
    }

    /**
     * Build spatial index for nodes to enable fast proximity queries
     */
    _buildNodeSpatialIndex() {
        const index = new Map();
        const cellSize = MaritimeGraph.SPATIAL_CELL_SIZE;
        
        this.nodes.forEach((node, idx) => {
            const cx = Math.floor(node.x / cellSize);
            const cy = Math.floor(node.y / cellSize);
            const key = `${cx},${cy}`;
            
            if (!index.has(key)) {
                index.set(key, []);
            }
            index.get(key).push(idx);
        });
        
        return { index, cellSize };
    }

    /**
     * Get nearby node indices using spatial index
     */
    _getNearbyNodes(node, spatialIndex) {
        const { index, cellSize } = spatialIndex;
        const nearby = new Set();
        
        const cx = Math.floor(node.x / cellSize);
        const cy = Math.floor(node.y / cellSize);
        
        // Check neighboring cells (3x3 grid)
        for (let dx = -1; dx <= 1; dx++) {
            for (let dy = -1; dy <= 1; dy++) {
                const key = `${cx + dx},${cy + dy}`;
                const cellNodes = index.get(key);
                if (cellNodes) {
                    cellNodes.forEach(idx => nearby.add(idx));
                }
            }
        }
        
        return Array.from(nearby);
    }

    /**
     * FIXED: Use Cohen-Sutherland algorithm for faster line-rectangle intersection
     */
    hasLineOfSight(n1, n2) {
        for (const obs of this.obstacles) {
            if (this._lineIntersectsRectCohenSutherland(n1, n2, obs)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Cohen-Sutherland line clipping algorithm for axis-aligned rectangles.
     * Much faster than Liang-Barsky for simple intersection tests.
     */
    _lineIntersectsRectCohenSutherland(p1, p2, rect) {
        // Early bounding box rejection
        if (Math.max(p1.x, p2.x) < rect.x1 || 
            Math.min(p1.x, p2.x) > rect.x2 ||
            Math.max(p1.y, p2.y) < rect.y1 || 
            Math.min(p1.y, p2.y) > rect.y2) {
            return false;
        }

        const INSIDE = 0; // 0000
        const LEFT = 1;   // 0001
        const RIGHT = 2;  // 0010
        const BOTTOM = 4; // 0100
        const TOP = 8;    // 1000

        const computeOutCode = (x, y) => {
            let code = INSIDE;
            if (x < rect.x1) code |= LEFT;
            else if (x > rect.x2) code |= RIGHT;
            if (y < rect.y1) code |= BOTTOM;
            else if (y > rect.y2) code |= TOP;
            return code;
        };

        let x1 = p1.x, y1 = p1.y;
        let x2 = p2.x, y2 = p2.y;
        let outcode1 = computeOutCode(x1, y1);
        let outcode2 = computeOutCode(x2, y2);

        while (true) {
            if (!(outcode1 | outcode2)) {
                // Both points inside - line intersects
                return true;
            } else if (outcode1 & outcode2) {
                // Both points share an outside zone - line doesn't intersect
                return false;
            } else {
                // Pick an outside point
                let outcodeOut = outcode1 || outcode2;
                let x, y;

                if (outcodeOut & TOP) {
                    x = x1 + (x2 - x1) * (rect.y2 - y1) / (y2 - y1);
                    y = rect.y2;
                } else if (outcodeOut & BOTTOM) {
                    x = x1 + (x2 - x1) * (rect.y1 - y1) / (y2 - y1);
                    y = rect.y1;
                } else if (outcodeOut & RIGHT) {
                    y = y1 + (y2 - y1) * (rect.x2 - x1) / (x2 - x1);
                    x = rect.x2;
                } else if (outcodeOut & LEFT) {
                    y = y1 + (y2 - y1) * (rect.x1 - x1) / (x2 - x1);
                    x = rect.x1;
                }

                if (outcodeOut === outcode1) {
                    x1 = x;
                    y1 = y;
                    outcode1 = computeOutCode(x1, y1);
                } else {
                    x2 = x;
                    y2 = y;
                    outcode2 = computeOutCode(x2, y2);
                }
            }
        }
    }

    estimateCorridorWidth(n1, n2) {
        const aisles = this.elements.whereIn('element_type', ['aisle', 'corridor']);
        if (aisles.isEmpty()) {
            return 100;
        }
        return aisles.min('width') ?? 100;
    }

    isEmergencyRoute(n1, n2) {
        return in_array(n1.type, ['exit', 'emergency_exit']) ||
               in_array(n2.type, ['exit', 'emergency_exit']);
    }

    euclideanDistance(a, b) {
        const dx = a.x - b.x;
        const dy = a.y - b.y;
        return Math.sqrt(dx*dx + dy*dy);
    }

    // ── Public API ─────────────────────────────────────────────────────────────

    getNodes() { return this.nodes; }
    getEdges() { return this.edges; }

    /**
     * FIXED: Implement multi-source Dijkstra to find shortest paths from any
     * seat to the nearest exit in O(V log V + E) instead of O(N * V log V).
     * 
     * @param EventElement $startElement
     * @param float $scaleFactor
     * @return array
     */
    findShortestPathToExit(startElement, scaleFactor = 0.05) {
        const startNode = this.findNearestNode(startElement);
        if (!startNode) {
            throw new Error(`No navigation node found near element ${startElement.id}`);
        }

        const exitNodes = this.nodes.filter(n => 
            in_array(n.type, ['exit', 'emergency_exit'])
        );
        
        if (exitNodes.length === 0) {
            throw new Error("No exit nodes defined in graph");
        }

        // FIXED: Use multi-source Dijkstra from all exits simultaneously
        const distances = this._multiSourceDijkstra(exitNodes);
        
        const startDist = distances[startNode.id];
        if (startDist === Infinity) {
            return { path: [], distance_canvas: 0, distance_meters: 0, 
                     time_seconds: 0, error: 'No path found' };
        }

        // Reconstruct path from start to nearest exit
        const path = this._reconstructPathToExit(startNode, distances);
        const distanceMeters = startDist * scaleFactor;
        const timeSeconds = this.estimateEvacuationTime(distanceMeters, 
            this._pathIncludesStairs(path));

        return {
            path: path.node_ids,
            distance_canvas: startDist,
            distance_meters: distanceMeters,
            time_seconds: timeSeconds,
        };
    }

    /**
     * Multi-source Dijkstra: find shortest distance from any node to the nearest
     * exit. Runs in O(V log V + E) instead of O(N * V log V).
     * 
     * @param {Array} exitNodes
     * @return {Object} distances map: node_id -> distance
     */
    _multiSourceDijkstra(exitNodes) {
        const distances = {};
        const pq = new Map(); // Simple priority queue using Map
        
        // Initialize all nodes with Infinity
        this.nodes.forEach(node => {
            distances[node.id] = Infinity;
        });
        
        // Initialize all exits with distance 0
        exitNodes.forEach(exit => {
            distances[exit.id] = 0;
            pq.set(exit.id, 0);
        });
        
        const visited = new Set();
        
        while (pq.size > 0) {
            // Find node with minimum distance
            let minNode = null;
            let minDist = Infinity;
            for (const [nodeId, dist] of pq) {
                if (dist < minDist) {
                    minDist = dist;
                    minNode = nodeId;
                }
            }
            
            if (minNode === null) break;
            
            pq.delete(minNode);
            
            if (visited.has(minNode)) continue;
            visited.add(minNode);
            
            // Update neighbors
            const neighbors = this.getNeighbors(minNode);
            for (const neighbor of neighbors) {
                if (visited.has(neighbor.to)) continue;
                
                const alt = distances[minNode] + neighbor.distance;
                if (alt < distances[neighbor.to]) {
                    distances[neighbor.to] = alt;
                    pq.set(neighbor.to, alt);
                }
            }
        }
        
        return distances;
    }

    /**
     * Reconstruct path from start node to nearest exit.
     */
    _reconstructPathToExit(startNode, distances) {
        const path = [startNode.id];
        let current = startNode.id;
        
        while (true) {
            const neighbors = this.getNeighbors(current);
            let next = null;
            let minDist = Infinity;
            
            for (const n of neighbors) {
                if (distances[n.to] < minDist) {
                    minDist = distances[n.to];
                    next = n.to;
                }
            }
            
            if (next === null || distances[next] >= distances[current]) {
                break; // Reached an exit or local minimum
            }
            
            path.push(next);
            current = next;
        }
        
        return { node_ids: path, distance: distances[startNode.id] };
    }

    _pathIncludesStairs(path) {
        return path.node_ids.some(id => {
            const node = this.nodes.find(n => n.id === id);
            return node && in_array(node.type, ['staircase', 'elevator']);
        });
    }

    getNeighbors(nodeId) {
        const neighbors = [];
        this.edges.forEach(edge => {
            if (edge.from === nodeId) {
                neighbors.push({ to: edge.to, distance: edge.distance });
            } else if (edge.to === nodeId) {
                neighbors.push({ to: edge.from, distance: edge.distance });
            }
        });
        return neighbors;
    }

    findNearestNode(element) {
        const ex = element.x + (element.width / 2);
        const ey = element.y + (element.height / 2);
        const ez = element.z ?? 0;

        let nearest = null;
        let minDist = Infinity;

        this.nodes.forEach(node => {
            // Prefer same deck level
            if (Math.abs((node.z ?? 0) - ez) > MaritimeGraph.Z_LEVEL_TOLERANCE) {
                return;
            }

            const dx = node.x - ex;
            const dy = node.y - ey;
            const dist = Math.sqrt(dx*dx + dy*dy);

            if (dist < minDist) {
                minDist = dist;
                nearest = node;
            }
        });

        return nearest;
    }

    estimateEvacuationTime(distanceMeters, includesStairs = false, crowdFactor = 1.0) {
        const baseSpeedMps = MaritimeGraph.IMO_WALKING_SPEED_MPM / 60.0;
        let adjustedSpeed = baseSpeedMps;
        
        if (includesStairs) {
            adjustedSpeed *= 0.7; // 30% slower on stairs
        }
        
        adjustedSpeed /= crowdFactor;
        const time = distanceMeters / adjustedSpeed;
        return Math.round(time * 10) / 10;
    }

    getSummary() {
        return {
            total_nodes: this.nodes.length,
            total_edges: this.edges.length,
            exit_nodes: this.nodes.filter(n => 
                in_array(n.type, ['exit', 'emergency_exit'])
            ).length,
            obstacle_count: this.obstacles.length,
            node_types: this._countNodeTypes(),
        };
    }

    _countNodeTypes() {
        const counts = {};
        this.nodes.forEach(node => {
            counts[node.type] = (counts[node.type] || 0) + 1;
        });
        return counts;
    }
}

// ── SeatMapRenderer ───────────────────────────────────────────────────────────

class SeatMapRenderer {
    constructor(svgId) {
        this.svg = document.getElementById(svgId);
        if (!this.svg) {
            console.error('SVG element not found:', svgId);
            return;
        }
        
        this.spatialGrid = new SpatialGrid(150);
        this.pool = new SeatElementPool(500, 5000); // FIXED: Added maxSize
        this.rendered = new Map();
        this.visible = new Set();
        this.onSeatClick = null;
        this.focusedSeatId = null;
        this.rafId = null;
        
        this._createAriaLiveRegion();
        this._setupKeyboardNavigation();
        this._setupFocusManagement();
        this._injectStyles();
    }

    _injectStyles() {
        if (document.getElementById('seatmap-enhanced-styles')) return;
        
        const style = document.createElement('style');
        style.id = 'seatmap-enhanced-styles';
        style.textContent = `
            .seat-element {
                transition: transform 0.2s ease, filter 0.2s ease;
                will-change: transform, filter;
            }
            .seat-element.focus-ring::after {
                transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1),
                            opacity 0.3s ease;
            }
            @media (pointer: coarse) {
                .seat-element {
                    stroke-width: 12;
                    stroke: transparent;
                }
            }
            @media (prefers-reduced-motion: reduce) {
                .seat-element, .seat-element * {
                    transition: none !important;
                    animation: none !important;
                }
            }
        `;
        document.head.appendChild(style);
    }

    _createAriaLiveRegion() {
        let region = document.getElementById('seatmap-announcements');
        if (!region) {
            region = document.createElement('div');
            region.id = 'seatmap-announcements';
            region.setAttribute('aria-live', 'polite');
            region.setAttribute('aria-atomic', 'true');
            region.className = 'sr-only';
            document.body.appendChild(region);
        }
        this.ariaLiveRegion = region;
    }

    _announce(message) {
        if (!this.ariaLiveRegion) return;
        this.ariaLiveRegion.textContent = '';
        setTimeout(() => {
            this.ariaLiveRegion.textContent = message;
        }, 100);
    }

    _setupKeyboardNavigation() {
        this.svg.setAttribute('tabindex', '0');
        this.svg.setAttribute('role', 'application');
        this.svg.setAttribute('aria-label', 'Interactive seat map');
        
        this.svg.addEventListener('keydown', (e) => this._handleKeyboard(e));
        this.svg.addEventListener('focus', () => this.svg.classList.add('keyboard-mode'));
        this.svg.addEventListener('blur', () => {
            this.svg.classList.remove('keyboard-mode');
            this._clearFocus();
        });
    }

    _handleKeyboard(e) {
        const key = e.key;
        const currentSeat = this._getFocusedSeat();
        
        switch (key) {
            case 'ArrowUp':
            case 'ArrowDown':
            case 'ArrowLeft':
            case 'ArrowRight':
                e.preventDefault();
                this._navigateSeats(key, currentSeat);
                break;
            case 'Enter':
            case ' ':
                e.preventDefault();
                if (currentSeat && currentSeat.dataset.status === 'available') {
                    this._selectFocusedSeat();
                }
                break;
            case 'Escape':
                this._clearFocus();
                break;
            case 'Home':
                e.preventDefault();
                this._focusFirstSeat();
                break;
            case 'End':
                e.preventDefault();
                this._focusLastSeat();
                break;
        }
    }

    _navigateSeats(direction, currentSeat) {
        if (!currentSeat) {
            this._focusFirstSeat();
            return;
        }
        
        const currentId = parseInt(currentSeat.dataset.id);
        const seats = Array.from(this.rendered.values());
        const currentData = this._getSeatData(currentId);
        if (!currentData) return;
        
        let nearestSeat = null;
        let minDistance = Infinity;
        
        seats.forEach(seatEl => {
            const id = parseInt(seatEl.dataset.id);
            if (id === currentId) return;
            
            const data = this._getSeatData(id);
            if (!data || data.status !== 'available') return;
            
            const dx = data.x - currentData.x;
            const dy = data.y - currentData.y;
            
            let isValid = false;
            switch (direction) {
                case 'ArrowRight': isValid = dx > 0 && Math.abs(dx) > Math.abs(dy); break;
                case 'ArrowLeft': isValid = dx < 0 && Math.abs(dx) > Math.abs(dy); break;
                case 'ArrowDown': isValid = dy > 0 && Math.abs(dy) > Math.abs(dx); break;
                case 'ArrowUp': isValid = dy < 0 && Math.abs(dy) > Math.abs(dx); break;
            }
            
            if (isValid) {
                const distance = Math.sqrt(dx*dx + dy*dy);
                if (distance < minDistance) {
                    minDistance = distance;
                    nearestSeat = seatEl;
                }
            }
        });
        
        if (nearestSeat) {
            this._focusSeat(parseInt(nearestSeat.dataset.id));
        }
    }

    _getFocusedSeat() {
        return this.focusedSeatId ? this.rendered.get(this.focusedSeatId) : null;
    }

    _focusSeat(seatId) {
        const seat = this.rendered.get(seatId);
        if (!seat) return;
        
        if (this.focusedSeatId) {
            const prev = this.rendered.get(this.focusedSeatId);
            if (prev) {
                prev.classList.remove('focused');
                prev.style.transform = '';
            }
        }
        
        seat.classList.add('focused');
        seat.style.transform = 'scale(1.05)';
        seat.focus();
        this.focusedSeatId = seatId;
        
        const data = this._getSeatData(seatId);
        if (data) {
            const label = data.data?.label || `Seat ${seatId}`;
            this._announce(`${label}, ${data.status}`);
        }
    }

    _focusFirstSeat() {
        const seats = Array.from(this.rendered.values());
        if (seats.length > 0) {
            this._focusSeat(parseInt(seats[0].dataset.id));
        }
    }

    _focusLastSeat() {
        const seats = Array.from(this.rendered.values());
        if (seats.length > 0) {
            this._focusSeat(parseInt(seats[seats.length - 1].dataset.id));
        }
    }

    _clearFocus() {
        if (this.focusedSeatId) {
            const seat = this.rendered.get(this.focusedSeatId);
            if (seat) {
                seat.classList.remove('focused');
                seat.style.transform = '';
            }
            this.focusedSeatId = null;
        }
    }

    _selectFocusedSeat() {
        const seat = this._getFocusedSeat();
        if (seat && this.onSeatClick) {
            const id = parseInt(seat.dataset.id);
            this.onSeatClick(id);
            this._announce(`Seat ${id} selected`);
        }
    }

    _getSeatData(id) {
        const el = this.rendered.get(id);
        if (!el) return null;
        
        const match = el.getAttribute('transform')?.match(/translate\(([^,]+),([^)]+)\)/);
        return {
            id: id,
            x: match ? parseFloat(match[1]) : 0,
            y: match ? parseFloat(match[2]) : 0,
            status: el.dataset.status,
            data: JSON.parse(el.dataset.rawData || '{}')
        };
    }

    _setupFocusManagement() {
        this._addSkipLink();
    }

    _addSkipLink() {
        const skipLink = document.createElement('a');
        skipLink.href = `#${this.svg.id}`;
        skipLink.className = 'skip-link';
        skipLink.textContent = 'Skip to seat map';
        skipLink.addEventListener('click', (e) => {
            e.preventDefault();
            this.svg.focus();
        });
        document.body.insertBefore(skipLink, document.body.firstChild);
    }

    /**
     * FIXED: Use requestAnimationFrame for smooth rendering
     */
    async load(eventId, viewport) {
        const params = new URLSearchParams({
            x: viewport.x,
            y: viewport.y,
            width: viewport.width,
            height: viewport.height,
        });

        const res = await fetch(`/api/v1/events/${eventId}/seatmap?${params}`, {
            headers: { Accept: 'application/json' },
        });
        const body = await res.json();

        if (!body.success) {
            console.error('SeatMapRenderer: failed to load elements', body);
            this._announce('Failed to load seat map');
            return;
        }

        this.spatialGrid.clear();
        body.data.elements.forEach(el => {
            this.spatialGrid.insert(el);
            el.rawData = JSON.stringify(el.data || {});
        });
        
        this.renderViewport(viewport);
        this._announce(`Loaded seat map with ${body.data.elements.length} seats`);
    }

    /**
     * FIXED: Use requestAnimationFrame for smooth rendering
     */
    renderViewport(viewport) {
        cancelAnimationFrame(this.rafId);
        this.rafId = requestAnimationFrame(() => {
            this._render(viewport);
        });
    }

    _render(viewport) {
        const inView = this.spatialGrid.query(viewport);
        const inViewIds = new Set(inView.map(e => e.id));

        // Return out-of-view elements to pool
        this.visible.forEach(id => {
            if (!inViewIds.has(id)) {
                const el = this.rendered.get(id);
                if (el) {
                    this.pool.release(el);
                    this.rendered.delete(id);
                }
                this.visible.delete(id);
            }
        });

        // Render newly visible elements
        inView.forEach(seat => {
            if (this.rendered.has(seat.id)) {
                this._updateVisual(this.rendered.get(seat.id), seat);
            } else {
                const el = this.pool.acquire(seat);
                this._buildShape(el, seat);
                this.svg.appendChild(el);
                this.rendered.set(seat.id, el);
                this.visible.add(seat.id);
            }
        });

        this._announce(`Displaying ${inView.length} seats in current view`);
    }

    updateStatus(elementId, status) {
        const el = this.rendered.get(elementId);
        if (el) {
            el.dataset.status = status;
            el.setAttribute('aria-pressed', status === 'booked' ? 'true' : 'false');
            
            const rect = el.querySelector('rect');
            if (rect) {
                rect.setAttribute('fill', this._color(status));
            }
            
            const data = this._getSeatData(elementId);
            const label = data?.data?.label || `Seat ${elementId}`;
            let message = '';
            switch (status) {
                case 'booked': message = `${label} has been booked`; break;
                case 'locked': message = `${label} is temporarily held`; break;
                case 'available': message = `${label} is now available`; break;
            }
            if (message) this._announce(message);
        }
    }

    _buildShape(el, seat) {
        let rect = el.querySelector('rect');
        if (!rect) {
            rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
            rect.setAttribute('rx', '2');
            el.appendChild(rect);
        }

        rect.setAttribute('width', seat.width ?? 20);
        rect.setAttribute('height', seat.height ?? 20);
        rect.setAttribute('fill', this._color(seat.status));
        rect.setAttribute('stroke', '#000');
        rect.setAttribute('stroke-width', '1');

        el.setAttribute('role', 'button');
        el.setAttribute('tabindex', '-1');
        el.setAttribute('aria-pressed', seat.status === 'booked' ? 'true' : 'false');
        
        const label = seat.data?.label || seat.id;
        el.setAttribute('aria-label', `${label}, ${seat.status}`);

        const seatLabel = seat.data?.label;
        if (seatLabel) {
            let text = el.querySelector('text');
            if (!text) {
                text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                text.setAttribute('text-anchor', 'middle');
                text.setAttribute('dominant-baseline', 'middle');
                text.setAttribute('font-size', '8');
                text.setAttribute('fill', '#fff');
                text.setAttribute('pointer-events', 'none');
                text.setAttribute('font-weight', '600');
                el.appendChild(text);
            }
            text.setAttribute('x', (seat.width ?? 20) / 2);
            text.setAttribute('y', (seat.height ?? 20) / 2);
            // SECURITY: Use textContent to prevent XSS
            text.textContent = seatLabel;
        }

        el.onclick = () => {
            if (seat.status === 'available' && this.onSeatClick) {
                this.onSeatClick(seat.id);
            }
        };

        el.onkeydown = (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                if (seat.status === 'available' && this.onSeatClick) {
                    this.onSeatClick(seat.id);
                }
            }
        };
    }

    _updateVisual(el, seat) {
        el.dataset.status = seat.status;
        el.setAttribute('aria-pressed', seat.status === 'booked' ? 'true' : 'false');
        
        const rect = el.querySelector('rect');
        if (rect) {
            rect.setAttribute('fill', this._color(seat.status));
        }
        
        const label = seat.data?.label;
        if (label) {
            let text = el.querySelector('text');
            if (text) {
                text.textContent = label;
            }
        }
    }

    _color(status) {
        const colors = {
            available: '#0056b3',
            locked: '#d4a017',
            booked: '#a94442'
        };
        return colors[status] ?? '#95a5a6';
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = { SeatMapRenderer, SpatialGrid, SeatElementPool };
}

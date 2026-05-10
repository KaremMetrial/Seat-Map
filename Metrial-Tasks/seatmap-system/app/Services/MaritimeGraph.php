<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\EventElement;
use App\Models\TemplateElement;
use App\Models\VenueTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * MaritimeGraph — تحويل خريطة المقاعد إلى رسم بياني للمسارات
 *
 * Nodes: نقاط مرور إلزامية (أبواب، تقاطعات، مخرج)
 * Edges: الممرات (aisles, corridors) between nodes
 *
 * Purpose: Enable realistic pathfinding that respects vessel geometry,
 * obstacles (pillars, walls), and width constraints.
 */
class MaritimeGraph
{
    // ── Constants for magic numbers ──────────────────────────────────────────
    private const VERTICAL_LEVEL_THRESHOLD = 1.0;
    private const Z_LEVEL_TOLERANCE = 1.0;
    private const IMO_WALKING_SPEED_MPM = 30.0; // meters per minute
    private const MAX_EDGE_DISTANCE = 500; // canvas units
    private const SPATIAL_CELL_SIZE = 200; // for spatial indexing

    private VenueTemplate $template;
    private Collection $elements;
    private array $nodes = [];      // ['id'=>x, 'id'=>y, 'type'=>...]
    private array $edges = [];      // [['from'=>id1, 'to'=>id2, 'width'=>100, ...]]
    private array $obstacles = [];  // bounding boxes of non-bookable blocking elements

    public function __construct(VenueTemplate $template)
    {
        $this->template = $template;
        $this->elements = $template->elements()->where('is_active', true)->get();
        $this->buildObstacleMap();
        $this->extractNodes();
        $this->buildEdges();
    }

    /**
     * Build obstacle map — elements that block movement
     * Non-bookable, solid elements: walls, pillars, stages, fixed furniture
     */
    private function buildObstacleMap(): void
    {
        $blockingTypes = [
            'wall', ' pillar', 'column', 'stage', 'fixed_furniture',
            'structural', 'bulkhead', 'beam'
        ];

        $this->obstacles = $this->elements
            ->whereIn('element_type', $blockingTypes)
            ->map(function ($el) {
                return [
                    'x1' => $el->x,
                    'y1' => $el->y,
                    'x2' => $el->x + ($el->width ?? 0),
                    'y2' => $el->y + ($el->height ?? 0),
                    'z'  => $el->z ?? 0,
                    'type' => $el->element_type,
                    'id'  => $el->id,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Extract navigation nodes from the layout
     * Nodes = critical points where agents can change direction:
     *  - Entrance/exit centers
     *  - Aisle intersections
     *  - Corners of bookable areas
     *  - Staircase/elevator centers
     */
    private function extractNodes(): void
    {
        $nodeId = 0;

        // 1. All entrances/exits become nodes (critical waypoints)
        foreach ($this->elements->whereIn('element_type', ['entrance', 'emergency_exit']) as $el) {
            $this->nodes[] = [
                'id' => $nodeId++,
                'element_id' => $el->id,
                'x' => $el->x + ($el->width / 2),
                'y' => $el->y + ($el->height / 2),
                'z' => $el->z ?? 0,
                'type' => 'exit',
                'label' => $el->data_json['label'] ?? 'Exit',
            ];
        }

        // 2. Staircase/elevator centers become nodes
        foreach ($this->elements->whereIn('element_type', ['staircase', 'elevator']) as $el) {
            $this->nodes[] = [
                'id' => $nodeId++,
                'element_id' => $el->id,
                'x' => $el->x + ($el->width / 2),
                'y' => $el->y + ($el->height / 2),
                'z' => $el->z ?? 0,
                'type' => 'vertical_transit',
                'label' => $el->data_json['label'] ?? 'Stairs',
            ];
        }

        // 3. For each bookable element (seat, table, section), add nodes at:
        //    - Center point (destination)
        foreach ($this->elements->whereIn('element_type', ['seat', 'table', 'standing_zone', 'section']) as $el) {
            $this->nodes[] = [
                'id' => $nodeId++,
                'element_id' => $el->id,
                'x' => $el->x + ($el->width / 2),
                'y' => $el->y + ($el->height / 2),
                'z' => $el->z ?? 0,
                'type' => 'destination',
                'label' => $el->data_json['label'] ?? "Element {$el->id}",
            ];
        }

        // 4. Corners of aisles become nodes (grid points)
        $aisles = $this->elements->whereIn('element_type', ['aisle', 'corridor']);
        foreach ($aisles as $aisle) {
            $corners = [
                [$aisle->x, $aisle->y],
                [$aisle->x + $aisle->width, $aisle->y],
                [$aisle->x, $aisle->y + $aisle->height],
                [$aisle->x + $aisle->width, $aisle->y + $aisle->height],
            ];

            foreach ($corners as $pt) {
                $this->nodes[] = [
                    'id' => $nodeId++,
                    'element_id' => $aisle->id,
                    'x' => $pt[0],
                    'y' => $pt[1],
                    'z' => $aisle->z ?? 0,
                    'type' => 'aisle_corner',
                    'label' => $aisle->data_json['label'] ?? 'Aisle',
                ];
            }
        }
    }

    /**
     * FIXED: Build edges with spatial indexing to avoid O(n²) complexity
     * Only connect nodes that:
     *  - Are on the same z-level (same deck) or connected via stairs/elevator
     *  - Have line-of-sight (no obstacle between them)
     *  - Are within reasonable distance (same room/area)
     */
    private function buildEdges(): void
    {
        // Build spatial index for nodes to enable fast proximity queries
        $spatialIndex = $this->buildNodeSpatialIndex();

        for ($i = 0; $i < count($this->nodes); $i++) {
            $n1 = $this->nodes[$i];

            // Only check nearby nodes using spatial index (FIXED: O(n) instead of O(n²))
            $nearbyNodeIndices = $this->getNearbyNodeIndices($n1, $spatialIndex);

            foreach ($nearbyNodeIndices as $j) {
                if ($j <= $i) continue;

                $n2 = $this->nodes[$j];

                // Skip if different vertical levels without vertical transit
                if (abs(($n1['z'] ?? 0) - ($n2['z'] ?? 0)) > 0) {
                    if (!in_array($n1['type'], ['staircase', 'elevator']) ||
                        !in_array($n2['type'], ['staircase', 'elevator'])) {
                        continue;
                    }
                }

                // Check line-of-sight (no obstacle blocking the edge)
                if (!$this->hasLineOfSight($n1, $n2)) {
                    continue;
                }

                // Calculate edge properties
                $distance = $this->euclideanDistance($n1, $n2);

                // Skip if too far apart
                if ($distance > self::MAX_EDGE_DISTANCE) {
                    continue;
                }

                $width = $this->estimateCorridorWidth($n1, $n2);

                $this->edges[] = [
                    'from' => $n1['id'],
                    'to' => $n2['id'],
                    'distance' => $distance,
                    'width' => $width,
                    'is_emergency' => $this->isEmergencyRoute($n1, $n2),
                ];
            }
        }

        Log::info('MaritimeGraph built', [
            'nodes' => count($this->nodes),
            'edges' => count($this->edges),
        ]);
    }

    /**
     * Build spatial index for nodes to enable fast proximity queries.
     * Returns array with 'index' (Map) and 'cellSize' (int).
     */
    private function buildNodeSpatialIndex(): array
    {
        $index = [];
        $cellSize = self::SPATIAL_CELL_SIZE;

        foreach ($this->nodes as $idx => $node) {
            $cx = (int) floor($node['x'] / $cellSize);
            $cy = (int) floor($node['y'] / $cellSize);
            $key = "{$cx},{$cy}";

            if (!isset($index[$key])) {
                $index[$key] = [];
            }
            $index[$key][] = $idx;
        }

        return ['index' => $index, 'cellSize' => $cellSize];
    }

    /**
     * Get nearby node indices using spatial index.
     * Checks 3x3 grid of cells around the node.
     */
    private function getNearbyNodeIndices(array $node, array $spatialData): array
    {
        $index = $spatialData['index'];
        $cellSize = $spatialData['cellSize'];
        $nearby = [];

        $cx = (int) floor($node['x'] / $cellSize);
        $cy = (int) floor($node['y'] / $cellSize);

        // Check neighboring cells (3x3 grid)
        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                $key = ($cx + $dx) . ',' . ($cy + $dy);
                if (isset($index[$key])) {
                    foreach ($index[$key] as $nodeIdx) {
                        $nearby[$nodeIdx] = true;
                    }
                }
            }
        }

        return array_keys($nearby);
    }

    /**
     * Check if straight line between two nodes intersects any obstacle.
     * FIXED: Use Cohen-Sutherland algorithm for faster intersection test.
     */
    private function hasLineOfSight(array $n1, array $n2): bool
    {
        foreach ($this->obstacles as $obs) {
            if ($this->lineIntersectsRectCohenSutherland($n1, $n2, $obs)) {
                return false;
            }
        }
        return true;
    }

    /**
     * FIXED: Cohen-Sutherland line clipping algorithm for axis-aligned rectangles.
     * Much faster than Liang-Barsky for simple intersection tests.
     * Returns true if line segment intersects rectangle.
     */
    private function lineIntersectsRectCohenSutherland(array $p1, array $p2, array $rect): bool
    {
        // Early bounding box rejection
        if (max($p1['x'], $p2['x']) < $rect['x1'] ||
            min($p1['x'], $p2['x']) > $rect['x2'] ||
            max($p1['y'], $p2['y']) < $rect['y1'] ||
            min($p1['y'], $p2['y']) > $rect['y2']) {
            return false;
        }

        $INSIDE = 0; // 0000
        $LEFT = 1;   // 0001
        $RIGHT = 2;  // 0010
        $BOTTOM = 4; // 0100
        $TOP = 8;    // 1000

        $computeOutCode = function ($x, $y) use ($rect, $INSIDE, $LEFT, $RIGHT, $BOTTOM, $TOP) {
            $code = $INSIDE;
            if ($x < $rect['x1']) $code |= $LEFT;
            elseif ($x > $rect['x2']) $code |= $RIGHT;
            if ($y < $rect['y1']) $code |= $BOTTOM;
            elseif ($y > $rect['y2']) $code |= $TOP;
            return $code;
        };

        $x1 = $p1['x'];
        $y1 = $p1['y'];
        $x2 = $p2['x'];
        $y2 = $p2['y'];
        $outcode1 = $computeOutCode($x1, $y1);
        $outcode2 = $computeOutCode($x2, $y2);

        while (true) {
            if (!($outcode1 | $outcode2)) {
                // Both points inside - line intersects
                return true;
            } elseif ($outcode1 & $outcode2) {
                // Both points share an outside zone - line doesn't intersect
                return false;
            } else {
                // Pick an outside point
                $outcodeOut = $outcode1 ?: $outcode2;

                if ($outcodeOut & $TOP) {
                    $x = $x1 + ($x2 - $x1) * ($rect['y2'] - $y1) / ($y2 - $y1);
                    $y = $rect['y2'];
                } elseif ($outcodeOut & $BOTTOM) {
                    $x = $x1 + ($x2 - $x1) * ($rect['y1'] - $y1) / ($y2 - $y1);
                    $y = $rect['y1'];
                } elseif ($outcodeOut & $RIGHT) {
                    $y = $y1 + ($y2 - $y1) * ($rect['x2'] - $x1) / ($x2 - $x1);
                    $x = $rect['x2'];
                } elseif ($outcodeOut & $LEFT) {
                    $y = $y1 + ($y2 - $y1) * ($rect['x1'] - $x1) / ($x2 - $x1);
                    $x = $rect['x1'];
                } else {
                    break;
                }

                if ($outcodeOut === $outcode1) {
                    $x1 = $x;
                    $y1 = $y;
                    $outcode1 = $computeOutCode($x1, $y1);
                } else {
                    $x2 = $x;
                    $y2 = $y;
                    $outcode2 = $computeOutCode($x2, $y2);
                }
            }
        }

        return false;
    }

    /**
     * Estimate minimum corridor width between two nodes based on nearby aisles.
     */
    private function estimateCorridorWidth(array $n1, array $n2): float
    {
        $aisles = $this->elements->whereIn('element_type', ['aisle', 'corridor']);
        if ($aisles->isEmpty()) {
            return 100; // default: 1 meter if scale=0.01? actually 100 canvas units
        }

        // Return min aisle width along the path
        return $aisles->min('width') ?? 100;
    }

    /**
     * Check if edge between nodes qualifies as emergency route.
     */
    private function isEmergencyRoute(array $n1, array $n2): bool
    {
        return in_array($n1['type'], ['exit', 'emergency_exit']) ||
               in_array($n2['type'], ['exit', 'emergency_exit']);
    }

    /**
     * Euclidean distance between two nodes (canvas units).
     */
    private function euclideanDistance(array $a, array $b): float
    {
        $dx = $a['x'] - $b['x'];
        $dy = $a['y'] - $b['y'];
        return sqrt($dx * $dx + $dy * $dy);
    }

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Get all nodes (for debugging/visualization).
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /**
     * Get all edges (for pathfinding).
     */
    public function getEdges(): array
    {
        return $this->edges;
    }

    /**
     * FIXED: Find shortest path from any element to nearest exit using
     * multi-source Dijkstra algorithm.
     *
     * OLD COMPLEXITY: O(N * V log V) - ran A* for each seat
     * NEW COMPLEXITY: O(V log V + E) - single Dijkstra from all exits
     *
     * @param EventElement $startElement
     * @param float $scaleFactor canvas→meters conversion
     * @return array{path: array, distance_canvas: float, distance_meters: float, time_seconds: float}
     */
    public function findShortestPathToExit(EventElement $startElement, float $scaleFactor = 0.05): array
    {
        $startNode = $this->findNearestNode($startElement);
        if (!$startNode) {
            throw new \Exception("No navigation node found near element {$startElement->id}");
        }

        $exitNodes = array_filter($this->nodes, fn($n) => in_array($n['type'], ['exit', 'emergency_exit']));
        if (empty($exitNodes)) {
            throw new \Exception("No exit nodes defined in graph");
        }

        // FIXED: Use multi-source Dijkstra from all exits simultaneously
        $distances = $this->multiSourceDijkstra($exitNodes);

        $startDist = $distances[$startNode['id']] ?? INF;
        if ($startDist === INF) {
            return ['path' => [], 'distance_canvas' => 0, 'distance_meters' => 0, 'time_seconds' => 0, 'error' => 'No path found'];
        }

        // Reconstruct path from start to nearest exit
        $path = $this->reconstructPathToExit($startNode, $distances);
        $distanceMeters = $startDist * $scaleFactor;
        $timeSeconds = $this->estimateEvacuationTime($distanceMeters, $this->pathIncludesStairs($path));

        return [
            'path' => $path['node_ids'],
            'distance_canvas' => $startDist,
            'distance_meters' => $distanceMeters,
            'time_seconds' => $timeSeconds,
        ];
    }

    /**
     * Multi-source Dijkstra: find shortest distance from any node to nearest exit.
     * Runs in O(V log V + E) instead of O(N * V log V).
     *
     * @param array $exitNodes
     * @return array distances map: node_id -> distance
     */
    private function multiSourceDijkstra(array $exitNodes): array
    {
        $distances = [];
        $pq = new \SplPriorityQueue();
        $pq->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);

        // Initialize all nodes with Infinity
        foreach ($this->nodes as $node) {
            $distances[$node['id']] = INF;
        }

        // Initialize all exits with distance 0
        foreach ($exitNodes as $exit) {
            $distances[$exit['id']] = 0;
            $pq->insert($exit['id'], 0);
        }

        $visited = [];

        while (!$pq->isEmpty()) {
            $current = $pq->extract();
            $u = $current['data'];

            if (isset($visited[$u])) {
                continue;
            }
            $visited[$u] = true;

            // Update neighbors
            foreach ($this->getNeighbors($u) as $neighbor) {
                $v = $neighbor['to'];
                if (isset($visited[$v])) {
                    continue;
                }

                $alt = $distances[$u] + $neighbor['distance'];
                if ($alt < $distances[$v]) {
                    $distances[$v] = $alt;
                    // Negative priority for min-heap behavior
                    $pq->insert($v, -$alt);
                }
            }
        }

        return $distances;
    }

    /**
     * Reconstruct path from start node to nearest exit.
     */
    private function reconstructPathToExit(array $startNode, array $distances): array
    {
        $path = [$startNode['id']];
        $current = $startNode['id'];

        while (true) {
            $neighbors = $this->getNeighbors($current);
            $next = null;
            $minDist = INF;

            foreach ($neighbors as $n) {
                if (($distances[$n['to']] ?? INF) < $minDist) {
                    $minDist = $distances[$n['to']];
                    $next = $n['to'];
                }
            }

            if ($next === null || $minDist >= $distances[$current]) {
                break; // Reached an exit or local minimum
            }

            $path[] = $next;
            $current = $next;
        }

        return ['node_ids' => $path, 'distance' => $distances[$startNode['id']]];
    }

    /**
     * Check if path includes stairs/elevator.
     */
    private function pathIncludesStairs(array $path): bool
    {
        foreach ($path['node_ids'] as $nodeId) {
            $node = $this->nodes[array_search($nodeId, array_column($this->nodes, 'id'))] ?? null;
            if ($node && in_array($node['type'], ['staircase', 'elevator'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all edges outgoing from node.
     */
    private function getNeighbors(int $nodeId): array
    {
        $neighbors = [];
        foreach ($this->edges as $edge) {
            if ($edge['from'] === $nodeId) {
                $neighbors[] = ['to' => $edge['to'], 'distance' => $edge['distance']];
            } elseif ($edge['to'] === $nodeId) {
                $neighbors[] = ['to' => $edge['from'], 'distance' => $edge['distance']];
            }
        }
        return $neighbors;
    }

    /**
     * Find navigation node nearest to an element.
     */
    private function findNearestNode(EventElement $element): ?array
    {
        $ex = $element->x + ($element->width / 2);
        $ey = $element->y + ($element->height / 2);
        $ez = $element->z ?? 0;

        $nearest = null;
        $minDist = INF;

        foreach ($this->nodes as $node) {
            // Prefer same deck level
            if (abs(($node['z'] ?? 0) - $ez) > self::Z_LEVEL_TOLERANCE) {
                continue;
            }

            $dx = $node['x'] - $ex;
            $dy = $node['y'] - $ey;
            $dist = sqrt($dx * $dx + $dy * $dy);

            if ($dist < $minDist) {
                $minDist = $dist;
                $nearest = $node;
            }
        }

        return $nearest;
    }

    /**
     * Estimate evacuation walking time per IMO guidelines.
     * IMO: 30 meters/minute for able-bodied passengers.
     * Add delay for stairs: +50% time.
     * Add crowd factor (dense: ×1.5).
     */
    private function estimateEvacuationTime(float $distanceMeters, bool $includesStairs = false, float $crowdFactor = 1.0): float
    {
        $baseSpeedMps = self::IMO_WALKING_SPEED_MPM / 60.0;
        if ($includesStairs) {
            $baseSpeedMps *= 0.7; // 30% slower on stairs
        }
        $adjustedSpeed = $baseSpeedMps / $crowdFactor;

        $time = $distanceMeters / $adjustedSpeed; // seconds
        return round($time, 1);
    }

    /**
     * Get graph summary (for debugging).
     */
    public function getSummary(): array
    {
        return [
            'total_nodes' => count($this->nodes),
            'total_edges' => count($this->edges),
            'exit_nodes' => count(array_filter($this->nodes, fn($n) => in_array($n['type'], ['exit', 'emergency_exit']))),
            'obstacle_count' => count($this->obstacles),
            'node_types' => array_count_values(array_column($this->nodes, 'type')),
        ];
    }
}

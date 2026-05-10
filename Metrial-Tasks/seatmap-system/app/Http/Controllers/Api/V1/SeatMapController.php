<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventElement;
use App\Models\VenueTemplate;
use App\Services\BookingService;
use App\Services\EgressValidator;
use App\Services\MaritimeGraph;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SeatMapController extends Controller
{
    public function __construct(private BookingService $bookingService) {}

    /**
     * GET /api/v1/events/{event}/seatmap
     *
     * Returns the full seat map for an event.
     * Accepts optional viewport parameters (x, y, width, height) to limit
     * the elements returned — useful for large stadiums where the client
     * only renders the visible area.
     *
     * Status is resolved via EventElement::hydrateBookingStatus() — exactly
     * 2 bulk queries regardless of how many elements are returned.
     */
    public function show(Event $event, Request $request): JsonResponse
    {
        // Validate viewport parameters if provided
        if ($request->filled(['x', 'y', 'width', 'height'])) {
            $validator = Validator::make($request->all(), [
                'x' => 'required|numeric|min:0',
                'y' => 'required|numeric|min:0',
                'width' => 'required|numeric|min:1|max:10000',
                'height' => 'required|numeric|min:1|max:10000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid viewport parameters',
                    'errors' => $validator->errors(),
                ], 422);
            }
        }

        // Eager-load template so canvas dimensions cost 0 extra queries
        $event->loadMissing('template.zones');

        $query = $event->eventElements()->orderBy('z_index');

        // Optional viewport culling — only apply when all four params are present
        if ($request->filled(['x', 'y', 'width', 'height'])) {
            $vx = (float) $request->input('x');
            $vy = (float) $request->input('y');
            $vw = (float) $request->input('width');
            $vh = (float) $request->input('height');

            $query
                ->where('x', '>=', $vx)
                ->where('x', '<=', $vx + $vw)
                ->where('y', '>=', $vy)
                ->where('y', '<=', $vy + $vh);
        }

        $elements = $query->get();

        // Resolve booking_status for the whole collection in 2 queries (not 2N)
        EventElement::hydrateBookingStatus($elements);

        return response()->json([
            'success' => true,
            'data'    => [
                'event' => [
                    'id'       => $event->id,
                    'title'    => $event->title,
                    'start_at' => $event->start_at,
                    'canvas'   => [
                        'width'            => $event->template->canvas_width,
                        'height'           => $event->template->canvas_height,
                        'background_image' => $event->template->background_image,
                        'background_color' => $event->template->background_color,
                    ],
                ],
                'elements' => $elements->map(fn (EventElement $el) => [
                    'id'          => $el->id,
                    'type'        => $el->element_type,
                    'x'           => (float) $el->x,
                    'y'           => (float) $el->y,
                    'width'       => (float) $el->width,
                    'height'      => (float) $el->height,
                    'rotation'    => (float) $el->rotation,
                    'z_index'     => $el->z_index,
                    'parent_id'   => $el->parent_id,
                    'data'        => $el->data_json,
                    'style'       => $el->style_json,
                    'is_bookable' => $el->is_bookable,
                    'zone_id'     => $el->zone_id,
                    'status'      => $el->booking_status,
                ]),
                'zones' => $event->template->zones,
            ],
        ]);
    }

    /**
     * GET /api/v1/events/{event}/available
     *
     * Returns only bookable, available elements.
     * Uses subquery exclusions — zero per-element queries.
     */
    public function available(Event $event): JsonResponse
    {
        $event->loadMissing('template');

        // Use the scope so status is resolved in the SELECT — 0 extra queries
        $elements = $event->eventElements()
            ->withBookingStatus()
            ->where('is_bookable', true)
            ->orderBy('z_index')
            ->get()
            ->filter(fn (EventElement $el) => $el->booking_status === 'available')
            ->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'total_available' => $elements->count(),
                'elements'        => $elements->map(fn (EventElement $el) => [
                    'id'     => $el->id,
                    'type'   => $el->element_type,
                    'x'      => (float) $el->x,
                    'y'      => (float) $el->y,
                    'width'  => (float) $el->width,
                    'height' => (float) $el->height,
                    'data'   => $el->data_json,
                    'status' => 'available',
                ]),
            ],
        ]);
    }

    /**
     * GET /api/v1/events/{event}/egress-analysis
     *
     * Detailed evacuation analysis using A* pathfinding graph.
     * Returns per-seat path distances, times, risk levels, and bottleneck stats.
     * Requires event to be snapshotted (published).
     *
     * Response includes:
     * - statistics (max/avg distance & time)
     * - heatmap array for frontend visualization
     * - graph summary (nodes, edges)
     * - critical seats list (over 60m)
     */
    public function egressAnalysis(Event $event, Request $request): JsonResponse
    {
        if (!$event->snapshotted_at) {
            return response()->json([
                'success' => false,
                'message' => 'Event must be published (snapshotted) before egress analysis',
            ], 422);
        }

        $template = $event->template;

        // FIXED: Validate scale factor is present and valid
        if (!$template || !$template->scale_factor || $template->scale_factor <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Template must have valid positive scale_factor for metric conversion',
            ], 422);
        }

        // Additional validation: ensure scale factor is reasonable
        if ($template->scale_factor > 10) {
            return response()->json([
                'success' => false,
                'message' => 'Template scale_factor appears too large (max 10)',
            ], 422);
        }

        $validator = new EgressValidator($template->scale_factor);
        $report = $validator->validateEventEvacuationRoutes($event);

        return response()->json([
            'success' => true,
            'data' => [
                'event_id' => $event->id,
                'template_id' => $template->id,
                'scale_factor' => $template->scale_factor,
                'units' => $template->units ?? 'meters',
                'valid' => $report['valid'],
                'violations' => $report['violations'],
                'statistics' => $report['statistics'],
                'heatmap' => $validator->getEvacuationHeatMap($event),
                'graph_summary' => $this->getGraphSummary($event, $template),
            ],
        ]);
    }

    /**
     * GET /api/v1/events/{event}/marine-compliance
     *
     * Maritime safety compliance check per SOLAS/IMO regulations.
     * Requires template to have scale_factor set for accurate metric conversion.
     *
     * Returns cabin area violations, aisle width issues, exit clearance,
     * and full evacuation route analysis.
     */
    public function marineCompliance(Event $event, Request $request): JsonResponse
    {
        $template = $event->template;

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Event has no associated template',
            ], 404);
        }

        // FIXED: Validate scale factor
        if (!$template->scale_factor || $template->scale_factor <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Template must have valid positive scale_factor for compliance check',
            ], 422);
        }

        // 1. Template-level compliance (cabin sizes, aisle widths, exit buffers)
        $templateReport = $template->marineComplianceCheck();

        // 2. Evacuation route analysis (if event has published elements)
        $egressResult = ['valid' => true, 'statistics' => null];
        if ($event->snapshotted_at) {
            $validator = new EgressValidator($template->scale_factor);
            $egressResult = $validator->validateEventEvacuationRoutes($event);
        }

        // Combine verdicts
        $overallValid = $templateReport['valid'] && $egressResult['valid'];

        return response()->json([
            'success' => true,
            'data' => [
                'event_id' => $event->id,
                'template_id' => $template->id,
                'scale_factor' => $template->scale_factor,
                'units' => $template->units ?? 'meters',
                'compliant' => $overallValid,
                'template_compliance' => $templateReport,
                'egress_analysis' => $egressResult,
                'recommendations' => $this->generateRecommendations($templateReport, $egressResult),
            ],
        ]);
    }

    /**
     * Get summary of navigation graph for debugging/UI
     */
    private function getGraphSummary(Event $event, VenueTemplate $template): array
    {
        try {
            $graph = new MaritimeGraph($template);
            return $graph->getSummary();
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Generate actionable recommendations based on violations
     */
    private function generateRecommendations(array $templateReport, array $egressResult): array
    {
        $recommendations = [];

        if (!$templateReport['valid']) {
            foreach ($templateReport['violations'] as $v) {
                if (str_contains($v, 'area')) {
                    $recommendations[] = "Increase cabin dimensions or reduce cabin count to meet IMO minimum area requirements.";
                }
                if (str_contains($v, 'width')) {
                    $recommendations[] = "Widen aisles/corridors to meet SOLAS emergency egress width minimums.";
                }
                if (str_contains($v, 'clearance')) {
                    $recommendations[] = "Remove obstructions within 1m radius of emergency exits.";
                }
            }
        }

        if (isset($egressResult['statistics']['over_limit_count']) &&
            $egressResult['statistics']['over_limit_count'] > 0) {
            $recommendations[] = "Add additional emergency exits or re-arrange seating to reduce maximum evacuation distance below 60m.";
        }

        if (empty($recommendations)) {
            $recommendations[] = "Layout complies with SOLAS/IMO requirements. Continue periodic reviews.";
        }

        return $recommendations;
    }
}

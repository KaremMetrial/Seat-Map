<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class VenueTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'venue_id',
        'name',
        'slug',
        'description',
        'canvas_width',
        'canvas_height',
        'background_image',
        'background_color',
        'grid_size',
        'show_grid',
        'settings',
        'is_default',
        'is_active',
        // Maritime Geospatial Extension
        'scale_factor',
        'units',
        'origin_offset_x',
        'origin_offset_y',
        'rotation_degrees',
    ];

    protected $casts = [
        'settings'          => 'array',
        'is_default'        => 'boolean',
        'is_active'         => 'boolean',
        // Maritime Geospatial Extension
        'scale_factor'      => 'decimal:4',
        'units'             => 'string',
        'origin_offset_x'   => 'decimal:2',
        'origin_offset_y'   => 'decimal:2',
        'rotation_degrees'  => 'decimal:2',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $template): void {
            if (empty($template->slug)) {
                $template->slug = Str::slug($template->name);
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function elements(): HasMany
    {
        return $this->hasMany(TemplateElement::class, 'template_id');
    }

    public function rootElements(): HasMany
    {
        return $this->elements()->whereNull('parent_id')->orderBy('z_index');
    }

    public function zones(): HasMany
    {
        return $this->hasMany(TemplateZone::class, 'template_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Returns the element tree with children eager-loaded.
     *
     * @return array<int, mixed>
     */
    public function getElementsTree(): array
    {
        $elements = $this->elements()->with('children')->get();

        return $elements
            ->whereNull('parent_id')
            ->sortBy('z_index')
            ->values()
            ->toArray();
    }

    // ── Maritime Compliance ────────────────────────────────────────────────────

    /**
     * Convert canvas units to real-world meters
     *
     * @param float $canvasValue
     * @return float
     */
    public function toMeters(float $canvasValue): float
    {
        $factor = $this->scale_factor ?? 0.05; // default: 1 unit = 5cm
        return $canvasValue * $factor;
    }

    /**
     * Get all bookable elements with real-world dimensions
     *
     * @return \Illuminate\Support\Collection<int, array>
     */
    public function getBookableElementsWithMetrics()
    {
        return $this->elements()
            ->where('is_active', true)
            ->whereIn('element_type', ['seat', 'table', 'standing_zone'])
            ->get()
            ->map(function ($el) {
                return [
                    'id' => $el->id,
                    'label' => $el->data_json['label'] ?? "Element {$el->id}",
                    'type' => $el->element_type,
                    'canvas' => [
                        'x' => $el->x,
                        'y' => $el->y,
                        'width' => $el->width,
                        'height' => $el->height,
                        'area_units' => $el->width * $el->height,
                    ],
                    'metrics' => [
                        'width_m' => $this->toMeters($el->width),
                        'depth_m' => $this->toMeters($el->height),
                        'area_sqm' => $this->toMeters($el->width) * $this->toMeters($el->height),
                    ],
                    'z_index' => $el->z_index,
                ];
            });
    }

    /**
     * Run full maritime compliance check
     *
     * Checks:
     * - Minimum cabin area (6m² for regular, 10m² for wheelchair)
     * - Minimum aisle width (1.2m for service, 0.9m for emergency)
     * - Clearance zones around entrances/exits (1m buffer)
     * - Accessibility proximity (wheelchair seats near accessible toilets & muster stations)
     *
     * @return array{valid: bool, violations: array, summary: array}
     */
    public function marineComplianceCheck(): array
    {
        $elements = $this->elements()->where('is_active', true)->get();
        $violations = [];
        $summary = [
            'total_elements' => $elements->count(),
            'seats_checked' => 0,
            'aisles_checked' => 0,
            'exits_checked' => 0,
            'area_violations' => 0,
            'width_violations' => 0,
            'accessibility_violations' => 0,
        ];

        // Sailor/cabin minimum area per IMO (6m² standard, 10m² accessible)
        $MIN_CABIN_AREA_SQM = 6.0;
        $MIN_ACCESSIBLE_AREA_SQM = 10.0;
        $MIN_AISLE_WIDTH_M = 1.2;
        $MIN_EMERGENCY_WIDTH_M = 0.9;
        $EXIT_BUFFER_M = 1.0;

        foreach ($elements as $el) {
            switch ($el->element_type) {
                case 'seat':
                    $areaSqm = $this->toMeters($el->width * $el->height);
                    $summary['seats_checked']++;

                    $seatType = $el->data_json['seat_type'] ?? 'regular';
                    $minRequired = ($seatType === 'wheelchair' || $seatType === 'companion')
                                   ? $MIN_ACCESSIBLE_AREA_SQM
                                   : $MIN_CABIN_AREA_SQM;

                    if ($areaSqm < $minRequired) {
                        $summary['area_violations']++;
                        $violations[] = "Cabin '{$el->data_json['label']}': {$areaSqm}m² < minimum {$minRequired}m² for {$seatType} type";
                    }
                    break;

                case 'aisle':
                case 'corridor':
                    $widthM = $this->toMeters($el->width);
                    $summary['aisles_checked']++;

                    $isEmergency = $el->data_json['is_emergency'] ?? false;
                    $minWidth = $isEmergency ? $MIN_EMERGENCY_WIDTH_M : $MIN_AISLE_WIDTH_M;

                    if ($widthM < $minWidth) {
                        $summary['width_violations']++;
                        $violations[] = "Aisle '{$el->data_json['label']}': width {$widthM}m < minimum {$minWidth}m";
                    }
                    break;

                case 'entrance':
                case 'emergency_exit':
                    $summary['exits_checked']++;
                    // Check clearance zone (1m radius must be free of permanent obstructions)
                    // Pass empty array - we want to FIND obstructions, not exclude them
                    $clearanceRadiusM = $EXIT_BUFFER_M;
                    $obstructing = $this->findObstructingElementsInRadius($el, $clearanceRadiusM, ['seat', 'table', 'stage']);
                    if ($obstructing->isNotEmpty()) {
                        $names = $obstructing->pluck('data_json.label')->join(', ');
                        $violations[] = "Exit '{$el->data_json['label']}': clearance zone obstructed by {$names}";
                    }
                    break;
            }
        }

        // Separate accessibility proximity check (requires all elements)
        $accessibilityViolations = $this->validateAccessibilityProximity($elements);
        foreach ($accessibilityViolations as $av) {
            $summary['accessibility_violations']++;
            $violations[] = $av;
        }

        return [
            'valid' => empty($violations),
            'violations' => $violations,
            'summary' => $summary,
            'scale_factor' => $this->scale_factor ?? 0.05,
            'units' => $this->units ?? 'meters',
        ];
    }

    /**
     * Validate accessibility proximity requirements (ADA/IMO)
     *
     * Checks:
     * - Wheelchair seats within 30m of an accessible toilet
     * - Wheelchair seats within 50m of a muster station
     *
     * @param \Illuminate\Support\Collection $elements All active template elements
     * @return array<string, string> List of violation messages
     */
    private function validateAccessibilityProximity($elements): array
    {
        $violations = [];
        $toiletMaxDistM = 30;
        $musterMaxDistM = 50;

        $wheelchairSeats = $elements->where('element_type', 'seat')
            ->filter(fn($el) => in_array(($el->data_json['seat_type'] ?? ''), ['wheelchair', 'companion'], true));

        $accessibleToilets = $elements->where('element_type', 'toilet')
            ->filter(fn($el) => ($el->data_json['accessible'] ?? false) === true);

        $musterStations = $elements->where('element_type', 'zone')
            ->filter(fn($el) => ($el->data_json['zone_type'] ?? '') === 'muster_station');

        if ($accessibleToilets->isEmpty()) {
            foreach ($wheelchairSeats as $seat) {
                $violations[] = "Wheelchair seat '{$seat->data_json['label']}': No accessible toilet defined in template";
            }
            return $violations;
        }

        if ($musterStations->isEmpty()) {
            foreach ($wheelchairSeats as $seat) {
                $violations[] = "Wheelchair seat '{$seat->data_json['label']}': No muster station defined in template";
            }
            return $violations;
        }

        foreach ($wheelchairSeats as $seat) {
            $sx = $seat->x + ($seat->width / 2);
            $sy = $seat->y + ($seat->height / 2);

            $nearestToiletDist = $this->findNearestElementDistance($seat, $accessibleToilets);
            $nearestMusterDist = $this->findNearestElementDistance($seat, $musterStations);

            $toiletDistM = $this->toMeters($nearestToiletDist);
            $musterDistM = $this->toMeters($nearestMusterDist);

            if ($toiletDistM > $toiletMaxDistM) {
                $violations[] = "Wheelchair seat '{$seat->data_json['label']}': Too far from accessible toilet ({$toiletDistM}m > {$toiletMaxDistM}m)";
            }

            if ($musterDistM > $musterMaxDistM) {
                $violations[] = "Wheelchair seat '{$seat->data_json['label']}': Too far from muster station ({$musterDistM}m > {$musterMaxDistM}m)";
            }
        }

        return $violations;
    }

    /**
     * Find minimum canvas distance from a reference element to any element in a set
     */
    private function findNearestElementDistance(TemplateElement $reference, $targets): float
    {
        $rx = $reference->x + ($reference->width / 2);
        $ry = $reference->y + ($reference->height / 2);

        $minDist = PHP_INT_MAX;
        foreach ($targets as $target) {
            $tx = $target->x + ($target->width / 2);
            $ty = $target->y + ($target->height / 2);
            $dx = $rx - $tx;
            $dy = $ry - $ty;
            $dist = sqrt($dx*$dx + $dy*$dy);
            if ($dist < $minDist) {
                $minDist = $dist;
            }
        }

        return $minDist;
    }

    /**
     * Find elements of specific types within radius (meters) of a reference element
     *
     * @param TemplateElement $center
     * @param float $radiusMeters
     * @param array $elementTypes Types to search for (empty = all types)
     * @return \Illuminate\Support\Collection
     */
    private function findObstructingElementsInRadius(TemplateElement $center, float $radiusMeters, array $elementTypes = []): \Illuminate\Support\Collection
    {
        $radiusCanvas = $radiusMeters / ($this->scale_factor ?? 0.05);

        $query = $this->elements()->where('id', '!=', $center->id);
        
        if (!empty($elementTypes)) {
            $query->whereIn('element_type', $elementTypes);
        }

        return $query->get()
            ->filter(function ($el) use ($center, $radiusCanvas) {
                $dx = ($el->x + $el->width/2) - ($center->x + $center->width/2);
                $dy = ($el->y + $el->height/2) - ($center->y + $center->height/2);
                $dist = sqrt($dx*$dx + $dy*$dy);
                return $dist <= $radiusCanvas;
            });
    }
}

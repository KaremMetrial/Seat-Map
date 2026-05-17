<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

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
            if (!$template->slug) {
                $base = Str::slug($template->name);
                $slug = $base;
                $i = 1;

                while (self::where('slug', $slug)->exists()) {
                    $slug = "{$base}-{$i}";
                    $i++;
                }

                $template->slug = $slug;
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
        return $this->elements()
            ->with('children')
            ->whereNull('parent_id')
            ->orderBy('z_index')
            ->get()
            ->toArray();
    }
    private function findObstructions($center, float $radiusM, Collection $elements): Collection
    {
        $radius = $radiusM / $this->scaleFactor();

        $cx = $center->x + ($center->width / 2);
        $cy = $center->y + ($center->height / 2);

        return $elements->filter(function ($el) use ($center, $radius, $cx, $cy) {

            if ($el->id === $center->id) {
                return false;
            }

            $ex = $el->x + ($el->width / 2);
            $ey = $el->y + ($el->height / 2);

            $dx = $cx - $ex;
            $dy = $cy - $ey;

            return sqrt($dx * $dx + $dy * $dy) <= $radius;
        });
    }
        public function findNearestDistance($reference, Collection $targets): float
    {
        $rx = $reference->x + ($reference->width / 2);
        $ry = $reference->y + ($reference->height / 2);

        $min = INF;

        foreach ($targets as $t) {

            $tx = $t->x + ($t->width / 2);
            $ty = $t->y + ($t->height / 2);

            $dist = sqrt(($rx - $tx) ** 2 + ($ry - $ty) ** 2);

            $min = min($min, $dist);
        }

        return $min;
    }
    // ── Maritime Compliance ────────────────────────────────────────────────────

    /**
     * Convert canvas units to real-world meters
     *
     * @param float $canvasValue
     * @return float
     */
    public function scaleFactor(): float
    {
        return (float) ($this->scale_factor ?? 0.05);
    }

    public function toMeters(float $value): float
    {
        return $value * $this->scaleFactor();
    }

    /**
     * Get all bookable elements with real-world dimensions
     *
     * @return \Illuminate\Support\Collection<int, array>
     */
    public function getBookableElementsWithMetrics(): Collection
    {
        return $this->elements()
            ->where('is_active', true)
            ->whereIn('element_type', ['seat', 'table', 'standing_zone'])
            ->get()
            ->map(function ($el) {

                $widthM = $this->toMeters($el->width);
                $heightM = $this->toMeters($el->height);

                return [
                    'id' => $el->id,
                    'label' => $this->data($el)['label'] ?? "Element {$el->id}",
                    'type' => $el->element_type,

                    'canvas' => [
                        'x' => $el->x,
                        'y' => $el->y,
                        'width' => $el->width,
                        'height' => $el->height,
                        'area_units' => $el->width * $el->height,
                    ],

                    'metrics' => [
                        'width_m' => $widthM,
                        'depth_m' => $heightM,
                        'area_sqm' => $widthM * $heightM,
                    ],

                    'z_index' => $el->z_index,
                ];
            });
    }
    private function data($el): array
    {
        return $el->data_json ?? [];
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

        $MIN_AREA = 6.0;
        $MIN_ACCESSIBLE = 10.0;
        $MIN_AISLE = 1.2;
        $MIN_EMERGENCY = 0.9;
        $EXIT_BUFFER = 1.0;

        foreach ($elements as $el) {

            $data = $this->data($el);

            /* ───── Seats ───── */
            if ($el->element_type === 'seat') {

                $summary['seats_checked']++;

                $widthM = $this->toMeters($el->width);
                $heightM = $this->toMeters($el->height);

                $area = $widthM * $heightM;

                $type = $data['seat_type'] ?? 'regular';

                $min = in_array($type, ['wheelchair', 'companion'], true)
                    ? $MIN_ACCESSIBLE
                    : $MIN_AREA;

                if ($area < $min) {
                    $summary['area_violations']++;
                    $violations[] = "Seat '{$data['label']}' too small ({$area}m² < {$min}m²)";
                }
            }

            /* ───── Aisles ───── */
            if (in_array($el->element_type, ['aisle', 'corridor'], true)) {

                $summary['aisles_checked']++;

                $widthM = $this->toMeters($el->width);
                $min = ($data['is_emergency'] ?? false) ? $MIN_EMERGENCY : $MIN_AISLE;

                if ($widthM < $min) {
                    $summary['width_violations']++;
                    $violations[] = "Aisle '{$data['label']}' too narrow ({$widthM}m < {$min}m)";
                }
            }

            /* ───── Exits ───── */
            if (in_array($el->element_type, ['entrance', 'emergency_exit'], true)) {

                $summary['exits_checked']++;

                $obstructing = $this->findObstructions($el, $EXIT_BUFFER, $elements);

                if ($obstructing->isNotEmpty()) {
                    $names = $obstructing->map(fn ($o) => $this->data($o)['label'] ?? $o->id)->join(', ');
                    $violations[] = "Exit '{$data['label']}' blocked by {$names}";
                }
            }
        }

        return [
            'valid' => empty($violations),
            'violations' => $violations,
            'summary' => $summary,
            'scale_factor' => $this->scaleFactor(),
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

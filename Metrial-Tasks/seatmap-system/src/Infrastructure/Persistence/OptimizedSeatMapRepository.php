<?php

namespace App\Infrastructure\Persistence;

use App\Core\DataTransfer\ElementDTO;
use App\Core\DataTransfer\SeatMapDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Optimized repository for large-scale seat maps (100K+ seats)
 * Features:
 * - Chunked database operations
 * - Redis-based viewport caching
 * - Lazy loading with spatial indexing
 * - Delta updates for real-time sync
 */
class OptimizedSeatMapRepository
{
    private const VIEWPORT_CACHE_TTL = 60; // 1 minute
    private const CHUNK_SIZE = 5000;
    private const REDIS_PREFIX = 'seatmap:optimized:';

    /**
     * Get seat map with lazy loading - only loads metadata initially
     */
    public function findByIdLazy(string $id): ?SeatMapDTO
    {
        $cacheKey = self::REDIS_PREFIX . "meta:{$id}";
        
        return Cache::remember($cacheKey, 3600, function () use ($id) {
            $template = DB::table('venue_templates')->where('id', $id)->first();
            if (!$template) {
                return null;
            }

            // Don't load elements yet - just metadata
            return new SeatMapDTO([
                'id' => $template->id,
                'name' => $template->name,
                'width' => $template->width ?? 800,
                'height' => $template->height ?? 600,
                'elements' => [], // Empty - load on demand
                'zones' => $this->getTemplateZones($id),
                'metadata' => json_decode($template->metadata_json ?? '{}', true),
                'version' => $template->version ?? '1.0',
                'created_at' => $template->created_at,
                'updated_at' => $template->updated_at,
                '_elements_loaded' => false,
                '_element_count' => $this->getElementCount($id),
            ]);
        });
    }

    /**
     * Get elements in viewport with spatial indexing
     * Optimized for 100K+ seats
     */
    public function getElementsInViewport(
        string $templateId,
        float $minX,
        float $minY,
        float $maxX,
        float $maxY,
        int $page = 1,
        int $perPage = 5000
    ): array {
        $cacheKey = self::REDIS_PREFIX . "viewport:{$templateId}:" . 
                    "{$minX}:{$minY}:{$maxX}:{$maxY}:p{$page}";
        
        return Cache::remember($cacheKey, self::VIEWPORT_CACHE_TTL, function () use (
            $templateId, $minX, $minY, $maxX, $maxY, $page, $perPage
        ) {
            // Use spatial index if available (PostGIS/MySQL spatial extensions)
            $elements = DB::table('template_elements')
                ->where('template_id', $templateId)
                ->where('is_active', true)
                ->where('x', '<=', $maxX)
                ->whereRaw('x + width >= ?', [$minX])
                ->where('y', '<=', $maxY)
                ->whereRaw('y + height >= ?', [$minY])
                ->orderBy('z_index')
                ->orderBy('sort_order')
                ->forPage($page, $perPage)
                ->get();
                
            return array_map(function ($el) {
                return new ElementDTO([
                    'id' => $el->id,
                    'element_type' => $el->element_type,
                    'x' => (float)$el->x,
                    'y' => (float)$el->y,
                    'z' => (float)$el->z,
                    'width' => (float)$el->width,
                    'height' => (float)$el->height,
                    'rotation' => (float)$el->rotation,
                    'z_index' => (float)$el->z_index,
                    'parent_id' => $el->parent_id,
                    'data' => json_decode($el->data_json, true) ?? [],
                    'style' => json_decode($el->style_json, true) ?? [],
                    'is_bookable' => $el->is_bookable ?? false,
                    'created_at' => $el->created_at,
                    'updated_at' => $el->updated_at,
                ]);
            }, $elements->toArray());
        });
    }

    /**
     * Stream elements in chunks for memory efficiency
     */
    public function streamElements(string $templateId, callable $callback, int $chunkSize = 5000): void
    {
        DB::table('template_elements')
            ->where('template_id', $templateId)
            ->where('is_active', true)
            ->orderBy('z_index')
            ->orderBy('sort_order')
            ->chunk($chunkSize, function ($elements) use ($callback) {
                $dtos = array_map(function ($el) {
                    return new ElementDTO([
                        'id' => $el->id,
                        'element_type' => $el->element_type,
                        'x' => (float)$el->x,
                        'y' => (float)$el->y,
                        'z' => (float)$el->z,
                        'width' => (float)$el->width,
                        'height' => (float)$el->height,
                        'rotation' => (float)$el->rotation,
                        'z_index' => (float)$el->z_index,
                        'parent_id' => $el->parent_id,
                        'data' => json_decode($el->data_json, true) ?? [],
                        'style' => json_decode($el->style_json, true) ?? [],
                        'is_bookable' => $el->is_bookable ?? false,
                        'created_at' => $el->created_at,
                        'updated_at' => $el->updated_at,
                    ]);
                }, $elements->toArray());
                
                $callback($dtos);
            });
    }

    /**
     * Get element count without loading all data
     */
    public function getElementCount(string $templateId): int
    {
        return DB::table('template_elements')
            ->where('template_id', $templateId)
            ->where('is_active', true)
            ->count();
    }

    /**
     * Bulk insert elements with chunking for 100K+ records
     */
    public function bulkInsertElements(string $templateId, array $elements): bool
    {
        try {
            DB::beginTransaction();
            
            // Clear existing elements
            DB::table('template_elements')
                ->where('template_id', $templateId)
                ->delete();
            
            // Insert in chunks to avoid memory issues
            $chunks = array_chunk($elements, self::CHUNK_SIZE);
            
            foreach ($chunks as $chunk) {
                $elementData = array_map(function ($element) use ($templateId) {
                    if ($element instanceof ElementDTO) {
                        $element = $element->toArray();
                    }
                    return [
                        'id' => $element['id'] ?? uniqid('el_'),
                        'template_id' => $templateId,
                        'element_type' => $element['element_type'],
                        'x' => $element['x'],
                        'y' => $element['y'],
                        'z' => $element['z'] ?? 0,
                        'width' => $element['width'],
                        'height' => $element['height'],
                        'rotation' => $element['rotation'] ?? 0,
                        'z_index' => $element['z_index'] ?? 0,
                        'parent_id' => $element['parent_id'] ?? null,
                        'data_json' => json_encode($element['data'] ?? []),
                        'style_json' => json_encode($element['style'] ?? []),
                        'is_bookable' => $element['is_bookable'] ?? false,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }, $chunk);
                
                DB::table('template_elements')->insert($elementData);
            }
            
            DB::commit();
            
            // Clear cache
            Cache::forget(self::REDIS_PREFIX . "meta:{$templateId}");
            Cache::tags([self::REDIS_PREFIX . "viewport:{$templateId}"])->flush();
            
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to bulk insert elements: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get only changed elements since last version (delta updates)
     */
    public function getChangesSince(string $templateId, string $sinceVersion): array
    {
        $elements = DB::table('template_elements')
            ->where('template_id', $templateId)
            ->where('updated_at', '>', $sinceVersion)
            ->get();
            
        return array_map(function ($el) {
            return [
                'id' => $el->id,
                'action' => $el->is_deleted ? 'deleted' : 'updated',
                'element' => $el,
            ];
        }, $elements->toArray());
    }

    /**
     * Get template zones (cached)
     */
    private function getTemplateZones(string $templateId): array
    {
        return Cache::remember(self::REDIS_PREFIX . "zones:{$templateId}", 3600, function () use ($templateId) {
            $zones = DB::table('template_zones')
                ->where('template_id', $templateId)
                ->where('is_active', true)
                ->get();

            return array_map(function ($zone) {
                return [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'zone_type' => $zone->zone_type,
                    'x' => (float)$zone->x,
                    'y' => (float)$zone->y,
                    'width' => (float)$zone->width,
                    'height' => (float)$zone->height,
                    'metadata' => json_decode($zone->metadata_json, true) ?? [],
                ];
            }, $zones->toArray());
        });
    }

    /**
     * Pre-generate viewport cache for common views
     */
    public function warmViewportCache(string $templateId, array $commonViewports): void
    {
        foreach ($commonViewports as $viewport) {
            $this->getElementsInViewport(
                $templateId,
                $viewport['x'],
                $viewport['y'],
                $viewport['x'] + $viewport['width'],
                $viewport['y'] + $viewport['height']
            );
        }
    }
}

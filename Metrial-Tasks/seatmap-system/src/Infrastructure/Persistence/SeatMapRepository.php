<?php

namespace App\Infrastructure\Persistence;

use App\Core\DataTransfer\SeatMapDTO;
use App\Core\DataTransfer\ElementDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Repository for managing seat map templates with caching
 */
class SeatMapRepository
{
    private const CACHE_TTL = 3600; // 1 hour
    private const CACHE_PREFIX = 'seatmap:';

    /**
     * Get a seat map by ID
     */
    public function findById(string $id): ?SeatMapDTO
    {
        $cacheKey = self::CACHE_PREFIX . $id;
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($id) {
            $template = DB::table('venue_templates')->where('id', $id)->first();
            if (!$template) {
                return null;
            }

            $elements = $this->getTemplateElements($id);
            $zones = $this->getTemplateZones($id);

            return new SeatMapDTO([
                'id' => $template->id,
                'name' => $template->name,
                'width' => $template->width ?? 800,
                'height' => $template->height ?? 600,
                'elements' => $elements,
                'zones' => $zones,
                'metadata' => json_decode($template->metadata_json ?? '{}', true),
                'version' => $template->version ?? '1.0',
                'created_at' => $template->created_at,
                'updated_at' => $template->updated_at,
            ]);
        });
    }

    /**
     * Get template elements
     */
    private function getTemplateElements(string $templateId): array
    {
        $elements = DB::table('template_elements')
            ->where('template_id', $templateId)
            ->where('is_active', true)
            ->orderBy('z_index')
            ->orderBy('sort_order')
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
    }

    /**
     * Get template zones
     */
    private function getTemplateZones(string $templateId): array
    {
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
    }

    /**
     * Save a seat map template
     */
    public function save(SeatMapDTO $seatMap): bool
    {
        try {
            DB::beginTransaction();

            // Update or create template
            $templateId = $seatMap->getId();
            $existing = DB::table('venue_templates')->where('id', $templateId)->first();

            $templateData = [
                'name' => $seatMap->getName(),
                'width' => $seatMap->getWidth(),
                'height' => $seatMap->getHeight(),
                'metadata_json' => json_encode($seatMap->getMetadata()),
                'version' => $seatMap->getVersion(),
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('venue_templates')->where('id', $templateId)->update($templateData);
            } else {
                $templateData['id'] = $templateId;
                $templateData['created_at'] = now();
                DB::table('venue_templates')->insert($templateData);
            }

            // Delete existing elements
            DB::table('template_elements')->where('template_id', $templateId)->delete();

            // Insert new elements
            $elements = $seatMap->getElements();
            if (!empty($elements)) {
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
                }, $elements);

                DB::table('template_elements')->insert($elementData);
            }

            DB::commit();

            // Clear cache
            Cache::forget(self::CACHE_PREFIX . $templateId);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to save seat map: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a seat map template
     */
    public function delete(string $id): bool
    {
        try {
            DB::beginTransaction();

            DB::table('template_elements')->where('template_id', $id)->delete();
            DB::table('template_zones')->where('template_id', $id)->delete();
            DB::table('venue_templates')->where('id', $id)->delete();

            DB::commit();

            Cache::forget(self::CACHE_PREFIX . $id);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to delete seat map: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all seat map templates
     */
    public function getAll(): array
    {
        $templates = DB::table('venue_templates')->where('is_active', true)->get();

        return array_map(function ($template) {
            return [
                'id' => $template->id,
                'name' => $template->name,
                'width' => $template->width,
                'height' => $template->height,
                'version' => $template->version,
                'created_at' => $template->created_at,
                'updated_at' => $template->updated_at,
            ];
        }, $templates->toArray());
    }
}
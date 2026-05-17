<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\RebuildSeatCache;
use App\Models\Event;
use App\Models\VenueTemplate;
use Illuminate\Support\Facades\Log;

/**
 * Template Cache Service — handles cache invalidation when templates change.
 *
 * When an admin modifies a template (add/remove/update elements or zones),
 * all published events using that template need their Redis cache invalidated
 * because the seat map snapshot is derived from the template.
 *
 * Strategy:
 * - Template/element/zone changes → dispatch queue job per affected event
 * - Queue job rebuilds cache from DB (non-blocking)
 * - Draft events are not affected (no cache yet)
 */
class TemplateCacheService
{
    /**
     * Invalidate cache for all published events using a template.
     * Called after: template update, element create/update/delete, zone changes.
     */
    public function invalidateTemplateCache(int $templateId): int
    {
        $eventIds = Event::where('template_id', $templateId)
            ->where('status', 'published')
            ->pluck('id');

        if ($eventIds->isEmpty()) {
            Log::info('TemplateCacheService: no published events to invalidate', [
                'template_id' => $templateId,
            ]);
            return 0;
        }

        foreach ($eventIds as $eventId) {
            RebuildSeatCache::dispatch($eventId);
        }

        Log::info('TemplateCacheService: cache invalidation dispatched', [
            'template_id' => $templateId,
            'event_count' => $eventIds->count(),
            'event_ids' => $eventIds->toArray(),
        ]);

        return $eventIds->count();
    }

    /**
     * Invalidate cache for a specific event.
     * Called after: direct event element modifications.
     */
    public function invalidateEventCache(int $eventId): void
    {
        RebuildSeatCache::dispatch($eventId);

        Log::info('TemplateCacheService: event cache invalidation dispatched', [
            'event_id' => $eventId,
        ]);
    }

    /**
     * Check if a template has published events (for delete protection).
     */
    public function templateHasPublishedEvents(int $templateId): bool
    {
        return Event::where('template_id', $templateId)
            ->where('status', 'published')
            ->exists();
    }

    /**
     * Get count of published events using a template.
     */
    public function getPublishedEventCount(int $templateId): int
    {
        return Event::where('template_id', $templateId)
            ->where('status', 'published')
            ->count();
    }

    /**
     * Get all published event IDs using a template.
     */
    public function getPublishedEventIds(int $templateId): array
    {
        return Event::where('template_id', $templateId)
            ->where('status', 'published')
            ->pluck('id')
            ->toArray();
    }
}
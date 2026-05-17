<?php

namespace App\Console\Commands;

use App\Jobs\RebuildSeatCache;
use App\Models\Event;
use Illuminate\Console\Command;

/**
 * Artisan command to warm (or rebuild) the Redis seat cache for an event.
 *
 * Usage:
 *   php artisan cache:warm-seats 1           # Warm cache for event 1
 *   php artisan cache:warm-seats --all       # Warm cache for all published events
 *   php artisan cache:warm-seats 1 --force   # Force rebuild even if cache exists
 */
class WarmSeatCache extends Command
{
    protected $signature = 'cache:warm-seats
                            {event? : Event ID (omit for --all)}
                            {--all : Warm cache for all published events}
                            {--force : Force rebuild even if cache exists}';

    protected $description = 'Warm Redis seat cache for fast seat availability lookups';

    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->warmAll();
        }

        $eventId = $this->argument('event');

        if (!$eventId) {
            $this->error('Please provide an event ID or use --all');
            return self::FAILURE;
        }

        return $this->warmEvent((int) $eventId);
    }

    private function warmEvent(int $eventId): int
    {
        $event = Event::find($eventId);

        if (!$event) {
            $this->error("Event {$eventId} not found");
            return self::FAILURE;
        }

        if ($event->status !== 'published') {
            $this->warn("Event {$eventId} is not published (status: {$event->status})");
        }

        $this->info("Dispatching cache rebuild for event {$eventId}...");
        RebuildSeatCache::dispatch($eventId);

        $this->info('Cache rebuild job dispatched to queue.');
        $this->info('Run `php artisan queue:work` to process it.');

        return self::SUCCESS;
    }

    private function warmAll(): int
    {
        $events = Event::where('status', 'published')->get();

        if ($events->isEmpty()) {
            $this->warn('No published events found');
            return self::SUCCESS;
        }

        $this->info("Dispatching cache rebuild for {$events->count()} events...");

        foreach ($events as $event) {
            RebuildSeatCache::dispatch($event->id);
            $this->line("  → Event {$event->id}: {$event->title}");
        }

        $this->info('All cache rebuild jobs dispatched.');
        $this->info('Run `php artisan queue:work` to process them.');

        return self::SUCCESS;
    }
}
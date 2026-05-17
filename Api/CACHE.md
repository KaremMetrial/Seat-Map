# Cache & Queue Strategy — Complete Guide

## Philosophy

**Every write operation updates the cache. Every read operation checks the cache first.**

When an admin creates/updates/deletes templates, elements, or zones, the system:
1. Saves to database
2. Dispatches a queue job to rebuild Redis cache for affected events
3. Returns immediately (non-blocking)

When a user views the seat map:
1. Check Redis cache first (O(1) per seat)
2. If cache miss, fall back to DB
3. Return only visible seats (viewport filtering)

---

## Cache Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              WRITE PATH                                     │
│                                                                             │
│  Admin creates/updates/deletes template, element, or zone                   │
│       │                                                                     │
│       ▼                                                                     │
│  Controller saves to DB                                                     │
│       │                                                                     │
│       ▼                                                                     │
│  TemplateCacheService::invalidateTemplateCache(templateId)                  │
│       │                                                                     │
│       ▼                                                                     │
│  Find all published events using this template                              │
│       │                                                                     │
│       ▼                                                                     │
│  Dispatch RebuildSeatCache job per event (non-blocking)                     │
│       │                                                                     │
│       ▼                                                                     │
│  Queue worker: warmCache(eventId)                                           │
│       │                                                                     │
│       ▼                                                                     │
│  Redis Hash: event:{id}:seats = { 101: "available", 102: "booked", ... }   │
│                                                                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                              READ PATH                                      │
│                                                                             │
│  GET /seatmap?x=100&y=200&width=400&height=300                             │
│       │                                                                     │
│       ▼                                                                     │
│  Viewport query (uses composite index) → ~50-200 seat IDs                   │
│       │                                                                     │
│       ▼                                                                     │
│  SeatCacheService::getSeatStatuses(eventId, [101, 102, ...])                │
│       │                                                                     │
│       ├──▶ Cache HIT → Return immediately (< 50ms)                         │
│       │                                                                     │
│       └──▶ Cache MISS → Fallback to DB → Warm cache → Return               │
│                                                                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                           BOOKING PATH                                      │
│                                                                             │
│  User books seats 101, 102                                                  │
│       │                                                                     │
│       ▼                                                                     │
│  DB Transaction: create booking + booking_items                             │
│       │                                                                     │
│       ▼                                                                     │
│  SeatCacheService::setSeatStatuses(1, [101→booked, 102→booked])             │
│       │ (< 10ms, direct Redis write)                                        │
│       │                                                                     │
│       ▼                                                                     │
│  Dispatch UpdateSeatStatus job (non-blocking)                               │
│       │                                                                     │
│       ▼                                                                     │
│  Queue worker: broadcast Socket.IO event                                    │
│       │                                                                     │
│       ▼                                                                     │
│  All connected clients see seats 101, 102 turn red instantly                │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## When Cache is Invalidated

| Action | What Happens | Job Dispatched |
|--------|--------------|----------------|
| **Create element** | Cache invalidated for all published events using template | `RebuildSeatCache` per event |
| **Update element** | Cache invalidated for all published events using template | `RebuildSeatCache` per event |
| **Delete element** | Cache invalidated for all published events using template | `RebuildSeatCache` per event |
| **Bulk create/delete** | Cache invalidated once for all affected templates | `RebuildSeatCache` per event |
| **Create/update/delete zone** | Cache invalidated for all published events using template | `RebuildSeatCache` per event |
| **Assign/remove elements from zone** | Cache invalidated for all published events using template | `RebuildSeatCache` per event |
| **Update template** | Cache invalidated for all published events using template | `RebuildSeatCache` per event |
| **Publish event** | Cache warmed from snapshot | `RebuildSeatCache` for the event |
| **Booking created** | Specific seats updated in cache | `UpdateSeatStatus` |
| **Booking confirmed** | Specific seats updated in cache | `UpdateSeatStatus` |
| **Booking cancelled** | Specific seats updated in cache | `UpdateSeatStatus` |
| **Lock acquired** | Specific seats updated in cache | `UpdateSeatStatus` |
| **Lock released** | Specific seats updated in cache | `UpdateSeatStatus` |

**Important:** Draft events are NOT affected by template changes. Only published events have cache entries.

---

## Redis Data Structure

### Seat Status Hash

```
Key: event:{eventId}:seats
Type: Hash
TTL: 24 hours

Field → Value
  101 → "available"
  102 → "booked"
  103 → "locked"
  104 → "available"
  ...
```

### Memory Usage

| Event Size | Memory |
|------------|--------|
| 1K seats | ~80 KB |
| 10K seats | ~800 KB |
| 100K seats | ~8 MB |
| 1M seats | ~80 MB |

---

## Queue Jobs

### RebuildSeatCache

**Purpose:** Warm the entire cache for an event from DB.

**Triggered by:** Template changes, event publish, manual command.

**Duration:** ~10-30 seconds for 100K seats.

```php
// Dispatch for single event
RebuildSeatCache::dispatch($eventId);

// Dispatch for all events using a template
$eventIds = Event::where('template_id', $templateId)
    ->where('status', 'published')
    ->pluck('id');

foreach ($eventIds as $eventId) {
    RebuildSeatCache::dispatch($eventId);
}
```

**Job configuration:**
```php
public int $tries = 3;
public int $timeout = 300;      // 5 minutes
public array $backoff = [30, 60, 120];
```

### UpdateSeatStatus

**Purpose:** Update specific seats in cache + broadcast socket event.

**Triggered by:** Booking created/confirmed/cancelled, lock acquired/released.

**Duration:** < 100ms.

```php
UpdateSeatStatus::dispatch($eventId, [
    ['element_id' => 101, 'status' => 'booked'],
    ['element_id' => 102, 'status' => 'booked'],
]);
```

**Job configuration:**
```php
public int $tries = 3;
public int $timeout = 30;
```

---

## API Response Changes

### Template/Element/Zone Operations

All write operations now include cache invalidation metadata:

```json
{
    "success": true,
    "data": { ... },
    "meta": {
        "cache_invalidated": true,
        "affected_published_events": 3
    }
}
```

### Seat Map Endpoint

```json
{
    "success": true,
    "data": {
        "event": { ... },
        "elements": [ ... ],
        "zones": [ ... ],
        "meta": {
            "total_returned": 150,
            "viewport_applied": true,
            "cache_used": true
        }
    }
}
```

---

## Artisan Commands

### Warm Cache

```bash
# Single event
php artisan cache:warm-seats 1

# All published events
php artisan cache:warm-seats --all

# Force rebuild (even if cache exists)
php artisan cache:warm-seats 1 --force
```

### Queue Worker

```bash
# Process jobs
php artisan queue:work

# Process with specific connection
php artisan queue:work --queue=cache,default

# Monitor queue
php artisan queue:monitor

# Retry failed jobs
php artisan queue:retry all
```

---

## Production Setup

### Supervisor Configuration

```ini
# /etc/supervisor/conf.d/seatmap-queue.conf
[program:seatmap-queue]
command=php /var/www/seat-map/Api/artisan queue:work --sleep=3 --tries=3 --max-time=300
directory=/var/www/seat-map/Api
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/seatmap-queue.log
```

### PM2 for Socket.IO

```bash
cd Api/socket-server
pm2 start index.js --name seatmap-socket -i 2
pm2 save
pm2 startup
```

### Redis Configuration

```conf
# /etc/redis/redis.conf
maxmemory 512mb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
appendonly yes
```

---

## Monitoring

### Check Cache Status

```bash
redis-cli

# Check if cache exists
EXISTS event:1:seats

# Get seat count
HLEN event:1:seats

# Get specific seat
HGET event:1:seats 101

# Get multiple seats
HMGET event:1:seats 101 102 103

# Memory usage
INFO memory

# Keys pattern
KEYS event:*:seats
```

### Check Queue Status

```bash
# View pending jobs
php artisan queue:monitor

# Failed jobs
php artisan queue:failed

# Retry failed
php artisan queue:retry all
```

---

## Troubleshooting

| Problem | Cause | Solution |
|---------|-------|----------|
| Stale seat data | Cache not invalidated | `php artisan cache:warm-seats 1 --force` |
| Cache not warming | Queue worker not running | `php artisan queue:work` |
| High Redis memory | Too many cached events | Reduce TTL or use LRU eviction |
| Slow cache warm | Large event | Normal — runs in background |
| Socket not updating | Job failing | Check `php artisan queue:failed` |
| Cache miss on read | Cache expired or cleared | Automatic fallback to DB |

---

## Performance Summary

| Operation | Without Cache | With Cache | Improvement |
|-----------|---------------|------------|-------------|
| Seat map load (100K) | 5-30s | 50-200ms | 25-150x |
| Available seats query | 2-5s | 10-50ms | 40-100x |
| Booking status check | 500ms | < 1ms | 500x |
| Memory per request | 500MB-1GB | 1-5MB | 100-1000x |
| Concurrent users | ~50 | ~5000+ | 100x |

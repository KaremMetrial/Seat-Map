# Design Documentation — 2D Seat Map Booking System

## Table of Contents
1. [System Overview](#1-system-overview)
2. [Database Schema](#2-database-schema)
3. [Table Purpose Explained](#3-table-purpose-explained)
4. [Snapshot Flow](#4-snapshot-flow)
5. [Booking Flow](#5-booking-flow)
6. [Performance Strategy](#6-performance-strategy)
7. [API Design](#7-api-design)
8. [Frontend Integration](#8-frontend-integration)
9. [Advanced Features](#9-advanced-features)

---

## 1. System Overview

### Architecture Flow

```
┌─────────────┐
│    Venue    │ (cinema, stadium, theater, custom)
└──────┬──────┘
       │ has many
       ▼
┌──────────────────┐
│  VenueTemplate   │ (2D canvas layout)
│  - canvas_width  │
│  - canvas_height │
└──────┬───────────┘
       │ contains
       ▼
┌──────────────────┐
│ TemplateElements │ (dynamic elements)
│ - type: seat     │
│ - x,y: position  │
│ - data_json: {}  │
└──────┬───────────┘
       │
       ▼ publish ───────────────────────┐
       │                                │
       ▼                                ▼
┌──────────────────┐              ┌──────────┐
│   Event          │              │  Zones   │
│   - status       │──────────────│ Pricing  │
│   - start_at     │              │  Rules   │
└──────┬───────────┘              └──────────┘
       │ event_elements (SNAPSHOT!)
       │
       ▼
┌────────────────────────────────────────┐
│ EventElements (IMMUTABLE COPY)         │
│ x,y,data_json,style_json snapshotted   │
│ NEVER CHANGES after creation           │
│ booking_status derived at query time   │
└────────────────────────────────────────┘
       │
       ▼ bookings / booking_items
       │
       ▼ element_locks (temporary, TTL-based)
```

---

## 2. Database Schema

See `FULL_DATABASE_SCHEMA.md` for complete SQL with indexes and constraints.

---

## 3. Table Purpose Explained

### venues
Physical location container. Separate from templates because one venue can have multiple configurations (orchestra setup vs. standing room).

### venue_templates
Layout definition with 2D canvas. Key insight: templates are reusable — multiple events can use the same template.

### template_elements
**THE CORE INNOVATION**: Instead of fixed `rows` and `seats` tables, we use dynamic elements stored as JSON.

```php
// Why this beats the traditional approach:
//
// Traditional:
//   SELECT * FROM seats WHERE row_id = 5
//
// This system:
//   SELECT * FROM template_elements WHERE template_id = 1
//   Returns: seats, tables, sections, stages all together
```

### template_zones
Pricing areas. Enables different prices for VIP vs. Regular sections independently of physical layout.

### element_zone_map
Many-to-many because elements can span zones (e.g., a table straddling VIP and regular areas).

### events
Event instance (specific screening/show). When published, triggers snapshot creation.

### event_elements
**CRITICAL**: Point-in-time copy of template elements. Never updated after creation.

**WHY SNAPSHOT?**
```
User selects seat at (100, 200) → Admin moves seat in template →
Without snapshot: booked seat shows wrong position
With snapshot:    booked seat stays at (100, 200)
```

**No `booking_status` column** — see section 5 for why and how status is derived.

### bookings → booking_items
Standard order pattern. `booking_items` links to `event_elements` (not template elements).

### element_locks
Temporary holds with TTL. Prevents race conditions without database row locking.

**Key design points:**
- `lock_key` is `NOT NULL` — always required. A nullable key would cause all callers that omit it to share the same Redis mutex and DB rows.
- `UNIQUE (event_element_id)` — one active lock row per element. Expired rows are deleted before re-inserting.

---

## 4. Snapshot Flow

### Step 1: Create Template
Admin builds layout in designer tool; elements saved to `template_elements`.

### Step 2: Publish Event

```
Event::publish():
1. Set status = 'published'
2. Set snapshotted_at = NOW()
3. FOR EACH template_element:
   a. Call toEventElement($eventId, $zoneId)
      - JSON-encodes data_json / style_json (insert() skips cast pipeline)
      - Sets created_at / updated_at explicitly (insert() skips auto-timestamps)
   b. Bulk INSERT into event_elements
4. Update capacity counts via whereExists subquery
```

### Step 3: Booking Uses Snapshot
All queries use `event_elements`, never `template_elements` after publish.

### Step 4: Template Changes Are Safe
Admin can modify the template — existing events are unaffected because they use their own snapshot.

### Why `toEventElement()` Must Handle Timestamps and JSON Encoding

`EventElement::insert()` bypasses Eloquent entirely — no model events, no cast pipeline, no auto-timestamps. If `toEventElement()` returned a raw PHP array with `data_json` as an array and no timestamps, the bulk insert would store `null` for `created_at`/`updated_at` and either throw or store `"[object Object]"` for the JSON fields.

```php
// Correct implementation in TemplateElement::toEventElement()
return [
    // ...
    'data_json'  => $this->data_json !== null ? json_encode($this->data_json) : null,
    'style_json' => $this->style_json !== null ? json_encode($this->style_json) : null,
    'created_at' => $now,
    'updated_at' => $now,
];
```

---

## 5. Booking Flow

### Race Condition Prevention — Layered Defence

```
Client                    Server                         Database / Redis
  │                         │                                │
  ├─ Select Seats ──────────┤                                │
  │                         │                                │
  ├─ POST /lock ───────────▶│                                │
  │                         ├─ Redis SET NX (mutex) ────────▶
  │                         │  Acquired FIRST — before DB   │
  │                         │                                │
  │                         ├─ Check availability ──────────▶
  │                         │  (inside mutex — TOCTOU-safe) │
  │                         │                                │
  │                         ├─ BEGIN TRANSACTION             │
  │                         ├─ DELETE expired lock ─────────▶
  │                         ├─ INSERT element_locks ────────▶
  │                         │  (UNIQUE on event_element_id) │
  │                         │  Fails if active lock exists  │
  │                         ├─ COMMIT                        │
  │                         ├─ Redis DEL (release mutex) ───▶
  │◀── Lock Result ─────────┤                                │
  │                         │                                │
  ├─ POST /bookings ───────▶│                                │
  │                         ├─ INSERT bookings ─────────────▶
  │                         ├─ INSERT booking_items ────────▶
  │                         │  (UNIQUE on event_element_id) │
  │◀── Booking ID ──────────┤                                │
  │                         │                                │
  ├─ Process Payment ───────▶│                                │
  │                         ├─ UPDATE bookings confirmed ───▶
  │                         ├─ DELETE element_locks ────────▶
  │◀── Confirmation ────────┤                                │
```

### Why Redis Mutex Must Come Before the Availability Check

The old order was: check → mutex → insert. This left a TOCTOU window where two concurrent requests could both pass the check before either acquired the mutex.

The correct order: **mutex → check → insert**. The availability check now happens inside the mutex, so it is serialised for requests with the same `lock_key`.

### Why `UNIQUE (event_element_id)` on `element_locks`

The old constraint was `UNIQUE (event_element_id, expires_at)`. Since `expires_at` changes on every insert, this allowed multiple active locks on the same element — the constraint was completely ineffective.

The correct constraint is on `event_element_id` alone. `SeatLockService` deletes any expired row for an element inside the same DB transaction before inserting the new lock.

### Booking Status — No `booking_status` Column

`booking_status` is not stored as a column. A MySQL `GENERATED ALWAYS AS` virtual column with correlated subqueries is the natural fit, but Laravel's `Blueprint` cannot express it without raw DDL.

**Two N+1-safe patterns are provided instead:**

**Pattern A — scope (best for filtering/pagination):**
```php
// Resolves status via CASE/EXISTS in the SELECT — 0 extra queries
$elements = EventElement::withBookingStatus()
    ->where('event_id', $id)
    ->get();
```

**Pattern B — batch hydration (best for already-loaded collections):**
```php
// Fires exactly 2 bulk queries for the whole collection
$elements = $event->eventElements()->orderBy('z_index')->get();
EventElement::hydrateBookingStatus($elements);
```

The accessor `$element->booking_status` short-circuits to the pre-set attribute when either pattern has been used. For single-model lookups it falls back to two individual queries, which is acceptable.

**Never call `$element->booking_status` in a plain loop over a collection without pre-hydrating first.**

### `updateCapacityCounts()` — No Virtual Column Dependency

`booking_status` is not a real column, so `->where('booking_status', 'booked')` would silently return 0 or throw. The booked count is derived via a correlated `whereExists` subquery:

```php
$bookedCount = $this->eventElements()
    ->where('is_bookable', true)
    ->whereExists(fn($q) => $q
        ->select(DB::raw(1))
        ->from('booking_items')
        ->whereColumn('booking_items.event_element_id', 'event_elements.id')
        ->where('booking_items.status', 'booked')
    )
    ->count();
```

---

## 6. Performance Strategy

### Query Budget for getSeatMap()

| Query | Purpose |
|-------|---------|
| 1 | Load event elements ordered by z_index |
| 2 | Eager-load template + zones |
| 3 | `hydrateBookingStatus` — booked IDs (whereIn) |
| 4 | `hydrateBookingStatus` — locked IDs (whereIn) |
| **4 total** | Regardless of element count |

Before the N+1 fix: `1 + 2N` queries — 601 queries for a 300-seat cinema.

### Indexing

```sql
-- Fast seatmap fetch and capacity count
CREATE INDEX idx_event_bookable ON event_elements (event_id, is_bookable);

-- Cleanup expired locks
CREATE INDEX idx_locks_expires ON element_locks (expires_at);

-- Booking status pagination
CREATE INDEX idx_bookings_expires ON bookings (status, expires_at);
```

### Redis Caching

```php
// Cache available count per zone (30-second TTL)
Redis::setex("event:{$eventId}:zone:{$zoneId}:count", 30, $available);

// Acquire mutex — NX (only if not exists), EX (TTL in seconds)
Redis::set("seatlock:{$lockKey}", 1, 'EX', 30, 'NX');

// Release mutex after DB work completes
Redis::del("seatlock:{$lockKey}");
```

### Query Optimisation

```php
// Eager-load template to avoid lazy-load in getSeatMap()
$event->loadMissing('template.zones');

// Subquery for available count (no N+1)
Event::withCount(['eventElements as available_count' => function ($q) {
    $q->where('is_bookable', true)
      ->whereNotIn('id', BookingItem::select('event_element_id')
          ->where('status', 'booked'));
}])->find($id);
```

---

## 7. API Design

### GET /api/v1/events/{id}/seatmap

Response:
```json
{
  "event": {
    "id": 1,
    "title": "Movie Premiere",
    "canvas": { "width": 1200, "height": 700 }
  },
  "elements": [
    {
      "id": 101,
      "type": "seat",
      "x": 120, "y": 150,
      "width": 20, "height": 20,
      "data": { "label": "A1" },
      "status": "available | locked | booked"
    }
  ]
}
```

### POST /api/v1/bookings/lock

Request:
```json
{
  "event_id": 1,
  "element_ids": [101, 102],
  "lock_key": "session-xyz"
}
```

Response (success):
```json
{
  "success": true,
  "locked_elements": [101, 102],
  "expires_at": "2024-01-01T12:10:00+00:00"
}
```

Response (conflict):
```json
{
  "success": false,
  "message": "Seat was just taken by another user",
  "conflict_element": 102
}
```

### POST /api/v1/bookings

```json
{
  "event_id": 1,
  "element_ids": [101, 102],
  "customer_name": "John Doe",
  "customer_email": "john@example.com",
  "lock_key": "session-xyz"
}
```

---

## 8. Frontend Integration

### SVG Rendering (Recommended)

```jsx
function SeatMap({ elements, canvasWidth, canvasHeight }) {
  return (
    <svg viewBox={`0 0 ${canvasWidth} ${canvasHeight}`}>
      {elements.map(el => (
        <g
          key={el.id}
          transform={`translate(${el.x},${el.y}) rotate(${el.rotation})`}
        >
          {el.type === 'seat' && (
            <rect
              width={el.width}
              height={el.height}
              fill={
                el.status === 'booked' ? '#ccc' :
                el.status === 'locked' ? '#f39c12' : '#3498db'
              }
              onClick={() => el.status === 'available' && selectSeat(el.id)}
            />
          )}
          {el.data?.label && (
            <text x={el.width / 2} y={el.height / 2} textAnchor="middle">
              {el.data.label}
            </text>
          )}
        </g>
      ))}
    </svg>
  );
}
```

### Zoom/Pan

```jsx
const [transform, setTransform] = useState({ x: 0, y: 0, scale: 1 });

<svg
  onMouseDown={startPan}
  onWheel={handleZoom}
  style={{
    transform: `translate(${transform.x}px, ${transform.y}px) scale(${transform.scale})`
  }}
/>
```

### Real-time Updates

```js
const channel = Echo.channel(`event.${eventId}`);
channel.listen('SeatStatusChanged', (e) => {
  updateSeatStatus(e.elementId, e.status);
});
```

---

## 9. Advanced Features

### Dynamic Pricing

```php
class PricingRule {
    public function calculate(EventElement $element, Carbon $when, Event $event): float
    {
        $base = $event->base_price;

        // Early bird: 30+ days = 20% off
        if ($when->diffInDays($event->start_at) >= 30) {
            return $base * 0.8;
        }

        // Last minute: within 2 hours = 10% premium
        if ($when->diffInHours($event->start_at) <= 2) {
            return $base * 1.1;
        }

        // Group discount: 4+ tickets = 15% off (requires booking context)
        return $base;
    }
}
```

### Curved Seating
Store curve parameters in `data_json`:

```json
{
  "curve": {
    "center_x": 500,
    "center_y": 300,
    "radius": 200,
    "start_angle": -45,
    "end_angle": 45
  }
}
```

Generate positions programmatically:

```php
$angle = deg2rad($startAngle + $i * $angleStep);
$x = $centerX + $radius * cos($angle);
$y = $centerY + $radius * sin($angle);
```

### Admin Layout Builder
Features:
- Drag-drop elements from palette
- Grid snapping
- Multi-select + batch edit
- Import/export as JSON
- Real-time preview mode

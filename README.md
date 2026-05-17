# Data Models & Database Migrations Documentation

**Version:** 1.0
**Last Updated:** 2026-05-14
**Audience:** Developers, Software Engineering 

---

## Executive Summary

This document provides a comprehensive overview of the Seat-Map application's data architecture, encompassing the **Models** (Eloquent ORM entities) and **Migrations** (database schema definitions) that form the foundation of the seat booking system.

### Purpose & Scope

The Seat-Map application is a **venue seat mapping and booking platform** designed to manage complex venue layouts (stadiums, theaters, cinemas) with dynamic pricing, availability tracking, and real-time booking capabilities. The data layer serves three critical functions:

1. **Layout Management** — Store and manage reusable venue templates and their visual elements
2. **Event Lifecycle** — Support the complete event lifecycle from draft to post-event
3. **Booking Integrity** — Ensure atomic booking operations with race-condition protection

### System Lifecycle Context

```
┌──────────────┐     ┌───────────────────┐     ┌──────────────┐
│  Templates   │────▶│     Events        │────▶│  Bookings    │
│  (Master)    │     │  (Snapshots)      │     │  (Checkout)  │
└──────────────┘     └───────────────────┘     └──────────────┘
        ▲                       ▲                       ▲
        │                       │                       │
        │                       │                       │
   Admin/Designer         Publish Event          Customer Checkout
```

---

## Core Concepts

### What Are Models?

A **Model** in Laravel is an Eloquent class that represents a database table and provides an Object-Relational Mapping (ORM) interface. Models allow you to interact with your database using expressive, chainable methods instead of writing raw SQL.

**Key Responsibilities of Models:**

- Define the table name and primary key
- Specify which attributes can be mass-assigned (`$fillable`)
- Define data type casting (`$casts`)
- Establish relationships with other models
- Implement business logic methods

```php
// Example: A Model attribute cast
protected $casts = [
    'is_active' => 'boolean',        // Converts string '1' to true
    'start_at'  => 'datetime',        // Converts string to Carbon instance
    'metadata'  => 'array',           // Converts JSON to/from PHP array
];
```

### What Are Migrations?

A **Migration** is a version-controlled schema definition file that describes how to construct (or modify) database tables. Migrations enable teams to collaboratively manage database schema changes with a clear history and rollback capability.

**Key Benefits:**

- **Version Control** — Schema changes are tracked in Git alongside code
- **Reproducibility** — Any developer can spin up an identical database
- **Rollback Safety** — The `down()` method reverses every change
- **CI/CD Integration** — Automated testing with fresh databases

---

## Architectural Deep Dive

### Entity Relationship Diagram

```
┌─────────────┐           ┌─────────────────┐
│   Venues    │──────────▶│  VenueTemplates │
│  (1..*)     │           │    (1..*)       │
└─────────────┘           └─────────────────┘
                                   │
                                   ▼
┌─────────────────┐           ┌─────────────────┐
│  TemplateZones  │◀──────────│ TemplateElements│
│   (1..*)        │           │   (1..*)        │
└─────────────────┘           └─────────────────┘
      │                              │
      │ (BelongsToMany)              │ (Self-Referencing)
      │                              │
      ▼                              ▼
┌─────────────────┐           ┌─────────────────┐
│ ElementZoneMap  │           │ TemplateElements│
│  (Pivot)        │           │ (children)      │
└─────────────────┘           └─────────────────┘
      ▲                              │
      │                              │
      │  EventTemplate (FK)          │
      │                              │
      ▼                              ▼
┌─────────────────┐           ┌─────────────────┐
│     Events      │──────────▶│  EventElements  │
│   (1..*)        │    1      │   (Snapshot)    │
└─────────────────┘           └─────────────────┘
      │                              │
      │                              │
      ▼                              ▼
┌─────────────────┐           ┌─────────────────┐
│   Bookings      │──────────▶│  BookingItems   │
│   (1..*)        │    1      │   (1..*)        │
└─────────────────┘           └─────────────────┘
      ▲                              ▲
      │                              │
      │                              │
      ▼                              ▼
┌─────────────────┐           ┌─────────────────┐
│  ElementLocks   │           │ BookingItems    │
│  (1..1 per EL)  │           │  (Constraint)   │
└─────────────────┘           └─────────────────┘
```

### Model Reference Table

| Model               | Table                 | Relationship                                  | Purpose                                        |
| ------------------- | --------------------- | --------------------------------------------- | ---------------------------------------------- |
| `Venue`           | `venues`            | HasMany Templates                             | Physical location entity                       |
| `VenueTemplate`   | `venue_templates`   | BelongsTo Venue, HasMany Elements/Zones       | Reusable seat map layout                       |
| `TemplateElement` | `template_elements` | Self-Referencing, BelongsToMany Zones         | Visual/structural element                      |
| `TemplateZone`    | `template_zones`    | BelongsTo Template, BelongsToMany Elements    | Pricing/pricing grouping                       |
| `Event`           | `events`            | BelongsTo Template, HasMany Elements/Bookings | Published event instance                       |
| `EventElement`    | `event_elements`    | BelongsTo Event                               | **Snapshot** of template at publish time |
| `Booking`         | `bookings`          | BelongsTo Event/User, HasMany Items           | Customer booking header                        |
| `BookingItem`     | `booking_items`     | BelongsTo Booking/Event/Element               | Individual seat/item booking                   |
| `ElementLock`     | `element_locks`     | BelongsTo Event                               | Temporary hold during checkout                 |
| `PricingRule`     | `pricing_rules`     | BelongsTo Zone/Template                       | Dynamic pricing configuration                  |

### Data Types & Constraints Summary

| Column Type                 | Usage Example          | Database Type    |
| --------------------------- | ---------------------- | ---------------- |
| `id()`                    | Primary key            | BIGINT UNSIGNED  |
| `foreignId()`             | Relationship reference | BIGINT UNSIGNED  |
| `string('name', 50)`      | Short text             | VARCHAR(50)      |
| `text('description')`     | Long text              | TEXT             |
| `decimal('price', 10, 2)` | Currency               | DECIMAL(10,2)    |
| `json('metadata')`        | Flexible structure     | JSON/JSONB       |
| `boolean('is_active')`    | Toggle flag            | TINYINT(1)       |
| `timestamp('starts_at')`  | Date/time              | DATETIME         |
| `softDeletes()`           | Logical deletion       | Added deleted_at |

---

## Step-by-Step Implementation Guide

### Local Development Setup

**Prerequisites:**

- PHP 8.2+
- Composer
- MySQL 8.0+ or SQLite 3
- Node.js 18+ (for frontend assets)

### Environment Configuration

```bash
# 1. Copy environment file
cp .env.example .env

# 2. Configure database connection
vim .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=seatmap
# DB_USERNAME=root
# DB_PASSWORD=
```

### Running Migrations

```bash
# 1. Generate application key (required for sessions)
php artisan key:generate

# 2. Run all migrations (fresh database)
php artisan migrate:fresh --seed

# 3. Run migrations (existing database, incremental)
php artisan migrate

# 4. Rollback last migration
php artisan migrate:rollback

# 5. Reset and re-run all migrations
php artisan migrate:refresh --seed
```

### Migration Best Practices

| Command               | Use Case                 |
| --------------------- | ------------------------ |
| `Schema::create()`  | New table creation       |
| `Schema::table()`   | Modifying existing table |
| `foreignId()`       | Foreign key column       |
| `cascadeOnDelete()` | Automatic child deletion |
| `nullable()`        | Optional column          |
| `default(value)`    | Default value            |

### Safety Precautions

1. **Always review migrations before running** — Check for destructive operations (`dropColumn`, `dropTable`)
2. **Use transactions for multi-table changes** — Wrap related modifications in `DB::transaction()`
3. **Test on a copy first** — Never run unverified migrations on production data
4. **Keep migrations small** — One logical change per migration file

---

## Professional Real-World Example

### Complex Model: `EventElement` with N+1 Prevention

The `EventElement` model demonstrates advanced patterns for handling computed attributes efficiently.

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Event Element — SNAPSHOT COPY of TemplateElement.
 *
 * CRITICAL: This is a point-in-time copy that NEVER changes after creation.
 * Layout changes after publish cannot affect booked seats.
 *
 * Booking Status Flow:  available → locked → booked
 *
 * ── N+1 Prevention ───────────────────────────────────────────────────────────
 * booking_status is NOT a stored column. Never call $element->booking_status
 * in a plain loop — use one of the two batch patterns below first.
 *
 * Pattern A — scope (best for filtering / pagination):
 *   $elements = EventElement::withBookingStatus()->where('event_id', $id)->get();
 *
 * Pattern B — batch hydration (best for already-loaded Collections):
 *   EventElement::hydrateBookingStatus($elements);
 * ─────────────────────────────────────────────────────────────────────────────
 */
class EventElement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'event_id',
        'template_element_id',
        'element_type',
        'x',
        'y',
        'width',
        'height',
        'rotation',
        'z_index',
        'parent_id',
        'data_json',
        'style_json',
        'is_bookable',
        'zone_id',
        'booked_price',
    ];

    protected $casts = [
        'x'           => 'decimal:2',
        'y'           => 'decimal:2',
        'width'       => 'decimal:2',
        'height'      => 'decimal:2',
        'rotation'    => 'decimal:2',
        'data_json'   => 'array',
        'style_json'  => 'array',
        'is_bookable' => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    // ── Booking status — N+1-safe ─────────────────────────────────────────────

    /**
     * Query scope: resolves booking_status for every row via a CASE/EXISTS
     * subquery added to the SELECT. Zero extra queries regardless of row count.
     */
    public function scopeWithBookingStatus(Builder $query): Builder
    {
        $now = Carbon::now()->toDateTimeString();

        return $query->selectRaw("
            event_elements.*,
            CASE
                WHEN EXISTS (
                    SELECT 1 FROM booking_items bi
                    WHERE bi.event_element_id = event_elements.id
                      AND bi.status = 'booked'
                ) THEN 'booked'
                WHEN EXISTS (
                    SELECT 1 FROM element_locks el
                    WHERE el.event_element_id = event_elements.id
                      AND el.expires_at > ?
                ) THEN 'locked'
                ELSE 'available'
            END AS booking_status
        ", [$now]);
    }

    /**
     * Batch hydration: resolves booking_status for an already-loaded Collection
     * using exactly TWO queries total, regardless of collection size.
     */
    public static function hydrateBookingStatus(Collection $elements): void
    {
        if ($elements->isEmpty()) {
            return;
        }

        $ids = $elements->pluck('id')->all();

        // Query 1 — all booked element IDs
        $bookedIds = BookingItem::whereIn('event_element_id', $ids)
            ->where('status', 'booked')
            ->pluck('event_element_id')
            ->flip()
            ->all();

        // Query 2 — all actively locked element IDs
        $lockedIds = ElementLock::whereIn('event_element_id', $ids)
            ->where('expires_at', '>', Carbon::now())
            ->pluck('event_element_id')
            ->flip()
            ->all();

        foreach ($elements as $element) {
            if (isset($bookedIds[$element->id])) {
                $element->booking_status = 'booked';
            } elseif (isset($lockedIds[$element->id])) {
                $element->booking_status = 'locked';
            } else {
                $element->booking_status = 'available';
            }
        }
    }
}
```

### Corresponding Migration: `event_elements` Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SNAPSHOT TABLE — CRITICAL FOR DATA INTEGRITY
     *
     * This table holds a point-in-time copy of template_elements.
     * Once created at publish time, it NEVER changes.
     */
    public function up(): void
    {
        Schema::create('event_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            // Reference to original template element (audit trail only)
            $table->unsignedBigInteger('template_element_id')->nullable();

            // SNAPSHOT DATA — copied at publish time, NEVER changes
            $table->string('element_type');
            $table->decimal('x', 10, 2);
            $table->decimal('y', 10, 2);
            $table->decimal('width', 10, 2)->default(0);
            $table->decimal('height', 10, 2)->default(0);
            $table->decimal('rotation', 5, 2)->default(0);
            $table->integer('z_index')->default(0);
            $table->unsignedBigInteger('parent_id')->nullable();

            $table->json('data_json')->nullable();   // SNAPSHOTTED
            $table->json('style_json')->nullable();  // SNAPSHOTTED

            $table->boolean('is_bookable')->default(true);
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->decimal('booked_price', 10, 2)->nullable();

            $table->timestamps();

            // ── Indexes ──────────────────────────────────────────────────────────
            $table->index('event_id');
            $table->index(['event_id', 'is_bookable']);
            $table->index(['event_id', 'element_type']);
            $table->index('parent_id');
            $table->index('zone_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_elements');
    }
};
```

**Best Practices Demonstrated:**

| Pattern                       | Implementation                                              |
| ----------------------------- | ----------------------------------------------------------- |
| **Snapshot Pattern**    | `event_elements` copies data at publish time              |
| **Computed Attributes** | `booking_status` derived via SQL CASE/EXISTS              |
| **N+1 Prevention**      | `scopeWithBookingStatus()` and `hydrateBookingStatus()` |
| **Composite Indexes**   | `['event_id', 'is_bookable']` for capacity queries        |
| **Audit Trail**         | `template_element_id` preserved for reference             |

---

## Stadium Example: Advanced Step-by-Step Flow

### Stadium Data Structure

```sql
-- Stadium Venue
INSERT INTO venues (name, slug, venue_type, default_width, default_height, is_active)
VALUES ('Metropolitan Stadium', 'metropolitan-stadium', 'stadium', 1200, 800, 1);

-- Stadium Template with Sections
INSERT INTO venue_templates (venue_id, name, canvas_width, canvas_height, scale_factor, units, is_default)
VALUES (1, 'Stadium - Main', 1200, 800, 0.1, 'meters', 1);

-- VIP Sections (Lower Tier)
INSERT INTO template_zones (template_id, name, code, base_price, color, priority)
VALUES (1, 'VIP Lower Box', 'VIP_LOWER', 150.00, '#ffd700', 1),
       (1, 'VIP Mid Level', 'VIP_MID', 125.00, '#ffdf00', 2),
       (1, 'VIP Upper Deck', 'VIP_UPPER', 100.00, '#ffe136', 3);

-- General Admission Sections
INSERT INTO template_zones (template_id, name, code, base_price, color, priority)
VALUES (1, 'Section 100', 'SEC_100', 45.00, '#3b82f6', 10),
       (1, 'Section 200', 'SEC_200', 40.00, '#1e40af', 11),
       (1, 'Section 300', 'SEC_300', 35.00, '#1e3a8a', 12),
       (1, 'Bleacher Seats', 'BLEACHER', 25.00, '#0369a1', 13);
```

### Step-by-Step Stadium Flow

#### Step 1: Create Stadium Elements (5000 seats + concourses + exits)

```php
// Generate stadium seating grid
$elements = [];
$sectionConfigs = [
    ['zone_id' => 1, 'rows' => 30, 'seats_per_row' => 25, 'prefix' => 'A'],  // VIP Lower
    ['zone_id' => 2, 'rows' => 25, 'seats_per_row' => 28, 'prefix' => 'B'],  // VIP Mid
    ['zone_id' => 3, 'rows' => 20, 'seats_per_row' => 22, 'prefix' => 'C'],  // VIP Upper
    ['zone_id' => 10, 'rows' => 50, 'seats_per_row' => 30, 'prefix' => '1'], // Sec 100
    ['zone_id' => 11, 'rows' => 45, 'seats_per_row' => 28, 'prefix' => '2'], // Sec 200
    ['zone_id' => 12, 'rows' => 40, 'seats_per_row' => 25, 'prefix' => '3'], // Sec 300
    ['zone_id' => 13, 'rows' => 60, 'seats_per_row' => 20, 'prefix' => 'B'), // Bleacher
];

foreach ($sectionConfigs as $config) {
    for ($row = 0; $row < $config['rows']; $row++) {
        for ($seat = 0; $seat < $config['seats_per_row']; $seat++) {
            $elements[] = [
                'template_id' => 1,
                'element_type' => 'seat',
                'x' => 50 + $seat * 25,
                'y' => 50 + $row * 22,
                'width' => 20,
                'height' => 18,
                'z_index' => 10,
                'zone_id' => $config['zone_id'],
                'data_json' => json_encode([
                    'label' => $config['prefix'] . ($row + 1) . '-' . ($seat + 1),
                    'row' => $config['prefix'] . ($row + 1),
                    'seat_number' => $seat + 1,
                    'seat_type' => $config['zone_id'] <= 3 ? 'vip' : 'standard',
                ]),
                'style_json' => json_encode([
                    'fill' => $config['zone_id'] <= 3 ? '#ffd700' : '#3b82f6',
                    'stroke' => '#ffffff',
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
    }
}
```

#### Step 2: Create Aisles and Concourses

```php
// Aisles between sections
$aisleElements = [
    // Main concourse
    [
        'template_id' => 1,
        'element_type' => 'aisle',
        'x' => 0,
        'y' => 350,
        'width' => 1200,
        'height' => 20,
        'data_json' => json_encode(['label' => 'Main Concourse']),
        'style_json' => json_encode(['fill' => '#64748b']),
        'is_active' => true,
    ],
    // Entry/exit points
    [
        'template_id' => 1,
        'element_type' => 'entrance',
        'x' => 550,
        'y' => 750,
        'width' => 100,
        'height' => 30,
        'data_json' => json_encode(['label' => 'Main Entrance']),
        'style_json' => json_encode(['fill' => '#ef4444']),
        'is_active' => true,
    ],
];
```

#### Step 3: Create Event with Capacity Tracking

```sql
-- Event with calculated capacity
INSERT INTO events (
    template_id, title, start_at, end_at, 
    booking_open_at, booking_close_at, status,
    total_capacity, available_capacity, sold_count, base_price
) VALUES (
    1, 'Concert: Rock Band Final Tour',
    '2026-08-15 19:00:00', '2026-08-15 22:30:00',
    NOW(), '2026-08-14 19:00:00', 'published',
    5000, 5000, 0, 50.00
);
```

#### Step 4: Booking Flow with Lock Pattern

```
User selects seat A-15
       │
       ▼
┌─────────────────────────────────────┐
│ Check if seat is available:         │
│ - Not in booking_items (status=booked)│
│ - Not in element_locks (active)     │
└─────────────────┬───────────────────┘
                  │
         ┌────────▼────────┐
         │   Available     │
         └────────┬────────┘
                  │
                  ▼
┌─────────────────────────────────────┐
│ INSERT INTO element_locks:          │
│ - event_element_id: 12345           │
│ - lock_key: "user-session-abc123"   │
│ - expires_at: NOW() + 15 minutes    │
│ - UNIQUE constraint on element_id   │
└─────────────────┬───────────────────┘
                  │
                  ▼
┌─────────────────────────────────────┐
│ Show checkout form:                 │
│ - Seat: A-15                        │
│ - Zone: VIP Lower ($150)            │
│ - Total: $165 ($150 + $15 service)  │
└─────────────────┬───────────────────┘
                  │
         ┌────────▼────────┐
         │ User confirms   │
         └────────┬────────┘
                  │
                  ▼
┌─────────────────────────────────────┐
│ TRANSACTION:                          │
│ 1. INSERT INTO bookings:              │
│    - booking_reference: "BK-X9K2M4"   │
│    - customer_name, email, etc.       │
│    - total_amount: 165.00             │
│                                       │
│ 2. INSERT INTO booking_items:         │
│    - booking_id: [new_id]             │
│    - event_element_id: 12345          │
│    - unit_price: 150.00               │
│    - total_price: 165.00              │
│    - UNIQUE constraint prevents dup   │
│                                       │
│ 3. DELETE FROM element_locks          │
│    WHERE event_element_id = 12345     │
│                                       │
│ COMMIT                              │
└─────────────────────────────────────┘
```

#### Step 5: Capacity Update After Booking

```php
// updateCapacityCounts() method
public function updateCapacityCounts(): void
{
    $bookableCount = $this->eventElements()
        ->where('is_bookable', true)
        ->count();  // Returns 5000

    $bookedCount = $this->eventElements()
        ->where('is_bookable', true)
        ->whereExists(function ($query): void {
            $query->select(DB::raw(1))
                ->from('booking_items')
                ->whereColumn('booking_items.event_element_id', 'event_elements.id')
                ->where('booking_items.status', 'booked');
        })
        ->count();  // Returns 1 after first booking

    $this->total_capacity     = 5000;
    $this->available_capacity = 4999;  // 5000 - 1
    $this->sold_count         = 1;
    $this->save();
}
```

#### Step 6: Lock Cleanup (Scheduled Command)

```php
// CleanupExpiredLocks command
class CleanupExpiredLocks extends Command
{
    public function handle(): int
    {
        $deleted = ElementLock::where('expires_at', '<', now())->delete();
      
        $this->info("Deleted {$deleted} expired locks");
      
        return self::SUCCESS;
    }
}

// Scheduled to run every 5 minutes
$schedule->command('locks:cleanup')->everyFiveMinutes();
```

### Stadium-Specific Indexes

```php
// Performance indexes for stadium queries
Schema::table('event_elements', function (Blueprint $table) {
    // Zone-based pricing lookups
    $table->index(['event_id', 'zone_id']);
  
    // Seat type filtering (VIP vs standard)
    $table->index(['event_id', 'element_type']);
  
    // Bulk capacity queries
    $table->index(['event_id', 'is_bookable', 'element_type']);
});

Schema::table('booking_items', function (Blueprint $table) {
    // Revenue reporting by zone
    $table->index(['event_id', 'total_price']);
});
```

### Real-World Query Examples

```sql
-- Find all available VIP seats for an event
SELECT ee.id, ee.x, ee.y, ee.data_json
FROM event_elements ee
WHERE ee.event_id = 1
  AND ee.zone_id IN (1, 2, 3)  -- VIP zones
  AND ee.is_bookable = 1
  AND NOT EXISTS (
    SELECT 1 FROM booking_items bi
    WHERE bi.event_element_id = ee.id
      AND bi.status = 'booked'
  )
  AND NOT EXISTS (
    SELECT 1 FROM element_locks el
    WHERE el.event_element_id = ee.id
      AND el.expires_at > NOW()
  );

-- Calculate total revenue by section
SELECT 
    tz.name as section,
    COUNT(bi.id) as tickets_sold,
    SUM(bi.total_price) as revenue
FROM booking_items bi
JOIN event_elements ee ON bi.event_element_id = ee.id
JOIN template_zones tz ON ee.zone_id = tz.id
WHERE bi.event_id = 1
  AND bi.status = 'booked'
GROUP BY tz.id, tz.name
ORDER BY tz.priority;
```

---

## Best Practices & Common Pitfalls

### Pro-Tips

#### 1. Version Control for Migrations

```bash
# Always use descriptive, timestamped filenames
php artisan make:migration add_vertical_dimensions_to_template_elements

# Good: 2024_01_01_000001_create_venues_table.php
# Bad:  create_table.php
```

**Commit Strategy:**

```
feat(db): add maritime geospatial columns to venue_templates
- Add scale_factor, units, origin_offset_x/y columns
- Include down() method for rollback
```

#### 2. Handling Schema Changes

| Change Type   | Approach                                           |
| ------------- | -------------------------------------------------- |
| New table     | `Schema::create()` in new migration              |
| Add column    | `Schema::table()` with `addColumn()`           |
| Remove column | `dropColumn()` in dedicated migration            |
| Rename column | `renameColumn()` (SQLite requires doctrine/dbal) |
| Index         | Add in same migration as column                    |

#### 3. Avoiding Data Loss

```php
// ❌ DANGEROUS — No data backup
Schema::table('venues', function (Blueprint $table) {
    $table->dropColumn('description');  // Data lost forever
});

// ✅ SAFE — Preserve data if needed
Schema::table('venues', function (Blueprint $table) {
    $table->text('legacy_description')->nullable();  // New column
    $table->dropColumn('description');               // Old column
});
```

#### 4. Foreign Key Constraints

```php
// Always specify onDelete behavior
$table->foreignId('venue_id')
    ->constrained()
    ->cascadeOnDelete();  // Children deleted when parent removed

// For optional relationships
$table->foreignId('parent_id')
    ->nullable()
    ->constrained('template_elements')
    ->nullOnDelete();  // Set to null instead of delete
```

#### 5. Index Strategy

```php
// Composite indexes for multi-column WHERE clauses
$table->index(['event_id', 'is_bookable']);  // Capacity queries
$table->index(['status', 'expires_at']);     // Cleanup queries

// Don't over-index — each index slows INSERT/UPDATE/DELETE
```

### Common Pitfalls

| Mistake                                 | Consequence                        | Solution                          |
| --------------------------------------- | ---------------------------------- | --------------------------------- |
| **Missing `$fillable`**         | Mass assignment exceptions         | Define all assignable columns     |
| **Forgetting casts**              | String '1' instead of boolean true | Add `'is_active' => 'boolean'`  |
| **No indexes on FKs**             | Slow relationship queries          | Laravel doesn't auto-index        |
| **N+1 in loops**                  | 1000+ queries for 1000 items       | Use `withBookingStatus()` scope |
| **Changing snapshots**            | Booking data inconsistency         | EventElement is immutable         |
| **Nullable FKs without behavior** | Orphaned records                   | Specify `nullOnDelete()`        |

### Testing Migrations

```bash
# Test migration on fresh database
php artisan migrate:fresh --seed

# Verify table structure
php artisan schema:dump --prune

# Run specific migration
php artisan migrate --path=database/migrations/2024_01_01_000001_create_venues_table.php

# Test rollback
php artisan migrate:rollback --step=1
```

---

## Quick Reference

### Artisan Commands

| Command              | Purpose                    |
| -------------------- | -------------------------- |
| `make:model`       | Create new model           |
| `make:migration`   | Create new migration       |
| `migrate`          | Run pending migrations     |
| `migrate:fresh`    | Drop all tables and re-run |
| `migrate:rollback` | Reverse last migration     |
| `migrate:refresh`  | Restart migrations         |
| `tinker`           | Interactive PHP shell      |

### Model Relationship Methods

| Method               | Use Case               |
| -------------------- | ---------------------- |
| `belongsTo()`      | Many-to-one (inverted) |
| `hasMany()`        | One-to-many            |
| `hasOne()`         | One-to-one             |
| `belongsToMany()`  | Many-to-many           |
| `hasManyThrough()` | Nested relationships   |

---

## Conclusion

This documentation provides a foundation for understanding the Seat-Map application's data architecture. The combination of well-structured models, carefully designed migrations, and performance-conscious patterns ensures data integrity while enabling rapid feature development.

For questions or clarifications, consult the team lead or review the inline code documentation in the respective model classes.

# SeatMap Models — Complete Reference

## Overview

This project is a **seat booking system** for venues (stadiums, theaters, cruise ships). The core idea is a **snapshot pattern**: venue templates are designed once, then copied into events when published. Bookings reference the event's snapshot, not the template directly.

---

## Entity Relationship Diagram

```
┌──────────┐       ┌──────────┐       ┌──────────┐
│   User   │       │  Venue   │       │ Pricing  │
│          │       │          │       │  Rule    │
└────┬─────┘       └────┬─────┘       └────┬─────┘
     │ 1        N       │                  │
     ├──────────────────┤                  │ belongs to
     │                  │ 1        N       │ (template, zone)
     │           ┌──────┴──────┐           │
     │           │   Venue     │           │
     │           │  Template   │◀──────────┘
     │           └──────┬──────┘
     │                  │ 1        N
     │           ┌──────┴──────┐
     │           │  Template   │
     │           │  Element    │◀──┐ (self-ref: parent/children)
     │           └──────┬──────┘   │
     │                  │ N     N  │
     │           ┌──────┴──────┐   │
     │           │  Template   │   │
     │           │    Zone     │   │
     │           └─────────────┘   │
     │                             │
     │           ┌─────────────┐   │
     │           │    Event    │   │
     │           │  (snapshot) │   │
     │           └──────┬──────┘   │
     │                  │ 1     N  │
     │           ┌──────┴──────┐   │
     │           │   Event     │   │
     │           │  Element    │───┘ (copied from TemplateElement)
     │           └─────────────┘
     │
     │ 1        N
┌────┴─────┐       ┌──────────┐       ┌──────────┐
│ Booking  │──────▶│ Booking  │──────▶│  Event   │
│          │  1  N │  Item    │  N  1 │  Element │
└────┬─────┘       └──────────┘       └──────────┘
     │
     │ (has many)
┌────┴─────┐
│ Element  │
│  Lock    │
└──────────┘
```

---

## Models

### 1. User

Standard Laravel user model. Can make bookings.

```php
// Table: users
// Traits: HasFactory, Notifiable

// Relationships
$user->bookings;  // HasMany → Booking

// Example
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => bcrypt('secret'),
]);
```

---

### 2. Venue

A physical location (stadium, theater, ship).

```php
// Table: venues
// Traits: SoftDeletes
// Auto-generates: slug from name

// Fillable
name, slug, description, venue_type, default_width, default_height,
metadata, is_active

// Relationships
$venue->templates;        // HasMany → VenueTemplate
$venue->defaultTemplate;  // ?VenueTemplate (where is_default = true)

// Example
$venue = Venue::create([
    'name' => 'Royal Stadium',
    'venue_type' => 'stadium',
    'default_width' => 1200,
    'default_height' => 800,
    'metadata' => ['country' => 'UK', 'city' => 'London'],
]);
```

---

### 3. VenueTemplate

A reusable seating layout for a venue. This is the **design-time** model — admins create templates, add elements (seats), and define zones.

```php
// Table: venue_templates
// Traits: SoftDeletes
// Auto-generates: slug from name

// Fillable
venue_id, name, slug, description, canvas_width, canvas_height,
background_image, background_color, grid_size, show_grid, settings,
is_default, is_active, scale_factor, units, origin_offset_x,
origin_offset_y, rotation_degrees

// Relationships
$template->venue;          // BelongsTo → Venue
$template->elements;       // HasMany → TemplateElement
$template->rootElements;   // HasMany → TemplateElement (parent_id = null)
$template->zones;          // HasMany → TemplateZone

// Key Methods
$template->getElementsTree();              // Returns nested tree with children
$template->toMeters(100);                  // Canvas units → real meters
$template->marineComplianceCheck();        // IMO/ADA compliance validation
$template->getBookableElementsWithMetrics(); // Seats with real-world dimensions

// Example
$template = VenueTemplate::create([
    'venue_id' => $venue->id,
    'name' => 'Concert Layout A',
    'canvas_width' => 1200,
    'canvas_height' => 800,
    'scale_factor' => 0.05,  // 1 canvas unit = 0.05 meters
    'units' => 'meters',
    'is_default' => true,
]);

// Add seats to template
$template->elements()->create([
    'element_type' => 'seat',
    'x' => 100, 'y' => 200,
    'width' => 8, 'height' => 8,
    'z_index' => 1,
    'is_bookable' => true,
    'data_json' => ['label' => 'A1', 'row' => 'A', 'seat_number' => '1'],
]);

// Add a zone
$template->zones()->create([
    'name' => 'VIP Section',
    'code' => 'VIP',
    'color' => '#FFD700',
    'base_price' => 150.00,
    'service_fee' => 10.00,
    'capacity' => 100,
]);

// Maritime compliance check (for cruise ships)
$result = $template->marineComplianceCheck();
// Returns: { valid: bool, violations: [], summary: [], scale_factor, units }
```

---

### 4. TemplateElement

Individual seats, tables, or standing areas within a template. Supports **nested hierarchies** (parent/child).

```php
// Table: template_elements
// Traits: SoftDeletes

// Fillable
template_id, element_type, x, y, z, width, height, vertical_clearance,
rotation, z_index, parent_id, data_json, style_json, sort_order, is_active

// Relationships
$element->template;  // BelongsTo → VenueTemplate
$element->parent;    // BelongsTo → TemplateElement (self-ref)
$element->children;  // HasMany → TemplateElement (self-ref)
$element->zones;     // BelongsToMany → TemplateZone (pivot: price_modifier, modifier_type)

// Scopes
TemplateElement::query()->active();  // where is_active = true

// Accessors
$element->label;       // data_json['label']
$element->seat_row;    // data_json['row']
$element->seat_number; // data_json['seat_number']

// Key Methods
$element->isBookable();  // true if type is seat/table/standing_zone
$element->toEventElement(1, 5);  // Convert to EventElement array for snapshot

// Example: Create a seat group with children
$group = TemplateElement::create([
    'template_id' => $template->id,
    'element_type' => 'group',
    'x' => 100, 'y' => 100,
    'width' => 200, 'height' => 200,
    'data_json' => ['label' => 'Section A'],
]);

$seat = TemplateElement::create([
    'template_id' => $template->id,
    'element_type' => 'seat',
    'parent_id' => $group->id,
    'x' => 110, 'y' => 110,
    'width' => 8, 'height' => 8,
    'is_bookable' => true,
    'data_json' => ['label' => 'A1', 'row' => 'A', 'seat_number' => '1'],
]);
```

---

### 5. TemplateZone

Pricing/section zones (VIP, General Admission, etc.). Elements can belong to multiple zones with different price modifiers.

```php
// Table: template_zones
// Traits: SoftDeletes

// Fillable
template_id, name, code, description, color, priority, base_price,
service_fee, capacity, max_booking_per_order, settings, is_active

// Relationships
$zone->template;  // BelongsTo → VenueTemplate
$zone->elements;  // BelongsToMany → TemplateElement (pivot: price_modifier, modifier_type)

// Scopes
TemplateZone::query()->active();  // where is_active = true

// Key Methods
$zone->calculateFinalPrice(50.00, 10.00, 'percent');
// Base: 50 + Zone: 100 + Modifier: 5 (10%) + Fee: 10 = 165.00

$zone->syncCapacity();  // Updates capacity to match actual element count

// Example
$zone = TemplateZone::create([
    'template_id' => $template->id,
    'name' => 'VIP',
    'code' => 'VIP',
    'color' => '#FFD700',
    'base_price' => 150.00,
    'service_fee' => 15.00,
    'capacity' => 50,
    'max_booking_per_order' => 4,
]);

// Attach elements with price modifiers
$zone->elements()->attach($seat->id, [
    'price_modifier' => 25.00,
    'modifier_type' => 'fixed',  // or 'percent'
]);
```

---

### 6. Event

A published instance of a template. When published, all template elements are **snapshotted** into `event_elements`.

```php
// Table: events
// Traits: SoftDeletes
// Auto-generates: slug from title

// Fillable
template_id, title, slug, description, start_at, end_at,
booking_open_at, booking_close_at, status, snapshotted_at,
snapshot_version, total_capacity, available_capacity, sold_count,
base_price, metadata

// Relationships
$event->template;       // BelongsTo → VenueTemplate
$event->eventElements;  // HasMany → EventElement
$event->bookings;       // HasMany → Booking

// Key Methods
$event->isBookingOpen();  // published + within booking window
$event->publish();        // Creates snapshot + sets status to published
$event->updateCapacityCounts();  // Recalculates from booking_items

// Example
$event = Event::create([
    'template_id' => $template->id,
    'title' => 'Taylor Swift - Eras Tour',
    'start_at' => '2026-07-15 20:00:00',
    'end_at' => '2026-07-15 23:00:00',
    'booking_open_at' => '2026-05-01 10:00:00',
    'booking_close_at' => '2026-07-14 23:59:59',
    'status' => 'draft',
]);

// Publish — this snapshots all template elements into event_elements
$event->publish();

// Check if booking is open
if ($event->isBookingOpen()) {
    // Allow bookings
}

// Get real-time capacity
echo $event->available_capacity;  // e.g., 450
echo $event->sold_count;          // e.g., 50
```

---

### 7. EventElement

A **snapshot copy** of a `TemplateElement`. This is what bookings reference. The `booking_status` is computed at runtime — NOT stored in the database.

```php
// Table: event_elements
// Traits: SoftDeletes

// Fillable
event_id, template_element_id, element_type, x, y, width, height,
rotation, z_index, parent_id, data_json, style_json, is_bookable,
zone_id, booked_price

// Relationships
$element->event;  // BelongsTo → Event
$element->zone;   // BelongsTo → TemplateZone

// Scopes
EventElement::query()->withBookingStatus();  // Adds computed booking_status column

// Key Methods
$element->isAvailable();  // is_bookable AND booking_status = 'available'
$element->getLabel();     // data_json['label']

// Batch hydration (prevents N+1 queries)
EventElement::hydrateBookingStatus($collection);  // 2 queries for any number of elements

// Example: Get all available seats for an event
$availableSeats = EventElement::where('event_id', 1)
    ->where('is_bookable', true)
    ->withBookingStatus()
    ->get()
    ->filter(fn ($el) => $el->booking_status === 'available');

// Example: Check single seat availability
$seat = EventElement::where('event_id', 1)
    ->withBookingStatus()
    ->find(101);

if ($seat->isAvailable()) {
    // Seat is free to book
}
```

**booking_status values:**

| Status | Meaning |
|--------|---------|
| `available` | Not booked, not locked |
| `booked` | In a booking (may still be locked for payment) |
| `locked` | Temporarily reserved (before booking created) |

---

### 8. Booking

A user's reservation. Goes through states: `locked` → `confirmed` or `cancelled`.

```php
// Table: bookings
// Traits: SoftDeletes
// Auto-generates: booking_reference (BK-XXXXXXXX)

// Fillable
booking_reference, internal_reference, event_id, user_id, customer_name,
customer_email, customer_phone, subtotal, service_fee, tax_amount,
total_amount, currency, status, locked_at, confirmed_at, completed_at,
cancelled_at, expires_at, payment_intent_id, payment_provider, metadata

// Relationships
$booking->event;  // BelongsTo → Event
$booking->user;   // BelongsTo → User
$booking->items;  // HasMany → BookingItem

// Key Methods
$booking->isExpired();  // expires_at is past
$booking->confirm();    // locked → confirmed
$booking->cancel();     // any active → cancelled

// Example: Create a booking
$booking = Booking::create([
    'event_id' => 1,
    'user_id' => $user->id,
    'customer_name' => 'John Doe',
    'customer_email' => 'john@example.com',
    'subtotal' => 300.00,
    'service_fee' => 30.00,
    'tax_amount' => 33.00,
    'total_amount' => 363.00,
    'currency' => 'USD',
    'status' => 'locked',
    'locked_at' => now(),
    'expires_at' => now()->addMinutes(10),  // 10-min hold
]);

// After payment succeeds
$booking->confirm();
// Status: locked → confirmed
// Deletes related ElementLocks
// Updates Event capacity counts

// If payment fails or user cancels
$booking->cancel();
// Status: locked → cancelled
// Deletes related ElementLocks
// Updates Event capacity counts
```

**Booking lifecycle:**

```
User selects seats
       │
       ▼
  ┌─────────┐
  │ LOCKED  │ ← ElementLocks created, 10-min timer
  └────┬────┘
       │
       ├── Payment success ──▶ CONFIRMED ──▶ Locks deleted
       │
       ├── Payment fail ─────▶ CANCELLED ──▶ Locks deleted
       │
       └── Timer expires ────▶ CANCELLED ──▶ Locks deleted (via scheduler)
```

---

### 9. BookingItem

Individual seats within a booking. Each booking has many items.

```php
// Table: booking_items
// No SoftDeletes (deleted via cascade)

// Fillable
booking_id, event_element_id, event_id, element_type, label,
unit_price, total_price, quantity, status

// Relationships
$item->booking;      // BelongsTo → Booking
$item->event;        // BelongsTo → Event
$item->eventElement; // BelongsTo → EventElement

// Scopes
BookingItem::query()->booked();     // status = 'booked'
BookingItem::query()->cancelled();  // status = 'cancelled'

// Example
BookingItem::create([
    'booking_id' => $booking->id,
    'event_element_id' => 101,
    'event_id' => 1,
    'element_type' => 'seat',
    'label' => 'A1',
    'unit_price' => 150.00,
    'total_price' => 150.00,
    'quantity' => 1,
    'status' => 'booked',
]);
```

---

### 10. ElementLock

Temporary seat reservation before payment. Prevents double-booking during the booking flow.

```php
// Table: element_locks
// No SoftDeletes (cleaned up by scheduler)

// Fillable
event_element_id, event_id, lock_key, booking_reference, expires_at,
locked_at, ip_address, user_agent, metadata

// Relationships
$lock->event;  // BelongsTo → Event

// Key Methods
$lock->isValid();         // Not expired
$lock->extend(15);        // Add 15 minutes
ElementLock::cleanup();   // Delete all expired locks (called by scheduler)

// Example: Lock seats during checkout
$lock = ElementLock::create([
    'event_element_id' => 101,
    'event_id' => 1,
    'lock_key' => 'uuid-lock-key',
    'expires_at' => now()->addMinutes(10),
    'locked_at' => now(),
    'ip_address' => request()->ip(),
]);

// Check if still valid
if ($lock->isValid()) {
    // Proceed to payment
}

// Extend while user is on payment page
$lock->extend(15);

// Cleanup runs every minute via scheduler
// Schedule::command('locks:cleanup-expired')->everyMinute();
```

---

### 11. PricingRule

Dynamic pricing rules (early bird, group discounts, etc.).

```php
// Table: pricing_rules
// No SoftDeletes

// Fillable
name, code, rule_type, conditions_json, price_adjustment,
adjustment_type, priority, zone_id, template_id, valid_from,
valid_to, is_active

// Relationships
$rule->zone;      // BelongsTo → TemplateZone (optional)
$rule->template;  // BelongsTo → VenueTemplate (optional)

// Example: Early bird discount
PricingRule::create([
    'name' => 'Early Bird 20%',
    'code' => 'EARLY20',
    'rule_type' => 'discount',
    'conditions_json' => ['days_before_event' => 30],
    'price_adjustment' => 20,
    'adjustment_type' => 'percent',
    'priority' => 10,
    'template_id' => $template->id,
    'valid_from' => '2026-05-01',
    'valid_to' => '2026-06-01',
    'is_active' => true,
]);
```

---

## Common Query Patterns

### Get seat map with availability

```php
$event = Event::with('template')->find(1);

$elements = $event->eventElements()
    ->withBookingStatus()
    ->orderBy('z_index')
    ->get();

// Each element now has:
// $element->booking_status  // 'available', 'booked', or 'locked'
// $element->isAvailable()   // true if bookable AND available
```

### Check if seats are free before booking

```php
$seatIds = [101, 102, 103];

$unavailable = EventElement::whereIn('id', $seatIds)
    ->where('event_id', $eventId)
    ->withBookingStatus()
    ->get()
    ->filter(fn ($el) => !$el->isAvailable())
    ->pluck('id');

if ($unavailable->isNotEmpty()) {
    return "Seats {$unavailable->implode(', ')} are no longer available";
}
```

### Get booking details with all related data

```php = Booking::with(['event.template', 'items.eventElement.zone'])
    ->where('booking_reference', 'BK-ABC123')
    ->first();
```

### Get zone pricing for a seat

```php
$element = EventElement::with('zone')->find(101);
$zone = $element->zone;

$finalPrice = $zone->calculateFinalPrice(
    $basePrice = 100.00,
    $pivotModifier = 25.00,
    $pivotModifierType = 'fixed'
);
// Result: 100 + 150 (zone) + 25 (modifier) + 15 (fee) = 290.00
```

---

## Key Design Decisions

### 1. Snapshot Pattern
Templates are **copied** to events on publish. This means:
- Template changes don't affect live events
- Each event has its own immutable seat map
- Bookings always reference the same event elements

### 2. Runtime booking_status
Seat availability is **computed, not stored**. This prevents stale data — no need to update a `status` column on every lock/booking/cancel.

### 3. Batch Hydration
`EventElement::hydrateBookingStatus()` uses exactly **2 queries** regardless of how many elements:
1. Get all booked element IDs from `booking_items`
2. Get all locked element IDs from `element_locks`

### 4. Soft Deletes
Most models use `SoftDeletes` except:
- `BookingItem` — deleted via cascade
- `ElementLock` — temporary data, cleaned by scheduler
- `PricingRule` — no need for recovery

### 5. Maritime Compliance
`VenueTemplate::marineComplianceCheck()` validates IMO/ADA requirements:
- Minimum cabin area
- Aisle width
- Exit clearance zones
- Wheelchair proximity to toilets/muster stations

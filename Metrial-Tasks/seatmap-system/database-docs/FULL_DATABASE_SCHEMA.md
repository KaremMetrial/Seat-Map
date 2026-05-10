# Full Database Schema & Design

## Overview

This schema supports a dynamic 2D seat map booking system handling cinemas, stadiums, theaters, and custom venues. The key innovation is using JSON-based elements instead of fixed rows/seats tables.

---

## Core Tables

### 1. `venues`
Physical locations that host events.

```sql
CREATE TABLE venues (
    id             BIGINT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(255) NOT NULL,
    slug           VARCHAR(255) UNIQUE NOT NULL,
    description    TEXT,
    venue_type     ENUM('cinema', 'stadium', 'theater', 'custom'),
    default_width  INT DEFAULT 1000,
    default_height INT DEFAULT 600,
    metadata       JSON,
    is_active      BOOLEAN DEFAULT TRUE,
    created_at     TIMESTAMP,
    updated_at     TIMESTAMP,
    deleted_at     TIMESTAMP NULL,

    INDEX idx_venue_type (venue_type),
    INDEX idx_is_active  (is_active)
);
```

### 2. `venue_templates`
2D layout definitions with canvas dimensions.

```sql
CREATE TABLE venue_templates (
    id               BIGINT AUTO_INCREMENT PRIMARY KEY,
    venue_id         BIGINT NOT NULL,
    name             VARCHAR(255) NOT NULL,
    slug             VARCHAR(255) NOT NULL,
    description      TEXT,
    canvas_width     INT DEFAULT 1000,
    canvas_height    INT DEFAULT 600,
    background_image VARCHAR(255),
    background_color VARCHAR(7) DEFAULT '#ffffff',
    grid_size        INT DEFAULT 10,
    show_grid        BOOLEAN DEFAULT TRUE,
    settings         JSON,
    is_default       BOOLEAN DEFAULT FALSE,
    is_active        BOOLEAN DEFAULT TRUE,
    created_at       TIMESTAMP,
    updated_at       TIMESTAMP,
    deleted_at       TIMESTAMP NULL,

    FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE,
    UNIQUE KEY uk_venue_slug (venue_id, slug),
    INDEX idx_is_default (is_default)
);
```

### 3. `template_elements`
**Core table: Dynamic building blocks on the 2D canvas.**

```sql
CREATE TABLE template_elements (
    id           BIGINT AUTO_INCREMENT PRIMARY KEY,
    template_id  BIGINT NOT NULL,
    element_type VARCHAR(50) NOT NULL, /* seat, section, table, stage, shape, entrance, text */
    x            DECIMAL(10,2) NOT NULL,
    y            DECIMAL(10,2) NOT NULL,
    width        DECIMAL(10,2) DEFAULT 0,
    height       DECIMAL(10,2) DEFAULT 0,
    rotation     DECIMAL(5,2)  DEFAULT 0,
    z_index      INT           DEFAULT 0,
    parent_id    BIGINT NULL,           /* Hierarchical: section contains seats */
    data_json    JSON,                  /* Dynamic attributes: label, row, capacity, etc. */
    style_json   JSON,                  /* Visual: colors, opacity, border */
    sort_order   INT     DEFAULT 0,
    is_active    BOOLEAN DEFAULT TRUE,
    created_at   TIMESTAMP,
    updated_at   TIMESTAMP,
    deleted_at   TIMESTAMP NULL,

    FOREIGN KEY (template_id) REFERENCES venue_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id)   REFERENCES template_elements(id) ON DELETE SET NULL,
    INDEX idx_template     (template_id),
    INDEX idx_element_type (element_type),
    INDEX idx_parent       (parent_id),
    INDEX idx_z_index      (z_index)
);
```

#### Element Type Examples in `data_json`

**Seat:**
```json
{ "label": "A1", "row": "A", "seat_number": "1", "seat_type": "regular" }
```

**Section (curved):**
```json
{ "label": "VIP Section", "curve": { "radius": 200, "start_angle": -30, "end_angle": 30 } }
```

**Table (multi-seat):**
```json
{ "label": "Table 5", "capacity": 4, "shape": "round" }
```

**Standing Zone:**
```json
{ "label": "General Admission", "capacity": 100, "standing_only": true }
```

### 4. `template_zones`
Pricing/density areas within templates.

```sql
CREATE TABLE template_zones (
    id                    BIGINT AUTO_INCREMENT PRIMARY KEY,
    template_id           BIGINT NOT NULL,
    name                  VARCHAR(255) NOT NULL,
    code                  VARCHAR(50),
    description           TEXT,
    color                 VARCHAR(7) DEFAULT '#3498db',
    priority              INT DEFAULT 0,
    base_price            DECIMAL(10,2) DEFAULT 0,
    service_fee           DECIMAL(10,2) DEFAULT 0,
    capacity              INT DEFAULT 0,  /* 0 = unlimited */
    max_booking_per_order INT DEFAULT 10,
    settings              JSON,
    is_active             BOOLEAN DEFAULT TRUE,
    created_at            TIMESTAMP,
    updated_at            TIMESTAMP,
    deleted_at            TIMESTAMP NULL,

    FOREIGN KEY (template_id) REFERENCES venue_templates(id) ON DELETE CASCADE,
    INDEX idx_template (template_id),
    INDEX idx_code     (code)
);
```

### 5. `element_zone_map`
Many-to-many: elements can belong to multiple zones.

```sql
CREATE TABLE element_zone_map (
    id                  BIGINT AUTO_INCREMENT PRIMARY KEY,
    template_element_id BIGINT NOT NULL,
    template_zone_id    BIGINT NOT NULL,
    price_modifier      DECIMAL(10,2) DEFAULT 0,
    modifier_type       ENUM('fixed', 'percent') DEFAULT 'fixed',
    created_at          TIMESTAMP,

    FOREIGN KEY (template_element_id) REFERENCES template_elements(id) ON DELETE CASCADE,
    FOREIGN KEY (template_zone_id)    REFERENCES template_zones(id)    ON DELETE CASCADE,
    UNIQUE KEY uk_element_zone (template_element_id, template_zone_id)
);
```

### 6. `events`
Specific instances of shows/screenings.

```sql
CREATE TABLE events (
    id                 BIGINT AUTO_INCREMENT PRIMARY KEY,
    template_id        BIGINT NOT NULL,
    title              VARCHAR(255) NOT NULL,
    slug               VARCHAR(255) NOT NULL,
    description        TEXT,
    start_at           DATETIME NOT NULL,
    end_at             DATETIME NOT NULL,
    booking_open_at    DATETIME NULL,
    booking_close_at   DATETIME NULL,
    status             ENUM('draft', 'published', 'cancelled', 'postponed') DEFAULT 'draft',
    snapshotted_at     TIMESTAMP NULL,
    snapshot_version   INT DEFAULT 1,
    total_capacity     INT DEFAULT 0,
    available_capacity INT DEFAULT 0,
    sold_count         INT DEFAULT 0,
    base_price         DECIMAL(10,2) DEFAULT 0,
    metadata           JSON,
    created_at         TIMESTAMP,
    updated_at         TIMESTAMP,
    deleted_at         TIMESTAMP NULL,

    FOREIGN KEY (template_id) REFERENCES venue_templates(id),
    INDEX idx_template     (template_id),
    INDEX idx_status       (status),
    INDEX idx_start_at     (start_at),
    INDEX idx_status_start (status, start_at)
);
```

### 7. `event_elements` — THE SNAPSHOT TABLE

```sql
CREATE TABLE event_elements (
    id                  BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_id            BIGINT NOT NULL,
    template_element_id BIGINT NULL,          /* Audit trail only */
    element_type        VARCHAR(50) NOT NULL,
    x                   DECIMAL(10,2) NOT NULL,
    y                   DECIMAL(10,2) NOT NULL,
    width               DECIMAL(10,2) DEFAULT 0,
    height              DECIMAL(10,2) DEFAULT 0,
    rotation            DECIMAL(5,2)  DEFAULT 0,
    z_index             INT           DEFAULT 0,
    parent_id           BIGINT NULL,
    data_json           JSON,                 /* SNAPSHOTTED AT PUBLISH — NEVER CHANGES */
    style_json          JSON,                 /* SNAPSHOTTED AT PUBLISH — NEVER CHANGES */
    is_bookable         BOOLEAN DEFAULT TRUE,
    zone_id             BIGINT NULL,
    booked_price        DECIMAL(10,2) NULL,
    created_at          TIMESTAMP NOT NULL,   /* Set explicitly — insert() skips auto-timestamps */
    updated_at          TIMESTAMP NOT NULL,   /* Set explicitly — insert() skips auto-timestamps */

    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,

    INDEX idx_event          (event_id),
    INDEX idx_type           (element_type),
    INDEX idx_zone           (zone_id),
    INDEX idx_event_bookable (event_id, is_bookable),
    INDEX idx_event_type     (event_id, element_type)
    /*
     * NO booking_status column.
     *
     * A MySQL GENERATED ALWAYS AS virtual column with correlated subqueries
     * is the natural fit, but Laravel Blueprint cannot express it without raw DDL.
     * Using ->virtual() without a raw expression creates a plain static column
     * that is never updated — wrong.
     *
     * Status is derived at query time via two N+1-safe patterns:
     *   - EventElement::withBookingStatus() scope (CASE/EXISTS in SELECT)
     *   - EventElement::hydrateBookingStatus($collection) (2 bulk queries)
     *
     * See SYSTEM_DESIGN.md section 3 for full details.
     */
);
```

---

## Booking Tables

### 8. `pricing_rules`
Dynamic pricing logic.

```sql
CREATE TABLE pricing_rules (
    id               BIGINT AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(255) NOT NULL,
    code             VARCHAR(50) UNIQUE,
    rule_type        ENUM('early_bird', 'last_minute', 'group', 'zone', 'time_based', 'custom'),
    conditions_json  JSON,
    price_adjustment DECIMAL(10,2) DEFAULT 0,
    adjustment_type  ENUM('fixed', 'percent') DEFAULT 'fixed',
    priority         INT DEFAULT 0,
    zone_id          BIGINT NULL,
    template_id      BIGINT NULL,
    valid_from       DATETIME NULL,
    valid_to         DATETIME NULL,
    is_active        BOOLEAN DEFAULT TRUE,
    created_at       TIMESTAMP,
    updated_at       TIMESTAMP,

    INDEX idx_type     (rule_type),
    INDEX idx_priority (priority),
    INDEX idx_active   (is_active)
);
```

### 9. `bookings`
Order header with status flow.

```sql
CREATE TABLE bookings (
    id                 BIGINT AUTO_INCREMENT PRIMARY KEY,
    booking_reference  VARCHAR(50) UNIQUE NOT NULL,
    internal_reference VARCHAR(100) NULL,
    event_id           BIGINT NOT NULL,
    user_id            BIGINT NULL,
    customer_name      VARCHAR(255) NOT NULL,
    customer_email     VARCHAR(255) NOT NULL,
    customer_phone     VARCHAR(50)  NULL,
    subtotal           DECIMAL(12,2) DEFAULT 0,
    service_fee        DECIMAL(12,2) DEFAULT 0,
    tax_amount         DECIMAL(12,2) DEFAULT 0,
    total_amount       DECIMAL(12,2) DEFAULT 0,
    currency           CHAR(3) DEFAULT 'USD',
    status             ENUM('pending','locked','confirmed','completed','cancelled','expired') DEFAULT 'pending',
    locked_at          TIMESTAMP NULL,
    confirmed_at       TIMESTAMP NULL,
    completed_at       TIMESTAMP NULL,
    cancelled_at       TIMESTAMP NULL,
    expires_at         TIMESTAMP NULL,
    payment_intent_id  VARCHAR(255) NULL,
    payment_provider   VARCHAR(50)  NULL,
    metadata           JSON,
    created_at         TIMESTAMP,
    updated_at         TIMESTAMP,
    deleted_at         TIMESTAMP NULL,

    INDEX idx_event     (event_id),
    INDEX idx_user      (user_id),
    INDEX idx_status    (status),
    INDEX idx_reference (booking_reference),
    INDEX idx_expires   (status, expires_at)
);
```

### 10. `booking_items`
**Critical: Double-booking prevention via unique constraint.**

```sql
CREATE TABLE booking_items (
    id               BIGINT AUTO_INCREMENT PRIMARY KEY,
    booking_id       BIGINT NOT NULL,
    event_element_id BIGINT NOT NULL,
    event_id         BIGINT NOT NULL,
    element_type     VARCHAR(50)   NOT NULL,
    label            VARCHAR(50)   NULL,
    unit_price       DECIMAL(10,2) NOT NULL,
    total_price      DECIMAL(10,2) NOT NULL,
    quantity         INT DEFAULT 1,
    status           ENUM('booked', 'cancelled') DEFAULT 'booked',
    created_at       TIMESTAMP,
    updated_at       TIMESTAMP,

    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id)   REFERENCES events(id),

    /* PREVENTS DOUBLE BOOKING:
       Only one row with status='booked' can exist per event_element_id.
       Cancelling sets status='cancelled', which frees the slot. */
    UNIQUE KEY unique_booked_element (event_element_id, status),

    INDEX idx_booking (booking_id),
    INDEX idx_element (event_element_id)
);
```

### 11. `element_locks`
Temporary holds during the booking process.

```sql
CREATE TABLE element_locks (
    id                BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_element_id  BIGINT        NOT NULL,
    event_id          BIGINT        NOT NULL,
    lock_key          VARCHAR(255)  NOT NULL,  /* NOT NULL — always required */
    booking_reference VARCHAR(50)   NULL,
    expires_at        TIMESTAMP     NOT NULL,
    locked_at         TIMESTAMP     NOT NULL,
    ip_address        VARCHAR(45)   NULL,
    user_agent        TEXT          NULL,
    metadata          JSON          NULL,
    created_at        TIMESTAMP,
    updated_at        TIMESTAMP,

    FOREIGN KEY (event_id) REFERENCES events(id),

    /* ONE active lock per element — the core double-booking guard.
     *
     * WHY NOT UNIQUE (event_element_id, expires_at)?
     * expires_at changes on every insert, so the composite key would allow
     * multiple active locks on the same element — defeating its purpose.
     *
     * SeatLockService deletes any expired row for an element inside the same
     * DB transaction before inserting the new lock, so this constraint is
     * never violated by legitimate re-locking after TTL expiry.
     */
    UNIQUE KEY unique_element_lock (event_element_id),

    INDEX idx_event_id        (event_id),
    INDEX idx_lock_key        (lock_key),
    INDEX idx_expires         (expires_at),
    INDEX idx_element_expires (event_element_id, expires_at)
);
```

---

## Indexing Strategy Summary

```sql
-- Fast seatmap fetch + capacity count
CREATE INDEX idx_event_bookable ON event_elements (event_id, is_bookable);

-- Fast booking expiration cleanup
CREATE INDEX idx_locks_cleanup ON element_locks (expires_at);

-- Booking status pagination
CREATE INDEX idx_bookings_status_expires ON bookings (status, expires_at);
```

---

## Why JSON Instead of Fixed Tables?

| Approach | Pros | Cons |
|----------|------|------|
| Fixed Tables (rows/seats) | Faster queries, strict validation | Cannot handle curved seating, tables, custom venues |
| JSON Elements | Infinite flexibility, any layout, extensible | Slower queries (mitigated by caching), no FK integrity on JSON fields |

**Decision: JSON wins** because:
1. This is a design/templating system — writes are infrequent
2. Cache the element tree for read-heavy seatmap display
3. Flexibility to handle any venue type without schema changes
4. Status is derived at query time via efficient batch patterns — no generated column needed

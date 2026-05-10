# Seatmap System - Setup Guide

## Quick Start with Laravel Sail

### 1. Start Sail Containers

```bash
cd Api
./vendor/bin/sail up -d
```

Or if you have Sail installed globally:
```bash
sail up -d
```

### 2. Install Dependencies

```bash
./vendor/bin/sail composer install
```

### 3. Run Migrations & Seed Test Data

```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

This will create:
- 1 Venue (Grand Theater)
- 1 Template (Main Hall Layout)
- 200 Seats (10 rows × 20 seats)
- 2 Zones (VIP & Standard)
- 1 Event (Summer Concert 2026 - draft status)

### 4. Access the Application

- **API**: http://localhost:8080/api/v1/
- **Test Interface**: http://localhost:8080/seatmap-test

---

## API Endpoints

### Venues
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/venues` | List all venues |
| POST | `/api/v1/venues` | Create venue |
| GET | `/api/v1/venues/{id}` | Get venue details |
| PUT | `/api/v1/venues/{id}` | Update venue |
| DELETE | `/api/v1/venues/{id}` | Delete venue |

### Templates
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/venues/{venue}/templates` | List venue templates |
| POST | `/api/v1/venues/{venue}/templates` | Create template |
| GET | `/api/v1/templates/{id}` | Get template with elements |
| PUT | `/api/v1/templates/{id}` | Update template |
| DELETE | `/api/v1/templates/{id}` | Delete template |
| POST | `/api/v1/templates/{id}/duplicate` | Duplicate template |

### Elements
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/templates/{template}/elements` | List all elements |
| POST | `/api/v1/templates/{template}/elements` | Create single element |
| POST | `/api/v1/templates/{template}/elements/bulk` | Bulk create elements |
| POST | `/api/v1/templates/{template}/elements/generate-seats` | Generate seat grid |
| GET | `/api/v1/elements/{id}` | Get element details |
| PUT | `/api/v1/elements/{id}` | Update element |
| DELETE | `/api/v1/elements/{id}` | Delete element |
| PUT | `/api/v1/elements/bulk-update` | Bulk update elements |
| POST | `/api/v1/elements/bulk-delete` | Bulk delete elements |

### Zones
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/templates/{template}/zones` | List zones |
| POST | `/api/v1/templates/{template}/zones` | Create zone |
| POST | `/api/v1/templates/{template}/zones/create-defaults` | Create default zones |
| GET | `/api/v1/zones/{id}` | Get zone details |
| PUT | `/api/v1/zones/{id}` | Update zone |
| DELETE | `/api/v1/zones/{id}` | Delete zone |
| POST | `/api/v1/zones/{id}/assign-elements` | Assign elements to zone |
| GET | `/api/v1/zones/{id}/elements` | Get zone elements |

### Events
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/events` | List all events |
| POST | `/api/v1/events` | Create event |
| GET | `/api/v1/events/{id}` | Get event details |
| PUT | `/api/v1/events/{id}` | Update event |
| POST | `/api/v1/events/{id}/publish` | Publish event (creates snapshot) |
| DELETE | `/api/v1/events/{id}` | Delete event |

### Seatmap
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/events/{event}/seatmap` | Get event seatmap with status |
| GET | `/api/v1/events/{event}/available` | Get available seats |

### Bookings
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/bookings/lock` | Lock seats (10 min TTL) |
| POST | `/api/v1/bookings` | Create booking |
| GET | `/api/v1/bookings/{ref}` | Get booking details |
| POST | `/api/v1/bookings/{ref}/confirm` | Confirm booking |
| DELETE | `/api/v1/bookings/{ref}` | Cancel booking |

---

## Example API Calls

### Create a Venue
```bash
curl -X POST http://localhost:8080/api/v1/venues \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Concert Hall",
    "venue_type": "theater",
    "default_width": 1000,
    "default_height": 800
  }'
```

### Create a Template
```bash
curl -X POST http://localhost:8080/api/v1/venues/1/templates \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Main Hall",
    "canvas_width": 1000,
    "canvas_height": 800,
    "scale_factor": 0.05
  }'
```

### Generate Seats
```bash
curl -X POST http://localhost:8080/api/v1/templates/1/elements/generate-seats \
  -H "Content-Type: application/json" \
  -d '{
    "start_x": 50,
    "start_y": 50,
    "rows": 10,
    "seats_per_row": 20,
    "seat_width": 30,
    "seat_height": 25,
    "gap_x": 5,
    "gap_y": 5,
    "seat_type": "regular"
  }'
```

### Create an Event
```bash
curl -X POST http://localhost:8080/api/v1/events \
  -H "Content-Type: application/json" \
  -d '{
    "template_id": 1,
    "title": "Rock Concert 2026",
    "start_at": "2026-06-15T19:00:00",
    "end_at": "2026-06-15T23:00:00",
    "base_price": 75.00
  }'
```

### Publish Event
```bash
curl -X POST http://localhost:8080/api/v1/events/1/publish
```

### Get Seatmap
```bash
curl http://localhost:8080/api/v1/events/1/seatmap
```

### Lock Seats
```bash
curl -X POST http://localhost:8080/api/v1/bookings/lock \
  -H "Content-Type: application/json" \
  -d '{
    "event_id": 1,
    "element_ids": [1, 2, 3]
  }'
```

### Create Booking
```bash
curl -X POST http://localhost:8080/api/v1/bookings \
  -H "Content-Type: application/json" \
  -d '{
    "event_id": 1,
    "element_ids": [1, 2, 3],
    "customer_name": "John Doe",
    "customer_email": "john@example.com"
  }'
```

---

## Testing with Frontend Interface

1. Open http://localhost:8080/seatmap-test
2. Create a venue or select existing one
3. Create a template for the venue
4. Generate seats or add individual elements
5. Create zones for pricing
6. Create an event from the template
7. Publish the event
8. Select seats and make a booking

---

## Database Structure

```
venues
├── venue_templates
│   ├── template_elements
│   ├── template_zones
│   └── element_zone_map (pivot)
├── events
│   ├── event_elements (snapshot)
│   ├── bookings
│   │   └── booking_items
│   └── element_locks
└── pricing_rules
```

---

## Troubleshooting

### Sail Not Starting
```bash
./vendor/bin/sail down -v
./vendor/bin/sail up -d
```

### Reset Database
```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

### Clear Cache
```bash
./vendor/bin/sail artisan optimize:clear
```

### View Logs
```bash
./vendor/bin/sail artisan pail
```

---

## Architecture Highlights

### Concurrency Safety
- Database transactions with `SELECT FOR UPDATE`
- N+1 prevention with batch hydration
- TTL-based seat locking (10 min default)

### Data Integrity
- Immutable event element snapshots
- Soft deletes for audit trail
- JSON schema validation for element data

### Performance
- Bulk insert for seat generation
- Viewport culling for large venues
- Eager loading relationships

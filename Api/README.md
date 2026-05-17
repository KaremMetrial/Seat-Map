# Seat-Map API Documentation

**Laravel Seat Booking API**

## Overview

This is the backend API for the Seat-Map venue seat mapping and booking platform. It provides endpoints for managing venues, templates, events, and bookings.

## Quick Start

```bash
# Install dependencies
composer install

# Setup environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate:fresh --seed

# Start development server
php artisan serve
```

## Database Documentation

Complete data models and migrations documentation is available in the [root README.md](../../README.md).

### Key Entities

| Entity | Description |
|--------|-------------|
| **Venues** | Physical locations (theaters, stadiums, custom venues) |
| **Templates** | Reusable seat map layouts |
| **Elements** | Seats, stages, entrances (visual components) |
| **Zones** | Pricing zones (VIP, Standard, etc.) |
| **Events** | Published events with immutable snapshots |
| **Bookings** | Customer bookings with checkout flow |

### Booking Flow

```
1. User selects seat → Check availability
2. Create ElementLock (temporary hold)
3. Show checkout form
4. Create Booking + BookingItems
5. Delete ElementLock
```

## API Endpoints

### Venues
- `GET /api/venues` - List all venues
- `GET /api/venues/{slug}` - Get venue details
- `POST /api/venues` - Create venue (admin)

### Events
- `GET /api/events` - List published events
- `GET /api/events/{slug}` - Event details with seat map
- `POST /api/events` - Create event (admin)
- `POST /api/events/{slug}/publish` - Publish event

### Bookings
- `POST /api/bookings` - Create booking
- `GET /api/bookings/{reference}` - Booking status
- `POST /api/bookings/{reference}/confirm` - Confirm booking

## Development

```bash
# Run tests
php artisan test

# Check code style
./vendor/bin/pint

# Database seeders
php artisan db:seed --class=SeatmapSeeder
```

## Architecture Notes

- **Event Elements** are immutable snapshots taken at publish time
- **ElementLocks** prevent race conditions during checkout
- **BookingItems** use unique constraints to prevent double-booking
- All pricing is stored in USD with 2 decimal precision

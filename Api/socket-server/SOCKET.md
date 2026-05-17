# Real-Time Seat Availability — Socket.IO Integration

## What This Does

When a user books, confirms, or cancels seats, **all other users viewing the same event see the seat colors change instantly** — no page refresh needed.

**Example:** User A books seats 101-102. User B is looking at the same event's 3D seat map. User B sees seats 101-102 turn red immediately.

---

## Architecture

```
┌──────────┐     ┌──────────────┐     ┌───────┐     ┌────────────┐     ┌────────┐
│  Browser │────▶│  Laravel API │────▶│ Redis │────▶│ Socket.IO  │────▶│Browser │
│  (User A)│     │  (PHP)       │     │       │     │ Server     │     │(User B)│
└──────────┘     └──────────────┘     └───────┘     └────────────┘     └────────┘
     │                │                    │               │                │
     │  POST /bookings│                    │               │                │
     │───────────────▶│                    │               │                │
     │                │  event()           │               │                │
     │                │───────────────────▶│               │                │
     │                │                    │  pSubscribe   │                │
     │                │                    │──────────────▶│                │
     │                │                    │               │  emit()        │
     │                │                    │               │───────────────▶│
     │                │                    │               │  Seat turns red│
```

### Three Components

| Component | Technology | Role |
|-----------|------------|------|
| Laravel Events | PHP | Fires events when bookings change |
| Redis | Pub/Sub | Message queue between Laravel and Socket.IO |
| Socket.IO Server | Node.js | Pushes events to browser clients via WebSocket |

---

## File Map

```
Api/
├── app/
│   ├── Events/
│   │   ├── SeatStatusChanged.php    ← Broadcasts seat status changes
│   │   ├── SeatsLocked.php         ← Broadcasts temporary locks
│   │   └── BookingCreated.php      ← Broadcasts new bookings
│   └── Services/
│       ├── SocketService.php        ← Centralized broadcaster
│       └── BookingService.php        ← Calls SocketService after DB commits
├── config/
│   └── api.php                      ← Socket host/port config
├── .env                             ← SOCKET_HOST, SOCKET_PORT, BROADCAST_CONNECTION
└── socket-server/
    ├── index.js                     ← Node.js Socket.IO server
    ├── package.json                 ← Node dependencies
    └── test.html                    ← Browser test page

Metrial-Tasks/seatmap-system/resources/js/
└── 3d-seatmap-engine.js             ← Socket.IO client (connectSocket, updateSeatStatus)
```

---

## How Each File Works

### 1. Laravel Events (`app/Events/`)

Events implement `ShouldBroadcast` — Laravel automatically publishes them to Redis.

**SeatStatusChanged.php** — Used for ALL seat status changes:
```php
// Channel: event.{eventId}
// Payload:
{
    "type": "seat-batch-update",
    "event_id": 1,
    "seats": [
        {"element_id": 101, "status": "booked"},
        {"element_id": 102, "status": "booked"}
    ],
    "timestamp": "2026-05-13T14:00:00+00:00"
}
```

**SeatsLocked.php** — Seats temporarily reserved:
```php
{
    "type": "seats-locked",
    "event_id": 1,
    "element_ids": [103, 104],
    "lock_key": "uuid-here",
    "timestamp": "2026-05-13T14:00:00+00:00"
}
```

**BookingCreated.php** — New booking confirmed:
```php
{
    "type": "booking-created",
    "event_id": 1,
    "booking_reference": "BK-A1B2C3D4",
    "element_ids": [101, 102],
    "timestamp": "2026-05-13T14:00:00+00:00"
}
```

### 2. SocketService (`app/Services/SocketService.php`)

A thin wrapper that fires Laravel events. This is the ONLY class that should be called from business logic.

```php
class SocketService
{
    // Single seat changed
    public function broadcastSeatUpdate(int $eventId, int $elementId, string $status): void;

    // Multiple seats changed (most common)
    public function broadcastSeatBatchUpdate(int $eventId, array $seats): void;

    // New booking created
    public function broadcastBookingCreated(int $eventId, string $bookingReference, array $elementIds): void;

    // Seats temporarily locked
    public function broadcastSeatLocked(int $eventId, array $elementIds, string $lockKey): void;
}
```

### 3. BookingService Integration

SocketService is **optional** — injected as `?SocketService`. If not configured, broadcasts are silently skipped:

```php
// After booking created
$this->socketService?->broadcastSeatBatchUpdate(
    $event->id,
    array_map(fn ($id) => ['element_id' => $id, 'status' => 'booked'], $elementIds)
);

// After booking confirmed
$this->socketService?->broadcastSeatBatchUpdate(
    $booking->event_id,
    $booking->items->map(fn ($item) => [
        'element_id' => $item->event_element_id,
        'status' => 'confirmed'
    ])->toArray()
);

// After booking cancelled
$this->socketService?->broadcastSeatBatchUpdate(
    $booking->event_id,
    $booking->items->map(fn ($item) => [
        'element_id' => $item->event_element_id,
        'status' => 'available'
    ])->toArray()
);
```

### 4. Socket.IO Server (`socket-server/index.js`)

Node.js server that:
1. Subscribes to Redis `event.*` channels
2. Manages Socket.IO client connections and rooms
3. Forwards Redis messages to the correct room

```javascript
// Redis → Socket.IO flow
redisSubscriber.pSubscribe('event.*', (channel, message) => {
    const eventId = channel.split('.')[1];  // "event.1" → "1"
    const payload = JSON.parse(message);
    io.to(`event:${eventId}`).emit(payload.event, payload.data);
});
```

### 5. Client-Side (`3d-seatmap-engine.js`)

The 3D engine connects to Socket.IO and updates seat colors:

```javascript
// Initialize with event ID
const engine = new SeatMap3DEngine('container', {
    eventId: 123,
    socketUrl: 'http://localhost:3001'
});

// Engine automatically:
// 1. Connects to Socket.IO
// 2. Joins room "event:123"
// 3. Listens, BookingCreated, for SeatStatusChanged SeatsLocked
// 4. Updates 3D seat colors in real-time
```

**Seat Color Mapping:**

| Status | Color | Meaning |
|--------|-------|---------|
| `available` | Blue | Can be booked |
| `locked` | Gold | Temporarily reserved |
| `booked` | Red | Booked, awaiting payment |
| `confirmed` | Dark Red | Paid and confirmed |
| `selected` | Green | Current user's selection |

---

## Setup & Run

### Prerequisites

```bash
# 1. Redis
sudo apt install redis-server
sudo systemctl start redis

# 2. PHP Redis extension
sudo apt install php-redis

# 3. MySQL (for Laravel)
sudo apt install mysql-server
sudo systemctl start mysql
```

### Configuration

**`.env`** — Critical settings:
```env
# Must be "redis" for socket to work (default is "log")
BROADCAST_CONNECTION=redis
QUEUE_CONNECTION=redis

# Redis connection
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Socket server
SOCKET_ENABLED=true
SOCKET_HOST=127.0.0.1
SOCKET_PORT=3001
```

### Start All Services

You need **4 terminals** running simultaneously:

```bash
# Terminal 1: Laravel queue worker (processes broadcast jobs)
cd Api && php artisan queue:work

# Terminal 2: Socket.IO server
cd Api/socket-server && npm install && npm start

# Terminal 3: Laravel API
cd Api && php artisan serve

# Terminal 4: Laravel scheduler (for cleanup jobs)
cd Api && php artisan schedule:work
```

---

## Testing

### Test 1: Browser Test Page

Open `Api/socket-server/test.html` in a browser:

1. Click **"Join Event 1"**
2. Trigger events from Laravel (Test 2 below)
3. Watch events appear in real-time

### Test 2: Trigger Events via Tinker

```bash
cd Api && php artisan tinker
```

```php
// Fire a seat status change
event(new App\Events\SeatStatusChanged(1, [
    ['element_id' => 101, 'status' => 'booked'],
    ['element_id' => 102, 'status' => 'booked']
]));

// Fire a booking created
event(new App\Events\BookingCreated(1, 'BK-TEST123', [101, 102]));

// Fire seats locked
event(new App\Events\SeatsLocked(1, [103, 104], 'lock-abc'));
```

### Test 3: Full Booking Flow

```bash
# Create a booking via API
curl -X POST http://localhost:8080/api/v1/bookings \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "event_id": 1,
    "element_ids": [101, 102],
    "customer_name": "John Doe",
    "customer_email": "john@example.com"
  }'
```

Watch the browser test page — seats 101 and 102 should turn red instantly.

---

## Common Issues

| Problem | Cause | Fix |
|---------|-------|-----|
| Events not reaching browser | `BROADCAST_CONNECTION=log` | Change to `redis` in `.env` |
| `Class "Redis" not found` | phpredis not installed | `sudo apt install php-redis` |
| Socket server won't start | Port 3001 in use | `kill $(lsof -t -i:3001)` |
| Events queued but not sent | Queue worker not running | `php artisan queue:work` |
| Client not receiving | Not joined to room | Check `eventId` matches |

---

## How to Add a New Event Type

**Example:** Notify when pricing changes.

**1. Create the event:**
```php
// app/Events/PricingChanged.php
class PricingChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $eventId,
        public array $priceUpdates
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('event.' . $this->eventId)];
    }
}
```

**2. Add method to SocketService:**
```php
public function broadcastPricingChange(int $eventId, array $updates): void
{
    event(new PricingChanged($eventId, $updates));
}
```

**3. Call from business logic:**
```php
$this->socketService?->broadcastPricingChange($event->id, $updates);
```

**4. Client listens:**
```javascript
this.socket.on('PricingChanged', (data) => {
    // Update pricing display
});
```

No changes needed to the Socket.IO server — it automatically forwards any event type.

---

## Production Deployment

### Use PM2 for Socket.IO server

```bash
cd Api/socket-server
npm install -g pm2
pm2 start index.js --name seatmap-socket
pm2 save
pm2 startup
```

### Use Supervisor for Queue Worker

```ini
# /etc/supervisor/conf.d/seatmap-queue.conf
[program:seatmap-queue]
command=php /var/www/seat-map/Api/artisan queue:work --sleep=3 --tries=3
directory=/var/www/seat-map/Api
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/seatmap-queue.log
```

### Nginx Reverse Proxy (optional)

```nginx
location /socket.io/ {
    proxy_pass http://127.0.0.1:3001;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
}
```

This lets clients connect to `wss://yourdomain.com/socket.io/` without exposing port 3001.

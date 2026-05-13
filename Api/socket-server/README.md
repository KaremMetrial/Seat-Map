# Socket.IO Server for Real-Time Seat Updates

## Setup

1. Install dependencies:
```bash
cd socket-server
npm install
```

2. Configure Redis (required for pub/sub):
   - Ensure Redis is running on `127.0.0.1:6379`
   - Or set `REDIS_HOST` and `REDIS_PORT` in your Laravel `.env`

3. Start the socket server:
```bash
npm start
```

## Running with PM2 (production)

```bash
npm install -g pm2
pm2 start index.js --name seatmap-socket
pm2 save
pm2 startup
```

## Client Integration

Include Socket.IO client in your HTML:
```html
<script src="http://localhost:3001/socket.io/socket.io.js"></script>
```

Initialize the engine with event ID:
```javascript
const engine = new SeatMap3DEngine('container', {
    eventId: 123,
    socketUrl: 'http://localhost:3001'
});
```

## Events

The server subscribes to Redis channel `event:{eventId}` and broadcasts to Socket.IO clients.

### Laravel emits:
- `seat-update` - Single seat status change
- `seat-batch-update` - Multiple seats updated
- `booking-created` - New booking made
- `seats-locked` - Seats temporarily locked

### Client events:
- `join-event` - Join event room
- `leave-event` - Leave event room
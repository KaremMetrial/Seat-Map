const { Server } = require('socket.io');
const { createClient } = require('redis');

const io = new Server(3001, {
  cors: {
    origin: ['http://localhost:3000', 'http://localhost:8000', 'http://127.0.0.1:5173'],
    methods: ['GET', 'POST']
  }
});

// Redis subscriber for receiving Laravel broadcast events
const redisSubscriber = createClient();
redisSubscriber.on('error', (err) => console.error('Redis Subscriber Error:', err));

// Track connected clients per event
const eventRooms = new Map();

async function start() {
  await redisSubscriber.connect();

  console.log('Redis connected');
  console.log('Socket.IO server running on port 3001');

  // Subscribe to Laravel's broadcast channels
  // Laravel broadcasts to: "laravel_database_event.{eventId}"
  // The "laravel_database_" prefix comes from the database cache prefix
  await redisSubscriber.pSubscribe('event.*', (channel, message) => {
    const channelParts = channel.split('.');
    if (channelParts.length >= 2) {
      const eventId = channelParts[1];
      const payload = JSON.parse(message);

      console.log(`Event ${eventId}:`, payload.event);

      // Emit the event name to clients (e.g., 'SeatStatusChanged')
      // The data is in payload.data (Laravel wraps it)
      io.to(`event:${eventId}`).emit(payload.event, payload.data);
    }
  });

  // Handle socket connections
  io.on('connection', (socket) => {
    console.log(`Client connected: ${socket.id}`);

    // Join event room
    socket.on('join-event', async (eventId) => {
      if (!eventId) return;

      socket.join(`event:${eventId}`);

      if (!eventRooms.has(eventId)) {
        eventRooms.set(eventId, new Set());
      }
      eventRooms.get(eventId).add(socket.id);

      console.log(`Socket ${socket.id} joined event ${eventId}`);

      // Confirm join
      socket.emit('joined-event', {
        eventId,
        message: `Connected to event ${eventId}`
      });
    });

    // Leave event room
    socket.on('leave-event', (eventId) => {
      if (!eventId) return;

      socket.leave(`event:${eventId}`);

      if (eventRooms.has(eventId)) {
        eventRooms.get(eventId).delete(socket.id);
      }

      console.log(`Socket ${socket.id} left event ${eventId}`);
    });

    // Handle disconnect
    socket.on('disconnect', () => {
      eventRooms.forEach((sockets, eventId) => {
        sockets.delete(socket.id);
      });
      console.log(`Client disconnected: ${socket.id}`);
    });
  });
}

// Handle graceful shutdown
process.on('SIGINT', async () => {
  console.log('Shutting down...');
  await redisSubscriber.quit();
  process.exit(0);
});

start().catch(console.error);
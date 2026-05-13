<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $eventId,
        public string $bookingReference,
        public array $elementIds
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('event.' . $this->eventId)];
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'booking-created',
            'event_id' => $this->eventId,
            'booking_reference' => $this->bookingReference,
            'element_ids' => $this->elementIds,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
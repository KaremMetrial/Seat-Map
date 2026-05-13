<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SeatStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $eventId,
        public array $seats,
        public string $type = 'seat-batch-update'
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('event.' . $this->eventId)];
    }

    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'event_id' => $this->eventId,
            'seats' => $this->seats,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
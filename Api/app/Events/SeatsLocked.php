<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SeatsLocked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $eventId,
        public array $elementIds,
        public string $lockKey
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('event.' . $this->eventId)];
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'seats-locked',
            'event_id' => $this->eventId,
            'element_ids' => $this->elementIds,
            'lock_key' => $this->lockKey,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
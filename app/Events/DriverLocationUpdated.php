<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $location
     */
    public function __construct(
        public int $tripHistoryId,
        public array $location,
        public bool $active = true,
    ) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('trip.'.$this->tripHistoryId),
        ];
    }

    public function broadcastAs(): string
    {
        return (string) config('trips.location_broadcast_event', 'driver.location.updated');
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'trip_history_id' => $this->tripHistoryId,
            'active' => $this->active,
            'location' => $this->location,
        ];
    }
}

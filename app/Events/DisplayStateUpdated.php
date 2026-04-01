<?php

namespace App\Events;

use App\Models\DisplayDevice;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DisplayStateUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public DisplayDevice $displayDevice,
        public array $payload = [],
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('display.' . $this->displayDevice->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'display.state.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'display_id' => $this->displayDevice->id,
            'mode' => $this->displayDevice->current_mode,
            'payload' => $this->payload,
        ];
    }
}

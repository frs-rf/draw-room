<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class DrawCancelled implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public int $drawId)
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('draw');
    }

    public function broadcastAs(): string
    {
        return 'draw.cancelled';
    }

    public function broadcastWith(): array
    {
        return ['draw_id' => $this->drawId];
    }
}

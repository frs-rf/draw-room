<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class DrawStepRevealed implements ShouldBroadcastNow
{
    use Dispatchable;

    /** @param array<string,mixed> $payload Draw::stepPayload() */
    public function __construct(public array $payload)
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('draw');
    }

    public function broadcastAs(): string
    {
        return 'draw.step';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}

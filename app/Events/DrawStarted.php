<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Sengaja bukan model: model Draw berisi seluruh `steps` (rahasia). */
class DrawStarted implements ShouldBroadcastNow
{
    use Dispatchable;

    /** @param array<string,mixed> $draw publicMeta() */
    public function __construct(public array $draw)
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('draw');
    }

    public function broadcastAs(): string
    {
        return 'draw.started';
    }

    public function broadcastWith(): array
    {
        return ['draw' => $this->draw];
    }
}

<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Daftar peserta berubah: penonton di layar tunggu memuat ulang daftarnya. */
class RosterChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public function broadcastOn(): Channel
    {
        return new Channel('draw');
    }

    public function broadcastAs(): string
    {
        return 'roster.updated';
    }

    public function broadcastWith(): array
    {
        return [];
    }
}

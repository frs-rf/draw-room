<?php

namespace App\Services;

use App\Models\Draw;

/** Data bersama untuk halaman penonton & panitia. */
final class DrawPage
{
    /** @return array<string,mixed> */
    public static function data(): array
    {
        $roles   = config('draw.roles');
        $people  = Roster::people();
        $summary = $people
            ? DrawPlanner::summary($people, config('draw.room_size'), Roster::leadRoles())
            : null;

        $draw  = Draw::latestVisible();
        $state = $draw ? $draw->publicState() : ['draw' => null, 'steps' => []];

        $grouped = [];
        foreach ($roles as $key => $_) {
            $grouped[$key] = array_values(array_filter($people, fn ($p) => $p['role'] === $key));
        }

        $broadcast = [
            'driver' => config('broadcasting.default'),
            'reverb' => [
                'key'    => config('broadcasting.connections.reverb.key'),
                'host'   => config('broadcasting.connections.reverb.options.host'),
                'port'   => config('broadcasting.connections.reverb.options.port'),
                'scheme' => config('broadcasting.connections.reverb.options.scheme'),
            ],
            'pusher' => [
                'key'     => config('broadcasting.connections.pusher.key'),
                'cluster' => config('broadcasting.connections.pusher.options.cluster') ?: env('PUSHER_APP_CLUSTER', 'mt1'),
                'scheme'  => config('broadcasting.connections.pusher.options.scheme', 'https'),
            ],
        ];

        return [
            'roles'     => $roles,
            'people'    => $people,
            'grouped'   => $grouped,
            'excluded'  => Roster::excluded(),
            'summary'   => $summary,
            'state'     => $state,
            'broadcast' => $broadcast,
        ];
    }
}

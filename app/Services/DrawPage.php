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

        return [
            'roles'    => $roles,
            'people'   => $people,
            'grouped'  => $grouped,
            'excluded' => Roster::excluded(),
            'summary'  => $summary,
            'state'    => $state,
        ];
    }
}

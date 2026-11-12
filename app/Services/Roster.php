<?php

namespace App\Services;

use App\Models\ExcludedPerson;
use App\Models\Participant;

final class Roster
{
    /**
     * Peserta aktif, urut sesuai config('draw.roles') lalu `sort`.
     *
     * @return array<int, array{name:string, role:string}>
     */
    public static function people(): array
    {
        $order = array_flip(array_keys(config('draw.roles')));

        return Participant::query()
            ->where('active', true)
            ->get(['id', 'name', 'role', 'sort'])
            ->sortBy(fn ($p) => sprintf('%02d-%05d-%08d', $order[$p->role] ?? 99, $p->sort, $p->id))
            ->map(fn ($p) => ['name' => $p->name, 'role' => $p->role])
            ->values()
            ->all();
    }

    /**
     * Model peserta aktif (dengan id) untuk layar kelola, urut sama seperti people().
     *
     * @return \Illuminate\Support\Collection<int, Participant>
     */
    public static function participantModels()
    {
        $order = array_flip(array_keys(config('draw.roles')));

        return Participant::query()
            ->where('active', true)
            ->get()
            ->sortBy(fn ($p) => sprintf('%02d-%05d-%08d', $order[$p->role] ?? 99, $p->sort, $p->id))
            ->values();
    }

    /** @return array<string, array<int, string>> */
    public static function excluded(): array
    {
        return ExcludedPerson::query()
            ->orderBy('sort')->orderBy('id')
            ->get()
            ->groupBy('group')
            ->map(fn ($g) => $g->pluck('name')->all())
            ->all();
    }

    public static function leadRoles(): array
    {
        return array_keys(array_filter(config('draw.roles'), fn ($r) => $r['lead']));
    }
}

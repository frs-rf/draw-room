<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Logika undian murni (tanpa dependensi framework, mudah dites).
 *
 * Aturan:
 * - Jumlah kamar = ceil(peserta / ukuran kamar). Kapasitas dibagi rata.
 * - "Lead" (PM/SM/ATM) disebar ke kamar yang DIPILIH ACAK dari seluruh kamar,
 *   bukan selalu kamar 1..N.
 * - Lead == kamar  -> tiap kamar tepat 1 lead (pas).
 * - Lead <  kamar  -> sebagian kamar tanpa lead; kamar penerima acak.
 * - Lead >  kamar  -> kelebihan diundi bersama peserta biasa.
 */
final class DrawPlanner
{
    /**
     * @param  array<int, array{name:string, role:string}>  $people
     * @param  array<int, string>  $leadRoles
     * @return array{total:int, leads:int, others:int, rooms:int, caps:int[], lead_gap:int}
     */
    public static function summary(array $people, int $roomSize, array $leadRoles): array
    {
        self::guard($people, $roomSize);

        $total = count($people);
        $rooms = (int) ceil($total / $roomSize);
        $leads = count(array_filter($people, fn ($p) => in_array($p['role'], $leadRoles, true)));

        return [
            'total'    => $total,
            'leads'    => $leads,
            'others'   => $total - $leads,
            'rooms'    => $rooms,
            'caps'     => self::capacities($total, $rooms),
            // > 0: kamar tanpa lead. 0: pas. < 0: lead berlebih.
            'lead_gap' => $rooms - $leads,
        ];
    }

    /**
     * @param  array<int, array{name:string, role:string}>  $people
     * @param  array<int, string>  $leadRoles
     * @param  (callable(array): array)|null  $shuffle  injeksi untuk tes
     * @param  array<int, string>|null  $balancedRoles  role wajib per kamar (mis. prg & imp)
     * @return array{caps:int[], lead_rooms:int[], steps:array<int, array<string,mixed>>}
     */
    public static function plan(
        array $people,
        int $roomSize,
        array $leadRoles,
        ?callable $shuffle = null,
        ?array $balancedRoles = null
    ): array
    {
        self::guard($people, $roomSize);
        $shuffle ??= [self::class, 'shuffle'];
        $balancedRoles ??= (function_exists('config') ? config('draw.balanced_roles', ['prg', 'imp']) : ['prg', 'imp']);

        $total = count($people);
        $rooms = (int) ceil($total / $roomSize);
        $caps  = self::capacities($total, $rooms);

        $leads = [];
        $byRole = [];
        foreach ($balancedRoles as $br) {
            $byRole[$br] = [];
        }
        $rest = [];

        foreach ($people as $p) {
            $role = $p['role'];
            if (in_array($role, $leadRoles, true)) {
                $leads[] = $p;
            } elseif (in_array($role, $balancedRoles, true)) {
                $byRole[$role][] = $p;
            } else {
                $rest[] = $p;
            }
        }

        $leads = $shuffle($leads);
        $take  = min(count($leads), $rooms);

        // Kelebihan lead masuk kolam peserta biasa.
        $rest  = array_merge($rest, array_slice($leads, $take));
        $leads = array_slice($leads, 0, $take);

        // Kamar penerima lead dipilih acak dari SEMUA kamar. Urutan ini
        // juga urutan pengungkapan Babak 1.
        $leadRooms = array_slice($shuffle(range(0, $rooms - 1)), 0, $take);

        $count = array_fill(0, $rooms, 0);
        $steps = [];

        // Putaran 1: PM/SM/ATM (satu per kamar penerima).
        if ($take > 0) {
            foreach ($leads as $i => $p) {
                $room    = $leadRooms[$i];
                $steps[] = self::step($p, $room, $count[$room]++, true, 1);
            }
            $round = 2;
        } else {
            $round = 1;
        }

        // Putaran untuk role yang wajib seimbang (mis. Programmer lalu Implementor)
        foreach ($balancedRoles as $bRole) {
            $pool = $shuffle($byRole[$bRole] ?? []);
            if (empty($pool)) {
                continue;
            }

            // Cari kamar yang masih punya slot
            $availableRooms = array_values(array_filter(
                range(0, $rooms - 1),
                fn ($k) => $count[$k] < $caps[$k]
            ));

            $takeRole = min(count($pool), count($availableRooms));
            if ($takeRole > 0) {
                $chosenRooms = array_slice($shuffle($availableRooms), 0, $takeRole);
                foreach ($chosenRooms as $room) {
                    $p = array_shift($pool);
                    $steps[] = self::step($p, $room, $count[$room]++, false, $round);
                }
                $round++;
            }

            // Sisa role ini (jika ada lebih banyak dari jumlah kamar) masuk kolam umum
            if (!empty($pool)) {
                $rest = array_merge($rest, $pool);
            }
        }

        // Putaran sisa peserta: tiap putaran = satu orang per kamar yang masih punya slot, urutan kamar acak.
        $rest = $shuffle($rest);
        while ($rest) {
            $placed = 0;
            // Urutan kamar diacak ulang di tiap putaran (bukan 1, 2, 3, ...).
            foreach ($shuffle(range(0, $rooms - 1)) as $k) {
                if (! $rest) {
                    break;
                }
                if ($count[$k] < $caps[$k]) {
                    $steps[] = self::step(array_shift($rest), $k, $count[$k]++, false, $round);
                    $placed++;
                }
            }
            if ($placed === 0) {
                throw new \LogicException('Kapasitas kamar tidak cukup untuk semua peserta.');
            }
            $round++;
        }

        foreach ($steps as $i => &$s) {
            $s['index'] = $i;
        }
        unset($s);

        $sortedLeadRooms = $leadRooms;
        sort($sortedLeadRooms);

        return ['caps' => $caps, 'lead_rooms' => $sortedLeadRooms, 'steps' => $steps];
    }

    /** @return int[] */
    private static function capacities(int $total, int $rooms): array
    {
        $base  = intdiv($total, $rooms);
        $extra = $total % $rooms;
        $caps  = [];
        for ($i = 0; $i < $rooms; $i++) {
            $caps[] = $base + ($i < $extra ? 1 : 0);
        }

        return $caps;
    }

    private static function step(array $p, int $room, int $slot, bool $lead, int $round): array
    {
        return [
            'name' => $p['name'], 'role' => $p['role'], 'room' => $room,
            'slot' => $slot, 'lead' => $lead, 'round' => $round,
        ];
    }

    /** Fisher-Yates dengan random_int (CSPRNG). */
    public static function shuffle(array $a): array
    {
        $a = array_values($a);
        for ($i = count($a) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$a[$i], $a[$j]] = [$a[$j], $a[$i]];
        }

        return $a;
    }

    private static function guard(array $people, int $roomSize): void
    {
        if ($roomSize < 1) {
            throw new InvalidArgumentException('Ukuran kamar minimal 1.');
        }
        if (count($people) === 0) {
            throw new InvalidArgumentException('Tidak ada peserta aktif.');
        }
    }
}

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
        $separated = count(array_filter($people, fn ($p) => !empty($p['separated'])));

        return [
            'total'           => $total,
            'leads'           => $leads,
            'others'          => $total - $leads,
            'rooms'           => $rooms,
            'caps'            => self::capacities($total, $rooms),
            // > 0: kamar tanpa lead. 0: pas. < 0: lead berlebih.
            'lead_gap'        => $rooms - $leads,
            'separated_count' => $separated,
        ];
    }

    /**
     * @param  array<int, array{name:string, role:string, separated?:bool}>  $people
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

        $markedCount = count(array_filter($people, fn ($p) => !empty($p['separated'])));
        if ($markedCount > $rooms) {
            throw new InvalidArgumentException("Jumlah peserta bertanda pisah kamar ($markedCount) melebihi jumlah kamar ($rooms). Maksimal $rooms orang.");
        }

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

        $markedRooms = [];
        $count = array_fill(0, $rooms, 0);
        $steps = [];

        $splitMarked = function (array $arr) use ($shuffle): array {
            $marked = [];
            $unmarked = [];
            foreach ($arr as $item) {
                if (!empty($item['separated'])) {
                    $marked[] = $item;
                } else {
                    $unmarked[] = $item;
                }
            }
            return [
                'marked'   => $shuffle($marked),
                'unmarked' => $shuffle($unmarked),
            ];
        };

        // --- Putaran 1: PM/SM/ATM (satu per kamar penerima) ---
        $sLeads = $splitMarked($leads);
        $take   = min(count($leads), $rooms);

        $allLeads = array_merge($sLeads['marked'], $sLeads['unmarked']);
        $chosenLeads = array_slice($allLeads, 0, $take);
        $excessLeads = array_slice($allLeads, $take);
        if (!empty($excessLeads)) {
            $rest = array_merge($rest, $excessLeads);
        }

        $leadRooms = array_slice($shuffle(range(0, $rooms - 1)), 0, $take);
        $round = 1;

        if ($take > 0) {
            $chosenMarked = array_values(array_filter($chosenLeads, fn ($p) => !empty($p['separated'])));
            $chosenUnmarked = array_values(array_filter($chosenLeads, fn ($p) => empty($p['separated'])));

            $markedCountInLeads = count($chosenMarked);
            for ($i = 0; $i < $markedCountInLeads; $i++) {
                $room = $leadRooms[$i];
                $markedRooms[$room] = true;
                $steps[] = self::step($chosenMarked[$i], $room, $count[$room]++, true, 1);
            }
            for ($i = 0; $i < count($chosenUnmarked); $i++) {
                $room = $leadRooms[$markedCountInLeads + $i];
                $steps[] = self::step($chosenUnmarked[$i], $room, $count[$room]++, true, 1);
            }
            $round = 2;
        }

        // --- Putaran untuk role yang wajib seimbang (mis. Programmer lalu Implementor) ---
        foreach ($balancedRoles as $bRole) {
            $sPool = $splitMarked($byRole[$bRole] ?? []);
            $poolTotal = count($sPool['marked']) + count($sPool['unmarked']);
            if ($poolTotal === 0) {
                continue;
            }

            $availableRooms = array_values(array_filter(
                range(0, $rooms - 1),
                fn ($k) => $count[$k] < $caps[$k]
            ));

            $takeRole = min($poolTotal, count($availableRooms));
            if ($takeRole === 0) {
                continue;
            }

            $allPool = array_merge($sPool['marked'], $sPool['unmarked']);
            $chosenPool = array_slice($allPool, 0, $takeRole);
            $excessPool = array_slice($allPool, $takeRole);
            if (!empty($excessPool)) {
                $rest = array_merge($rest, $excessPool);
            }

            $poolMarked = array_values(array_filter($chosenPool, fn ($p) => !empty($p['separated'])));
            $poolUnmarked = array_values(array_filter($chosenPool, fn ($p) => empty($p['separated'])));

            $candidateForMarked = array_values(array_filter(
                $availableRooms,
                fn ($r) => !isset($markedRooms[$r])
            ));
            $candidateForMarked = $shuffle($candidateForMarked);

            $assignedMarkedRooms = array_slice($candidateForMarked, 0, count($poolMarked));
            foreach ($assignedMarkedRooms as $r) {
                $markedRooms[$r] = true;
            }

            $remainingAvailable = array_values(array_filter(
                $availableRooms,
                fn ($r) => !in_array($r, $assignedMarkedRooms, true)
            ));
            $remainingAvailable = $shuffle($remainingAvailable);
            $assignedUnmarkedRooms = array_slice($remainingAvailable, 0, count($poolUnmarked));

            $roundAssignments = [];
            for ($i = 0; $i < count($poolMarked); $i++) {
                $roundAssignments[] = ['person' => $poolMarked[$i], 'room' => $assignedMarkedRooms[$i]];
            }
            for ($i = 0; $i < count($poolUnmarked); $i++) {
                $roundAssignments[] = ['person' => $poolUnmarked[$i], 'room' => $assignedUnmarkedRooms[$i]];
            }

            foreach ($shuffle($roundAssignments) as $item) {
                $steps[] = self::step($item['person'], $item['room'], $count[$item['room']]++, false, $round);
            }
            $round++;
        }

        // --- Putaran sisa peserta: tiap putaran = satu orang per kamar yang masih punya slot, urutan kamar acak ---
        $rest = $shuffle($rest);
        while ($rest) {
            $sRest = $splitMarked($rest);
            $availableRooms = array_values(array_filter(
                range(0, $rooms - 1),
                fn ($k) => $count[$k] < $caps[$k]
            ));
            if (empty($availableRooms)) {
                break;
            }

            $takeCount = min(count($rest), count($availableRooms));
            $allRest = array_merge($sRest['marked'], $sRest['unmarked']);
            $chosenRest = array_slice($allRest, 0, $takeCount);
            $rest = array_slice($allRest, $takeCount);

            $restMarked = array_values(array_filter($chosenRest, fn ($p) => !empty($p['separated'])));
            $restUnmarked = array_values(array_filter($chosenRest, fn ($p) => empty($p['separated'])));

            $candidateForMarked = array_values(array_filter(
                $availableRooms,
                fn ($r) => !isset($markedRooms[$r])
            ));
            $candidateForMarked = $shuffle($candidateForMarked);

            $assignedMarkedRooms = array_slice($candidateForMarked, 0, count($restMarked));
            foreach ($assignedMarkedRooms as $r) {
                $markedRooms[$r] = true;
            }

            $remainingAvailable = array_values(array_filter(
                $availableRooms,
                fn ($r) => !in_array($r, $assignedMarkedRooms, true)
            ));
            $remainingAvailable = $shuffle($remainingAvailable);
            $assignedUnmarkedRooms = array_slice($remainingAvailable, 0, count($restUnmarked));

            $roundAssignments = [];
            for ($i = 0; $i < count($restMarked); $i++) {
                $roundAssignments[] = ['person' => $restMarked[$i], 'room' => $assignedMarkedRooms[$i]];
            }
            for ($i = 0; $i < count($restUnmarked); $i++) {
                $roundAssignments[] = ['person' => $restUnmarked[$i], 'room' => $assignedUnmarkedRooms[$i]];
            }

            foreach ($shuffle($roundAssignments) as $item) {
                $steps[] = self::step($item['person'], $item['room'], $count[$item['room']]++, false, $round);
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
            'name'      => $p['name'],
            'role'      => $p['role'],
            'room'      => $room,
            'slot'      => $slot,
            'lead'      => $lead,
            'round'     => $round,
            'separated' => (bool) ($p['separated'] ?? false),
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

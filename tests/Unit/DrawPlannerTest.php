<?php

namespace Tests\Unit;

use App\Services\DrawPlanner;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DrawPlannerTest extends TestCase
{
    private const LEAD = ['pm', 'sm', 'atm'];

    /** @return array<int, array{name:string, role:string}> */
    private function people(int $pm, int $sm, int $atm, int $other): array
    {
        $p = [];
        foreach (['pm' => $pm, 'sm' => $sm, 'atm' => $atm, 'prg' => $other] as $role => $n) {
            for ($i = 1; $i <= $n; $i++) {
                $p[] = ['name' => strtoupper($role) . $i, 'role' => $role];
            }
        }

        return $p;
    }

    private function roomsOf(array $plan): array
    {
        $by = [];
        foreach ($plan['steps'] as $s) {
            $by[$s['room']][] = $s;
        }
        ksort($by);

        return $by;
    }

    public function test_leads_equal_rooms_gives_exactly_one_lead_per_room(): void
    {
        // 7 PM + 2 SM + 1 ATM = 10 lead, 40 orang -> 10 kamar
        $plan = DrawPlanner::plan($this->people(7, 2, 1, 30), 4, self::LEAD);
        $by = $this->roomsOf($plan);

        $this->assertCount(10, $plan['caps']);
        $this->assertCount(10, $by);
        foreach ($by as $members) {
            $this->assertSame(1, count(array_filter($members, fn ($m) => $m['lead'])));
            $this->assertSame(4, count($members));
        }
        $this->assertSame(range(0, 9), $plan['lead_rooms']);
    }

    public function test_every_person_placed_once_and_caps_respected(): void
    {
        $people = $this->people(7, 2, 1, 31); // 41 orang -> 11 kamar, kapasitas tidak rata
        $plan = DrawPlanner::plan($people, 4, self::LEAD);

        $this->assertCount(41, $plan['steps']);
        $names = array_column($plan['steps'], 'name');
        $this->assertSame(41, count(array_unique($names)));
        $this->assertSame(41, array_sum($plan['caps']));
        foreach ($this->roomsOf($plan) as $room => $members) {
            $this->assertSame($plan['caps'][$room], count($members));
            $slots = array_column($members, 'slot');
            sort($slots);
            $this->assertSame(range(0, count($members) - 1), $slots);
        }
        foreach ($plan['steps'] as $i => $s) {
            $this->assertSame($i, $s['index']);
        }
    }

    public function test_fewer_leads_than_rooms_can_land_in_any_room_including_last(): void
    {
        // 7 lead, 40 orang -> 10 kamar. Kamar 8-10 (indeks 7-9) HARUS bisa kebagian.
        $hit = array_fill(0, 10, 0);
        for ($run = 0; $run < 400; $run++) {
            $plan = DrawPlanner::plan($this->people(7, 0, 0, 33), 4, self::LEAD);
            $this->assertCount(7, $plan['lead_rooms']);
            $this->assertSame(7, count(array_unique($plan['lead_rooms'])));
            foreach ($plan['lead_rooms'] as $r) {
                $hit[$r]++;
            }
        }
        foreach ([7, 8, 9] as $r) {
            $this->assertTrue($hit[$r] > 0, "kamar indeks $r tidak pernah dapat lead");
        }
        foreach ($hit as $r => $n) {
            $this->assertTrue($n > 0, "kamar indeks $r tidak pernah dapat lead");
        }
    }

    public function test_babak1_steps_come_first_and_only_lead(): void
    {
        $plan = DrawPlanner::plan($this->people(7, 0, 0, 33), 4, self::LEAD);
        foreach ($plan['steps'] as $i => $s) {
            $this->assertSame($i < 7, $s['lead']);
        }
    }

    public function test_surplus_leads_are_drawn_with_regular_people(): void
    {
        // 12 lead, 40 orang -> 10 kamar: tiap kamar >=1 lead, tak ada kamar kelebihan isi
        $plan = DrawPlanner::plan($this->people(12, 0, 0, 28), 4, self::LEAD);
        $by = $this->roomsOf($plan);
        foreach ($by as $room => $members) {
            $this->assertTrue(count(array_filter($members, fn ($m) => $m['role'] === 'pm')) >= 1);
            $this->assertSame($plan['caps'][$room], count($members));
        }
        $this->assertSame(10, count(array_filter($plan['steps'], fn ($s) => $s['lead'])));
    }

    /** @return int[] jumlah langkah per putaran */
    private function roundSizes(array $plan): array
    {
        $sizes = [];
        foreach ($plan['steps'] as $s) {
            $sizes[$s['round']] = ($sizes[$s['round']] ?? 0) + 1;
        }
        ksort($sizes);

        return array_values($sizes);
    }

    public function test_each_round_puts_at_most_one_person_per_room(): void
    {
        foreach ([[7, 2, 1, 31], [7, 0, 0, 33], [12, 0, 0, 28], [0, 0, 0, 9]] as $c) {
            $plan = DrawPlanner::plan($this->people(...$c), 4, self::LEAD);
            $seen = [];
            foreach ($plan['steps'] as $s) {
                $key = $s['round'] . ':' . $s['room'];
                $this->assertTrue(! isset($seen[$key]), "putaran {$s['round']} memasukkan 2 orang ke kamar {$s['room']}");
                $seen[$key] = true;
            }
            // nomor putaran naik tanpa lompat, urutan langkah mengikuti putaran
            $prev = 1;
            foreach ($plan['steps'] as $s) {
                $this->assertTrue($s['round'] === $prev || $s['round'] === $prev + 1);
                $prev = $s['round'];
            }
        }
    }

    public function test_round_sizes_when_leads_fill_every_room(): void
    {
        // Data nyata: 8 PM + 2 SM + 1 ATM = 11 lead, 41 orang, 11 kamar
        $plan = DrawPlanner::plan($this->people(8, 2, 1, 30), 4, self::LEAD);
        $this->assertSame([11, 11, 11, 8], $this->roundSizes($plan));
        foreach ($plan['steps'] as $s) {
            $this->assertSame($s['round'] === 1, $s['lead']);
        }
    }

    public function test_round_sizes_when_leads_fewer_than_rooms(): void
    {
        $plan = DrawPlanner::plan($this->people(7, 0, 0, 33), 4, self::LEAD);
        $this->assertSame([7, 10, 10, 10, 3], $this->roundSizes($plan));
    }

    public function test_room_order_inside_every_round_is_random_not_ascending(): void
    {
        $ascendingRuns = 0;
        $firstRoomSeen = [];
        $runs = 60;
        for ($i = 0; $i < $runs; $i++) {
            $plan = DrawPlanner::plan($this->people(8, 2, 1, 30), 4, self::LEAD);
            foreach ([1, 2, 3] as $round) {
                $order = array_values(array_map(
                    fn ($s) => $s['room'],
                    array_filter($plan['steps'], fn ($s) => $s['round'] === $round)
                ));
                $sorted = $order;
                sort($sorted);
                $this->assertSame(range(0, 10), $sorted); // tiap kamar tepat sekali per putaran
                if ($order === $sorted) {
                    $ascendingRuns++;
                }
                if ($round === 2) {
                    $firstRoomSeen[$order[0]] = true;
                }
            }
        }
        // 11! kemungkinan urutan: berurutan murni praktis mustahil.
        $this->assertSame(0, $ascendingRuns);
        // Kamar pertama di putaran 2 bervariasi, bukan selalu kamar 1.
        $this->assertTrue(count($firstRoomSeen) >= 5, 'kamar pertama putaran 2 kurang bervariasi');
    }

    public function test_no_leads_at_all_still_works(): void
    {
        $plan = DrawPlanner::plan($this->people(0, 0, 0, 8), 4, self::LEAD);
        $this->assertSame([], $plan['lead_rooms']);
        $this->assertCount(8, $plan['steps']);
    }

    public function test_summary_reports_gap(): void
    {
        $s = DrawPlanner::summary($this->people(7, 0, 0, 33), 4, self::LEAD);
        $this->assertSame(3, $s['lead_gap']);
        $this->assertSame(10, $s['rooms']);

        $s = DrawPlanner::summary($this->people(7, 2, 1, 30), 4, self::LEAD);
        $this->assertSame(0, $s['lead_gap']);
    }

    public function test_every_room_has_at_least_one_programmer_and_one_implementor(): void
    {
        // 11 Lead (8 PM, 2 SM, 1 ATM), 13 Programmer, 16 Implementor, 1 Backbone = 41 orang -> 11 kamar
        $people = [];
        for ($i = 1; $i <= 8; $i++) $people[] = ['name' => 'PM' . $i, 'role' => 'pm'];
        for ($i = 1; $i <= 2; $i++) $people[] = ['name' => 'SM' . $i, 'role' => 'sm'];
        $people[] = ['name' => 'ATM1', 'role' => 'atm'];
        for ($i = 1; $i <= 13; $i++) $people[] = ['name' => 'PRG' . $i, 'role' => 'prg'];
        for ($i = 1; $i <= 16; $i++) $people[] = ['name' => 'IMP' . $i, 'role' => 'imp'];
        $people[] = ['name' => 'BB1', 'role' => 'bb'];

        for ($run = 0; $run < 50; $run++) {
            $plan = DrawPlanner::plan($people, 4, self::LEAD);
            $rooms = $this->roomsOf($plan);

            $this->assertCount(11, $rooms);
            foreach ($rooms as $room => $members) {
                $leads = count(array_filter($members, fn ($m) => in_array($m['role'], self::LEAD, true)));
                $prgs  = count(array_filter($members, fn ($m) => $m['role'] === 'prg'));
                $imps  = count(array_filter($members, fn ($m) => $m['role'] === 'imp'));

                $this->assertSame(1, $leads, "Kamar $room harus memiliki tepat 1 lead");
                $this->assertGreaterThanOrEqual(1, $prgs, "Kamar $room wajib ada minimal 1 programmer");
                $this->assertGreaterThanOrEqual(1, $imps, "Kamar $room wajib ada minimal 1 implementor");
            }
        }
    }

    public function test_empty_and_invalid_input_throw(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DrawPlanner::plan([], 4, self::LEAD);
    }
}

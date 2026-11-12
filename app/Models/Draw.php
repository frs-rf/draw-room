<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Draw extends Model
{
    public const RUNNING = 'running';
    public const FINISHED = 'finished';
    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'status', 'room_size', 'caps', 'lead_rooms', 'steps',
        'current_index', 'started_by', 'finished_at',
    ];

    protected $casts = [
        'caps'          => 'array',
        'lead_rooms'    => 'array',
        'steps'         => 'array',
        'current_index' => 'integer',
        'finished_at'   => 'datetime',
    ];

    /** Undian terbaru yang boleh dilihat publik. */
    public static function latestVisible(): ?self
    {
        return static::query()
            ->whereIn('status', [self::RUNNING, self::FINISHED])
            ->latest('id')
            ->first();
    }

    public function total(): int
    {
        return count($this->steps);
    }

    /**
     * State untuk publik. HANYA langkah yang sudah diungkap.
     * Langkah berikutnya tidak boleh bocor lewat endpoint ini.
     *
     * @return array{draw: array<string,mixed>, steps: array<int,array<string,mixed>>}
     */
    public function publicState(): array
    {
        return [
            'draw'  => $this->publicMeta(),
            'steps' => array_slice($this->steps, 0, $this->current_index),
        ];
    }

    /** @return array<string,mixed> */
    public function publicMeta(): array
    {
        return [
            'id'         => $this->id,
            'status'     => $this->status,
            'room_size'  => $this->room_size,
            'caps'       => $this->caps,
            'lead_rooms' => $this->lead_rooms,
            'total'      => $this->total(),
            'revealed'   => $this->current_index,
            'round_sizes' => $this->roundSizes(),
        ];
    }

    /**
     * Jumlah langkah per putaran, mis. [11, 11, 11, 8]. Penonton memakainya
     * untuk tahu kapan jeda. Hanya jumlah, tanpa nama / kamar.
     * Undian lama tanpa nomor putaran dianggap satu putaran.
     *
     * @return int[]
     */
    public function roundSizes(): array
    {
        $sizes = [];
        foreach ($this->steps as $s) {
            $r = $s['round'] ?? 1;
            $sizes[$r] = ($sizes[$r] ?? 0) + 1;
        }
        ksort($sizes);

        return array_values($sizes);
    }

    /**
     * Payload satu langkah. `candidates` = nama yang belum diungkap pada
     * babak yang sama (hanya untuk animasi kocokan; tanpa info kamar).
     *
     * @return array<string,mixed>
     */
    public function stepPayload(int $index): array
    {
        $step = $this->steps[$index];

        $candidates = [];
        foreach (array_slice($this->steps, $index) as $s) {
            if ($s['lead'] === $step['lead']) {
                $candidates[] = $s['name'];
            }
        }

        return [
            'draw_id'    => $this->id,
            'index'      => $index,
            'step'       => $step,
            'candidates' => $candidates,
            'total'      => $this->total(),
            'finished'   => $index === $this->total() - 1,
        ];
    }
}

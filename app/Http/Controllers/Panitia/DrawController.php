<?php

namespace App\Http\Controllers\Panitia;

use App\Events\DrawCancelled;
use App\Events\DrawStarted;
use App\Events\DrawStepRevealed;
use App\Http\Controllers\Controller;
use App\Models\Draw;
use App\Services\DrawPage;
use App\Services\DrawPlanner;
use App\Services\Roster;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Konsol panitia. Semua route di sini dilindungi middleware `auth`. */
class DrawController extends Controller
{
    public function index(): View
    {
        return view('panitia.console', DrawPage::data() + ['isPanitia' => true]);
    }

    public function start(Request $request): JsonResponse
    {
        try {
            $plan = DrawPlanner::plan(
                Roster::people(),
                (int) config('draw.room_size'),
                Roster::leadRoles(),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $draw = DB::transaction(function () use ($plan, $request) {
            // Hanya satu undian aktif. Yang lama disembunyikan (baris tetap ada).
            Draw::query()->where('status', Draw::RUNNING)->update(['status' => Draw::CANCELLED]);

            return Draw::create([
                'status'        => Draw::RUNNING,
                'room_size'     => (int) config('draw.room_size'),
                'caps'          => $plan['caps'],
                'lead_rooms'    => $plan['lead_rooms'],
                'steps'         => $plan['steps'],
                'current_index' => 0,
                'started_by'    => $request->user()->id,
            ]);
        });

        $this->safeBroadcast(new DrawStarted($draw->publicMeta()));

        return response()->json($draw->publicState());
    }

    /**
     * Ungkap langkah ke-`index`. Idempoten terhadap klik ganda / dua panitia:
     * kalau `index` tidak sama dengan posisi server, balas 409 tanpa mengubah apa pun.
     */
    public function advance(Request $request, Draw $draw): JsonResponse
    {
        $index = (int) $request->validate(['index' => ['required', 'integer', 'min:0']])['index'];

        $result = DB::transaction(function () use ($draw, $index) {
            $d = Draw::query()->whereKey($draw->id)->lockForUpdate()->firstOrFail();

            if ($d->status !== Draw::RUNNING) {
                return ['error' => 'not_running', 'status' => $d->status];
            }
            if ($index !== $d->current_index) {
                return ['error' => 'out_of_sync', 'expected' => $d->current_index];
            }

            $payload = $d->stepPayload($index);
            $d->current_index = $index + 1;
            if ($payload['finished']) {
                $d->status = Draw::FINISHED;
                $d->finished_at = now();
            }
            $d->save();

            return ['ok' => $payload];
        });

        if (isset($result['error'])) {
            return response()->json($result, 409);
        }

        $this->safeBroadcast(new DrawStepRevealed($result['ok']));

        return response()->json($result['ok']);
    }

    public function cancel(Draw $draw): JsonResponse
    {
        if ($draw->status !== Draw::CANCELLED) {
            $draw->update(['status' => Draw::CANCELLED]);
            $this->safeBroadcast(new DrawCancelled($draw->id));
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Reverb mati tidak boleh menggagalkan undian: DB sudah benar,
     * penonton akan sinkron lewat polling /draw/state.
     */
    private function safeBroadcast(object $event): void
    {
        try {
            event($event);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

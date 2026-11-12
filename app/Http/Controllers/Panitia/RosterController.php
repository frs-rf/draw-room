<?php

namespace App\Http\Controllers\Panitia;

use App\Events\RosterChanged;
use App\Http\Controllers\Controller;
use App\Models\Draw;
use App\Models\ExcludedPerson;
use App\Models\Participant;
use App\Services\DrawPlanner;
use App\Services\Roster;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Kelola peserta: satu tabel berisi peserta yang ikut undian dan yang di luar undian.
 * Hanya panitia (middleware `auth`). Terkunci saat ada undian berjalan.
 *
 * Baris diidentifikasi {type}/{id}: p = tabel participants, x = excluded_people.
 */
class RosterController extends Controller
{
    private const DEFAULT_GROUPS = ['Panitia (tidak ikut diundi)', 'Tidak ikut gathering', 'Driver & Backbone'];

    public function index(): View
    {
        $roles = config('draw.roles');
        $rows  = collect();

        foreach (Roster::participantModels() as $p) {
            $rows->push([
                'type' => 'p', 'id' => $p->id, 'name' => $p->name, 'status' => 'diundi',
                'role' => $p->role, 'group' => '', 'info' => $roles[$p->role]['label'] ?? $p->role,
            ]);
        }

        $excluded = ExcludedPerson::query()->orderBy('group')->orderBy('sort')->orderBy('id')->get();
        foreach ($excluded as $x) {
            $rows->push([
                'type' => 'x', 'id' => $x->id, 'name' => $x->name, 'status' => 'luar',
                'role' => '', 'group' => $x->group, 'info' => $x->group,
            ]);
        }

        $people  = Roster::people();
        $summary = $people
            ? DrawPlanner::summary($people, (int) config('draw.room_size'), Roster::leadRoles())
            : null;

        return view('panitia.peserta', [
            'roles'   => $roles,
            'rows'    => $rows,
            'groups'  => $excluded->pluck('group')->merge(self::DEFAULT_GROUPS)->unique()->values(),
            'summary' => $summary,
            'running' => $this->running(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, null);
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        return $this->save($request, $this->find($type, $id));
    }

    public function destroy(string $type, int $id): RedirectResponse
    {
        if ($r = $this->locked()) {
            return $r;
        }
        $model = $this->find($type, $id);
        $name  = $model->name;
        $model->delete();

        return $this->done("$name dihapus.");
    }

    /**
     * Tambah / ubah. Mengganti status memindahkan baris antar tabel
     * (diundi <-> di luar undian) dalam satu transaksi.
     */
    private function save(Request $request, ?Model $current): RedirectResponse
    {
        if ($r = $this->locked()) {
            return $r;
        }

        $data = $request->validate([
            'name'   => ['required', 'string', 'max:100'],
            'status' => ['required', Rule::in(['diundi', 'luar'])],
            'role'   => ['required_if:status,diundi', 'nullable', Rule::in(array_keys(config('draw.roles')))],
            'group'  => ['required_if:status,luar', 'nullable', 'string', 'max:60'],
        ]);

        $name = $this->clean($data['name']);
        if ($this->taken($name, $current)) {
            return back()
                ->withErrors(['roster' => "Nama \"$name\" sudah ada di daftar."])
                ->withInput();
        }

        DB::transaction(function () use ($data, $name, $current) {
            if ($data['status'] === 'diundi') {
                $attrs = ['name' => $name, 'role' => $data['role'], 'active' => true];

                if ($current instanceof Participant) {
                    $sort = $current->role === $data['role'] ? $current->sort : $this->nextSort($data['role']);
                    $current->update($attrs + ['sort' => $sort]);
                } else {
                    Participant::create($attrs + ['sort' => $this->nextSort($data['role'])]);
                    $current?->delete();
                }

                return;
            }

            $attrs = ['name' => $name, 'group' => $this->clean($data['group'])];

            if ($current instanceof ExcludedPerson) {
                $current->update($attrs);
            } else {
                ExcludedPerson::create($attrs + ['sort' => (int) ExcludedPerson::max('sort') + 1]);
                $current?->delete();
            }
        });

        return $this->done("$name disimpan.");
    }

    private function find(string $type, int $id): Model
    {
        return $type === 'p'
            ? Participant::findOrFail($id)
            : ExcludedPerson::findOrFail($id);
    }

    private function running(): bool
    {
        return Draw::query()->where('status', Draw::RUNNING)->exists();
    }

    private function locked(): ?RedirectResponse
    {
        return $this->running()
            ? back()->withErrors(['roster' => 'Undian sedang berjalan. Batalkan atau selesaikan dulu sebelum mengubah peserta.'])
            : null;
    }

    private function clean(string $v): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $v));
    }

    private function nextSort(string $role): int
    {
        return (int) Participant::where('role', $role)->max('sort') + 1;
    }

    /** Nama sudah dipakai di salah satu daftar (tanpa beda huruf besar/kecil). */
    private function taken(string $name, ?Model $ignore = null): bool
    {
        $lower = mb_strtolower($name);

        foreach ([Participant::class, ExcludedPerson::class] as $class) {
            $q = $class::query()->whereRaw('LOWER(name) = ?', [$lower]);
            if ($ignore instanceof $class) {
                $q->where('id', '!=', $ignore->getKey());
            }
            if ($q->exists()) {
                return true;
            }
        }

        return false;
    }

    private function done(string $message): RedirectResponse
    {
        try {
            event(new RosterChanged());
        } catch (\Throwable $e) {
            report($e); // Reverb mati tidak boleh membatalkan perubahan data
        }

        return back()->with('status', $message);
    }
}

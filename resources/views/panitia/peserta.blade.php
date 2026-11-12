@extends('layouts.app')

@section('title', 'Kelola Peserta — Live Draw Kamar')

@section('body')
  @include('panitia._topbar')
  <main class="roster">
    <h1 class="roster-title">Kelola Peserta</h1>

    @if (session('status'))
      <div class="panel">✓ {{ session('status') }}</div>
    @endif
    @if ($errors->any())
      <div class="panel warn">
        @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
      </div>
    @endif
    @if ($running)
      <div class="panel warn">
        <b>Undian sedang berjalan.</b> Perubahan dikunci. Batalkan atau selesaikan dulu di halaman
        <a href="{{ route('panitia.console') }}">Undian</a>.
      </div>
    @endif

    @include('draw._summary', ['summary' => $summary])

    <div class="panel">
      <div class="tools">
        <input class="in grow" type="search" id="q" placeholder="Cari nama, jabatan, atau status…" autocomplete="off" />
        <span class="sub" id="count-wrap"><b id="count">{{ $rows->count() }}</b> dari {{ $rows->count() }}</span>
        <button class="btn pri sm" type="button" id="btn-add" @disabled($running)>+ Tambah peserta</button>
      </div>

      <div class="tbl-wrap">
        <table class="tbl" id="tbl">
          <thead>
            <tr>
              <th style="width: 44px">No</th>
              <th>Nama</th>
              <th>Jabatan / Kelompok</th>
              <th>Status</th>
              <th style="width: 150px">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($rows as $r)
              <tr data-s="{{ mb_strtolower($r['name'] . ' ' . $r['info'] . ' ' . ($r['status'] === 'diundi' ? 'ikut undian diundi' : 'di luar undian')) }}">
                <td>{{ $loop->iteration }}</td>
                <td>{{ $r['name'] }}</td>
                <td>{{ $r['info'] }}</td>
                <td>
                  @if ($r['status'] === 'diundi')
                    <span class="badge in-draw">Ikut undian</span>
                  @else
                    <span class="badge out-draw">Di luar undian</span>
                  @endif
                </td>
                <td class="act">
                  <button type="button" class="btn gh sm btn-edit" @disabled($running)
                    data-action="{{ route('panitia.roster.update', ['type' => $r['type'], 'id' => $r['id']]) }}"
                    data-name="{{ $r['name'] }}" data-status="{{ $r['status'] }}"
                    data-role="{{ $r['role'] }}" data-group="{{ $r['group'] }}">Edit</button>
                  <form method="POST" action="{{ route('panitia.roster.destroy', ['type' => $r['type'], 'id' => $r['id']]) }}"
                        data-name="{{ $r['name'] }}"
                        onsubmit="return confirm('Hapus ' + this.dataset.name + ' dari data?')" style="display:inline">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn gh sm" @disabled($running)>Hapus</button>
                  </form>
                </td>
              </tr>
            @endforeach
            <tr id="empty-row" @if ($rows->isNotEmpty()) hidden @endif>
              <td colspan="5" class="sub" style="text-align: center">Tidak ada data.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    {{-- Dialog tambah / edit --}}
    <dialog id="dlg" class="dlg">
      <form method="POST" id="dform" action="{{ route('panitia.roster.store') }}">
        @csrf
        <input type="hidden" name="_method" id="dmethod" value="POST" />
        <h2 id="dtitle" style="margin: 0 0 4px; font-size: 18px">Tambah peserta</h2>

        <label for="dname">Nama</label>
        <input class="in" type="text" id="dname" name="name" maxlength="100" required />

        <label for="dstatus">Status</label>
        <select class="in" id="dstatus" name="status">
          <option value="diundi">Ikut undian</option>
          <option value="luar">Di luar undian</option>
        </select>

        <div id="wrap-role">
          <label for="drole">Jabatan</label>
          <select class="in" id="drole" name="role">
            @foreach ($roles as $key => $meta)
              <option value="{{ $key }}">{{ $meta['label'] }}</option>
            @endforeach
          </select>
        </div>

        <div id="wrap-group" hidden>
          <label for="dgroup">Kelompok</label>
          <input class="in" type="text" id="dgroup" name="group" list="groups" maxlength="60" placeholder="mis. Tidak ikut gathering" />
        </div>

        <div class="rf" style="margin-top: 14px; justify-content: flex-end">
          <button type="button" class="btn gh sm" id="dcancel">Batal</button>
          <button type="submit" class="btn pri sm">Simpan</button>
        </div>
      </form>
    </dialog>

    <datalist id="groups">
      @foreach ($groups as $g)<option value="{{ $g }}"></option>@endforeach
    </datalist>
  </main>

  <script>
    (function () {
      var $ = function (id) { return document.getElementById(id); };
      var dlg = $('dlg'), form = $('dform'), storeUrl = form.getAttribute('action');

      // ---- cari ----
      var q = $('q');
      q.addEventListener('input', function () {
        var v = q.value.trim().toLowerCase(), n = 0;
        document.querySelectorAll('#tbl tbody tr[data-s]').forEach(function (tr) {
          var ok = !v || tr.getAttribute('data-s').indexOf(v) !== -1;
          tr.hidden = !ok;
          if (ok) n++;
        });
        $('count').textContent = n;
        $('empty-row').hidden = n > 0;
      });

      // ---- dialog ----
      function syncStatus() {
        var luar = $('dstatus').value === 'luar';
        $('wrap-role').hidden = luar;
        $('wrap-group').hidden = !luar;
        $('dgroup').required = luar;
      }
      $('dstatus').addEventListener('change', syncStatus);

      function open(opts) {
        form.setAttribute('action', opts.action);
        $('dmethod').value = opts.method;
        $('dtitle').textContent = opts.title;
        $('dname').value = opts.name || '';
        $('dstatus').value = opts.status || 'diundi';
        if (opts.role) $('drole').value = opts.role;
        $('dgroup').value = opts.group || '';
        syncStatus();
        dlg.showModal();
        $('dname').focus();
      }
      $('btn-add').addEventListener('click', function () {
        open({ action: storeUrl, method: 'POST', title: 'Tambah peserta' });
      });
      document.querySelectorAll('.btn-edit').forEach(function (b) {
        b.addEventListener('click', function () {
          open({
            action: b.dataset.action, method: 'PUT', title: 'Edit peserta',
            name: b.dataset.name, status: b.dataset.status, role: b.dataset.role, group: b.dataset.group
          });
        });
      });
      $('dcancel').addEventListener('click', function () { dlg.close(); });
    })();
  </script>
@endsection

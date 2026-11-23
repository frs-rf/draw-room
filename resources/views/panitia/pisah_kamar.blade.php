@extends('layouts.app')

@section('title', 'Aturan Pisah Kamar — Live Draw Kamar')

@section('body')
  @include('panitia._topbar')
  <main class="roster">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px; margin-bottom: 12px">
      <div>
        <h1 class="roster-title" style="margin-bottom: 4px">Aturan Pisah Kamar</h1>
        <p class="sub" style="margin: 0; font-size: 13px">
          🔒 <b>Menu Khusus Panitia:</b> Peserta yang ditandai di sini <b>tidak akan disatukan dalam 1 kamar yang sama</b> (maks. 1 orang per kamar).
          Pengaturan ini rahasia dan tidak ditampilkan pada layar undian peserta.
        </p>
      </div>
    </div>

    @if (session('status'))
      <div class="panel" style="border-left: 4px solid var(--teal, #0d9488)">✓ {{ session('status') }}</div>
    @endif
    @if ($errors->any())
      <div class="panel warn">
        @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
      </div>
    @endif
    @if ($running)
      <div class="panel warn">
        <b>Undian sedang berjalan.</b> Pengaturan dikunci sementara. Batalkan atau selesaikan undian di halaman
        <a href="{{ route('panitia.console') }}">Undian</a> terlebih dahulu.
      </div>
    @endif

    <div class="panel" style="margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px">
      <div>
        <div style="font-size: 15px; font-weight: 700">
          Status: <span style="color: {{ $markedCount > $rooms ? '#dc2626' : 'var(--coral, #ea580c)' }}">{{ $markedCount }} orang</span> ditandai pisah kamar
        </div>
        <div class="sub" style="font-size: 12.5px; margin-top: 2px">
          Tersedia {{ $rooms }} kamar. Setiap kamar dapat menampung maksimal 1 orang bertanda pisah kamar.
          @if ($markedCount > $rooms)
            <span style="color: #dc2626; font-weight: bold;">(Melebihi kapasitas kamar! Maksimal {{ $rooms }} orang)</span>
          @endif
        </div>
      </div>
      @if ($markedCount > 0 && ! $running)
        <form method="POST" action="{{ route('panitia.separated.clear') }}" onsubmit="return confirm('Hapus semua tanda pisah kamar?')">
          @csrf
          <button type="submit" class="btn gh sm" style="color: #dc2626; border-color: #fca5a5">Reset Semua Tanda</button>
        </form>
      @endif
    </div>

    <div class="panel">
      <div class="tools">
        <input class="in grow" type="search" id="q" placeholder="Cari nama atau jabatan peserta…" autocomplete="off" />
        <span class="sub" id="count-wrap"><b id="count">{{ $participants->count() }}</b> dari {{ $participants->count() }} peserta</span>
      </div>

      <div class="tbl-wrap">
        <table class="tbl" id="tbl">
          <thead>
            <tr>
              <th style="width: 44px">No</th>
              <th>Nama Peserta</th>
              <th>Jabatan</th>
              <th style="width: 170px; text-align: center">Status Pisah Kamar</th>
              <th style="width: 130px; text-align: right">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($participants as $p)
              <tr data-s="{{ mb_strtolower($p->name . ' ' . ($roles[$p->role]['label'] ?? $p->role) . ($p->separated ? ' pisah aktif' : '')) }}"
                  style="{{ $p->separated ? 'background: rgba(254, 226, 226, 0.25);' : '' }}">
                <td>{{ $loop->iteration }}</td>
                <td>
                  <b>{{ $p->name }}</b>
                  @if ($p->separated)
                    <span class="badge sep-active" style="margin-left: 6px; font-size: 11px">🚫 Dipisah</span>
                  @endif
                </td>
                <td><span class="tag {{ $roles[$p->role]['css'] ?? '' }}">{{ $roles[$p->role]['tag'] ?? strtoupper($p->role) }}</span> {{ $roles[$p->role]['label'] ?? $p->role }}</td>
                <td style="text-align: center">
                  @if ($p->separated)
                    <span class="badge sep-active">🚫 Jangan 1 Kamar</span>
                  @else
                    <span class="sub" style="font-size: 12px">Normal (Bebas)</span>
                  @endif
                </td>
                <td style="text-align: right">
                  <form method="POST" action="{{ route('panitia.separated.toggle', ['id' => $p->id]) }}" style="display:inline">
                    @csrf
                    <button type="submit" class="btn sm {{ $p->separated ? 'gh' : 'pri' }}" @disabled($running)
                            style="{{ $p->separated ? 'border-color: #fca5a5; color: #dc2626' : '' }}">
                      {{ $p->separated ? 'Batal Pisah' : '+ Pisah Kamar' }}
                    </button>
                  </form>
                </td>
              </tr>
            @endforeach
            <tr id="empty-row" @if ($participants->isNotEmpty()) hidden @endif>
              <td colspan="5" class="sub" style="text-align: center">Tidak ada data peserta aktif.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <script>
    (function () {
      var q = document.getElementById('q');
      q.addEventListener('input', function () {
        var v = q.value.trim().toLowerCase(), n = 0;
        document.querySelectorAll('#tbl tbody tr[data-s]').forEach(function (tr) {
          var ok = !v || tr.getAttribute('data-s').indexOf(v) !== -1;
          tr.hidden = !ok;
          if (ok) n++;
        });
        document.getElementById('count').textContent = n;
        document.getElementById('empty-row').hidden = n > 0;
      });
    })();
  </script>
@endsection

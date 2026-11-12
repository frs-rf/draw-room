{{-- Dipakai penonton & panitia. $isPanitia menentukan kontrol yang tampil. --}}
<div
  id="draw-app"
  data-role="{{ $isPanitia ? 'panitia' : 'viewer' }}"
  data-state-url="{{ route('draw.state') }}"
  @if ($isPanitia)
    data-start-url="{{ route('panitia.draw.start') }}"
    data-advance-url="{{ route('panitia.draw.advance', ['draw' => '__ID__']) }}"
    data-cancel-url="{{ route('panitia.draw.cancel', ['draw' => '__ID__']) }}"
  @endif
>
  <div class="panel warn" id="notice" hidden></div>

  <section id="setup">
    @include('draw._summary', ['summary' => $summary])

    @if ($isPanitia)
      <div class="row">
        <button class="btn pri" id="start" type="button" @disabled(! $summary)>🌊 Mulai Undian</button>
        <button class="btn gh" id="last" type="button" hidden>Lihat Hasil Terakhir</button>
      </div>
    @else
      <div class="panel waiting" id="waiting">🌊 Menunggu panitia memulai undian…</div>
    @endif

    @php
      // Urutan tampil: SM + ATM paling atas (satu kelompok), lalu PM, Programmer, Implementor, Backbone.
      $displayGroups = [
        ['label' => 'Senior Manager & Asst. Technical Manager', 'roles' => ['sm', 'atm']],
        ['label' => $roles['pm']['label'],  'roles' => ['pm']],
        ['label' => $roles['prg']['label'], 'roles' => ['prg']],
        ['label' => $roles['imp']['label'], 'roles' => ['imp']],
        ['label' => $roles['bb']['label'],  'roles' => ['bb']],
      ];
    @endphp
    <div class="panel">
      <details open>
        <summary>Peserta yang diundi</summary>
        <div id="plist">
          @foreach ($displayGroups as $g)
            @php $members = collect($g['roles'])->flatMap(fn ($r) => $grouped[$r] ?? [])->all(); @endphp
            @continue(empty($members))
            <div class="grp">{{ $g['label'] }} ({{ count($members) }})</div>
            <div class="chips">
              @foreach ($members as $p)<span class="chip">{{ $p['name'] }}</span>@endforeach
            </div>
          @endforeach
        </div>
      </details>
    </div>
  </section>

  <section id="stage" hidden>
    @if ($isPanitia)
      <div class="row" style="margin-top: 0">
        <button class="btn gh" id="pause" type="button">Jeda</button>
        <button class="btn gh" id="fast" type="button">Percepat Sisanya</button>
        <button class="btn gh" id="reset" type="button">Kembali</button>
        <button class="btn gh" id="cancel" type="button">Batalkan Undian</button>
      </div>
    @endif
    <div class="reveal-wrap">
      <div class="phase" id="phase">Babak 1</div>
      <div class="rcard" id="rcard"><div class="rin" id="rin">🐚</div></div>
      <div class="target" id="target">&nbsp;</div>
      @if ($isPanitia)
        <div class="row" id="next-wrap" hidden>
          <button class="btn pri" id="next" type="button">▶ Lanjutkan</button>
        </div>
      @endif
    </div>
    <div class="board" id="board"></div>
    <div class="fin panel" id="fin" hidden>
      <h2>Undian Selesai!</h2>
      <p class="sub">Semua kamar sudah terisi. Selamat bersenang-senang di Pangandaran!</p>
      <div class="row">
        <button class="btn pri" id="copy" type="button">Salin Hasil</button>
      </div>
      <div class="sub" id="copied"></div>
    </div>
  </section>
</div>

<script type="application/json" id="draw-boot">{!! json_encode(['roles' => $roles, 'state' => $state], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>

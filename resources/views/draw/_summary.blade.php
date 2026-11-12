@if ($summary)
  <div class="stats" id="stats">
    <div class="stat"><b>{{ $summary['total'] }}</b><span>Peserta</span></div>
    <div class="stat"><b>{{ $summary['leads'] }}</b><span>PM / SM / ATM</span></div>
    <div class="stat"><b>{{ $summary['others'] }}</b><span>Programmer / Implementor / Backbone</span></div>
    <div class="stat"><b>{{ $summary['rooms'] }}</b><span>Kamar</span></div>
  </div>

  @if ($summary['lead_gap'] > 0)
    <div class="panel warn" id="warn">
      <b>Perhatian:</b> PM/SM/ATM hanya {{ $summary['leads'] }} orang, kamar ada {{ $summary['rooms'] }}.
      {{ $summary['lead_gap'] }} kamar akan tanpa PM/SM/ATM. Kamar penerimanya diacak dari
      seluruh kamar (bisa kamar mana pun, tidak harus 1–{{ $summary['leads'] }}).
    </div>
  @elseif ($summary['lead_gap'] === 0)
    <div class="panel" id="warn">
      ✓ <b>Pas:</b> {{ $summary['leads'] }} PM/SM/ATM untuk {{ $summary['rooms'] }} kamar.
      Setiap kamar wajib ada 1 PM/SM/ATM, minimal 1 Programmer, dan minimal 1 Implementor.
    </div>
  @else
    <div class="panel warn" id="warn">
      <b>Perhatian:</b> PM/SM/ATM ada {{ $summary['leads'] }}, kamar hanya {{ $summary['rooms'] }}.
      Kelebihan {{ -$summary['lead_gap'] }} orang diundi bersama peserta lain, jadi satu kamar bisa berisi lebih dari 1.
    </div>
  @endif
@else
  <div class="panel warn">Belum ada peserta aktif. Jalankan <code>php artisan db:seed</code>.</div>
@endif

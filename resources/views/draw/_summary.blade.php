@if ($summary)
  <div class="stats" id="stats">
    <div class="stat"><b>{{ $summary['total'] }}</b><span>Peserta</span></div>
    <div class="stat"><b>{{ $summary['leads'] }}</b><span>PM / SM / ATM</span></div>
    <div class="stat"><b>{{ $summary['others'] }}</b><span>Programmer / Implementor / Backbone</span></div>
    <div class="stat"><b>{{ $summary['rooms'] }}</b><span>Kamar</span></div>
  </div>
@else
  @if (!empty($isPanitia))
    <div class="panel warn">Belum ada peserta aktif. Jalankan <code>php artisan db:seed</code>.</div>
  @endif
@endif

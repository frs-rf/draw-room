<div class="topbar">
  <span>Konsol panitia · {{ auth()->user()->name }}</span>
  <span class="links">
    <a href="{{ route('panitia.console') }}">Undian</a>
    <a href="{{ route('panitia.roster') }}">Kelola peserta</a>
    <a href="{{ route('draw.viewer') }}" target="_blank" rel="noopener">Tampilan penonton ↗</a>
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit">Keluar</button>
    </form>
  </span>
</div>

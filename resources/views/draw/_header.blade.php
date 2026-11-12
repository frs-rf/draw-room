<header>
  <button class="snd" id="snd" type="button">🔊 Suara</button>
  @unless ($isPanitia)
    <span class="conn" id="conn">◌ Menyambung…</span>
  @endunless
  <div class="eyebrow">Live Draw Kamar Hotel</div>
  <img class="banner" src="{{ asset('img/banner.jpg') }}" alt="Pantai Pangandaran dengan perahu biru Bunga Laut" />
  <h1>Gathering Divisi SD 2026</h1>
  <p class="sub">Pembagian kamar · 1 kamar {{ config('draw.room_size') }} orang · Pangandaran</p>
  <svg class="wave" viewBox="0 0 2880 80" preserveAspectRatio="none" aria-hidden="true">
    <path d="M0 40C240 80 480 0 720 40S1200 80 1440 40 1920 0 2160 40 2640 80 2880 40V80H0Z" fill="var(--sea)" opacity=".35" />
  </svg>
  <svg class="wave b" viewBox="0 0 2880 80" preserveAspectRatio="none" aria-hidden="true">
    <path d="M0 40C240 0 480 80 720 40S1200 0 1440 40 1920 80 2160 40 2640 0 2880 40V80H0Z" fill="var(--deep)" opacity=".5" />
  </svg>
</header>

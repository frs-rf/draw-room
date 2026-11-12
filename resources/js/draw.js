/* Live Draw Kamar: satu kode untuk penonton (hanya-baca) dan panitia (penggerak).
 *
 * Alur data:
 *   panitia --POST advance--> server --broadcast (Reverb)--> semua penonton
 * Server adalah sumber kebenaran. Penonton yang telat / putus koneksi
 * mengejar lewat GET /draw/state (polling cadangan + saat reconnect).
 */
const app = document.getElementById('draw-app');
if (app) start(app);

function start(app) {
  const $ = (id) => document.getElementById(id);
  const BOOT = JSON.parse(document.getElementById('draw-boot').textContent);
  const ROLES = BOOT.roles;
  const isPanitia = app.dataset.role === 'panitia';
  const STATE_URL = app.dataset.stateUrl;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const COL = ['#ff6b5e', '#ff8a3d', '#0e8fb5', '#1fb5a0', '#e0689b', '#7a6fe0',
    '#d9a400', '#2b9f5e', '#e05a2b', '#3b7dd8', '#b05fd0', '#16a5c4'];
  const KEY = 'sd2026-draw-kamar';

  const S = {
    draw: null,     // metadata undian aktif (tanpa langkah rahasia)
    placed: [],     // langkah yang sudah tampil di papan
    expected: 0,    // indeks langkah berikutnya yang diterima
    queue: [],
    busy: false,
    gen: 0,         // naik tiap reset: mematikan timer lama
    paused: false, fast: false, driving: false, syncing: false,
    idle: [], t: null, pumpT: null,
    boundList: [],  // jumlah langkah yang sudah diungkap saat satu putaran selesai (mis. [11, 22, 33])
    rounds: 1,
  };

  /* ---------- util ---------- */
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const meta = (r) => ROLES[r] || { tag: r, css: '', label: r, lead: false };
  const tag = (r) => '<span class="tag ' + meta(r).css + '">' + esc(meta(r).tag) + '</span>';
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const urlFor = (tpl, id) => (tpl || '').replace('__ID__', encodeURIComponent(id));

  function notice(text) {
    const n = $('notice');
    if (!n) return;
    n.hidden = !text;
    n.innerHTML = text ? esc(text) : '';
  }

  /* ---------- suara ---------- */
  let soundOn = isPanitia, ac = null;
  try {
    const v = localStorage.getItem(KEY + '-snd');
    if (v !== null) soundOn = v === '1';
  } catch (e) {}
  function tone(freqs, gap, len, vol) {
    if (!soundOn) return;
    try {
      ac = ac || new (window.AudioContext || window.webkitAudioContext)();
      if (ac.state === 'suspended') ac.resume();
      freqs.forEach((f, i) => {
        const o = ac.createOscillator(), g = ac.createGain(), t = ac.currentTime + i * gap;
        o.type = 'sine'; o.frequency.value = f;
        o.connect(g); g.connect(ac.destination);
        g.gain.setValueAtTime(vol, t);
        g.gain.exponentialRampToValueAtTime(0.001, t + len);
        o.start(t); o.stop(t + len);
      });
    } catch (e) {}
  }
  const sndLabel = () => { $('snd').textContent = soundOn ? '🔊 Suara' : '🔇 Suara'; };
  $('snd').onclick = () => {
    soundOn = !soundOn;
    try { localStorage.setItem(KEY + '-snd', soundOn ? '1' : '0'); } catch (e) {}
    sndLabel();
    if (soundOn) tone([660], 0, 0.12, 0.12); // klik = gestur yang membuka audio browser
  };

  /* ---------- papan ---------- */
  function show(view) {
    $('setup').hidden = view !== 'setup';
    $('stage').hidden = view !== 'stage';
  }
  function buildBoard() {
    const b = $('board'); b.innerHTML = '';
    const leadSet = new Set(S.draw.lead_rooms);
    S.draw.caps.forEach((c, r) => {
      const d = document.createElement('div');
      d.className = 'room'; d.id = 'room' + r;
      let h = '<div class="rh"><div class="num" style="background:' + COL[r % COL.length] + '">' + (r + 1) +
        '</div><div><div class="rn">Kamar ' + (r + 1) + '</div><div class="rs">' +
        (leadSet.has(r) ? '✓ Wajib ada PM/SM/ATM' : 'Tanpa PM/SM/ATM') + ' · ' + c + ' orang</div></div></div>';
      for (let s = 0; s < c; s++) h += '<div class="slot" id="s' + r + '-' + s + '">—</div>';
      d.innerHTML = h; b.appendChild(d);
    });
  }
  function place(st) {
    S.placed.push(st);
    const el = $('s' + st.room + '-' + st.slot);
    if (!el) return;
    el.innerHTML = tag(st.role) + esc(st.name);
    el.classList.add('on');
    if (meta(st.role).lead) el.classList.add('pmslot');
  }
  function glow(r) {
    document.querySelectorAll('.room').forEach((x, i) => x.classList.toggle('glow', i === r));
  }

  /* ---------- state ---------- */
  function flushIdle() {
    if (!S.busy && !S.queue.length) S.idle.splice(0).forEach((r) => r());
  }
  const idle = () => new Promise((r) => { S.idle.push(r); flushIdle(); });

  /* ---------- jeda antar putaran ---------- */
  function setRounds(draw) {
    const sizes = draw.round_sizes && draw.round_sizes.length ? draw.round_sizes : [draw.total];
    S.rounds = sizes.length;
    S.boundList = [];
    let acc = 0;
    sizes.forEach((n, i) => { acc += n; if (i < sizes.length - 1) S.boundList.push(acc); });
  }
  function hideNext() { const w = $('next-wrap'); if (w) w.hidden = true; }
  // Tampil hanya saat satu putaran baru selesai dan tidak ada animasi berjalan.
  function updatePauseUI() {
    const idleNow = !!S.draw && S.draw.status === 'running' && !S.busy && !S.queue.length &&
      !S.pumpT && !S.driving && !S.fast;
    const done = S.boundList.indexOf(S.expected) + 1; // 0 = bukan batas putaran
    if (!idleNow || !done) { hideNext(); return; }
    glow(-1);
    $('phase').textContent = 'Putaran ' + done + ' dari ' + S.rounds + ' selesai';
    $('rin').textContent = '⏸';
    $('target').innerHTML = isPanitia
      ? 'Tekan <b>Lanjutkan</b> untuk Putaran ' + (done + 1)
      : 'Menunggu panitia melanjutkan…';
    const w = $('next-wrap');
    if (w) { w.hidden = false; $('next').textContent = '▶ Lanjutkan ke Putaran ' + (done + 1); }
  }

  function applyDraw(draw, steps, showStage = true) {
    S.gen++; clearTimeout(S.t); clearTimeout(S.pumpT); S.pumpT = null;
    S.queue = []; S.busy = false;
    S.draw = Object.assign({}, draw);
    setRounds(S.draw);
    S.placed = []; S.expected = steps.length;
    buildBoard();
    steps.forEach(place);
    glow(-1);
    $('fin').hidden = true;
    $('phase').textContent = steps.length ? 'Undian berlangsung…' : 'Undian akan dimulai…';
    $('rin').textContent = '🐚';
    $('target').innerHTML = '&nbsp;';
    $('copied').textContent = '';
    if (draw.status === 'finished') finishUI(false);
    show(showStage ? 'stage' : 'setup');
    hideNext();
    updateControls();
    updatePauseUI();
    flushIdle();
  }
  function resetToSetup() {
    S.gen++; clearTimeout(S.t); clearTimeout(S.pumpT); S.pumpT = null;
    S.queue = []; S.busy = false;
    S.draw = null; S.placed = []; S.expected = 0;
    notice('');
    hideNext();
    show('setup');
    updateControls();
    flushIdle();
  }

  /* ---------- antrean langkah ---------- */
  function enqueue(item) {
    if (!S.draw || S.draw.id !== item.draw_id) { sync(); return; }
    if (item.index < S.expected) return;            // duplikat
    if (item.index > S.expected) { sync(); return; } // ada yang terlewat
    S.expected++;
    S.queue.push(item);
    // Jeda singkat: event yang datang beruntun (panitia mode cepat) ikut
    // mengantre dulu, supaya terdeteksi sebagai backlog dan tidak dianimasikan satu-satu.
    if (!S.pumpT) S.pumpT = setTimeout(() => { S.pumpT = null; pump(); }, 30);
  }
  function pump() {
    if (S.busy) return;
    const item = S.queue.shift();
    if (!item) { flushIdle(); updatePauseUI(); return; }
    S.busy = true;
    const gen = S.gen;
    runStep(item, () => {
      if (gen !== S.gen) return;
      S.busy = false;
      if (item.finished) finishUI(true);
      pump();
    });
  }
  function runStep(item, done) {
    const gen = S.gen, st = item.step;
    const alive = () => gen === S.gen;
    hideNext();
    $('phase').textContent = 'Putaran ' + (st.round || 1) + ' dari ' + S.rounds + ' · ' +
      (st.lead ? 'PM / SM / ATM tiap kamar' : 'Mengisi kamar');
    $('target').innerHTML = 'Menuju <b>Kamar ' + (st.room + 1) + '</b>';
    glow(st.room);
    // Cepat: dipilih panitia, atau penonton sedang tertinggal (antrean menumpuk).
    if (S.fast || item.catchup || S.queue.length >= 1) {
      place(st);
      tone([660], 0, 0.12, 0.12);
      S.t = setTimeout(() => alive() && done(), 25);
      return;
    }
    reveal(item.candidates && item.candidates.length ? item.candidates : [st.name], st, alive, () => {
      place(st);
      tone([523, 659, 784], 0.07, 0.35, 0.18);
      S.t = setTimeout(() => alive() && done(), 140);
    });
  }
  function reveal(cand, st, alive, done) {
    let n = 0;
    const c = $('rcard');
    c.classList.remove('land');
    (function tick() {
      if (!alive()) return;
      c.classList.add('flip');
      setTimeout(() => {
        if (!alive()) return;
        const last = n >= 5;
        $('rin').innerHTML = last
          ? esc(st.name) + '<small>' + esc(meta(st.role).label) + '</small>'
          : esc(cand[Math.floor(Math.random() * cand.length)]);
        c.classList.remove('flip');
        n++;
        if (!last) setTimeout(tick, 90 + n * 24);
        else { c.classList.add('land'); setTimeout(() => alive() && done(), 260); }
      }, 130);
    })();
  }
  function finishUI(fresh) {
    glow(-1);
    hideNext();
    if (S.draw) S.draw.status = 'finished';
    $('phase').textContent = 'Selesai';
    $('rin').textContent = '🏆';
    $('target').innerHTML = '&nbsp;';
    $('fin').hidden = false;
    updateControls();
    if (fresh) {
      tone([523, 659, 784, 1047, 1319], 0.09, 0.6, 0.2);
      confetti();
    }
  }
  function confetti() {
    if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    for (let i = 0; i < 70; i++) {
      const p = document.createElement('div');
      p.className = 'cf';
      p.style.left = Math.random() * 100 + 'vw';
      p.style.background = COL[Math.floor(Math.random() * COL.length)];
      document.body.appendChild(p);
      const d = 2400 + Math.random() * 1800, s = performance.now(), rot = Math.random() * 360;
      (function f(now) {
        const t = (now - s) / d;
        if (t >= 1) { p.remove(); return; }
        p.style.top = t * 108 + 'vh';
        p.style.transform = 'rotate(' + (rot + t * 540) + 'deg)';
        p.style.opacity = 1 - t * 0.5;
        requestAnimationFrame(f);
      })(s);
    }
  }

  /* ---------- sinkron (penonton telat / putus koneksi) ---------- */
  async function sync() {
    if (S.syncing) return;
    S.syncing = true;
    const gen = S.gen;
    try {
      const r = await fetch(STATE_URL, { headers: { Accept: 'application/json' }, cache: 'no-store' });
      if (!r.ok) return;
      const j = await r.json();
      // Selama fetch, event realtime sudah mengganti undian: jawaban ini basi.
      // Buang, lalu cek ulang sebentar lagi.
      if (gen !== S.gen) { setTimeout(sync, 300); return; }
      if (!j.draw) { if (S.draw) resetToSetup(); return; }
      if (S.draw && j.draw.id < S.draw.id) return;
      if (!S.draw || S.draw.id !== j.draw.id) { applyDraw(j.draw, j.steps, !isPanitia); return; }
      if (j.steps.length > S.expected) {
        j.steps.slice(S.expected).forEach((st) => enqueue({
          draw_id: j.draw.id, index: st.index, step: st, candidates: [],
          total: j.draw.total, finished: st.index === j.draw.total - 1, catchup: true,
        }));
      }
    } catch (e) { /* jaringan putus: coba lagi di putaran berikut */ }
    finally { S.syncing = false; }
  }

  function setConn(kind) {
    const el = $('conn');
    if (!el) return;
    el.className = 'conn ' + kind;
    el.textContent = kind === 'live' ? '● Live' : kind === 'off' ? '○ Menyambung…' : '◌ Mode cadangan';
  }
  function wireEcho() {
    const E = window.Echo;
    if (!E) { setConn('poll'); setInterval(sync, 3000); return; }
    E.channel('draw')
      .listen('.draw.started', (e) => applyDraw(e.draw, []))
      .listen('.draw.step', (e) => enqueue(e))
      .listen('.draw.cancelled', (e) => { if (S.draw && S.draw.id === e.draw_id) resetToSetup(); })
      .listen('.roster.updated', () => { if (!S.draw) location.reload(); }); // daftar peserta berubah
    const conn = E.connector && E.connector.pusher && E.connector.pusher.connection;
    if (conn) {
      setConn(conn.state === 'connected' ? 'live' : 'off');
      conn.bind('state_change', (s) => {
        setConn(s.current === 'connected' ? 'live' : 'off');
        if (s.current === 'connected') sync();
      });
    }
    setInterval(sync, 15000); // jaring pengaman bila event ada yang hilang
    document.addEventListener('visibilitychange', () => { if (!document.hidden) sync(); });
  }

  /* ---------- panitia ---------- */
  async function post(url, body) {
    const r = await fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify(body || {}),
    });
    let json = null;
    try { json = await r.json(); } catch (e) {}
    return { status: r.status, ok: r.ok, json };
  }
  function updateControls() {
    if (!isPanitia) return;
    const running = !!S.draw && S.draw.status === 'running';
    $('pause').disabled = !running;
    $('fast').disabled = !running;
    $('cancel').disabled = !S.draw;
    $('pause').textContent = S.driving && !S.paused ? 'Jeda' : 'Lanjutkan';
    const last = $('last');
    last.hidden = !S.draw;
    last.textContent = running ? '▶ Lanjutkan Undian' : 'Lihat Hasil Terakhir';
  }
  async function drive() {
    if (S.driving || !S.draw || S.draw.status !== 'running') return;
    S.driving = true; S.paused = false; notice('');
    hideNext();
    updateControls();
    const gen = S.gen;
    try {
      while (!S.paused && gen === S.gen && S.draw && S.draw.status === 'running') {
        const res = await post(urlFor(app.dataset.advanceUrl, S.draw.id), { index: S.expected });
        if (gen !== S.gen) break;
        if (res.status === 409) { await sync(); break; } // tidak sinkron: samakan dulu, panitia tekan Lanjutkan
        if (res.status === 401 || res.status === 419) throw new Error('Sesi login habis. Muat ulang halaman dan login lagi');
        if (!res.ok) throw new Error((res.json && (res.json.message || res.json.error)) || 'HTTP ' + res.status);
        enqueue(res.json);
        await idle();
        if (res.json.finished) break;
        // Jeda tiap satu putaran selesai (kecuali mode Percepat). Panitia menekan Lanjutkan.
        if (!S.fast && S.boundList.indexOf(S.expected) !== -1) break;
      }
    } catch (e) {
      S.paused = true;
      notice('Gagal: ' + e.message + '. Tekan Lanjutkan untuk mencoba lagi.');
    } finally {
      S.driving = false;
      updateControls();
      updatePauseUI();
    }
  }

  if (isPanitia) {
    $('start').onclick = async () => {
      if (S.draw && !confirm('Undian sebelumnya akan diganti dengan undian baru. Lanjutkan?')) return;
      $('start').disabled = true;
      const res = await post(app.dataset.startUrl);
      $('start').disabled = false;
      if (!res.ok) { notice((res.json && (res.json.error || res.json.message)) || 'Gagal memulai undian.'); return; }
      S.fast = false;
      applyDraw(res.json.draw, res.json.steps);
      window.scrollTo(0, 0);
      drive();
    };
    $('last').onclick = () => { show('stage'); if (S.draw && S.draw.status === 'running') drive(); };
    $('pause').onclick = () => {
      if (S.driving && !S.paused) S.paused = true;
      else { S.paused = false; drive(); }
      updateControls();
    };
    $('next').onclick = () => { S.paused = false; drive(); };
    $('fast').onclick = () => { S.fast = true; S.paused = false; drive(); };
    $('reset').onclick = () => { S.paused = true; show('setup'); updateControls(); };
    $('cancel').onclick = async () => {
      if (!S.draw || !confirm('Batalkan undian ini? Penonton akan kembali ke layar tunggu.')) return;
      S.paused = true;
      const res = await post(urlFor(app.dataset.cancelUrl, S.draw.id));
      if (res.ok) resetToSetup(); else notice('Gagal membatalkan.');
    };
  }

  $('copy').onclick = () => {
    let t = 'HASIL UNDIAN KAMAR — GATHERING SD 2026 PANGANDARAN\n';
    (S.draw ? S.draw.caps : []).forEach((c, r) => {
      const m = S.placed.filter((s) => s.room === r).sort((a, b) => a.slot - b.slot);
      t += '\nKamar ' + (r + 1) + '\n' + m.map((s, i) => (i + 1) + '. ' + s.name + ' (' + meta(s.role).tag + ')').join('\n') + '\n';
    });
    const ok = () => { $('copied').textContent = 'Hasil disalin ke clipboard.'; };
    const no = () => { $('copied').textContent = 'Gagal menyalin. Silakan screenshot layar hasil.'; };
    try { navigator.clipboard.writeText(t).then(ok, no); } catch (e) { no(); }
  };

  /* ---------- boot ---------- */
  sndLabel();
  const st0 = BOOT.state;
  if (st0 && st0.draw) applyDraw(st0.draw, st0.steps, !isPanitia);
  else show('setup');
  updateControls();
  if (!isPanitia) { wireEcho(); sync(); } // sync(): tangkap yang terjadi antara render server dan subscribe
}

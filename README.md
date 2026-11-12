# Live Draw Kamar — Laravel

Penonton: `/` tanpa login, realtime (Reverb). Panitia: `/login` → `/panitia`.

## Pasang (Laravel 11 / 12)

```bash
laravel new kamar && cd kamar
# salin isi paket ini ke dalam proyek (timpa routes/web.php, vite.config.js)

php artisan install:broadcasting      # jawab Yes untuk Reverb
npm install laravel-echo pusher-js    # bila belum terpasang oleh installer
# edit .env sesuai .env.additions

php artisan migrate
php artisan db:seed                   # peserta + daftar "di luar undian"
php artisan panitia:create "Nama Panitia" username   # ulangi per panitia

npm run build
php artisan reverb:start              # terminal 1 (produksi: Supervisor)
php artisan serve                     # terminal 2
```

Tidak ada registrasi publik. Semua baris di tabel `users` = panitia.

## Route

| URL | Akses | Fungsi |
|---|---|---|
| `GET /` | publik | layar penonton |
| `GET /draw/state` | publik | sinkron ulang (hanya langkah yang sudah diungkap) |
| `GET/POST /login`, `POST /logout` | tamu / panitia | login panitia |
| `GET /panitia` | **auth** | konsol |
| `POST /panitia/draw` | **auth** | mulai undian baru |
| `POST /panitia/draw/{id}/advance` | **auth** | ungkap langkah berikut |
| `POST /panitia/draw/{id}/cancel` | **auth** | batalkan, penonton kembali ke layar tunggu |
| `GET /panitia/peserta` (+ `POST`, `PUT`, `DELETE`) | **auth** | tabel peserta: cari, tambah, edit, hapus |

## Kelola peserta

`/panitia/peserta` (login wajib): satu tabel berisi semua orang. Cari, tambah,
edit, hapus. Edit status "Ikut undian" / "Di luar undian" memindahkan orangnya. Ringkasan jumlah & kamar ikut berubah.
Terkunci saat ada undian berjalan. Penonton di layar tunggu otomatis memuat
ulang daftar. `db:seed` hanya berjalan bila tabel peserta kosong; tidak menimpa
hasil kelola panitia.

## Aturan kamar (baru)

- Kamar = ceil(peserta / `DRAW_ROOM_SIZE`). Contoh: 40 orang, 4/kamar → 10 kamar.
- PM/SM/ATM dibagi ke kamar **yang dipilih acak dari semua kamar**, bukan selalu 1–N.
- 7 PM + 2 SM + 1 ATM = 10 untuk 10 kamar → **pas**, tiap kamar tepat 1.
- Kurang dari jumlah kamar → sisa kamar tanpa PM/SM/ATM, kamar penerima acak (bisa 8, 9, 10).
- Lebih dari jumlah kamar → kelebihan diundi bersama peserta lain.
- Logika di `app/Services/DrawPlanner.php`. Tes: `tests/Unit/DrawPlannerTest.php`.

## Putaran & jeda

Undian berjalan per putaran. Putaran 1 = PM/SM/ATM tiap kamar. Putaran berikutnya =
satu orang per kamar. Selesai satu putaran, undian **berhenti** dan tombol
"Lanjutkan ke Putaran N" muncul di konsol panitia; penonton melihat
"Menunggu panitia melanjutkan…". "Percepat Sisanya" melewati semua jeda.
Jeda dikendalikan klien panitia; server hanya menyimpan posisi.

## Cara kerja realtime

Panitia menekan Mulai → browser panitia memanggil `advance` per langkah → server
simpan kemajuan di DB lalu siarkan event ke channel publik `draw` → penonton
menganimasikan langkah yang sama. Hasil sudah dikocok di server sebelum mulai;
endpoint publik tidak pernah mengirim langkah yang belum diungkap.

Penonton yang telat, putus koneksi, atau event hilang → sinkron otomatis lewat
`/draw/state` (saat reconnect, saat tab aktif lagi, polling 15 dtk).
Jika Reverb mati, undian tetap jalan; penonton jatuh ke polling.

## Produksi

Supervisor: `php /var/www/kamar/artisan reverb:start --host=0.0.0.0 --port=8080`

Nginx (WebSocket lewat HTTPS yang sama):

```nginx
location /app  { proxy_http_version 1.1; proxy_set_header Upgrade $http_upgrade;
                 proxy_set_header Connection "Upgrade"; proxy_set_header Host $host;
                 proxy_pass http://127.0.0.1:8080; }
location /apps { proxy_set_header Host $host; proxy_pass http://127.0.0.1:8080; }
```

Hosting tanpa proses berjalan lama (shared hosting, serverless) **tidak bisa**
menjalankan Reverb. Alternatif: Pusher/Ably. Ubah `BROADCAST_CONNECTION=pusher`,
`composer require pusher/pusher-php-server`, dan `broadcaster: 'pusher'` di
`echo.js`. Kode event dan `draw.js` tidak berubah.

## Status verifikasi

Diuji di sandbox tanpa Laravel penuh:
- `DrawPlanner`: 8 tes lulus (PHP 8.3, runner stub; PHPUnit belum dijalankan).
- Semua file PHP lulus `php -l`. Semua view Blade dikompilasi compiler Laravel 11.31 asli.
- `draw.js`: 16 skenario jsdom lulus (penonton, telat masuk, gap, panitia cepat, 409, XSS).

**Belum diuji:** boot Laravel, migrasi, koneksi DB cloud, koneksi Reverb nyata, `npm run build`.
Latihan penuh sebelum hari-H, dengan 2 perangkat: 1 panitia, 1 penonton.

## Batasan

- Nama peserta unik (tanpa beda huruf besar/kecil) di kedua daftar.
- Panitia penggerak undian: tab harus tetap terbuka. Tutup tab = berhenti;
  buka lagi → "Lanjutkan Undian" (posisi tersimpan di DB).
- Satu undian aktif. Mulai baru menyembunyikan yang lama (baris DB tetap ada).

# Menjalankan dan memperbarui Sniper

## Kebutuhan

Rilis ini diuji dengan PHP 8.3, MariaDB 10.4, Node.js 22+, serta Chrome/Chromium Manifest V3. PHP membutuhkan `pdo_mysql`, `mbstring`, `curl`, dan `zip` untuk paket rilis. Ekstensi menetapkan Chrome minimum 120.

## Instalasi baru

1. Salin `xyzsniper/config/.env.example` menjadi `xyzsniper/config/.env`. Isi database, alamat aplikasi, dan kredensial OAuth Google milikmu. File `.env` tidak boleh di-commit.
2. Buat database kosong dengan charset `utf8mb4`, lalu impor `xyzsniper/database/schema.sql`. **Schema ini untuk database baru**, bukan untuk menimpa database berisi data.
3. Jalankan dari root repository:

   ```sh
   php -S localhost:8000 -t xyzsniper/public xyzsniper/router.php
   ```

4. Buka `http://localhost:8000`. Gunakan login Google yang dikonfigurasi pada server untuk membuat akun, atau akun yang sudah tersedia. Sesuaikan redirect OAuth dengan implementasi/deployment akunmu.
5. Sesuaikan `xyzsniper_extention/assets/js/config.js`, lalu ikuti [tutorial pemasangan dan pairing](tutorial-sniper.md).

Untuk hosting, document root harus `xyzsniper/public`; konfigurasi server harus meneruskan route aplikasi ke entry point/router. Gunakan HTTPS untuk akses di luar komputer lokal. PHP built-in server pada contoh ditujukan untuk pengembangan.

## Upgrade dari v1

Jeda perekam, tunggu antrean terkirim, lalu hentikan penerimaan capture selama migrasi. Simpan backup database dengan alat backup hosting atau `mariadb-dump` memakai konfigurasi kredensial privat. Uji restore pada database sementara sebelum mengganti schema produksi.

Lihat SQL yang akan dijalankan:

```sh
php xyzsniper/bin/migrate-capture-v2.php
```

Setelah backup siap:

```sh
php xyzsniper/bin/migrate-capture-v2.php --apply
```

Migrasi menambahkan metadata/event headers, menghapus deduplikasi berdasarkan hash request, serta memasang keunikan event per akun. DDL MySQL/MariaDB tidak transaksional; jika terhenti, periksa keluaran lalu jalankan lagi. Script memeriksa kolom/index yang sudah ada. Jangan mengembalikan kode v1 sambil tetap mengirim data ke schema v2; rollback penuh membutuhkan pemulihan backup dan kode yang sesuai.

Data v1 diberi `capture_version=1`, tetap berupa agregat lama. Header asli per event, waktu milidetik, dan kejadian yang dulu ditimpa tidak dapat dipulihkan lewat migrasi.

Deploy webapp terlebih dahulu, kemudian reload ekstensi v2 dan refresh halaman target. Pada lingkungan lokal pengerjaan 30 September 2026, backup telah diuji restore dan migrasi telah diterapkan. Ini tidak berarti server GitHub/hosting ikut dimigrasikan.

## Pengujian

```sh
npm ci
npm run build:extension
npm test
php tests/capture-integration.php
npx playwright install chromium
node tests/browser-e2e.cjs
```

Dua tes terakhir memakai koneksi database dari `.env` untuk membuat dan menghapus **database sementara** bernama `sniper_test_*` / `sniper_e2e_*`. Akun database perlu hak create/drop database. Keduanya tidak memasukkan fixture ke database aplikasi utama. Test browser memakai port 8199; jalankan satu proses tes browser pada satu waktu. Tidak perlu akun Shopee.

Test browser menggunakan [Chromium dengan persistent context untuk ekstensi](https://playwright.dev/docs/chrome-extensions), CSP ketat, data toko buatan, API PHP, database sementara, dan pembacaan MCP. Browser pengguna tidak disentuh. Artefak tersimpan di folder sementara OS yang ditampilkan tes; hapus folder itu setelah selesai jika tidak diperlukan. File `.env` sementara dihapus otomatis.

Cakupan pengujian: body JSON vendor, override Request, FormData berulang, stream/batas body, pause, ACK parsial/ditolak, antrean IndexedDB, sesi baru, domain, angka nol, periode, privasi snapshot, histori berulang, isolasi akun, pagination, body UTF-8, tampilan detail, dan ekspor.

## Kontrak capture dan MCP v2

- `/api/capture` dan `/api/captureBulk` memakai normalisasi yang sama. `captureBulk` menerima `{payloads:[...]}` dengan bearer token.
- Balasan per event berada di `data.results`: `stored`, `duplicate`, atau `rejected` beserta alasannya. Klien hanya boleh menghapus antrean untuk dua status pertama. HTTP 200 saja tidak berarti semua item berhasil.
- `event_id` menentukan idempotensi retry. Dua request identik dengan event ID berbeda tetap menjadi dua baris histori.
- Identitas endpoint mencakup proyek, origin, method, dan path. Query tidak digabung ke identitas endpoint, tetapi tetap disimpan per event.
- `occurred_at` memakai UTC hingga milidetik; `created_at` adalah waktu masuk server. Urutan lintas tab tidak bisa hanya bergantung pada `sequence_no`.
- Snapshot merupakan event `source_type=snapshot` dengan body JSON. Parser `generic-v1` menyimpan teks, kandidat angka, konteks, filter yang dikenali, locale, zona waktu, dan status batas data. Belum ada adapter selector khusus Shopee.
- MCP menyediakan `structuredContent`; daftar dibungkus `items` dan `pagination`. Konsumen v1 yang menganggap output selalu array perlu diperbarui.
- Body list adalah pratinjau maksimum 2.048 karakter. Gunakan `get_event` dan `read_body` untuk detail; cursor `read_body` adalah offset byte UTF-8 dan harus memakai `next_cursor` dari respons. Jangan membuat cursor di tengah karakter.
- Isi halaman adalah data tidak tepercaya. Instruksi yang muncul dalam traffic/halaman tidak boleh dijalankan sebagai instruksi sistem AI.

## Status implementasi terhadap audit

Sudah tersedia: histori per kejadian, ACK per event, IndexedDB/retry/ekspor antrean, pause dan sesi baru, normalisasi domain, fetch/XHR serta pesan masuk socket dasar, snapshot DOM, kandidat angka, metadata batas, MCP detail dan pagination, detail web, serta ekspor sesi bertahap.

Belum tersedia: debugger/CDP, OCR screenshot, adapter Shopee dengan selector tervalidasi pada halaman asli, rekaman worker situs, korelasi lengkap websocket keluar/named SSE, dan penerimaan response yang masih berjalan setelah Stop. Validasi end-to-end angka Business Insights asli masih perlu dilakukan dengan pemilik akun. Rilis ini tidak menyatakan seluruh aktivitas browser tertangkap.

# Cara pakai Sniper v2, pelan-pelan 😄

Bayangin ada tiga teman: **ekstensi mencatat**, **webapp menyimpan**, lalu **AI membaca catatannya**. Kalau halaman belum kamu buka, catatannya juga belum ada.

## 1. Perbarui dulu

Untuk instalasi lokal yang sudah ada:

1. Ambil kode terbaru dari GitHub. Webapp perlu migrasi database v2; langkah teknis ada di [panduan developer](development.md). Database lokal pada sesi pengerjaan 30 September 2026 sudah dibackup dan dimigrasikan. Server lain tetap perlu dimigrasikan sendiri.
2. Buka `chrome://extensions`.
3. Cari **XYZ Sniper**, tekan tombol **Reload / Muat ulang**. Pastikan versinya **2.0.0**. Gunakan folder sumber yang sama agar penyimpanan antrean tetap terkait ekstensi yang sama.
4. Refresh tab Shopee yang mau direkam. Ini perlu agar tab memakai perekam baru.

Kalau belum pernah memasang: buka webapp → **Ekstensi Chrome** → unduh ZIP → ekstrak → buka `chrome://extensions` → aktifkan **Mode developer** → **Load unpacked** → pilih folder yang berisi `manifest.json`. Bisa juga langsung memilih folder `xyzsniper_extention` dari repository.

Jangan hapus ekstensi lama saat masih ada antrean. Pastikan antrean sudah terkirim atau ekspor dulu. Memuat folder baru dapat membuat ID ekstensi berbeda dan tidak membawa penyimpanan lama.

## 2. Sambungkan ekstensi dan webapp

1. Jalankan webapp. Untuk mode lokal, buka `http://localhost:8000` lalu login.
2. Buka halaman akun `/panel/account`. Salin **Kode Pas Kilat** yang masih berlaku.
3. Klik ikon ekstensi Sniper, tempel kode, lalu hubungkan.
4. Buat proyek, misalnya **Shopee Tokoku**, dengan domain **seller.shopee.co.id**. Pilih proyek itu sebagai proyek aktif di webapp.
5. Buka popup Sniper lagi. Periksa nama proyek dan domainnya. Popup mengambil proyek aktif dari webapp saat dibuka.
6. Jika tertulis **Perekam dijeda**, tekan **Lanjutkan**.

Alamat server pada ekstensi dan webapp harus sama. File `xyzsniper_extention/assets/js/config.js` memakai mode `local` secara default. Untuk server lain, sesuaikan URL dan mode, kemudian reload ekstensi. MCP dari komputer lain membutuhkan alamat server yang bisa dijangkau dari komputer itu.

## 3. Rekam Ringkasan Bisnis Shopee

1. Buka Seller Centre, lalu **Ringkasan Bisnis / Business Insights / Performa Toko**. Nama menu bisa berbeda antar tampilan Shopee.
2. Pilih periode yang ingin dibaca, misalnya **Hari ini**. Tunggu sampai angka selesai dimuat.
3. Biarkan tab Shopee terlihat di depan. Scroll pelan ke bagian angka yang kamu perlukan. Buka tab laporan atau tooltip yang diperlukan agar teksnya muncul.
4. Klik ikon Sniper → **Ambil angka halaman**. Snapshot otomatis juga berjalan saat teks, filter, atau halaman berubah.
5. Lihat status popup. Tunggu **menunggu = 0**, **gagal = 0**, dan waktu **Terakhir tersimpan** berubah. ACK artinya server sudah mengaku menerima rekaman; bukan sekadar ekstensi sudah melihatnya.
6. Buka webapp → **Sesi Audit** → pilih sesi → buka **Detail rekaman** pada event `snapshot`.
7. Cocokkan tiga hal: angka asli, nama/konteks metrik, dan periode. Coba dulu **Penjualan**, **Pesanan**, serta satu nilai **0** kalau tersedia.
8. Kalau sudah selesai, tekan **Akhiri sesi**. Perekam berhenti; permintaan tutup sesi menunggu antrean sesi tersebut selesai dikirim. Menekan **Lanjutkan** setelah ini membuat sesi baru ketika rekaman berikutnya masuk.

Contoh: kalau layar menulis `Rp1.234,50`, rekaman mempertahankan tulisan itu. Format Indonesia yang jelas juga bisa memiliki nilai desimal `1234.50`. Kalau layar menulis `1,2jt`, Sniper menyimpan tulisan aslinya dan menandai tampilan dibulatkan. Jangan menyebutnya angka persis tanpa bukti tambahan.

Filter kustom Shopee belum tentu dikenali sebagai kolom filter. Teks di sekitarnya tetap membantu jika tersedia. Kalau periode tidak ada di rekaman, ambil snapshot lagi saat kontrol periode terlihat; AI harus menyebut periode belum terverifikasi.

## 4. Minta AI membaca catatan

Sambungkan klien AI ke MCP Sniper memakai URL `/mcp` dan bearer token dari popup. [Panduan konfigurasi MCP](mcp-clients.md) memuat contoh. Token adalah kunci akun; simpan di konfigurasi privat atau environment, bukan repository atau chat publik.

Jika klien membatasi tool yang boleh dipakai, tambahkan tool v2: `get_event`, `read_body`, `get_page_snapshots`, `get_dashboard_metrics`, dan `get_capture_health`. Muat ulang koneksi MCP agar daftar tool diperbarui.

Contoh pesan ke AI:

> Pakai MCP Sniper. Pilih proyek Shopee Tokoku dan sesi paling baru. Ambil snapshot Business Insights, lalu tampilkan Penjualan, Pesanan, dan Pengunjung. Sertakan tulisan angka asli, periode, waktu rekaman, dan event_id bukti. Cocokkan kandidat nama dengan konteks. Kalau angka dibulatkan, terpotong, atau periodenya tidak jelas, bilang belum pasti. Jangan menebak angka yang tidak terekam.

Urutan tool yang membantu:

| Keperluan | Tool |
| --- | --- |
| Cari proyek dan sesi | `get_projects`, `get_sessions` |
| Cari catatan halaman | `get_page_snapshots` |
| Baca angka, konteks, dan filter | `get_dashboard_metrics` |
| Periksa satu bukti | `get_event` |
| Cari data request/API | `search_traffic` |
| Lanjutkan membaca isi panjang | `read_body` |
| Cek kualitas data di server | `get_capture_health` |

Hasil daftar mempunyai `pagination.has_more` dan `next_offset`. AI perlu melanjutkan jika masih ada halaman. Body pada daftar hanya pratinjau; lanjutkan dengan `read_body` sampai `has_more=false`. Tool tidak bisa memulihkan bagian yang sudah terpotong ketika direkam.

`get_capture_health` hanya melihat data yang sudah sampai server. Untuk antrean yang belum terkirim, lihat popup ekstensi.

## 5. Arti tombol

| Tombol | Artinya |
| --- | --- |
| **Jeda** | Berhenti menerima rekaman baru. Antrean lama tetap boleh dikirim. |
| **Lanjutkan** | Mengizinkan rekaman pada domain proyek aktif. |
| **Ambil angka halaman** | Mengambil teks dan kandidat angka dari tab aktif yang terlihat. |
| **Akhiri sesi** | Berhenti merekam dan mengantrekan penutupan sesi setelah datanya terkirim. |
| **Coba kirim ulang** | Mengaktifkan kembali pengiriman event pending/gagal. |
| **Ekspor antrean** | Mengunduh JSON data lokal yang belum selesai; tidak menghapusnya. |

Ekspor pada halaman sesi webapp mengambil semua event yang tersimpan sampai ID batas ekspor. Periksa `manifest.complete`, `event_count`, dan `through_id`. Event yang baru tiba setelah batas itu masuk ekspor berikutnya. Ekspor antrean lokal belum memiliki tombol impor ulang.

## 6. Kalau angka belum kebaca

| Yang terlihat | Coba ini |
| --- | --- |
| Tidak muncul sesi baru | Cocokkan domain dan proyek popup; tekan Lanjutkan; refresh Shopee. |
| Menunggu terus | Pastikan server hidup dan URL benar. Periksa pesan kesalahan; tekan Coba kirim ulang. Alarm retry berjalan sekitar tiap 30 detik saat Chrome aktif. |
| Token ditolak | Hubungkan ulang lewat Kode Pas Kilat dari akun yang sama. |
| Ada event gagal | Baca alasan di popup. Benahi server/token lalu retry; ekspor antrean jika tetap gagal. |
| Antrean penuh | Perekam otomatis dijeda. Pulihkan pengiriman atau ekspor. Setelah kapasitas cukup, tekan Lanjutkan dan rekam ulang bagian yang terlewat. |
| Angka ada di layar, snapshot kosong | Tunggu loading selesai, tampilkan tab, scroll ke bagiannya, lalu ambil snapshot. Canvas/gambar tidak dibaca sebagai teks. |
| Nama metrik salah | Periksa konteks dan tulisan asli di Detail rekaman. Nama masih kandidat dari struktur halaman. |
| Data lama tak lengkap | Rekaman v1 diberi label agregat. Kejadian yang dahulu sudah digabung tidak dapat dipulihkan; rekam ulang dengan v2. |
| Server offline | Antrean lokal bertahan; popup memakai proyek terakhir. Jangan uninstall ekstensi atau hapus data Chrome. |

## 7. Batas yang perlu kamu tahu

- Ini perekam domain proyek yang dipilih, bukan rekaman seluruh komputer/browser. Snapshot otomatis melewati tab tersembunyi.
- Request sebelum perekam siap, worker/service worker situs, gambar/CSS, canvas, dan closed shadow DOM tidak dijamin tertangkap. Belum ada debugger/CDP maupun OCR.
- Snapshot membaca teks DOM yang dianggap terlihat, hingga 60.000 karakter dan 200 kandidat angka. Halaman virtual yang belum memuat barisnya perlu discroll. Batas dipaparkan dalam rekaman.
- Isi network dibatasi sekitar 512 KiB per body. Stream fetch dibatasi 512 KiB atau 128 potongan; stream bisa terpotong di tengah karakter/JSON. WebSocket hanya pesan masuk; EventSource hanya event `message` biasa. File upload dicatat metadatanya, bukan isi file.
- Antrean lokal dibatasi sekitar 64 MiB. Saat penuh, event pemicu gagal masuk dan perekam dijeda; rekam ulang bagian itu setelah pulih. Penyimpanan browser tetap bisa hilang jika dihapus atau perangkat rusak.
- Tunggu halaman dan antrean tenang sebelum mengakhiri sesi. Response yang masih berjalan ketika perekam dijeda/diakhiri dapat tidak tercatat.
- Password, token, cookie, dan kunci sensitif yang dikenali disamarkan. Redaksi berdasarkan nama kunci/pola, bukan jaminan semua informasi pribadi hilang. Data bisnis yang terekam dan file ekspor tetap perlu dijaga.
- Pengujian otomatis memakai dashboard contoh dengan data buatan. Angka Shopee asli belum diverifikasi dari ujung ke ujung pada rilis ini; lakukan cek tiga angka pada langkah 3 sebelum mengandalkan hasil analisis.

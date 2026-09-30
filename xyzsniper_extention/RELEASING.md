# Menerbitkan ekstensi

Arsip rilis disimpan di `xyzsniper/public/downloads/extensions/<versi>/`. Setiap arsip bersifat permanen; penerbitan akan berhenti jika nomor versinya sudah pernah digunakan. Halaman panel membaca `releases.json`, sehingga versi terbaru, catatan changelog, dan arsip terdahulu muncul dari indeks yang sama.

Untuk setiap pembaruan:

1. Naikkan `version` pada `manifest.json`.
2. Tambahkan bagian baru di paling atas `CHANGELOG.md`, memakai format `## 1.0.1 - YYYY-MM-DD` dan setidaknya satu butir `- perubahan`.
3. Jalankan `node scripts/build-extension.cjs` untuk memperbarui bundle MAIN world, lalu `php xyzsniper/bin/publish-extension.php` dari direktori proyek.
4. Sertakan perubahan `xyzsniper/public/downloads/extensions/` dalam deployment aplikasi agar rilis tersedia di halaman panel.

Ekstensi Chrome berjalan sama di Windows dan macOS, jadi kedua tombol unduh menunjuk ke arsip ZIP yang sama. Pengguna mengekstrak ZIP lalu memuat folder hasil ekstrak melalui `chrome://extensions` dengan Mode developer aktif.

Untuk v2, deploy webapp dan [migrasikan database](../docs/development.md) sebelum memperbarui ekstensi. Jalankan tes sebelum membuat arsip. Paket memakai konfigurasi URL dari `assets/js/config.js`; pastikan mode lokal/hosting sesuai target distribusi.

ZIP diabaikan Git secara umum. Paket distribusi resmi yang sudah diverifikasi dapat ditambahkan secara eksplisit dengan `git add -f <path-arsip>` bersama `releases.json`. Jangan mengubah arsip versi yang telah diterbitkan.

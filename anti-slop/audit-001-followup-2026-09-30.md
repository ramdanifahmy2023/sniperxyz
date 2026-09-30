# Audit tindak lanjut UI dan rekaman

30 September 2026. Item audit 1 sampai 5 dari `audit-001-2026-09-29.md` sudah ditangani dalam pekerjaan v2.

- **R-27 / C-5 PASS:** popup memisahkan status izin, antrean menunggu, event gagal, ACK terakhir, error, retry, ekspor, dan akhir sesi. Status offline tetap bisa dibuka dari proyek terakhir.
- **R-27 PASS:** alasan event ditolak, oversized, domain tidak cocok, server belum mengakui, dan antrean penuh disimpan sebagai diagnostic yang dapat dilihat pengguna.
- **R-38 / C-5 PASS:** sesi dan timeline memakai pagination. Ekspor memakai keyset sampai `through_id` dan menyertakan manifest `complete` serta `event_count`.
- **R-32 PASS:** preview screenshot menjadi button berlabel. Detail event memakai link yang dapat difokuskan, disclosure keyboard, tabel yang bisa digeser, dan focus ring.
- **C-3 / R-31 PASS:** timeline terhubung ke detail event; snapshot menampilkan angka asli, kandidat nama, konteks, filter, zona waktu, waktu observasi, truncation, serta versi legacy.

Bukti runtime: `node tests/browser-e2e.cjs` memakai Chromium MV3, CSP ketat, API PHP, database sementara, MCP, tombol popup, keyboard disclosure, tema terang/gelap, ukuran 375/1280, ekspor, restart browser, retry offline, dan pemeriksaan exception. Contrast checker antislop-human lulus pada kombinasi palette yang dipakai. Batas yang masih sengaja ditampilkan: OCR, debugger/CDP, adapter selector Shopee tervalidasi, worker situs, stream lengkap setelah stop, serta isi gambar/canvas.

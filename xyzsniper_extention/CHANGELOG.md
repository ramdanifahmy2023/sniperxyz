# Changelog Ekstensi XYZ Sniper

## 2.0.0 - 2026-09-30
- Menyimpan setiap kejadian secara terpisah; retry event yang sama tidak menggandakan histori.
- Menambah snapshot teks, kandidat angka, konteks, dan filter halaman untuk pembacaan dashboard melalui MCP.
- Memindahkan antrean ke IndexedDB dengan ACK per event, retry, status gagal, dan ekspor antrean.
- Menambah kontrol jeda, lanjutkan, akhir sesi, dan ambil angka halaman; kontrol tetap tersedia ketika server offline.
- Memperbaiki pembacaan fetch/XHR, JSON vendor, FormData berulang, serta memberi tanda saat body dibatasi.
- Menambah detail bukti, pembacaan body bertahap, pagination MCP, dan ekspor sesi tanpa batas 10.000 event.
- Memerlukan migrasi database v2, reload ekstensi, dan refresh halaman setelah upgrade. OCR/CDP serta adapter khusus Shopee belum termasuk.

## 1.0.0 - 2026-09-29
- Rilis awal ekstensi Chrome XYZ Sniper untuk Windows dan macOS.
- Menyediakan pemasangan melalui menu ekstensi Chrome dalam mode developer.

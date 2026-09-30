# XYZ Sniper

Sniper mengumpulkan request dan teks halaman dari domain proyek yang kamu pilih. Webapp menyimpan riwayatnya, lalu AI membacanya melalui MCP.

- [Tutorial pemakaian, termasuk Shopee Business Insights](docs/tutorial-sniper.md)
- [Pemasangan, migrasi, dan pengujian untuk developer](docs/development.md)
- [Konfigurasi klien MCP](docs/mcp-clients.md)
- [Audit dan rencana perbaikan](docs/audit-plan-2026-09-29.md)
- [Catatan rilis ekstensi](xyzsniper_extention/CHANGELOG.md)

Versi 2 menyimpan kejadian secara terpisah, memakai antrean IndexedDB dengan pengakuan per event dari server, dan menyediakan snapshot angka beserta konteks halaman. Angka dari gambar/canvas belum dibaca dengan OCR. Kandidat nama metrik perlu dicocokkan dengan teks asli.

Webapp berada di `xyzsniper/`; ekstensi Chrome berada di `xyzsniper_extention/` (nama folder lama dipertahankan).

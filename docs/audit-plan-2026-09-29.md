# Audit dan rencana peningkatan Sniper

Tanggal: 29 September 2026. Fokus pengguna: angka pada dashboard Shopee Seller yang belum terbaca AI.

Sniper saat ini merekam sebagian request browser. Agar AI bisa menjawab angka dashboard dengan benar, Sniper perlu menyimpan kejadian tanpa saling menimpa, mengambil angka yang terlihat beserta konteksnya, lalu menyediakan cara membaca bukti tersebut lewat MCP.

Bahasa gampangnya: buku catatannya harus utuh, angka harus punya nama, dan AI harus tahu halaman buku mana yang perlu dibuka.

## Cakupan dan batas bukti

- Dibaca: manifest ekstensi, interceptor, content bridge, antrean background, popup, API capture, model penyimpanan, skema database, MCP, adapter stdio, halaman sesi dan payload.
- Tujuh probe lokal dijalankan terhadap kode asli menggunakan Node VM dan satu database tiruan PHP. Tidak ada request ke Shopee atau penulisan ke database aplikasi dalam probe tersebut.
- Belum dilakukan: reproduksi langsung pada dashboard Shopee, pengujian ekstensi terpasang di Chrome, pemeriksaan data produksi, benchmark, dan review visual di browser. Temuan Shopee di bawah adalah hipotesis yang perlu dicocokkan dengan rekaman nyata.
- Audit memakai kondisi working tree saat ini, termasuk perubahan pengguna yang sudah ada. File aplikasi tidak diubah dalam pekerjaan audit ini.
- Tidak ada persentase kelengkapan atau janji semua aktivitas browser pasti dapat direkam.

## Jalur data sekarang

`fetch/XHR halaman utama -> interceptor.js -> content.js -> background.js -> captureBulk -> CaptureModel -> MySQL -> MCP -> AI`

Fondasi yang sudah ada: ID event, metadata tab/frame, sesi, antrean persistent dengan retry dan pemulihan inflight, transaksi batch, pagination MCP, pemeriksaan kepemilikan akun, dan ekspor sesi. Bagian ini bisa diperbaiki bertahap tanpa mengganti seluruh aplikasi.

| Yang ingin diketahui | Kondisi kode sekarang |
| --- | --- |
| Request fetch/XHR di halaman utama | Ada, dengan keterbatasan parsing dan cakupan |
| Response API JSON biasa | Ada bila tertangkap dan lolos pengiriman |
| Request yang berulang dengan hasil berbeda | Bisa menimpa catatan sebelumnya |
| Angka dan judul kartu yang terlihat di dashboard | Belum ada pengambil teks/DOM |
| Filter tanggal, toko, mata uang, dan periode pembanding | Belum dihubungkan secara eksplisit ke metrik |
| Klik, perubahan filter, navigasi SPA | Belum ada event aktivitas tersendiri |
| Request dari iframe | Tidak ada `all_frames`; default hanya frame utama |
| WebSocket, EventSource, request mandiri dari worker | Tidak ada collector khusus |
| Streaming lewat fetch | Ditunggu sampai seluruh body selesai |
| Grafik canvas, gambar, PDF | Belum ada ekstraksi angka/teks; beberapa body hanya Base64 |
| Screenshot melalui jalur bulk yang digunakan ekstensi | Belum ada producer; API bulk mengisi `screenshot: null` |

## Temuan teknis

P0 = data bisa hilang atau riwayat salah. P1 = menghalangi pembacaan angka dan perluasan cakupan. P2 = kemudahan penggunaan dan skala.

| ID | Prioritas | Temuan dan dampak | Bukti kode |
| --- | --- | --- | --- |
| A01 | P0 | Request berbeda dengan hash sama dianggap duplikat. Response, event ID, dan session ID lama ditimpa. Riwayat nilai dashboard bisa hilang, termasuk dari sesi lama. Retry event yang sama juga menambah `hit_count`, sehingga hit bukan jumlah kejadian yang murni. | `CaptureModel.php:70–132`; `PayloadModel.php:43–72`; `schema.sql:104–105`; hash request dibuat di `background.js:263–264` |
| A02 | P0 | Status ACK `rejected` dianggap diterima karena hanya `error`, `failed`, dan `retry` yang dikecualikan. Antrean dapat membuang event yang tidak disimpan server. Respons sukses tanpa daftar ACK juga dianggap menerima seluruh batch. | `background.js:164–175`; `Api.php:468–473` |
| A03 | P0 | Antrean dibatasi jumlah item, bukan byte. Pending disimpan dua kali, callback storage mengabaikan `runtime.lastError`, dan dead-letter tertua dibuang setelah batas 200. Risiko data tampak sudah antre tetapi sebenarnya gagal tersimpan. | `background.js:8–10,30–57` |
| A04 | P0 | API menolak body di atas 4 MiB dan request batch di atas 12 MiB. Ekstensi hanya membatasi 25 item, tanpa memecah berdasarkan byte. Satu item terlalu besar dapat menggagalkan batch valid lain; retry tidak memperkecil data. | `Api.php:4–29,519–520`; `background.js:7,177–202` |
| A05 | P1 | MIME seperti `application/vnd.api+json` salah dianggap binary. Data masih ada sebagai Base64, tetapi tidak disajikan sebagai JSON yang bisa langsung dicari/dibaca. | `interceptor.js:25–48`; terbukti probe |
| A06 | P1 | `fetch(Request, init)` merekam method/body dari Request awal, mengabaikan override init. FormData dengan key berulang hanya menyisakan nilai terakhir. Catatan dapat berbeda dari request aktual. | `interceptor.js:61–68,140–149`; terbukti probe |
| A07 | P1 | Hanya fetch/XHR pada window yang di-hook. Injeksi melalui elemen script eksternal belum memiliki handshake kesiapan. Ada celah cakupan frame/worker/protokol dan potensi request awal terlewat; kondisi CSP/timing aktual perlu diuji. | `manifest.json:34–50`; `content.js:7–10`; `interceptor.js:134–236` |
| A08 | P1 | `response.arrayBuffer()` menunggu body habis. Stream panjang belum menghasilkan event; navigasi sebelum pembacaan selesai berisiko kehilangan hasil. Pengambilan body juga belum punya batas memori di sisi collector. | `interceptor.js:88–95,113–131`; terbukti probe untuk stream |
| A09 | P1 | Tidak ada pengambil angka DOM, label metrik, perubahan filter, atau snapshot halaman. AI hanya menerima traffic dan tidak tahu angka mana yang benar-benar sedang ditampilkan. | `content.js` seluruh file; `interceptor.js` seluruh file; skema `payloads` |
| A10 | P1 | `sequence_no` dimulai lagi pada setiap dokumen tetapi timeline diurutkan seolah satu urutan sesi. Waktu mulai dibuat tetapi tidak diteruskan pada payload capture. Stop menghapus storage sesi tanpa menghapus cache `sessionState`; lanjut dapat memakai sesi lama selama worker masih hidup. | `interceptor.js:3,104–130`; `Mcp.php:404–415`; `popup.js:153–170`; `background.js:116–136,300–305` |
| A11 | P1 | Request header disimpan di endpoint, lalu ditimpa request berikutnya. MCP memakai header endpoint itu untuk semua payload historis. Endpoint hanya dibedakan berdasarkan project+path, sehingga host berbeda dengan path sama bercampur dalam daftar endpoint. | `CaptureModel.php:38–46`; `schema.sql:55,66`; `Mcp.php:296–306` |
| A12 | P1 | API membuang format body, body mentah, pesan error, dan URL response akhir dari kontrak collector. Null response tidak membedakan benar-benar kosong, gagal dibaca, atau network error. | `interceptor.js:124–130,165–168` dibanding `Api.php:529–559` |
| A13 | P1 | MCP mengembalikan body penuh sebanyak 50 item secara default, maksimum 200; belum ada anggaran byte jawaban, pembacaan satu event berdasarkan ID, atau pembacaan body per bagian. Search tidak mencari `query_params`, `request_url`, atau `response_header`. Angka yang hanya ada di sana bisa tidak ditemukan. | `Mcp.php:4–5,181–203,296–333,428–439` |
| A14 | P2 | UI membatasi daftar sesi 100, timeline 1.000, ekspor 10.000 tanpa metadata kelanjutan. Popup hanya menampilkan hitungan antrean sekali, tidak menjelaskan penyebab kegagalan atau menyediakan pemulihan. | `Panel.php:86–136`; `popup.js:132–170`; `views/panel/session.php` |
| A15 | P1 | Hook tetap membaca dan memproses body pada halaman sebelum background memeriksa project/pause. Belum ada redaksi data sensitif atau validasi schema bridge yang ketat. Halaman dapat mengirim pesan dengan marker yang sama; `pageUrl` dari pesan diprioritaskan atas identitas sender. | `content.js:12–21`; `background.js:252–299`; tidak ditemukan collector policy/redaction |

Path di tabel relatif terhadap direktori utama masing-masing: JS di `xyzsniper_extention/assets/js`, PHP controller di `xyzsniper/app/controllers`, model di `xyzsniper/app/models`, schema di `xyzsniper/database`.

Chrome mendokumentasikan batas `storage.local` sebesar 10 MB, dengan kegagalan dilaporkan lewat error callback/Promise. Ini membuat pengecekan error dan kapasitas byte relevan untuk A03. [Dokumentasi storage](https://developer.chrome.com/docs/extensions/reference/api/storage).

## Hasil probe lokal

Data seluruhnya fixture buatan, bukan angka atau respons Shopee.

| Probe | Hasil yang diamati |
| --- | --- |
| Response JSON ber-MIME `application/vnd.api+json` | `responseBodyFormat: binary`, body menjadi Base64 |
| Request awal POST `old`, init override PUT `new` | Native fetch stub menerima override; rekaman tetap POST `old` |
| FormData `tag=a`, `tag=b` | Rekaman hanya `{ "tag": "b" }` |
| Stream mengirim satu potongan tetapi belum ditutup | 0 capture; setelah stream ditutup menjadi 1 capture |
| ACK server `status: rejected` | `extractAckIds()` memasukkan ID ke daftar diterima |
| Hapus storage sesi lalu panggil `ensureSession()` pada worker yang sama | ID sesi lama dipakai lagi; storage sesi tidak dibuat ulang |
| Event baru, session baru, hash sama dengan row lama | Model mengeluarkan UPDATE pada row lama dan mengganti event, session, response; status `duplicate` |

Probe PHP membuktikan keputusan query model dengan database tiruan, bukan hasil integrasi MySQL. Probe Node memakai Web API Node dan stub Chrome, sehingga perilaku injeksi Chrome, CSP, dan penghentian service worker masih perlu pengujian browser.

## Fokus angka Shopee Seller

Belum ada bukti bahwa Shopee pada halaman pengguna menggunakan streaming, iframe, canvas, MIME vendor, atau format tertentu. Temuan A05–A08 adalah kemungkinan penyebab tambahan, bukan diagnosis final website tersebut.

Satu angka perlu disimpan bersama:

- Teks tampilan asli, misalnya penulisan lokal, singkatan ribuan, atau tanda persen.
- Nilai terstruktur bila dapat ditentukan tanpa menebak. Gunakan decimal string untuk uang/presisi; jangan memaksa semua angka menjadi floating point.
- Nama metrik, judul kartu/tabel/grafik, definisi bila tersedia, serta posisi baris/kolom.
- Toko/account context yang relevan, periode, timezone, filter, currency/unit, dan periode pembanding.
- URL, tab, frame, page snapshot ID, waktu observasi, serta status sedang loading atau sudah stabil.
- Bukti DOM dan, bila cocok, event API serta lokasi field JSON. Pertautan berdasarkan waktu saja diberi status dugaan, bukan pasti.
- Asal nilai: `api`, `dom`, atau `ocr`; status kelengkapan dan alasan bila tidak tersedia.

Jika layar hanya menulis angka dibulatkan seperti `1,2 rb`, simpan teks tersebut dan tandai nilai sebagai perkiraan. Jangan mengklaim angka persis yang tidak ada di bukti. Nol, tanda strip, loading, dan data tidak tersedia harus berbeda.

Alur yang diusulkan: buka dashboard -> rekam filter dan snapshot yang sudah stabil -> ekstrak kartu/tabel -> tautkan response terkait -> AI mengambil metrik beserta bukti. Bila grafik memakai canvas, prioritaskan sumber API atau tooltip yang memang terlihat; screenshot/OCR menjadi fallback dan harus menampilkan ketidakpastian.

## Rencana implementasi bertahap

### 0. Buat patokan Shopee yang nyata

Pilih satu halaman dashboard dan satu toko. Catat beberapa metrik yang benar-benar gagal dibaca, periode, timezone, filter, waktu, dan tampilan angka. Rekam sesi kecil mulai sebelum halaman dimuat; cocokkan dengan traffic dan keluaran MCP. Gunakan akses pengguna yang sudah login, tanpa menyimpan kredensial ke fixture.

Hasil: daftar metrik target dan lokasi hilangnya data: belum tertangkap, ditolak saat pengiriman, tertimpa saat penyimpanan, salah format, atau tidak ditemukan MCP. Ini menentukan kebutuhan mode perekaman mendalam; jangan menganggap menambah permission otomatis menyelesaikan masalah.

### 1. Selamatkan riwayat dan pengiriman (A01–A04, A10–A12)

- Jadikan kejadian log append-only. Event baru selalu row baru walau request identik; retry event yang sama hanya mengembalikan ACK tanpa menambah hit. Jika body identik ingin dihemat, deduplikasi blob body, bukan kejadian.
- Hilangkan unique endpoint+payload hash pada event log. Gunakan unique `(account_id, event_id)` atau key identitas ekuivalen yang aman lintas akun. Simpan request header per event, serta origin+method+path untuk indeks endpoint.
- Kirim project ID eksplisit, verifikasi kepemilikan dan aturan domain di server. Pertahankan parent-tab/project mapping untuk iframe lintas domain dan samakan normalisasi host ekstensi/server.
- ACK memakai daftar status sukses eksplisit (`stored`, `duplicate` setelah idempotensi diperbaiki). `rejected` masuk catatan kegagalan dengan alasan; status tidak dikenal tidak dianggap berhasil. Pisahkan retry sementara dari kegagalan permanen.
- Pindahkan antrean ke IndexedDB dengan transaksi per item dan penyimpanan blob terpisah. Tetapkan batas byte, deteksi quota error, hentikan collector/beri feedback bila ruang habis, dan sediakan retry/export dead-letter. IndexedDB juga punya quota; tetap perlu penanganan gagal tulis.
- Batasi batch menurut ukuran serialisasi aktual, pecah batch saat 413, isolasi item terlalu besar, dan tambah upload body per bagian bila dibutuhkan. Simpan metadata event sekalipun body tidak dapat disimpan; jangan menghilangkan item diam-diam.
- Simpan waktu kejadian dan waktu diterima terpisah, urutan per dokumen, serta urutan penerimaan server. Gunakan timestamp+tie-breaker ID untuk timeline lintas tab; jangan mengklaim semua event paralel memiliki urutan sebab-akibat pasti.
- State sesi jelas: recording, paused, draining, ended. Saat stop, berhenti menerima event baru, selesaikan/nyatakan status antrean, lalu tutup sesi. Lanjut setelah ended membuat ID baru; retry lama tidak membuka ulang sesi.
- Pertahankan MIME, charset, encoding, body status, captured/original size, error reason, redirect/final URL, dan versi collector.

Area: `background.js`, `popup.js`, `Api.php`, `CaptureModel.php`, `PayloadModel.php`, `schema.sql`, query timeline/MCP.

Selesai bila: dua response berbeda atas request sama tetap dua kejadian; retry tidak menggandakan kejadian; rejected terlihat; offline/restart memulihkan data dalam kapasitas yang dinyatakan; batch besar tidak mengorbankan item kecil; stop/lanjut benar-benar menghasilkan sesi baru.

### 2. Perbaiki pembacaan dan ambil angka layar (A05–A09, A15)

- Benahi pengenalan `+json`/`+xml`, charset, Request+init, FormData multivalue, dan perlakuan body stream. Collector tidak boleh mengubah body atau menunggu serialisasi besar sebelum request asli berjalan.
- Gunakan injeksi MAIN world yang didaftarkan Chrome dan bridge isolated terpisah, dengan readiness handshake. Aktifkan collector hanya untuk scope yang dipilih; pause harus menghentikan kerja body collector juga.
- Tambah frame yang diizinkan beserta mapping ke tab induk. Test frame kosong/sandbox/lintas origin secara eksplisit; jangan hanya menambah `all_frames` lalu menganggap selesai.
- Tambah snapshot teks yang terlihat, heading, kartu metrik, tabel, label, filter, route SPA, dan perubahan isi. Gunakan debounce, dedup snapshot, batas ukuran, dan status loading agar setiap animasi tidak menjadi ribuan rekaman.
- Buat adapter Shopee yang kecil dan berversi setelah melihat DOM nyata. Utamakan label/struktur semantik, fallback extractor generik, dan tampilkan bila struktur tidak dikenali. Jangan mengarang selector atau nama endpoint sebelum inspeksi.
- Tangkap klik/perubahan filter secukupnya untuk konteks; jangan merekam semua ketikan atau password. Kaitkan tindakan ke snapshot dan request dengan confidence yang eksplisit.
- Mask token, authorization, password, dan field sensitif sebelum penyimpanan/pengiriman; pertahankan angka bisnis yang memang diperlukan. Izinkan pengecualian terarah sesuai konfigurasi pengguna, dengan retention dan akses yang jelas.
- Validasi schema/ukuran/rate pesan bridge, gunakan sender tab/frame/document sebagai asal terpercaya. Data dari MAIN world tetap dianggap berasal dari halaman yang dapat memodifikasinya.

Area: `manifest.json`, `content.js`, `interceptor.js`, collector/adapter baru, kontrak API dan tabel snapshot/metrik.

Selesai bila: setiap metrik patokan Shopee terbaca dengan label, periode, dan satuan yang tepat; pergantian filter menghasilkan snapshot baru yang tidak mencampur periode; angka nol tidak hilang; fixture format lokal dan angka dibulatkan menghasilkan status yang benar; navigasi normal tetap berfungsi.

Chrome menyediakan `world`, `all_frames`, dan pencocokan origin fallback. Pemilihannya harus disertai uji izin dan frame. [Dokumentasi content scripts](https://developer.chrome.com/docs/extensions/reference/manifest/content-scripts).

### 3. Beri AI jalan baca yang kecil dan jelas (A12–A13)

- Tambahkan `get_event(event_id)`, `read_body(body_id, cursor, max_bytes)`, `get_page_snapshot(snapshot_id)`, `get_dashboard_metrics(snapshot_id)`, dan `get_capture_health(session_id)` sebagai kontrak usulan.
- Hasil pencarian berisi metadata dan cuplikan dahulu. Body penuh dibaca sesuai kebutuhan; sediakan filter project, sesi, tab, URL/host, rentang waktu, status, dan jenis sumber.
- Semua list memberi `next_cursor`, `has_more`, dan scope/filter aktif. Batasi output berdasarkan byte juga, bukan hanya jumlah row. Gunakan bentuk hasil object yang konsisten untuk structured content; uji dengan client MCP pengguna.
- Metrik harus menunjuk snapshot/event/field asal. Bedakan data tidak ada, belum direkam, disamarkan, terpotong, gagal parse, dan hasil perkiraan.
- Instruksi MCP mengarahkan AI memilih sesi+periode sebelum menyebut angka dan tidak menyamakan response lama dengan layar terbaru. Isi website diperlakukan sebagai data, bukan instruksi untuk AI.
- Tidak perlu menambah vector database di tahap awal. Perbaiki pencarian terstruktur dan keterlacakan bukti lebih dulu.

Area: `Mcp.php`, query/index, adapter stdio bila kontrak berubah, `docs/mcp-clients.md`.

Selesai bila: AI dapat menjawab metrik patokan lengkap dengan periode dan bukti; body besar dibaca bertahap; pertanyaan lintas toko/periode tidak tertukar; query parameter bisa ditemukan; client bisa mencapai halaman hasil terakhir.

### 4. Buat webapp dan popup menjelaskan kondisi rekaman (A14)

- Popup menampilkan tab/domain yang sedang dicakup, status collector, terakhir tersimpan, ukuran antrean, jumlah ditolak, dan alasan. Bedakan perekam aktif dengan data berhasil tersimpan.
- Sediakan aksi yang nyata: jeda, lanjut, akhiri sesi, coba kirim lagi, lihat alasan gagal, dan ekspor antrean bermasalah.
- Timeline menghubungkan halaman/filter, tindakan, request, response, dan metrik. Detail event dapat dibuka langsung berdasarkan ID.
- Tampilan metrik menampilkan nilai, label, periode, sumber, serta status lengkap/perkiraan. Angka DOM dan API yang berbeda tetap ditampilkan sebagai perbedaan sampai penyebabnya diketahui.
- Pagination timeline/sesi dan ekspor menyatakan apakah seluruh data sudah termasuk. Ekspor besar dibuat bertahap dengan manifest jumlah dan status lengkap.
- Terapkan keyboard/focus, label status berbentuk teks, state kosong/loading/error yang memberi tindakan, serta pemeriksaan layout mobile dan tema yang sudah didukung. Pertahankan identitas UI yang ada; tidak perlu redesign dekoratif untuk menyelesaikan masalah angka.

Area: `popup.js`, `index.html`, `Panel.php`, `views/panel/session.php`, `sessions.php`, `payloads.php`, CSS seperlunya.

Selesai bila: pengguna bisa mengetahui alasan satu angka belum tersedia tanpa membuka console; status tidak menyatakan sukses sebelum ACK; setiap daftar/ekspor mengungkap kelengkapannya; alur utama dapat dipakai keyboard dan layar sempit.

### 5. Tambahkan mode mendalam bila bukti masih kurang (A07–A08)

Usulan dua mode: mode biasa untuk traffic halaman dan snapshot; mode mendalam yang diaktifkan pengguna per tab/sesi untuk collector berbasis `chrome.debugger`/CDP. Mode mendalam perlu permission dan indikator yang jelas serta penanganan attach/detach, navigasi, dan target anak.

Prototype kecil lebih dulu: request lifecycle, response body setelah selesai, frame WebSocket, pesan EventSource, redirect, cache, dan target worker/frame. Simpan alasan bila body tidak tersedia. Gabungkan sumber tanpa menggandakan event menggunakan ID sumber dan hubungan yang terukur, bukan URL saja.

`chrome.debugger` menyediakan akses CDP untuk instrumentasi network dan mendukung child sessions. Ini dasar teknis opsi tersebut, bukan jaminan semua body bisa diperoleh. [Dokumentasi debugger](https://developer.chrome.com/docs/extensions/reference/api/debugger). Daftar event/metode network perlu dicocokkan dengan versi Chrome yang ditargetkan. [CDP Network](https://chromedevtools.github.io/devtools-protocol/tot/Network/).

Screenshot/OCR dipakai terarah saat teks/angka hanya tersedia secara visual, dengan waktu dan viewport yang sama. Dokumen PDF dan format binary khusus masuk backlog berdasarkan contoh nyata; parser tidak boleh mengarang makna data terenkripsi atau format tanpa schema.

Selesai bila: kasus Shopee yang gagal pada mode biasa terbukti terbaca pada mode mendalam, atau ditandai unsupported dengan alasan; detach tidak merusak halaman; penggunaan memori/CPU/storage diukur sebelum mode diperluas.

## Urutan rilis yang disarankan

| Rilis | Isi | Nilai untuk pengguna |
| --- | --- | --- |
| R1 | Tahap 0–1 + perbaikan parser tahap 2 | Catatan lebih dapat dipercaya, kegagalan tidak tersembunyi |
| R2 | Snapshot/adapter Shopee tahap 2 + MCP tahap 3 + UI minimum tahap 4 | AI membaca angka dashboard beserta konteks dan bukti |
| R3 | Penyempurnaan UI dan mode mendalam tahap 5 berdasarkan gap nyata | Memperluas cakupan tanpa mengasumsikan semua fitur diperlukan |

Jangan menunda indikator kegagalan sampai rilis akhir: minimal status ditolak/antrean bermasalah harus ikut R1. Estimasi durasi baru dibuat setelah halaman Shopee target, kebutuhan migrasi data, dan matriks Chrome terverifikasi.

## Model data usulan

| Entitas | Tugas |
| --- | --- |
| `capture_sessions` | Project, scope, versi collector, waktu, state sesi |
| `capture_events` | Kejadian immutable, event ID, timestamp, asal tab/frame/dokumen, metadata request/response/error |
| `capture_bodies` | Body/blob dengan MIME, encoding, checksum, status kelengkapan; dedup hanya isi yang identik |
| `page_snapshots` | Teks/struktur halaman, filter, URL, waktu, loading/stable |
| `dashboard_metrics` | Teks angka, nilai decimal bila pasti, label, unit, periode, sumber bukti, kualitas ekstraksi |
| `capture_delivery_status` | ACK, retry, rejected, skipped, alasan dan diagnostic session |

Tabel di atas adalah rancangan logis; pemisahan fisiknya diputuskan saat migrasi. Hubungan tenant/project harus ada di semua query dan akses blob, termasuk data turunan.

## Migrasi dan pengujian sebelum rilis

- Backup dan uji restore database dahulu. Migrasi additive: tambah schema v2, ingestor kompatibel, feature flag client, lalu cutover reader setelah verifikasi. Rollback menghentikan capture v2 dengan jelas, bukan diam-diam menulis event baru lewat jalur lama yang menimpa riwayat.
- Catatan lama ditandai `legacy_aggregate`. `hit_count` tidak dipecah menjadi event palsu; response/timestamp yang sudah tertimpa tidak dapat dipulihkan tanpa sumber lain.
- Migrasi queue lama secara transactional dan hanya sekali. Pertahankan ID event, periksa jumlah/checksum sebelum membuang format queue lama.
- Uji kontrak parser dan ACK; integrasi MySQL untuk idempotensi, request berulang, transaksi, dan isolasi akun; browser fixture untuk iframe, SPA, reload, stream, WebSocket, body besar, dan restart worker.
- Uji offline -> online, token kedaluwarsa, quota habis, 413, server 5xx, satu item ditolak di batch campuran, stop ketika request masih berjalan, serta popup ditutup saat proses berakhir.
- Uji MCP dengan client pengguna: pagination sampai akhir, hasil kosong, hasil besar, tool error, dan pembacaan satu metrik dengan bukti.
- Uji Shopee dengan metrik yang terlihat sebelum/sesudah ganti tanggal, pagination tabel, pergantian tab, dan reload. Metrik yang belum dapat diverifikasi tidak ditandai akurat.
- Ukur overhead dengan fixture yang sama sebelum/sesudah: waktu halaman, CPU, memori, ukuran queue, waktu ACK, serta event teramati versus tersimpan/ditolak/dilewati. Jangan menyebut persentase seluruh traffic bila collector belum tahu traffic yang tidak pernah dilihatnya.

## Review UX yang relevan

UI UX Pro Max dipakai untuk pencarian lokal `status feedback error recovery` pada domain UX. Hasil yang relevan: alasan gagal harus terlihat, tindakan pemulihan harus tersedia, dan loading/sukses/error harus berbeda. Antislop diterapkan pada kejujuran status dan kegunaan alur; detail temuan ada di [audit UX](../anti-slop/audit-001-2026-09-29.md).

Review dokumen: PASS untuk pemisahan bukti dan hipotesis; PASS untuk angka pengujian yang bersumber dari probe; PASS untuk rencana yang terkait temuan. Validasi visual, kontras, mobile, dan keyboard belum dijalankan; tidak ada klaim UI siap rilis.

<?php
$event=$data['event']; $snapshot=$data['snapshot'];
$escape=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
?>
<main class="panel-main event-detail" aria-label="Detail rekaman">
  <div class="panel-page-header">
    <div><h1>Detail rekaman</h1><p><?= $escape($event['source_type']); ?> · <?= $escape($event['occurred_at'] ?? $event['created_at']); ?><?= $event['occurred_at'] ? ' UTC' : ''; ?></p></div>
    <a class="btn" href="<?= burl; ?>/panel/session/<?= rawurlencode($event['session_id'] ?? ''); ?>">Kembali ke sesi</a>
  </div>
  <p style="overflow-wrap:anywhere;"><?= $escape($event['request_url']); ?></p>
  <p>ID bukti: <code><?= $escape($event['event_id']); ?></code></p>
  <?php if ($event['truncated']): ?><p role="status">Isi rekaman dibatasi. Sebagian data tidak tersimpan.</p><?php endif; ?>
  <?php if ((int)$event['capture_version']===1): ?><p>Rekaman lama berupa agregat. Riwayat setiap kejadian tidak tersedia.</p><?php endif; ?>
  <?php if (is_array($snapshot)): ?>
    <section class="card" style="padding:20px; margin-bottom:20px;">
      <h2><?= $escape($snapshot['title'] ?? 'Snapshot halaman'); ?></h2>
      <p>Angka di bawah berasal dari teks halaman. Nama metrik adalah kandidat; cocokkan dengan konteks dan filter sebelum menyimpulkan.</p>
      <p>Zona waktu browser: <?= $escape($snapshot['timezone'] ?? 'Tidak tercatat'); ?> · <?= !empty($snapshot['loading']) ? 'Halaman masih memuat data' : 'Tidak ada indikator loading yang terdeteksi'; ?></p>
      <?php if (!empty($snapshot['metrics_truncated'])): ?><p>Daftar angka dibatasi hingga 200 kandidat. Gunakan teks rekaman untuk memeriksa konteks lain.</p><?php endif; ?>
      <h3>Filter yang tertangkap</h3>
      <?php if (empty($snapshot['filters'])): ?><p>Filter tidak dikenali otomatis. Periksa teks halaman atau rekam lagi setelah memilih periode.</p><?php else: ?>
        <ul><?php foreach($snapshot['filters'] as $filter): ?><li><?= $escape($filter['label'] ?? 'Filter'); ?>: <?= $escape($filter['value'] ?? ''); ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
      <div class="table-container" tabindex="0" role="region" aria-label="Kandidat angka, geser untuk melihat seluruh kolom"><table class="table w-full"><thead><tr><th>Angka / teks asli</th><th>Kandidat nama</th><th>Konteks halaman</th><th>Status</th></tr></thead><tbody>
        <?php if (empty($snapshot['metrics'])): ?><tr><td colspan="4">Belum ada angka yang dikenali. Canvas dan gambar tidak dibaca sebagai teks.</td></tr><?php endif; ?>
        <?php foreach(($snapshot['metrics'] ?? []) as $metric): ?><tr>
          <td><?= $escape($metric['raw_text'] ?? ''); ?></td><td><?= $escape($metric['label'] ?? 'Belum dikenali'); ?></td>
          <td style="min-width:180px;max-width:420px;white-space:normal;overflow-wrap:anywhere;"><?= $escape($metric['context'] ?? ''); ?></td>
          <td><?= ($metric['interpretation'] ?? '')==='rounded_display' ? 'Tampilan dibulatkan' : 'Perlu verifikasi'; ?></td>
        </tr><?php endforeach; ?>
      </tbody></table></div>
    </section>
  <?php endif; ?>
  <?php foreach(['request_header'=>'Header request','response_header'=>'Header response','request_body'=>'Isi request','response_body'=>'Isi response / snapshot','meta_json'=>'Keterangan rekaman'] as $key=>$label): ?>
    <details class="card" style="padding:16px;margin-bottom:12px;"><summary style="cursor:pointer;min-height:44px;"><?= $label; ?></summary>
      <pre style="white-space:pre-wrap;overflow-wrap:anywhere;max-height:60vh;overflow:auto;"><?= $escape($event[$key] ?? 'Tidak tersedia'); ?></pre>
    </details>
  <?php endforeach; ?>
</main>

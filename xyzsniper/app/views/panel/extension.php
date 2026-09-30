<main class="panel-main extension-page" aria-label="Konten Ekstensi Chrome">
  <div class="panel-page-header">
    <div>
      <span class="panel-eyebrow">Integrasi browser</span>
      <h1>Ekstensi Chrome</h1>
      <p>Pasang XYZ Sniper di Chrome dan hubungkan browser ke workspace Anda.</p>
    </div>
  </div>

  <?php if (!empty($data['extension_release'])): ?>
    <?php $release = $data['extension_release']; ?>
    <section class="extension-release-card" aria-labelledby="extension-download-title">
      <div class="extension-release-heading">
        <div class="extension-mark" aria-hidden="true">
          <span class="material-symbols-outlined">extension</span>
        </div>
        <div>
          <div class="extension-label-row">
            <span class="extension-label">Versi terbaru</span>
            <span class="extension-version">v<?= htmlspecialchars($release['version'], ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <h2 id="extension-download-title">Unduh ekstensi Chrome</h2>
          <p>Gunakan file yang sama untuk Windows dan macOS. Ekstensi berjalan di browser Chrome.</p>
        </div>
      </div>

      <div class="extension-download-grid">
        <a class="extension-download-option" href="<?= burl; ?>/<?= htmlspecialchars($release['file'], ENT_QUOTES, 'UTF-8'); ?>" download>
          <span class="material-symbols-outlined" aria-hidden="true">desktop_windows</span>
          <span><strong>Unduh untuk Windows</strong><small>ZIP · Chrome</small></span>
          <span class="material-symbols-outlined extension-download-arrow" aria-hidden="true">download</span>
        </a>
        <a class="extension-download-option" href="<?= burl; ?>/<?= htmlspecialchars($release['file'], ENT_QUOTES, 'UTF-8'); ?>" download>
          <span class="material-symbols-outlined" aria-hidden="true">laptop_mac</span>
          <span><strong>Unduh untuk macOS</strong><small>ZIP · Chrome</small></span>
          <span class="material-symbols-outlined extension-download-arrow" aria-hidden="true">download</span>
        </a>
      </div>

      <div class="extension-install-note">
        <span class="material-symbols-outlined" aria-hidden="true">info</span>
        <p>Ekstrak ZIP, buka <code>chrome://extensions</code>, aktifkan <strong>Mode developer</strong>, lalu pilih <strong>Load unpacked</strong>.</p>
      </div>
    </section>

    <section class="extension-history" aria-labelledby="extension-history-title">
      <div class="extension-section-heading">
        <div>
          <span class="panel-eyebrow">Riwayat rilis</span>
          <h2 id="extension-history-title">Versi dan changelog</h2>
        </div>
        <span class="extension-history-count"><?= count($data['extension_releases']); ?> rilis tersimpan</span>
      </div>

      <div class="extension-release-list">
        <?php foreach ($data['extension_releases'] as $item): ?>
          <article class="extension-history-item">
            <div class="extension-history-meta">
              <strong>v<?= htmlspecialchars($item['version'], ENT_QUOTES, 'UTF-8'); ?></strong>
              <time datetime="<?= htmlspecialchars($item['published_at'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($item['published_at'], ENT_QUOTES, 'UTF-8'); ?></time>
            </div>
            <ul>
              <?php foreach ($item['changelog'] as $change): ?>
                <li><?= htmlspecialchars($change, ENT_QUOTES, 'UTF-8'); ?></li>
              <?php endforeach; ?>
            </ul>
            <a class="extension-archive-link" href="<?= burl; ?>/<?= htmlspecialchars($item['file'], ENT_QUOTES, 'UTF-8'); ?>" download>
              <span class="material-symbols-outlined" aria-hidden="true">download</span>
              Unduh versi ini
            </a>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php else: ?>
    <section class="extension-release-card extension-empty-state">
      <div class="extension-mark" aria-hidden="true"><span class="material-symbols-outlined">extension</span></div>
      <h2>Belum ada rilis</h2>
      <p>File ekstensi dan catatan rilis akan muncul di sini saat versi pertama diterbitkan.</p>
    </section>
  <?php endif; ?>
</main>

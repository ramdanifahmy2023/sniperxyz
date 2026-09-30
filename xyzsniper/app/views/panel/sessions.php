<main class="panel-main" aria-label="Sesi audit browser">
  <div class="panel-page-header">
    <div>
      <h1 style="margin:0;">Sesi Audit</h1>
      <p style="margin-top:4px;">Urutan request browser yang direkam untuk menyusun mapping workflow.</p>
    </div>
  </div>
  <article class="card" style="padding:0; overflow:hidden;">
    <div class="table-container">
      <table class="table w-full">
        <thead><tr><th>Sesi</th><th>Project</th><th>Status</th><th>Event</th><th>Terakhir</th><th></th></tr></thead>
        <tbody>
        <?php if (empty($data['sessions'])): ?>
          <tr><td colspan="6" style="text-align:center;padding:32px;">Belum ada sesi audit.</td></tr>
        <?php else: foreach ($data['sessions'] as $session): ?>
          <tr>
            <td><code><?= htmlspecialchars($session['session_id']); ?></code></td>
            <td><strong><?= htmlspecialchars($session['project_title']); ?></strong><br><small><?= htmlspecialchars($session['domain']); ?></small></td>
            <td><span class="badge" data-variant="<?= $session['status'] === 'active' ? 'primary' : 'outline'; ?>"><?= htmlspecialchars($session['status']); ?></span></td>
            <td><?= number_format((int) $session['event_count']); ?></td>
            <td><?= htmlspecialchars($session['last_seen_at']); ?></td>
            <td style="text-align:right;display:flex;gap:6px;justify-content:flex-end;"><a class="btn" data-variant="outline" data-size="sm" href="<?= burl; ?>/panel/session/<?= rawurlencode($session['session_id']); ?>">Timeline</a><a class="btn" data-variant="primary" data-size="sm" href="<?= burl; ?>/panel/export_session/<?= rawurlencode($session['session_id']); ?>">Export JSON</a></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </article>
  <nav aria-label="Halaman sesi" class="panel-page-actions" style="margin-top:16px;">
    <?php if ($data['page']>1): ?><a class="btn" href="?page=<?= $data['page']-1; ?>">Sebelumnya</a><?php endif; ?>
    <span>Halaman <?= $data['page']; ?></span>
    <?php if ($data['has_more']): ?><a class="btn" href="?page=<?= $data['page']+1; ?>">Berikutnya</a><?php endif; ?>
  </nav>
</main>

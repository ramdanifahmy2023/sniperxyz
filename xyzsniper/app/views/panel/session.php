<main class="panel-main" aria-label="Timeline sesi audit">
  <div class="panel-page-header">
    <div><h1 style="margin:0;">Timeline Audit</h1><p style="margin-top:4px;"><?= htmlspecialchars($data['session']['project_title']); ?> · <code><?= htmlspecialchars($data['session']['session_id']); ?></code></p></div>
    <div class="panel-page-actions"><a class="btn" data-variant="primary" href="<?= burl; ?>/panel/export_session/<?= rawurlencode($data['session']['session_id']); ?>">Export JSON</a><a class="btn" data-variant="outline" href="<?= burl; ?>/panel/sessions">Semua Sesi</a></div>
  </div>
  <article class="card" style="padding:0;overflow:hidden;">
    <div class="table-container">
      <table class="table w-full">
        <thead><tr><th>#</th><th>Method</th><th>Endpoint</th><th>Status</th><th>Source</th><th>Waktu</th><th>Hit</th></tr></thead>
        <tbody>
        <?php if (empty($data['events'])): ?><tr><td colspan="7" style="text-align:center;padding:32px;">Belum ada event pada sesi ini.</td></tr>
        <?php else: foreach ($data['events'] as $event): ?>
          <tr>
            <td><?= htmlspecialchars((string) ($event['sequence_no'] ?? '-')); ?></td>
            <td><span class="badge" data-variant="primary"><?= htmlspecialchars($event['http_method']); ?></span></td>
            <td><code title="<?= htmlspecialchars($event['request_url'] ?? ''); ?>"><?= htmlspecialchars($event['path']); ?></code></td>
            <td><?= htmlspecialchars((string) ($event['status_code'] ?? '-')); ?></td>
            <td><?= htmlspecialchars($event['source_type'] ?? '-'); ?></td>
            <td><?= htmlspecialchars($event['last_seen'] ?? $event['created_at']); ?></td>
            <td><?= number_format((int) ($event['hit_count'] ?? 1)); ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </article>
</main>

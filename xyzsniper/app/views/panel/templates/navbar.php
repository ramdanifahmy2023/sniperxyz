<header class="panel-topbar">
  <div class="panel-topbar-inner">
    <button
      type="button"
      class="btn panel-menu-toggle"
      data-variant="ghost"
      data-size="icon-sm"
      data-sidebar-toggle
      data-tooltip="Toggle sidebar"
      data-side="bottom"
      data-align="start"
      aria-label="Toggle sidebar"
    >
      <span class="material-symbols-outlined" aria-hidden="true">menu</span>
    </button>

        <nav class="panel-breadcrumb" aria-label="Breadcrumb">
      <span>Workspace</span>
      <?php
      $current_url = isset($_GET['url']) ? rtrim($_GET['url'], '/') : '';
      $url_parts = explode('/', $current_url);
      $curr_meth = strtolower($url_parts[1] ?? 'index');
      
      if ($curr_meth == 'index'): ?>
        <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
        <strong>Dashboard</strong>
      <?php elseif ($curr_meth == 'endpoints'): ?>
        <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
        <strong>Kelola Endpoint</strong>
      <?php elseif ($curr_meth == 'payloads'): ?>
        <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
        <a href="<?= burl; ?>/panel/endpoints" style="text-decoration: none; color: inherit;">Kelola Endpoint</a>
        <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
        <strong>Payload</strong>
      <?php elseif ($curr_meth == 'extension'): ?>
        <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
        <strong>Ekstensi Chrome</strong>
      <?php elseif ($curr_meth == 'account'): ?>
        <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
        <strong>Pengaturan Akun</strong>
      <?php else: ?>
        <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
        <strong><?= htmlspecialchars(ucfirst($curr_meth)) ?></strong>
      <?php endif; ?>
    </nav>

    <div class="panel-topbar-actions">
      <button
        type="button"
        class="btn panel-theme-toggle"
        data-variant="ghost"
        data-size="icon-sm"
        data-theme-toggle
        data-tooltip="Aktifkan mode gelap"
        data-side="bottom"
        data-align="end"
        aria-label="Aktifkan mode gelap"
      >
        <span class="material-symbols-outlined" aria-hidden="true">dark_mode</span>
      </button>
    </div>
  </div>
</header>

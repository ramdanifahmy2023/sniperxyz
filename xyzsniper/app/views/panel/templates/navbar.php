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
      <label class="panel-theme-control" for="panel-theme-select">
        <span class="material-symbols-outlined" aria-hidden="true">contrast</span>
        <span>Tema</span>
        <select id="panel-theme-select" data-theme-select aria-label="Tema tampilan">
          <option value="system">Sistem</option>
          <option value="light">Terang</option>
          <option value="dark">Gelap</option>
        </select>
      </label>
    </div>
  </div>
</header>

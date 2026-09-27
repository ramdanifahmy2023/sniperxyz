<?php
$current_url = isset($_GET['url']) ? rtrim($_GET['url'], '/') : '';
$url_parts = explode('/', $current_url);
$curr_ctrl = strtolower($url_parts[0] ?? '');
$curr_meth = strtolower($url_parts[1] ?? 'index');
?>
<aside id="sidebar" class="sidebar" data-side="left" data-initial-open="true">
  <nav aria-label="Navigasi utama">
    <header class="panel-sidebar-header">
      <a href="<?= burl; ?>/panel" class="btn panel-brand" data-variant="ghost" data-size="lg">
        <span class="panel-brand-mark" aria-hidden="true">
          <img src="<?= assets; ?>/images/app_sources/logo.png" alt="">
        </span>
        <span class="panel-brand-copy">
          <span>Sniper</span>
          <small>Workspace</small>
        </span>
      </a>
    </header>

    <section class="scrollbar">
      <div role="group">
        <h3>Menu</h3>
        <ul>
          <li>
            <a href="<?= burl; ?>/panel" <?= ($curr_ctrl == 'panel' && $curr_meth == 'index') ? 'aria-current="page"' : '' ?>>
              <span class="material-symbols-outlined" aria-hidden="true">dashboard</span>
              <span>Dashboard</span>
            </a>
          </li>
          <li>
            <a href="<?= burl; ?>/panel/endpoints" <?= ($curr_ctrl == 'panel' && in_array($curr_meth, ['endpoints', 'payloads'])) ? 'aria-current="page"' : '' ?>>
              <span class="material-symbols-outlined" aria-hidden="true">api</span>
              <span>Kelola Endpoint</span>
            </a>
          </li>
          <li>
            <a href="<?= burl; ?>/panel/sessions" <?= ($curr_ctrl == 'panel' && in_array($curr_meth, ['sessions', 'session'])) ? 'aria-current="page"' : '' ?>>
              <span class="material-symbols-outlined" aria-hidden="true">timeline</span>
              <span>Sesi Audit</span>
            </a>
          </li>
          <li>
            <a href="<?= burl; ?>/panel/extension" <?= ($curr_ctrl == 'panel' && $curr_meth == 'extension') ? 'aria-current="page"' : '' ?>>
              <span class="material-symbols-outlined" aria-hidden="true">extension</span>
              <span>Ekstensi Chrome</span>
            </a>
          </li>
        </ul>
      </div>

    </section>

    <footer class="panel-sidebar-footer">
      <?php
      $account = $_SESSION['account'] ?? [];
      $accountName = $account['name'] ?? 'Ryad Septiyandi';
      $accountEmail = $account['email'] ?? 'iam.ryadxyz@gmail.com';
      $accountAvatar = trim((string) ($account['avatar'] ?? ''));
      $initial = strtoupper(substr($accountName, 0, 1));
      ?>
      <div class="dropdown-menu panel-profile-menu">
        <button
          type="button"
          id="panel-profile-trigger"
          class="panel-profile-trigger"
          aria-haspopup="menu"
          aria-controls="panel-profile-menu"
          aria-expanded="false"
        >
          <span class="avatar" data-size="sm" aria-hidden="true">
            <?php if ($accountAvatar !== ''): ?>
              <img src="<?= htmlspecialchars($accountAvatar); ?>" alt="<?= htmlspecialchars($accountName); ?>" />
            <?php else: ?>
              <span><?= htmlspecialchars($initial); ?></span>
            <?php endif; ?>
          </span>
          <span class="panel-profile-copy">
            <strong><?= htmlspecialchars($accountName); ?></strong>
            <span><?= htmlspecialchars($accountEmail); ?></span>
          </span>
          <span class="material-symbols-outlined panel-profile-chevron" aria-hidden="true">unfold_more</span>
        </button>

        <div data-popover aria-hidden="true" data-side="top">
          <div id="panel-profile-menu" role="menu" aria-labelledby="panel-profile-trigger">
            <div role="heading"><?= htmlspecialchars($accountName); ?></div>
            <div role="separator"></div>
            <a href="<?= burl; ?>/panel/account" role="menuitem">
              <span class="material-symbols-outlined" aria-hidden="true">settings</span>
              <span>Pengaturan</span>
            </a>
            <a href="<?= burl; ?>/ProcAuth/logout" role="menuitem">
              <span class="material-symbols-outlined" aria-hidden="true">logout</span>
              <span>Keluar</span>
            </a>
          </div>
        </div>
      </div>
    </footer>
  </nav>
</aside>

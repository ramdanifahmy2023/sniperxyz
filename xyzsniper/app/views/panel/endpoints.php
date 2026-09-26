<?php
$activeProjectTitle = 'Pilih Proyek';
$activeProjectLogo = null;
if (!empty($data['projects'])) {
  foreach ($data['projects'] as $p) {
    if (isset($p['is_active']) && (int) $p['is_active'] === 1) {
      $activeProjectTitle = htmlspecialchars($p['title']);
      $activeProjectLogo = !empty($p['logo']) ? htmlspecialchars($p['logo']) : null;
      $activeProjectId = $p['id'] ?? null;
      break;
    }
  }
}
?>
<main class="panel-main" aria-label="Konten endpoint">
  <div class="panel-page-header">
    <div>
      <h1 style="margin: 0;">Kelola Endpoint</h1>
      <p style="margin-top: 4px;">Buat, pantau, dan konfigurasikan titik akses (endpoint) untuk menangkap payload dari target Anda.</p>
    </div>

    <div class="panel-page-actions" style="display: flex; gap: 8px;">
      <?php if (!empty($data['projects']) && isset($activeProjectId) && !empty($data['endpoints'])): ?>
      <button type="button" class="btn" data-variant="destructive" style="display: flex; align-items: center; gap: 0.5rem;" onclick="confirmAction('Bersihkan Proyek', 'Apakah Anda yakin ingin membersihkan semua data endpoint dan payload pada proyek ini?', '<?= burl; ?>/ProcProjects/clear/<?= $activeProjectId; ?>', 'POST', 'Bersihkan', 'destructive', 'delete')">
        <span class="material-symbols-outlined" aria-hidden="true">delete</span>
        <span>Bersihkan</span>
      </button>
      <?php endif; ?>
      <?php if (!empty($data['projects'])): ?>
      <button type="button" class="btn" data-variant="outline" style="display: flex; align-items: center; gap: 0.5rem;" onclick="document.getElementById('selectProjectDialog').showModal()">
        <span class="material-symbols-outlined" aria-hidden="true">folder</span>
        <?php if ($activeProjectLogo): ?>
          <img src="<?= $activeProjectLogo; ?>" alt="logo" style="width: 18px; height: 18px; border-radius: 4px; object-fit: cover; flex-shrink: 0;" />
        <?php endif; ?>
        <span><?= $activeProjectTitle; ?></span>
      </button>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($data['active_project'])): ?>
    <style>
      @media (min-width: 768px) {
        .card-sticky {
          height: calc(100vh - 170px);
          display: flex;
          flex-direction: column;
        }
        .card-sticky section {
          flex: 1;
          display: flex;
          flex-direction: column;
          overflow: hidden;
        }
        .card-sticky .table-container {
          flex: 1;
          overflow: auto;
        }
        .card-sticky thead th {
          position: sticky;
          top: 0;
          z-index: 10;
          background-color: var(--color-muted);
          box-shadow: 0 1px 0 var(--color-border);
        }
        .card-sticky footer {
          position: sticky;
          bottom: 0;
          background-color: var(--color-background);
          z-index: 10;
        }
      }
      .label-cell {
        display: flex;
        align-items: center;
        padding: 0 8px;
        min-height: 32px;
        min-width: 150px;
        border-radius: 4px;
        transition: background-color 0.2s;
        color: var(--color-foreground);
        font-size: 0.85rem;
        cursor: text;
      }
      .label-cell:hover {
        background-color: var(--color-muted);
      }
      .label-empty {
        font-style: italic;
        color: var(--color-muted-foreground);
        opacity: 0.7;
      }
    </style>
    <article class="card card-sticky" style="padding: 0; overflow: hidden; gap: 0 !important;">
      <header class="dashboard-card-header" style="padding: 16px 24px; margin-bottom: 0; display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
          <h2>Daftar Endpoint</h2>
          <p>Semua endpoint yang terdaftar pada proyek aktif.</p>
        </div>
        
        <div style="flex: 1; max-width: 450px; margin: 0 20px; display: flex; align-items: center; gap: 8px;">
          <div id="select-referer" class="select" data-placeholder="Semua Referer">
            <button type="button" class="w-[180px]" id="select-referer-trigger" aria-haspopup="listbox" aria-expanded="false" aria-controls="select-referer-listbox" style="border-radius: 20px; font-size: 0.85rem; height: 100%;">
              <span class="truncate">
              <?php
                $selectedText = 'Semua Referer';
                $selectedValue = $data['referer'] ?? '';
                if (!empty($selectedValue) && !empty($data['referers'])) {
                    foreach ($data['referers'] as $ref) {
                        if ($ref['base_referer'] === $selectedValue) {
                            $selectedText = htmlspecialchars(parse_url($ref['base_referer'], PHP_URL_PATH) ?? $ref['base_referer']);
                            break;
                        }
                    }
                }
                echo $selectedText;
              ?>
              </span>
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-down text-muted-foreground opacity-50 shrink-0"><path d="m6 9 6 6 6-6" /></svg>
            </button>
            <div id="select-referer-popover" data-popover aria-hidden="true">
              <div role="listbox" id="select-referer-listbox" aria-orientation="vertical" aria-labelledby="select-referer-trigger">
                <div role="group" aria-labelledby="select-referer-group">
                  <span role="heading" id="select-referer-group" style="display:none;">Referers</span>
                  <div role="option" data-value="" <?= $selectedValue === '' ? 'aria-selected="true"' : '' ?>>Semua Referer</div>
                  <?php if (!empty($data['referers'])): ?>
                    <?php foreach ($data['referers'] as $ref): ?>
                      <?php 
                        $val = htmlspecialchars($ref['base_referer']); 
                        $label = htmlspecialchars(parse_url($ref['base_referer'], PHP_URL_PATH) ?? $ref['base_referer']); 
                      ?>
                      <div role="option" data-value="<?= $val ?>" <?= $selectedValue === $val ? 'aria-selected="true"' : '' ?>><?= $label ?></div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <input type="hidden" id="refererFilterSelect" name="refererFilterSelect" value="<?= htmlspecialchars($selectedValue) ?>" />
          </div>
          <div style="flex: 1; position: relative;">
            <input id="endpointSearchInput" class="input" type="search" placeholder="Cari path atau isi payload (Enter)..." style="width: 100%; border-radius: 20px; outline: none; padding-left: 2.25rem;" value="<?= htmlspecialchars($data['keyword'] ?? '') ?>" />
            <div style="pointer-events: none; position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--color-muted-foreground);">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 21-4.34-4.34" /><circle cx="11" cy="11" r="8" /></svg>
            </div>
          </div>
        </div>

        <div style="text-align: right;">
          <p style="margin: 0; font-size: 0.85rem; color: var(--color-muted-foreground);">Domain Target</p>
          <code style="font-size: 0.85rem; margin-top: 4px; display: inline-block;"><?= htmlspecialchars($data['active_project']['domain'] ?? '') ?></code>
        </div>
      </header>
      <section style="padding-inline: 0;">
        <div class="table-container">
          <style>
            .table-endpoints th:first-child, .table-endpoints td:first-child { padding-left: 24px; }
            .table-endpoints th:last-child, .table-endpoints td:last-child { padding-right: 24px; }
          </style>
          <table class="table w-full table-endpoints">
            <thead>
              <tr style="background-color: var(--color-muted);">
                <th>Path Endpoint</th>
                <th>Label</th>
                <th>Jumlah Payload</th>
                <th>Ditangkap Pada</th>
                <th style="width: 120px; text-align: right;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              if (empty($data['endpoints'])):
              ?>
                <tr><td colspan="5" style="text-align:center; padding: 32px 0; color: var(--color-muted-foreground);">Belum ada endpoint yang tertangkap.</td></tr>
              <?php
              else:
              foreach ($data['endpoints'] as $ep): 
                $method_badges = ['GET' => 'outline', 'POST' => 'primary', 'PUT' => 'secondary', 'DELETE' => 'destructive', 'PATCH' => 'secondary', 'OPTION' => 'outline'];
                
                // Kumpulkan metode yang diizinkan
                $allowed_methods = [];
                if (isset($ep['get']) && $ep['get'] == 1) $allowed_methods[] = 'GET';
                if (isset($ep['post']) && $ep['post'] == 1) $allowed_methods[] = 'POST';
                if (isset($ep['put']) && $ep['put'] == 1) $allowed_methods[] = 'PUT';
                if (isset($ep['delete']) && $ep['delete'] == 1) $allowed_methods[] = 'DELETE';
                if (isset($ep['patch']) && $ep['patch'] == 1) $allowed_methods[] = 'PATCH';
                if (isset($ep['option']) && $ep['option'] == 1) $allowed_methods[] = 'OPTION';
                
                $methods_to_display = !empty($allowed_methods) ? $allowed_methods : ['ANY'];
                
                $date = date('d M Y H:i', strtotime($ep['created_at']));
                $payload_count = number_format($ep['payload_count'] ?? 0);
                
                // Ambil dari kolom label di database, default jika kosong/null
                $hasLabel = !empty($ep['label']);
                $keterangan = $hasLabel ? $ep['label'] : "Belum ada label..";
                $labelClass = $hasLabel ? 'label-cell' : 'label-cell label-empty';
              ?>
              <tr>
                <td>
                  <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                      <?php foreach ($methods_to_display as $m): $b = $method_badges[$m] ?? 'outline'; ?>
                        <span class="badge" data-variant="<?= $b ?>" style="font-size: 0.7rem; min-width: 50px; justify-content: center;"><?= $m ?></span>
                      <?php endforeach; ?>
                    </div>
                    <code style="font-size: 0.85rem;"><?= htmlspecialchars($ep['path']) ?></code>
                  </div>
                </td>
                <td>
                  <span class="<?= $labelClass ?>" 
                        title="<?= htmlspecialchars($keterangan); ?>" 
                        onclick="editLabel(this, <?= $ep['id'] ?>)" 
                        data-original="<?= htmlspecialchars($ep['label'] ?? '') ?>"><?= htmlspecialchars($keterangan) ?></span>
                </td>
                <td><?= $payload_count ?></td>
                <td><?= $date ?></td>
                <td style="text-align: right;">
                  <a href="<?= burl; ?>/panel/payloads/<?= $ep['id'] ?>" class="btn" data-variant="primary" data-size="sm" style="text-decoration: none;">Lihat Payload</a>
                </td>
              </tr>
              <?php 
              endforeach; 
              endif;
              ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php
        $page = $data['page'] ?? 1;
        $limit = $data['limit'] ?? 25;
        $total = $data['total'] ?? 0;
        $totalPages = max(1, ceil($total / $limit));
        $start = ($page - 1) * $limit + 1;
        $end = min($page * $limit, $total);
        if ($total == 0) { $start = 0; $end = 0; }
      ?>
      <footer style="padding: 16px 24px; border-top: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center;">
        <div style="display: flex; align-items: center; gap: 12px; font-size: 0.875rem; color: var(--color-muted-foreground);">
          <div style="display: flex; align-items: center; gap: 8px;">
            <span>Baris per halaman:</span>
            <div id="select-limit" class="select" data-placeholder="<?= $limit ?>">
              <button type="button" id="select-limit-trigger" aria-haspopup="listbox" aria-expanded="false" aria-controls="select-limit-listbox" style="border-radius: 8px; font-size: 0.875rem; height: 32px; padding: 0 12px; min-height: 32px; display: flex; align-items: center; gap: 8px; border: 1px solid var(--color-border); background-color: var(--color-background);">
                <span class="truncate"><?= $limit ?></span>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-down text-muted-foreground opacity-50 shrink-0"><path d="m6 9 6 6 6-6" /></svg>
              </button>
              <div id="select-limit-popover" data-popover aria-hidden="true">
                <div role="listbox" id="select-limit-listbox" aria-orientation="vertical" aria-labelledby="select-limit-trigger">
                  <div role="group" aria-labelledby="select-limit-group">
                    <span role="heading" id="select-limit-group" style="display:none;">Limit</span>
                    <div role="option" data-value="5" <?= $limit == 5 ? 'aria-selected="true"' : '' ?>>5</div>
                    <div role="option" data-value="10" <?= $limit == 10 ? 'aria-selected="true"' : '' ?>>10</div>
                    <div role="option" data-value="25" <?= $limit == 25 ? 'aria-selected="true"' : '' ?>>25</div>
                    <div role="option" data-value="50" <?= $limit == 50 ? 'aria-selected="true"' : '' ?>>50</div>
                  </div>
                </div>
              </div>
              <input type="hidden" id="limitFilterSelect" name="limit" value="<?= $limit ?>" />
            </div>
          </div>
          <span>Menampilkan <?= $start ?>-<?= $end ?> dari <?= $total ?></span>
        </div>
        <div style="display: flex; gap: 8px;">
          <a href="<?= $page > 1 ? '?page='.($page-1).'&limit='.$limit : '#' ?>" class="btn" data-variant="outline" data-size="sm" <?= $page <= 1 ? 'style="pointer-events:none;opacity:0.5;" tabindex="-1"' : '' ?>>Sebelumnya</a>
          <a href="<?= $page < $totalPages ? '?page='.($page+1).'&limit='.$limit : '#' ?>" class="btn" data-variant="outline" data-size="sm" <?= $page >= $totalPages ? 'style="pointer-events:none;opacity:0.5;" tabindex="-1"' : '' ?>>Selanjutnya</a>
        </div>
      </footer>
    </article>
    
    <script>
      function editLabel(spanElem, endpointId) {
        // Jangan eksekusi jika sudah berupa input
        if (spanElem.querySelector('input')) return;
        
        const originalText = spanElem.getAttribute('data-original');
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'input';
        input.style.height = '32px';
        input.style.minHeight = '32px';
        input.style.padding = '0 8px';
        input.style.fontSize = '0.85rem';
        input.style.width = '100%';
        input.value = originalText;
        
        // Ganti isi span dengan input
        spanElem.innerHTML = '';
        spanElem.appendChild(input);
        
        // Hapus class empty sementara mengedit
        spanElem.classList.remove('label-empty');
        
        input.focus();
        
        // Simpan ketika blur atau tekan Enter
        const saveLabel = () => {
          const newVal = input.value.trim();
          
          // Batalkan jika tidak ada perubahan
          if (newVal === originalText) {
             spanElem.innerHTML = originalText || 'Belum ada label..';
             if (!originalText) {
               spanElem.classList.add('label-empty');
             } else {
               spanElem.classList.remove('label-empty');
             }
             return;
          }
          
          // Tampilkan teks sementara proses save
          spanElem.innerHTML = newVal || 'Menyimpan...';
          if (!newVal) spanElem.classList.add('label-empty');
          
          // Kirim POST ke backend
          const formData = new FormData();
          formData.append('id', endpointId);
          formData.append('label', newVal);
          formData.append('csrf_token', '<?= htmlspecialchars(ProcAuth::csrfToken(), ENT_QUOTES); ?>');
          
          fetch('<?= burl ?>/panel/update_endpoint_label', {
            method: 'POST',
            body: formData
          })
          .then(res => res.json())
          .then(data => {
             // Update data original
             spanElem.setAttribute('data-original', newVal);
             spanElem.innerHTML = newVal || 'Belum ada label..';
             if (!newVal) {
               spanElem.classList.add('label-empty');
             } else {
               spanElem.classList.remove('label-empty');
             }
          })
          .catch(err => {
             alert('Gagal menyimpan label.');
             spanElem.innerHTML = originalText || 'Belum ada label..';
             if (!originalText) spanElem.classList.add('label-empty');
          });
        };
        
        input.addEventListener('blur', saveLabel);
        input.addEventListener('keydown', (e) => {
          if (e.key === 'Enter') {
            input.blur();
          } else if (e.key === 'Escape') {
            // Kembalikan nilai asli dan keluar dari mode edit
            input.value = originalText;
            input.blur();
          }
        });
      }
    </script>
  <?php else: ?>
  <section class="empty" style="margin-top: 40px; padding: 60px 20px; text-align: center;">
    <div class="empty-icon" style="margin: 0 auto 16px; width: 64px; height: 64px; background: rgba(128,128,128,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
      <span class="material-symbols-outlined" style="font-size: 32px; opacity: 0.5;">folder_off</span>
    </div>
    <h2 style="margin-bottom: 8px;">Tidak ada proyek aktif</h2>
    <p style="opacity: 0.7; max-width: 400px; margin: 0 auto 24px;">Silakan pilih proyek yang ada melalui menu di sudut kanan atas untuk memulai.</p>
  </section>
  <?php endif; ?>

  <dialog id="newProjectDialog" class="dialog" aria-labelledby="newProjectDialogTitle" aria-describedby="newProjectDialogDesc" onclick="if (event.target === this) this.close()">
    <div class="sm:max-w-sm">
      <header>
        <h2 id="newProjectDialogTitle">Buat Proyek Baru</h2>
        <p id="newProjectDialogDesc">Isi detail proyek di bawah ini.</p>
      </header>
      <section>
        <form id="formNewProject" action="<?= burl; ?>/ProcProjects/create" method="POST">
          <div role="group" class="fieldset">
            <div role="group" class="field" style="position: relative;">
              <label for="newProjectTitle">Judul Proyek</label>
              <input type="text" placeholder="Masukkan judul proyek..." id="newProjectTitle" name="title" autofocus required />
              <span id="errorTitle" style="color: #ef4444; font-size: 0.75rem; position: absolute; bottom: -20px; left: 0; display: none;">Judul sudah digunakan</span>
            </div>
            <div role="group" class="field" style="position: relative;">
              <label for="newProjectDomain">Target Domain</label>
              <div class="input-group">
                <input type="text" placeholder="contoh: domain.com" id="newProjectDomain" name="domain" required />
                <span data-align="start" aria-hidden="true" id="domainIconContainer">
                  <span class="material-symbols-outlined" style="font-size: 18px;">language</span>
                </span>
              </div>
              <span id="errorDomain" style="color: #ef4444; font-size: 0.75rem; position: absolute; bottom: -20px; left: 0; display: none;">Domain sudah digunakan</span>
            </div>
          </div>
        </form>
      </section>
      <footer>
        <button type="submit" id="btnCreateProject" form="formNewProject" class="btn w-full" disabled>Buat Proyek</button>
      </footer>
      <button type="button" class="btn" data-variant="ghost" data-size="icon-sm" aria-label="Tutup" onclick="this.closest('dialog').close()">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>
  </dialog>

  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const titleInput = document.getElementById("newProjectTitle");
      const domainInput = document.getElementById("newProjectDomain");
      const errorTitle = document.getElementById("errorTitle");
      const errorDomain = document.getElementById("errorDomain");
      const submitBtn = document.getElementById("btnCreateProject");
      const domainIconContainer = document.getElementById("domainIconContainer");
      
      let typingTimer;
      const doneTypingInterval = 400; // ms

      function checkExists() {
        const title = titleInput.value.trim();
        const domain = domainInput.value.trim();

        if (domain === '') {
          domainIconContainer.innerHTML = '<span class="material-symbols-outlined" style="font-size: 18px;">language</span>';
        } else {
          domainIconContainer.innerHTML = `<img src="https://www.google.com/s2/favicons?domain=${encodeURIComponent(domain)}&sz=32" style="width: 18px; height: 18px; border-radius: 4px;" alt="favicon" onerror="this.outerHTML='<span class=\\'material-symbols-outlined\\' style=\\'font-size: 18px;\\'>language</span>'" />`;
        }

        if (title === '' && domain === '') {
          submitBtn.disabled = true;
          errorTitle.style.display = 'none';
          errorDomain.style.display = 'none';
          titleInput.removeAttribute('aria-invalid');
          domainInput.removeAttribute('aria-invalid');
          return;
        }

        const formData = new FormData();
        formData.append('title', title);
        formData.append('domain', domain);

        fetch('<?= burl; ?>/ProcProjects/check_exists', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          let isInvalid = false;
          
          if (data.title_exists) {
            errorTitle.style.display = 'block';
            titleInput.setAttribute('aria-invalid', 'true');
            isInvalid = true;
          } else {
            errorTitle.style.display = 'none';
            titleInput.removeAttribute('aria-invalid');
          }

          if (data.domain_exists) {
            errorDomain.style.display = 'block';
            domainInput.setAttribute('aria-invalid', 'true');
            isInvalid = true;
          } else {
            errorDomain.style.display = 'none';
            domainInput.removeAttribute('aria-invalid');
          }
          
          if (isInvalid || title === '' || domain === '') {
            submitBtn.disabled = true;
          } else {
            submitBtn.disabled = false;
          }
        })
        .catch(error => {
          console.error('Error checking project:', error);
        });
      }

      const handleInput = () => {
        clearTimeout(typingTimer);
        submitBtn.disabled = true; 
        typingTimer = setTimeout(checkExists, doneTypingInterval);
      };

      titleInput.addEventListener('input', handleInput);
      domainInput.addEventListener('input', handleInput);
      
      // Initial check (disabled by default in HTML)
    });
  </script>
  <dialog id="selectProjectDialog" class="dialog" aria-labelledby="selectProjectDialogTitle" aria-describedby="selectProjectDialogDesc" onclick="if (event.target === this) this.close()">
    <div style="max-width: 768px; width: 100%; padding: 0; overflow: hidden;">
      <header style="padding: 24px 24px 0 24px; margin-bottom: 0;">
        <h2 id="selectProjectDialogTitle">Pilih Proyek</h2>
        <p id="selectProjectDialogDesc">Pilih proyek yang ingin Anda kelola dari daftar di bawah ini.</p>
      </header>
      <section style="padding-inline: 0; height: 50vh; min-height: 400px; overflow-y: auto;">
        <?php if (empty($data['projects'])): ?>
          <div class="empty py-8" style="padding-inline: 24px;">
            <figure class="text-gray-400 mb-2">
              <span class="material-symbols-outlined" style="font-size: 3rem;">folder_open</span>
            </figure>
            <header>
              <p class="text-gray-500">Belum ada proyek.</p>
            </header>
          </div>
        <?php else: ?>
          <div class="table-container">
            <style>
              .table-projects th:first-child, .table-projects td:first-child { padding-left: 24px; }
              .table-projects th:last-child, .table-projects td:last-child { padding-right: 24px; }
              .table-projects tr.active-project:hover { background-color: transparent !important; }
            </style>
            <table class="table w-full table-projects">
              <thead>
                <tr style="background-color: var(--color-muted);">
                  <th class="w-8"></th>
                  <th>Judul Proyek</th>
                  <th>URL</th>
                  <th>Dibuat</th>
                  <th style="width: 64px; text-align: right;"></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($data['projects'] as $index => $project): ?>
                  <?php if (isset($project['is_active']) && (int) $project['is_active'] === 1): ?>
                    <tr class="active-project" style="cursor: default;">
                      <td class="text-center"><span class="material-symbols-outlined" style="font-size: 1.25rem;">check</span></td>
                      <td><?= htmlspecialchars($project['title']); ?></td>
                      <td><?= htmlspecialchars($project['domain']); ?></td>
                      <td><?= date('d M Y', strtotime($project['created_at'])); ?></td>
                      <td class="text-right">
                        <button type="button" class="btn" data-variant="destructive" data-size="icon-sm" onclick="event.stopPropagation(); confirmAction('Hapus Proyek', 'Apakah Anda yakin ingin menghapus proyek <?= htmlspecialchars(addslashes($project['title'])); ?>?', '<?= burl; ?>/ProcProjects/delete/<?= $project['id']; ?>', 'POST', 'Hapus', 'destructive', 'delete');">
                          <span class="material-symbols-outlined" style="font-size: 1.1rem;">delete</span>
                        </button>
                      </td>
                    </tr>
                  <?php else: ?>
                    <tr style="cursor: pointer;" onclick="setActiveProject(<?= $project['id']; ?>)">
                      <td></td>
                      <td><?= htmlspecialchars($project['title']); ?></td>
                      <td><?= htmlspecialchars($project['domain']); ?></td>
                      <td><?= date('d M Y', strtotime($project['created_at'])); ?></td>
                      <td class="text-right">
                        <button type="button" class="btn" data-variant="destructive" data-size="icon-sm" onclick="event.stopPropagation(); confirmAction('Hapus Proyek', 'Apakah Anda yakin ingin menghapus proyek <?= htmlspecialchars(addslashes($project['title'])); ?>?', '<?= burl; ?>/ProcProjects/delete/<?= $project['id']; ?>', 'POST', 'Hapus', 'destructive', 'delete');">
                          <span class="material-symbols-outlined" style="font-size: 1.1rem;">delete</span>
                        </button>
                      </td>
                    </tr>
                  <?php endif; ?>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
      <footer style="padding: 16px 24px; border-top: 1px solid var(--color-border);">
        <p style="margin: 0; font-size: 0.875rem; color: var(--color-muted-foreground);">Total Proyek: <strong style="color: var(--color-foreground);"><?= is_array($data['projects']) ? count($data['projects']) : 0; ?></strong></p>
      </footer>
      <button type="button" class="btn" data-variant="ghost" data-size="icon-sm" aria-label="Tutup" onclick="this.closest('dialog').close()">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>
  </dialog>
</main>

<script>
function setActiveProject(id) {
  fetch('<?= burl; ?>/ProcProjects/set_active/' + id, {
    method: 'POST'
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      window.location.reload();
    } else {
      alert(data.message || 'Gagal mengubah proyek aktif');
    }
  })
  .catch(err => {
    console.error(err);
    alert('Terjadi kesalahan jaringan.');
  });
}
</script>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('endpointSearchInput');
    const refererSelect = document.getElementById('refererFilterSelect');
    
    function updateUrl() {
      const keyword = searchInput ? searchInput.value.trim() : '';
      const referer = refererSelect ? refererSelect.value : '';
      const url = new URL(window.location.href);
      
      if (keyword) {
          url.searchParams.set('keyword', keyword);
      } else {
          url.searchParams.delete('keyword');
      }
      
      if (referer) {
          url.searchParams.set('referer', referer);
      } else {
          url.searchParams.delete('referer');
      }
      
      // Hapus pagination jika ada filter aktif
      if (keyword || referer) {
          url.searchParams.delete('page');
      }
      
      window.location.href = url.toString();
    }

    if (searchInput) {
      searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            updateUrl();
        }
      });
    }
    
    if (refererSelect) {
      const options = document.querySelectorAll('#select-referer-listbox [role="option"]');
      if (options.length > 0) {
        options.forEach(opt => {
          opt.addEventListener('click', function() {
            refererSelect.value = this.getAttribute('data-value');
            setTimeout(updateUrl, 50);
          });
        });
      }
    }
    
    const limitSelect = document.getElementById('limitFilterSelect');
    if (limitSelect) {
      const limitOptions = document.querySelectorAll('#select-limit-listbox [role="option"]');
      if (limitOptions.length > 0) {
        limitOptions.forEach(opt => {
          opt.addEventListener('click', function() {
             const newLimit = this.getAttribute('data-value');
             const url = new URL(window.location.href);
             url.searchParams.set('page', '1');
             url.searchParams.set('limit', newLimit);
             window.location.href = url.toString();
          });
        });
      }
    }
  });
</script>

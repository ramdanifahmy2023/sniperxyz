
<main class="panel-main" aria-label="Konten endpoint">
  <div class="panel-page-header">
    <div>
      <h1 style="margin: 0;">Kelola Payload</h1>
      <p style="margin-top: 4px;">Pantau dan analisis data payload yang masuk ke endpoint ini.</p>
    </div>
  </div>

  <?php if (!empty($data['active_project'])): ?>
    <article class="card" style="padding: 0; overflow: hidden; gap: 0 !important;">
      <header class="dashboard-card-header" style="padding: 16px 24px; margin-bottom: 0;">
        <div>
          <h2>Daftar Payload</h2>
          <p>Dari endpoint : <code style="font-size: 0.85rem;"><?= htmlspecialchars(rtrim($data['active_project']['domain'] ?? '', '/') . ($data['endpoint']['path'] ?? '')) ?></code></p>
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
                <th>Status Kode</th>
                <th>Metode</th>
                <th>User Agent</th>
                <th>Referer</th>
                <th>Screenshot</th>
                <th>Ditangkap Pada</th>
                <th style="width: 120px; text-align: right;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              if (empty($data['payloads'])):
              ?>
                <tr><td colspan="8" style="text-align:center; padding: 32px 0; color: var(--color-muted-foreground);">Belum ada payload untuk endpoint ini.</td></tr>
              <?php
              else:
              $method_badges = ['GET' => 'outline', 'POST' => 'primary', 'PUT' => 'secondary', 'DELETE' => 'destructive', 'PATCH' => 'secondary', 'OPTIONS' => 'outline'];
              
              foreach ($data['payloads'] as $p): 
                $status = (int)$p['status_code'];
                
                // Menentukan warna badge status code
                $badge = 'outline';
                $badgeStyle = '';
                if ($status >= 200 && $status < 300) {
                  // Berhasil (Hijau)
                  $badge = 'primary';
                  $badgeStyle = 'background-color: #16a34a; border-color: #16a34a; color: #fff;';
                } elseif ($status >= 300 && $status < 400) {
                  // Redirect
                  $badge = 'secondary';
                } elseif ($status >= 400 && $status < 500) {
                  // Client Error (Orange/Red)
                  $badge = 'destructive';
                  $badgeStyle = 'background-color: #ea580c; border-color: #ea580c;';
                } elseif ($status >= 500) {
                  // Server Error (Merah)
                  $badge = 'destructive';
                }
                
                $method = strtoupper($p['http_method'] ?? 'GET');
                $method_badge = $method_badges[$method] ?? 'outline';
                
                $referer = $p['referer'] ?? '-';
                
                // Ekstrak User Agent dari request_header di tabel endpoint jika ada
                $ua = '-';
                if (!empty($data['endpoint']['request_header'])) {
                    $headers = json_decode($data['endpoint']['request_header'], true);
                    if ($headers) {
                        // Cari kunci user-agent case insensitive
                        foreach ($headers as $k => $v) {
                            if (strtolower($k) === 'user-agent') {
                                $ua = $v;
                                break;
                            }
                        }
                    }
                }
                
                // Screenshot URL
                $screenshot_url = !empty($p['screenshot']) ? burl . '/' . $p['screenshot'] : null;
                
                $hash = substr($p['payload_hash'], 0, 12);
                
                $ua_short = strlen($ua) > 20 ? substr($ua, 0, 20) . '...' : $ua;
                $ref_short = strlen($referer) > 20 ? substr($referer, 0, 20) . '...' : $referer;
                
                $date = date('d M Y H:i', strtotime($p['created_at']));
                
                // Siapkan data mentah untuk dimasukkan ke modal
                $req_body = $p['request_body'] ?? '';
                $query_params = $p['query_params'] ?? '';
                $res_body = $p['response_body'] ?? '';
                $res_header = $p['response_header'] ?? '';
              ?>
              <tr>
                <td>
                  <span class="badge" data-variant="<?= $badge ?>" style="font-size: 0.7rem; min-width: 40px; justify-content: center; <?= $badgeStyle ?>"><?= $status ?></span>
                </td>
                <td>
                  <span class="badge" data-variant="<?= $method_badge ?>" style="font-size: 0.7rem; min-width: 50px; justify-content: center;"><?= $method ?></span>
                </td>
                <td><span title="<?= htmlspecialchars($ua) ?>"><?= htmlspecialchars($ua_short) ?></span></td>
                <td><span title="<?= htmlspecialchars($referer) ?>"><?= htmlspecialchars($ref_short) ?></span></td>
                <td>
                  <?php if ($screenshot_url): ?>
                    <img src="<?= $screenshot_url ?>" alt="Screenshot Preview" class="avatar" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px; cursor: pointer; border: 1px solid var(--color-border);" onclick="document.getElementById('screenshotImageModal').src='<?= $screenshot_url ?>'; document.getElementById('screenshotModal').showModal();">
                  <?php else: ?>
                    <span style="color: var(--color-muted-foreground); font-size: 0.85rem; font-style: italic;">Tidak Ada</span>
                  <?php endif; ?>
                </td>
                <td><?= $date ?></td>
                <td style="text-align: right;">
                  <button type="button" class="btn" data-variant="primary" data-size="sm" onclick="showResponseModal(<?= htmlspecialchars(json_encode($req_body), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($query_params), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($res_body), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($res_header), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($method), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($referer), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($ua), ENT_QUOTES, 'UTF-8') ?>)">Lihat Response</button>
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
            <select class="input" style="padding: 4px 24px 4px 8px; font-size: 0.875rem; width: auto; height: 32px; min-height: 32px;" onchange="window.location.href='?page=1&limit='+this.value">
              <option value="5" <?= $limit == 5 ? 'selected' : '' ?>>5</option>
              <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
              <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
              <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
            </select>
          </div>
          <span>Menampilkan <?= $start ?>-<?= $end ?> dari <?= $total ?></span>
        </div>
        <div style="display: flex; gap: 8px;">
          <a href="<?= $page > 1 ? '?page='.($page-1).'&limit='.$limit : '#' ?>" class="btn" data-variant="outline" data-size="sm" <?= $page <= 1 ? 'style="pointer-events:none;opacity:0.5;" tabindex="-1"' : '' ?>>Sebelumnya</a>
          <a href="<?= $page < $totalPages ? '?page='.($page+1).'&limit='.$limit : '#' ?>" class="btn" data-variant="outline" data-size="sm" <?= $page >= $totalPages ? 'style="pointer-events:none;opacity:0.5;" tabindex="-1"' : '' ?>>Selanjutnya</a>
        </div>
      </footer>
    </article>
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

  <dialog id="screenshotModal" class="dialog" aria-labelledby="screenshotModalTitle" aria-describedby="screenshotModalDesc" onclick="if (event.target === this) this.close()">
    <div style="max-width: 800px; width: 100%; padding: 0; overflow: hidden; background: transparent; box-shadow: none;">
      <div style="position: relative;">
        <button type="button" class="btn" data-variant="ghost" data-size="icon-sm" style="position: absolute; top: 16px; right: 16px; background: rgba(0,0,0,0.5); color: white; border-radius: 50%;" aria-label="Tutup" onclick="this.closest('dialog').close()">
          <span class="material-symbols-outlined">close</span>
        </button>
        <img id="screenshotImageModal" src="" alt="Screenshot" style="width: 100%; height: auto; display: block; border-radius: 8px; box-shadow: var(--shadow-lg);">
      </div>
    </div>
  </dialog>

  <dialog id="responseModal" class="dialog" aria-labelledby="responseModalTitle" aria-describedby="responseModalDesc" onclick="if (event.target === this) this.close()">
    <div style="max-width: 768px; width: 100%;">
      <header>
        <h2 id="responseModalTitle">Detail Response Payload</h2>
        <p id="responseModalDesc">Dari endpoint : <code style="font-size: 0.85rem;"><?= htmlspecialchars(rtrim($data['active_project']['domain'] ?? '', '/') . ($data['endpoint']['path'] ?? '')) ?></code></p>
      </header>
      <section style="height: 60vh; min-height: 400px; padding-top: 0; display: flex; flex-direction: column; overflow: hidden;">
        <div style="display: flex; gap: 24px; border-bottom: 1px solid var(--color-border); margin-bottom: 16px; padding-top: 16px; flex-shrink: 0; overflow-x: auto;">
          <button type="button" class="tab-btn active" style="white-space: nowrap; background: none; border: none; padding: 0 0 8px 0; border-bottom: 2px solid var(--color-primary); color: var(--color-foreground); font-weight: 500; cursor: pointer; font-size: 0.875rem;" onclick="switchTab(this, 'tabReqBody')">Request Body</button>
          <button type="button" class="tab-btn" style="white-space: nowrap; background: none; border: none; padding: 0 0 8px 0; border-bottom: 2px solid transparent; color: var(--color-muted-foreground); font-weight: 500; cursor: pointer; font-size: 0.875rem;" onclick="switchTab(this, 'tabQueryParam')">Query Parameter</button>
          <button type="button" class="tab-btn" style="white-space: nowrap; background: none; border: none; padding: 0 0 8px 0; border-bottom: 2px solid transparent; color: var(--color-muted-foreground); font-weight: 500; cursor: pointer; font-size: 0.875rem;" onclick="switchTab(this, 'tabResBody')">Response Body</button>
          <button type="button" class="tab-btn" style="white-space: nowrap; background: none; border: none; padding: 0 0 8px 0; border-bottom: 2px solid transparent; color: var(--color-muted-foreground); font-weight: 500; cursor: pointer; font-size: 0.875rem;" onclick="switchTab(this, 'tabResHeader')">Response Header</button>
          <button type="button" class="tab-btn" style="white-space: nowrap; background: none; border: none; padding: 0 0 8px 0; border-bottom: 2px solid transparent; color: var(--color-muted-foreground); font-weight: 500; cursor: pointer; font-size: 0.875rem;" onclick="switchTab(this, 'tabMeta')">Metadata</button>
        </div>
        
        <div id="tabReqBody" class="tab-pane" style="display: flex; flex-direction: column; flex-grow: 1; overflow: hidden; padding-bottom: 16px;">
          <div style="background-color: var(--color-background); border: 1px solid var(--color-border); border-radius: 8px; padding: 16px; flex-grow: 1; overflow: auto;">
            <pre style="margin: 0; font-family: monospace; font-size: 0.85rem; color: var(--color-foreground); white-space: pre-wrap; word-wrap: break-word;"><code id="modalReqBody"></code></pre>
          </div>
        </div>
        
        <div id="tabQueryParam" class="tab-pane" style="display: none; flex-direction: column; flex-grow: 1; overflow: hidden; padding-bottom: 16px;">
          <div style="background-color: var(--color-background); border: 1px solid var(--color-border); border-radius: 8px; padding: 16px; flex-grow: 1; overflow: auto;">
            <pre style="margin: 0; font-family: monospace; font-size: 0.85rem; color: var(--color-foreground); white-space: pre-wrap; word-wrap: break-word;"><code id="modalQueryParam"></code></pre>
          </div>
        </div>

        <div id="tabResBody" class="tab-pane" style="display: none; flex-direction: column; flex-grow: 1; overflow: hidden; padding-bottom: 16px;">
          <div style="background-color: var(--color-background); border: 1px solid var(--color-border); border-radius: 8px; padding: 16px; flex-grow: 1; overflow: auto;">
            <pre style="margin: 0; font-family: monospace; font-size: 0.85rem; color: var(--color-foreground); white-space: pre-wrap; word-wrap: break-word;"><code id="modalResBody"></code></pre>
          </div>
        </div>

        <div id="tabResHeader" class="tab-pane" style="display: none; flex-direction: column; flex-grow: 1; overflow: hidden; padding-bottom: 16px;">
          <div style="background-color: var(--color-background); border: 1px solid var(--color-border); border-radius: 8px; padding: 16px; flex-grow: 1; overflow: auto;">
            <pre style="margin: 0; font-family: monospace; font-size: 0.85rem; color: var(--color-foreground); white-space: pre-wrap; word-wrap: break-word;"><code id="modalResHeader"></code></pre>
          </div>
        </div>

        <div id="tabMeta" class="tab-pane" style="display: none; flex-direction: column; flex-grow: 1; overflow: hidden; padding-bottom: 16px;">
          <div style="background-color: var(--color-background); border: 1px solid var(--color-border); border-radius: 8px; padding: 16px; flex-grow: 1; overflow: auto;">
            <pre style="margin: 0; font-family: monospace; font-size: 0.85rem; color: var(--color-foreground); white-space: pre-wrap; word-wrap: break-word;"><code id="modalMeta"></code></pre>
          </div>
        </div>
      </section>
      <button type="button" class="btn" data-variant="ghost" data-size="icon-sm" aria-label="Tutup" onclick="this.closest('dialog').close()">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>
  </dialog>
</main>

<script>
  function switchTab(btnElement, targetId) {
    // Reset all buttons
    const buttons = btnElement.parentElement.querySelectorAll('.tab-btn');
    buttons.forEach(btn => {
      btn.classList.remove('active');
      btn.style.borderColor = 'transparent';
      btn.style.color = 'var(--color-muted-foreground)';
    });
    
    // Set active button
    btnElement.classList.add('active');
    btnElement.style.borderColor = 'var(--color-primary)';
    btnElement.style.color = 'var(--color-foreground)';
    
    // Hide all panes
    const panes = document.querySelectorAll('.tab-pane');
    panes.forEach(pane => pane.style.display = 'none');
    
    // Show target pane
    document.getElementById(targetId).style.display = 'flex';
  }

  function showResponseModal(reqBody, queryParams, resBody, resHeader, method, referer, ua) {
    // Helper function to format JSON if possible
    const formatJSON = (str) => {
        try {
            if (!str) return 'Kosong / Tidak Ada';
            const parsed = JSON.parse(str);
            return JSON.stringify(parsed, null, 2);
        } catch(e) {
            return str;
        }
    };

    document.getElementById('modalReqBody').textContent = formatJSON(reqBody);
    document.getElementById('modalQueryParam').textContent = formatJSON(queryParams);
    document.getElementById('modalResBody').textContent = formatJSON(resBody);
    document.getElementById('modalResHeader').textContent = formatJSON(resHeader);
    document.getElementById('modalMeta').textContent = `HTTP Method: ${method}\nReferer: ${referer}\nUser-Agent: ${ua}`;

    document.getElementById('responseModal').showModal();
  }

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

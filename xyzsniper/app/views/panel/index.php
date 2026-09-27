<?php
$activeProjectTitle = 'Pilih Proyek';
$activeProjectLogo = null;
if (!empty($data['projects'])) {
  foreach ($data['projects'] as $p) {
    if (isset($p['is_active']) && (int) $p['is_active'] === 1) {
      $activeProjectTitle = htmlspecialchars($p['title']);
      $activeProjectLogo = !empty($p['logo']) ? htmlspecialchars($p['logo']) : null;
      break;
    }
  }
}
?>
<main class="panel-main" aria-label="Konten dashboard">
  <div class="panel-page-header">
    <div>
      <div style="display: flex; align-items: center; gap: 12px;">
        <img src="<?= burl; ?>/assets/images/app_sources/logo.png" alt="Logo" style="height: 40px; width: auto;" />
        <h1 style="margin: 0;">Selamat Datang</h1>
      </div>
      <?php if (!empty($data['active_project'])): ?>
        <p style="margin-top: 4px;">Anda sedang bekerja di proyek <strong><?= htmlspecialchars($data['active_project']['title']); ?></strong>.</p>
      <?php else: ?>
        <p style="margin-top: 4px;">Belum ada proyek yang aktif. Silakan pilih atau buat proyek baru.</p>
      <?php endif; ?>
    </div>

    <div class="panel-page-actions">
      <?php if (!empty($data['projects'])): ?>
      <button type="button" class="btn" data-variant="outline" style="display: flex; align-items: center; gap: 0.5rem;" onclick="document.getElementById('selectProjectDialog').showModal()">
        <span class="material-symbols-outlined" aria-hidden="true">folder</span>
        <?php if ($activeProjectLogo): ?>
          <img src="<?= $activeProjectLogo; ?>" alt="logo" style="width: 18px; height: 18px; border-radius: 4px; object-fit: cover; flex-shrink: 0;" />
        <?php endif; ?>
        <span><?= $activeProjectTitle; ?></span>
      </button>
      <?php endif; ?>
      <button type="button" class="btn" data-variant="primary" onclick="document.getElementById('newProjectDialog').showModal()">
        <span class="material-symbols-outlined" aria-hidden="true">add</span>
        Buat Proyek Baru
      </button>
    </div>
  </div>

  <?php if (!empty($data['active_project'])): ?>
  <article class="card dashboard-chart-card" style="margin-bottom: 24px;">
    <header class="dashboard-card-header">
      <div>
        <h2>Tangkapan Payload (28 Hari Terakhir)</h2>
        <p>Frekuensi request masuk ke seluruh endpoint pada proyek aktif.</p>
      </div>
    </header>
    <section>
      <?php if (array_sum($data['chart_data']['data']) > 0): ?>
        <div class="w-full" style="height: 300px;">
          <canvas id="payload-chart" aria-label="Grafik aktivitas tangkapan payload"></canvas>
        </div>
      <?php else: ?>
        <div class="empty flex flex-col items-center justify-center p-8 text-center min-h-[300px]">
          <span class="material-symbols-outlined text-4xl text-muted-foreground mb-4" style="opacity: 0.5; font-size: 48px;">bar_chart_4_bars</span>
          <h3 class="text-lg font-medium text-foreground">Belum Ada Data</h3>
          <p class="text-sm text-muted-foreground mt-1">Grafik aktivitas akan muncul di sini setelah endpoint Anda menerima payload.</p>
        </div>
      <?php endif; ?>
    </section>
  </article>

  <?php if (array_sum($data['chart_data']['data']) > 0): ?>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script src="<?= assets; ?>/css/basecoat/js/chart.min.js"></script>
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      const chartData = <?= json_encode($data['chart_data']['data']); ?>;
      
      const formattedData = Object.keys(chartData).map(date => {
        const dateObj = new Date(date);
        const day = dateObj.getDate().toString().padStart(2, '0');
        const monthNames = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agt", "Sep", "Okt", "Nov", "Des"];
        const label = day + ' ' + monthNames[dateObj.getMonth()];
        return {
          label: label,
          payload: chartData[date]
        };
      });

      const ctx = document.getElementById('payload-chart');
      if (ctx) {
        new Chart(ctx, {
          type: 'bar',
          data: {
            labels: formattedData.map(d => d.label),
            datasets: [{
              label: 'Payloads',
              data: formattedData.map(d => d.payload),
              backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim(),
              borderColor: getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim(),
              borderWidth: 1,
              borderRadius: 6
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              y: {
                beginAtZero: true,
                ticks: {
                    precision: 0 // Pastikan sumbu Y berupa bilangan bulat
                },
                grid: {
                    color: getComputedStyle(document.documentElement).getPropertyValue('--color-border').trim()
                }
              },
              x: {
                grid: {
                    display: false
                }
              }
            },
            plugins: {
              legend: { display: false },
              tooltip: {
                callbacks: {
                  label: function(context) {
                    return context.parsed.y + ' request payload';
                  }
                }
              }
            }
          }
        });
      }
    });
  </script>
  <?php endif; ?>

  <section class="dashboard-stats" aria-label="Ringkasan proyek aktif">
    <article class="card dashboard-stat-card">
      <header>
        <span>Total Endpoint</span>
        <span class="material-symbols-outlined" aria-hidden="true">link</span>
      </header>
      <section>
        <strong><?= number_format($data['active_project']['endpoints_count']); ?></strong>
        <p>Endpoint pada proyek ini</p>
      </section>
    </article>

    <article class="card dashboard-stat-card">
      <header>
        <span>Total Payload</span>
        <span class="material-symbols-outlined" aria-hidden="true">data_object</span>
      </header>
      <section>
        <strong><?= number_format($data['active_project']['payload_hits_count'] ?? $data['active_project']['payloads_count']); ?></strong>
        <p>Total request terekam</p>
      </section>
    </article>

    <article class="card dashboard-stat-card">
      <header>
        <span>Domain Target</span>
        <span class="material-symbols-outlined" aria-hidden="true">language</span>
      </header>
      <section>
        <strong style="font-size: 1rem; word-break: break-all; line-height: 1.4; display: block; max-height: 2.8em; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;"><?= htmlspecialchars($data['active_project']['domain']); ?></strong>
        <p>URL target proyek</p>
      </section>
    </article>

    <article class="card dashboard-stat-card">
      <header>
        <span>Dibuat Pada</span>
        <span class="material-symbols-outlined" aria-hidden="true">calendar_today</span>
      </header>
      <section>
        <strong style="font-size: 1.25rem;"><?= date('d M Y', strtotime($data['active_project']['created_at'])); ?></strong>
        <p>Tanggal proyek dibuat</p>
      </section>
    </article>
  </section>
  <?php else: ?>
  <section class="empty" style="margin-top: 40px; padding: 60px 20px; text-align: center;">
    <div class="empty-icon" style="margin: 0 auto 16px; width: 64px; height: 64px; background: color-mix(in srgb, var(--color-foreground) 8%, transparent); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
      <span class="material-symbols-outlined" style="font-size: 32px; opacity: 0.5;">folder_off</span>
    </div>
    <h2 style="margin-bottom: 8px;">Tidak ada proyek aktif</h2>
    <p style="opacity: 0.7; max-width: 400px; margin: 0 auto 24px;">Silakan pilih proyek yang ada melalui menu di sudut kanan atas, atau buat proyek baru untuk memulai.</p>
    <button type="button" class="btn" data-variant="primary" onclick="document.getElementById('newProjectDialog').showModal()">
      <span class="material-symbols-outlined" aria-hidden="true">add</span>
      Buat Proyek Baru
    </button>
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
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ProcAuth::csrfToken(), ENT_QUOTES); ?>" />
          <div role="group" class="fieldset">
            <div role="group" class="field" style="position: relative;">
              <label for="newProjectTitle">Judul Proyek</label>
              <input type="text" placeholder="Masukkan judul proyek..." id="newProjectTitle" name="title" autofocus required />
              <span id="errorTitle" class="inline-field-error" style="font-size: 0.75rem; position: absolute; bottom: -20px; left: 0; display: none;">Judul sudah digunakan</span>
            </div>
            <div role="group" class="field" style="position: relative;">
              <label for="newProjectDomain">Target Domain</label>
              <div class="input-group">
                <input type="text" placeholder="contoh: domain.com" id="newProjectDomain" name="domain" required />
                <span data-align="start" aria-hidden="true" id="domainIconContainer">
                  <span class="material-symbols-outlined" style="font-size: 18px;">language</span>
                </span>
              </div>
              <span id="errorDomain" class="inline-field-error" style="font-size: 0.75rem; position: absolute; bottom: -20px; left: 0; display: none;">Domain sudah digunakan</span>
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
    method: 'POST',
    headers: { 'X-CSRF-Token': '<?= htmlspecialchars(ProcAuth::csrfToken(), ENT_QUOTES); ?>' }
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

// assets/js/popup.js
const API_URL = CONFIG.getApiUrl('auth');

document.addEventListener('DOMContentLoaded', () => {
  // Cek apakah ekstensi memiliki token
  chrome.storage.local.get(['apiToken','activeProject'], function(result) {
    if (result.apiToken) {
      // Ada token, mari kita validasi ke API
      document.getElementById('status').innerText = 'Validating token...';
      
      fetch(API_URL, {
        signal: AbortSignal.timeout(8000),
        method: 'POST', // atau GET sesuai kebutuhan, tapi di Api.php kita menangani POST/JSON
        headers: {
          'Authorization': 'Bearer ' + result.apiToken,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ token: result.apiToken })
      })
      .then(response => response.json())
      .then(data => {
        if (data.status === 'success') {
          // Token valid, sembunyikan status loading dan tampilkan section utama
          document.getElementById('status').style.display = 'none';
          document.getElementById('main-section').style.display = 'block';
          
          // Render Profil Pengguna
          const user = data.data.user;
          document.getElementById('user-name').innerText = user.name || 'Pengguna';
          document.getElementById('user-email').innerText = user.email || '';
          
          if (user.avatar) {
            const avatarImg = document.getElementById('user-avatar');
            avatarImg.src = user.avatar;
            avatarImg.style.display = 'block';
            document.getElementById('user-avatar-fallback').style.display = 'none';
          }

          // Render Proyek Aktif
          const project = data.data.project;
          if (project && project.id) {
            document.getElementById('project-title').innerText = project.title;
            document.getElementById('project-domain').innerText = project.domain;
            document.getElementById('project-info').style.display = 'block';
            document.getElementById('no-project').style.display = 'none';
            
            // Simpan proyek aktif ke storage agar bisa diakses oleh background.js
            chrome.storage.local.set({ activeProject: project }, () => {
              chrome.runtime.sendMessage({ type: 'XYZ_SNIPER_SYNC_PROJECT', project }).catch(() => {});
            });
            setupCaptureControls(result.apiToken);
            
            // Render MCP URL
            const mcpUrlEl = document.getElementById('mcp-url');
            if (mcpUrlEl) {
                const baseUrl = CONFIG.getBaseUrl();
                mcpUrlEl.value = `${baseUrl}/mcp`;
                const mcpTokenEl = document.getElementById('mcp-token');
                if (mcpTokenEl) mcpTokenEl.value = result.apiToken;
                document.getElementById('mcp-info').style.display = 'block';
                
                // Copy Event
                mcpUrlEl.addEventListener('click', () => {
                    navigator.clipboard.writeText(mcpUrlEl.value);
                    const btn = document.getElementById('copy-mcp-btn');
                    btn.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">check</span>';
                    btn.dataset.state = 'success';
                    setTimeout(() => {
                      btn.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">content_copy</span>';
                      delete btn.dataset.state;
                    }, 2000);
                });
                document.getElementById('copy-mcp-btn').addEventListener('click', () => {
                    mcpUrlEl.click();
                });
                if (mcpTokenEl) {
                  const copyToken = () => navigator.clipboard.writeText(mcpTokenEl.value);
                  mcpTokenEl.addEventListener('click', copyToken);
                  document.getElementById('copy-mcp-token-btn')?.addEventListener('click', copyToken);
                }
                const copyConfigBtn = document.getElementById('copy-mcp-config-btn');
                const copyStatus = document.getElementById('mcp-copy-status');
                if (copyConfigBtn && mcpTokenEl) {
                  copyConfigBtn.addEventListener('click', async () => {
                    const config = [
                      '# XYZ Sniper MCP',
                      `export SNIPER_MCP_URL="${mcpUrlEl.value}"`,
                      `export SNIPER_MCP_TOKEN="${mcpTokenEl.value}"`
                    ].join('\n');
                    try {
                      await navigator.clipboard.writeText(config);
                      copyStatus.textContent = 'Akses MCP tersalin. Siap ditempel ke terminal atau agent.';
                      copyStatus.dataset.state = 'success';
                      copyConfigBtn.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">check</span>Tersalin';
                      window.setTimeout(() => {
                        copyConfigBtn.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">content_copy</span>Salin akses MCP lengkap';
                      }, 2200);
                    } catch (error) {
                      copyStatus.textContent = 'Clipboard tidak tersedia. Salin URL dan token secara manual.';
                      copyStatus.dataset.state = 'error';
                    }
                  });
                }
            }
            
          } else {
            document.getElementById('project-info').style.display = 'none';
            document.getElementById('no-project').style.display = 'block';
            
            // Hapus jika sebelumnya ada
            chrome.storage.local.remove('activeProject');
          }
        } else {
          // Token tidak valid (misal expired atau dihapus di database)
          redirectToPairing();
        }
      })
      .catch(error => {
        document.getElementById('status').innerText = 'Server belum terhubung. Antrean tetap ada di perangkat ini.';
        if (result.activeProject) {
          document.getElementById('main-section').style.display = 'block';
          document.getElementById('user-name').innerText = 'Mode offline';
          document.getElementById('project-title').innerText = result.activeProject.title;
          document.getElementById('project-domain').innerText = result.activeProject.domain;
          document.getElementById('project-info').style.display = 'block';
          document.getElementById('no-project').style.display = 'none';
          setupCaptureControls(result.apiToken);
        }
      });
      
    } else {
      // Tidak ada token
      redirectToPairing();
    }
  });
});

function redirectToPairing() {
  window.location.href = 'pair.html';
}

function setupCaptureControls(token) {
  const status = document.getElementById('capture-status');
  const toggle = document.getElementById('toggle-capture-btn');
  const end = document.getElementById('end-capture-btn');
  if (!status || !toggle || !end) return;
  const queueStatus = document.getElementById('capture-queue-status');
  const feedback = document.getElementById('capture-feedback');
  let enabled = false;
  const request = async (type, extra = {}) => {
    const result = await chrome.runtime.sendMessage({ type, ...extra });
    if (result?.error || result?.ok === false) throw new Error(result.error || 'Perintah gagal.');
    return result;
  };
  const refresh = async () => {
    try {
      const state = await request('XYZ_SNIPER_QUEUE_STATUS');
      enabled = state.captureEnabled !== false;
      status.textContent = enabled ? 'Perekam diizinkan' : 'Perekam dijeda';
      toggle.textContent = enabled ? 'Jeda' : 'Lanjutkan';
      queueStatus.textContent = `${state.pending} menunggu · ${state.failed} gagal · ${(state.bytes / 1048576).toFixed(1)} MiB. ${state.closing ? `${state.closing} sesi menunggu ditutup. ` : ''}Terakhir tersimpan: ${state.lastAck ? new Date(state.lastAck).toLocaleTimeString() : 'belum ada ACK'}.`;
      const reasons = state.errors.map(item => item.reason);
      if (state.captureError || reasons.length) feedback.textContent = state.captureError || reasons.join(' · ');
    } catch (error) { feedback.textContent = error.message; }
  };
  const action = (id, work) => document.getElementById(id).onclick = async () => {
    const button = document.getElementById(id); button.disabled = true; feedback.textContent = 'Memproses...';
    try { feedback.textContent = await work() || 'Selesai.'; }
    catch (error) { feedback.textContent = error.message; }
    finally { button.disabled = false; await refresh(); }
  };
  action('toggle-capture-btn', async () => { await request('XYZ_SNIPER_SET_CAPTURE_STATE', { enabled: !enabled }); return enabled ? 'Perekam dijeda.' : 'Perekam diizinkan pada domain proyek. Refresh halaman jika baru memperbarui ekstensi.'; });
  action('end-capture-btn', async () => { await request('XYZ_SNIPER_END_SESSION'); return 'Perekam berhenti. Sesi ditutup setelah antreannya tersimpan. Data gagal tetap perlu ditangani.'; });
  action('retry-capture-btn', async () => { await request('XYZ_SNIPER_RETRY'); return 'Antrean dicoba kembali. Periksa jumlah gagal.'; });
  action('snapshot-now-btn', async () => {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    const result = await chrome.tabs.sendMessage(tab.id, { type: 'XYZ_SNIPER_SNAPSHOT_NOW' }, { frameId: 0 });
    if (!result?.ok) throw new Error('Belum direkam. Pastikan domain proyek cocok dan perekam tidak dijeda.');
    return 'Snapshot diminta. Tunggu status terakhir tersimpan sebelum membaca lewat AI.';
  });
  action('export-queue-btn', async () => {
    const data = await request('XYZ_SNIPER_EXPORT_QUEUE');
    const url = URL.createObjectURL(new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' }));
    const link = document.createElement('a'); link.href = url; link.download = `sniper-queue-${Date.now()}.json`; link.click();
    setTimeout(() => URL.revokeObjectURL(url), 30000);
    return 'Antrean diekspor. Data tetap disimpan di ekstensi.';
  });
  void refresh();
  setInterval(refresh, 2000);
}

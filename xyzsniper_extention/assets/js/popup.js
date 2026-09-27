// assets/js/popup.js
const API_URL = CONFIG.getApiUrl('auth');

document.addEventListener('DOMContentLoaded', () => {
  // Cek apakah ekstensi memiliki token
  chrome.storage.local.get(['apiToken'], function(result) {
    if (result.apiToken) {
      // Ada token, mari kita validasi ke API
      document.getElementById('status').innerText = 'Validating token...';
      
      fetch(API_URL, {
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
                    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size: 1.25rem; color: #10b981;">check</span>';
                    setTimeout(() => btn.innerHTML = '<span class="material-symbols-outlined" style="font-size: 1.25rem;">content_copy</span>', 2000);
                });
                document.getElementById('copy-mcp-btn').addEventListener('click', () => {
                    mcpUrlEl.click();
                });
                if (mcpTokenEl) {
                  const copyToken = () => navigator.clipboard.writeText(mcpTokenEl.value);
                  mcpTokenEl.addEventListener('click', copyToken);
                  document.getElementById('copy-mcp-token-btn')?.addEventListener('click', copyToken);
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
        console.error('Error:', error);
        document.getElementById('status').innerText = 'Connection error. Please try again.';
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
  chrome.runtime.sendMessage({ type: 'XYZ_SNIPER_QUEUE_STATUS' }).then(queue => {
    if (queueStatus && queue) queueStatus.textContent = `Queue: ${queue.pending || 0} pending · ${queue.inflight || 0} dikirim · ${queue.deadLetter || 0} gagal permanen`;
  }).catch(() => {});
  chrome.storage.local.get(['captureEnabled'], stored => {
    let enabled = stored.captureEnabled !== false;
    const render = () => {
      status.textContent = enabled ? 'Audit aktif' : 'Audit dijeda';
      status.dataset.variant = enabled ? 'primary' : 'outline';
      toggle.textContent = enabled ? 'Pause' : 'Lanjutkan';
    };
    render();
    toggle.onclick = () => {
      enabled = !enabled;
      chrome.storage.local.set({ captureEnabled: enabled }, render);
      chrome.runtime.sendMessage({ type: 'XYZ_SNIPER_SET_CAPTURE_STATE', enabled }).catch(() => {});
    };
    end.onclick = async () => {
      const state = await new Promise(resolve => chrome.storage.local.get(['captureSession'], resolve));
      const sessionId = state.captureSession && state.captureSession.session_id;
      if (sessionId) {
        try {
          await fetch(CONFIG.getApiUrl('sessionEnd'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
            body: JSON.stringify({ session_id: sessionId })
          });
        } catch (_) {}
      }
      await new Promise(resolve => chrome.storage.local.set({ captureEnabled: false }, resolve));
      await new Promise(resolve => chrome.storage.local.remove('captureSession', resolve));
      enabled = false;
      render();
    };
  });
}

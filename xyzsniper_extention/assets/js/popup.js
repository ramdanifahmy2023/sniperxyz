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
            chrome.storage.local.set({ activeProject: project });
            
            // Render MCP URL
            const mcpUrlEl = document.getElementById('mcp-url');
            if (mcpUrlEl) {
                const baseUrl = CONFIG.getBaseUrl();
                mcpUrlEl.value = `${baseUrl}/mcp?token=${result.apiToken}`;
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

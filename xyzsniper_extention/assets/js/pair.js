// assets/js/pair.js
const API_URL = CONFIG.getApiUrl('auth');

document.addEventListener('DOMContentLoaded', () => {
  const btnPair = document.getElementById('btn-pair');
  const inputPasscode = document.getElementById('passcode');
  const statusText = document.getElementById('pair-status');

  const setStatus = (message, state) => {
    statusText.innerText = message;
    statusText.dataset.state = state;
  };

  // Hanya perbolehkan input angka
  inputPasscode.addEventListener('input', (e) => {
    e.target.value = e.target.value.replace(/[^0-9]/g, '');
  });

  btnPair.addEventListener('click', () => {
    const passcode = inputPasscode.value.trim();
    
    if (!passcode) {
      setStatus('Please enter a passcode.', 'error');
      return;
    }

    setStatus('Connecting...', 'pending');
    btnPair.disabled = true;

    fetch(API_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ passcode: passcode })
    })
    .then(response => response.json())
    .then(data => {
      if (data.status === 'success' && data.data && data.data.token) {
        // Simpan token ke storage
        chrome.storage.local.set({ apiToken: data.data.token }, () => {
          setStatus('Connected successfully!', 'success');
          
          // Kembali ke index.html
          setTimeout(() => {
            window.location.href = 'index.html';
          }, 1000);
        });
      } else {
        setStatus(data.message || 'Invalid passcode.', 'error');
        btnPair.disabled = false;
      }
    })
    .catch(error => {
      console.error('Error:', error);
      setStatus('Gagal terhubung ke server. Silakan coba lagi.', 'error');
      btnPair.disabled = false;
    });
  });
});

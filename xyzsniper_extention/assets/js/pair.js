// assets/js/pair.js
const API_URL = CONFIG.getApiUrl('auth');

document.addEventListener('DOMContentLoaded', () => {
  const btnPair = document.getElementById('btn-pair');
  const inputPasscode = document.getElementById('passcode');
  const statusText = document.getElementById('pair-status');

  // Hanya perbolehkan input angka
  inputPasscode.addEventListener('input', (e) => {
    e.target.value = e.target.value.replace(/[^0-9]/g, '');
  });

  btnPair.addEventListener('click', () => {
    const passcode = inputPasscode.value.trim();
    
    if (!passcode) {
      statusText.innerText = 'Please enter a passcode.';
      statusText.style.color = '#ef4444';
      return;
    }

    statusText.innerText = 'Connecting...';
    statusText.style.color = 'black';
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
          statusText.innerText = 'Connected successfully!';
          statusText.style.color = 'green';
          
          // Kembali ke index.html
          setTimeout(() => {
            window.location.href = 'index.html';
          }, 1000);
        });
      } else {
        statusText.innerText = data.message || 'Invalid passcode.';
        statusText.style.color = '#ef4444';
        btnPair.disabled = false;
      }
    })
    .catch(error => {
      console.error('Error:', error);
      statusText.innerText = 'Gagal terhubung ke server. Silakan coba lagi.';
      statusText.style.color = '#ef4444';
      btnPair.disabled = false;
    });
  });
});

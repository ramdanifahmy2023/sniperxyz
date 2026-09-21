// assets/js/content.js

// Injeksi interceptor.js ke dalam halaman (MAIN world)
const script = document.createElement('script');
script.src = chrome.runtime.getURL('assets/js/interceptor.js');
script.onload = function() {
    this.remove(); // Bersihkan tag script setelah dimuat
};
(document.head || document.documentElement).appendChild(script);

// Dengarkan pesan dari interceptor.js
window.addEventListener('message', function(event) {
  // Hanya terima pesan dari window yang sama
  if (event.source !== window) return;

  if (event.data && event.data.source === 'XYZ_SNIPER_INTERCEPT') {
    // Kirim payload ke background.js dengan try..catch
    try {
      chrome.runtime.sendMessage({
        type: 'XYZ_SNIPER_CAPTURED_REQUEST',
        data: event.data.payload,
        pageUrl: window.location.href,
        userAgent: navigator.userAgent
      });
    } catch (err) {
      if (err.message.includes('Extension context invalidated')) {
        console.warn('XYZ Sniper: Ekstensi diperbarui. Silakan refresh halaman ini agar ekstensi dapat kembali memantau koneksi.');
      }
    }
  }
});

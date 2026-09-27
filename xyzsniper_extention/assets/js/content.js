// Bridge the page's MAIN-world request audit to the extension service worker.
const navigationId = (() => {
  if (globalThis.crypto && typeof globalThis.crypto.randomUUID === 'function') return `nav_${globalThis.crypto.randomUUID()}`;
  return `nav_${Date.now().toString(36)}_${Math.random().toString(36).slice(2)}`;
})();

const script = document.createElement('script');
script.src = chrome.runtime.getURL('assets/js/interceptor.js');
script.onload = function () { this.remove(); };
(document.head || document.documentElement).appendChild(script);

window.addEventListener('message', function (event) {
  if (event.source !== window || !event.data || event.data.source !== 'XYZ_SNIPER_INTERCEPT') return;
  try {
    chrome.runtime.sendMessage({
      type: 'XYZ_SNIPER_CAPTURED_REQUEST',
      data: event.data.payload,
      pageUrl: window.location.href,
      userAgent: navigator.userAgent,
      navigation_id: navigationId,
      document_id: document.visibilityState ? `${location.origin}:${navigationId}` : navigationId
    }).catch(error => {
      if (!String(error && error.message || error).includes('Extension context invalidated')) console.debug('XYZ Sniper: capture bridge unavailable', error);
    });
  } catch (error) {
    if (String(error && error.message || error).includes('Extension context invalidated')) {
      console.warn('XYZ Sniper: Ekstensi diperbarui. Refresh halaman untuk melanjutkan audit.');
    }
  }
});

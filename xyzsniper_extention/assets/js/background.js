// Background script untuk XYZ Sniper
importScripts('config.js');

console.log("Background script loaded.");

// Hash Set Cache (TIDAK DIPAKAI LAGI KARENA DIGANTIKAN BATCHING)
// Antrean (Queue) & Timer
let payloadQueue = [];
let debounceTimer = null;
let maxWaitTimer = null;
const DEBOUNCE_MS = 1500;
const MAX_WAIT_MS = 5000;

function flushQueue() {
  if (payloadQueue.length === 0) return;
  
  // Salin dan kosongkan antrean
  const batch = [...payloadQueue];
  payloadQueue = [];
  
  // Bersihkan semua timer
  if (debounceTimer) { clearTimeout(debounceTimer); debounceTimer = null; }
  if (maxWaitTimer) { clearTimeout(maxWaitTimer); maxWaitTimer = null; }
  
  chrome.storage.local.get(['apiToken'], (result) => {
    const token = result.apiToken;
    if (!token) return;
    
    chrome.tabs.captureVisibleTab({ format: 'jpeg', quality: 20 }, (dataUrl) => {
      if (chrome.runtime.lastError) {
         console.warn("XYZ Sniper: Could not capture tab:", chrome.runtime.lastError.message);
         dataUrl = '';
      }
      
      const captureApiUrl = CONFIG.getApiUrl('captureBulk');
      
      fetch(captureApiUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer ' + token
        },
        body: JSON.stringify({
          screenshot: dataUrl,
          payloads: batch
        })
      })
      .then(res => res.json())
      .then(resData => {
         console.log("🎯 XYZ Sniper: Bulk Payload successfully captured and sent.", resData);
      })
      .catch(err => {
         console.error("XYZ Sniper Bulk API Error:", err);
      });
    });
  });
}

function enqueuePayload(payloadData) {
  payloadQueue.push(payloadData);
  
  // Reset debounce timer
  if (debounceTimer) {
      clearTimeout(debounceTimer);
  }
  debounceTimer = setTimeout(() => {
      flushQueue();
  }, DEBOUNCE_MS);
  
  // Start maxWait timer jika belum ada
  if (!maxWaitTimer) {
      maxWaitTimer = setTimeout(() => {
          flushQueue();
      }, MAX_WAIT_MS);
  }
}

async function generateHash(message) {
  const msgBuffer = new TextEncoder().encode(message);
  const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
  const hashArray = Array.from(new Uint8Array(hashBuffer));
  return hashArray.map(b => b.toString(16).padStart(2, '0')).join('').substring(0, 32);
}

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.type === 'XYZ_SNIPER_CAPTURED_REQUEST') {
    const payload = message.data;
    const pageUrl = message.pageUrl;

    // Cek proyek aktif dari storage
    chrome.storage.local.get(['activeProject', 'apiToken'], (result) => {
      const activeProject = result.activeProject;
      const token = result.apiToken;
      
      if (!activeProject || !activeProject.domain || !token) {
        console.warn('XYZ Sniper: capture diabaikan; token atau proyek aktif belum tersedia. Buka popup ekstensi untuk menyegarkan proyek.');
        return; // Tidak ada proyek aktif atau token
      }

      try {
        // Pastikan domain tangkapan cocok dengan target domain
        // Compare host + port. `hostname` alone makes localhost:3000 fail.
        const targetUrl = /^https?:\/\//i.test(activeProject.domain)
          ? activeProject.domain
          : 'http://' + activeProject.domain;
        const target = new URL(targetUrl);
        const current = new URL(pageUrl);
        const targetHost = target.host.replace(/^www\./i, '').toLowerCase();
        const currentHost = current.host.replace(/^www\./i, '').toLowerCase();
        const domainMatches = currentHost === targetHost
          || currentHost.endsWith('.' + targetHost);

        if (domainMatches) {
          
          // Parsing untuk mendapatkan path dan query Params/payload
          let parsedUrl;
          try {
              parsedUrl = new URL(payload.url, pageUrl);
          } catch (e) {
              parsedUrl = new URL('http://dummy.com' + (payload.url.startsWith('/') ? '' : '/') + payload.url);
          }
          const path = parsedUrl.pathname;
          
          const actualQueryParams = parsedUrl.search; 
          let hashStr = actualQueryParams;
          if (payload.method !== 'GET' && payload.requestBody) {
            if (typeof payload.requestBody === 'object') {
               hashStr = JSON.stringify(payload.requestBody);
            } else {
               hashStr = payload.requestBody.toString();
            }
          }

          const hashInput = path + hashStr;
          generateHash(hashInput).then(hash => {
            const payloadData = {
              url: payload.url,
              method: payload.method,
              requestHeaders: { ...payload.requestHeaders, 'user-agent': message.userAgent },
              responseHeaders: payload.responseHeaders,
              requestBody: payload.requestBody,
              responseBody: payload.responseBody,
              status: payload.status,
              pageUrl: pageUrl,
              payloadHash: hash,
              queryParams: actualQueryParams
            };
            
            enqueuePayload(payloadData);
          });
        } else {
          console.debug('XYZ Sniper: capture diabaikan; domain tidak cocok.', { currentHost, targetHost });
        }
      } catch (e) {
        console.error("XYZ Sniper: Error processing captured request", e);
      }
    });
  }
});

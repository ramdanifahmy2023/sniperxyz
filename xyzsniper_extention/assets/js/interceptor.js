(function() {
  const SENDER_ID = 'XYZ_SNIPER_INTERCEPT';

  // Helper untuk mem-parsing body menjadi string atau JSON yang aman
  function parseOrString(data) {
    if (!data) return null;
    try {
      if (typeof data === 'string') return JSON.parse(data);
      if (data instanceof Blob) return "Blob data";
      if (data instanceof ArrayBuffer) return "ArrayBuffer data";
      if (data instanceof FormData) {
        let obj = {};
        data.forEach((value, key) => { obj[key] = value; });
        return obj;
      }
      if (data instanceof URLSearchParams) return data.toString();
      if (typeof data === 'object') return JSON.parse(JSON.stringify(data)); // Pastikan clean object
      return data;
    } catch(e) {
      return data.toString();
    }
  }

  // --- OVERRIDE FETCH ---
  const originalFetch = window.fetch;
  window.fetch = async function(...args) {
    let url = '';
    let method = 'GET';
    let reqBody = null;
    let reqHeaders = {};

    try {
      if (typeof args[0] === 'string' || args[0] instanceof URL) {
        url = args[0].toString();
        if (args[1]) {
          method = args[1].method || 'GET';
          reqBody = args[1].body;
          
          // Tangani Headers object agar aman di-clone
          if (args[1].headers) {
            if (args[1].headers instanceof Headers) {
              reqHeaders = Object.fromEntries(args[1].headers.entries());
            } else {
              reqHeaders = JSON.parse(JSON.stringify(args[1].headers));
            }
          }
        }
      } else if (args[0] instanceof Request) {
        url = args[0].url;
        method = args[0].method;
        reqHeaders = Object.fromEntries(args[0].headers.entries());
      }
    } catch (e) {
      console.warn("XYZ Sniper: Gagal parsing fetch request", e);
    }

    // Abaikan intersepsi untuk file statis (mencegah warning preload/credentials di browser)
    if (url && url.match(/\.(css|js|png|jpg|jpeg|gif|svg|woff|woff2|ico|webp|avif)(\?.*)?$/i)) {
      return originalFetch.apply(this, args);
    }

    try {
      const response = await originalFetch.apply(this, args);
      
      try {
        const clone = response.clone();
        clone.text().then(text => {
          let isJson = false;
          const resHeaders = Object.fromEntries(response.headers.entries());
          const contentType = resHeaders['content-type'] || resHeaders['Content-Type'] || '';
          
          if (contentType.includes('application/json') || url.includes('.json')) {
            isJson = true;
          }

          if (isJson) {
            window.postMessage({
              source: SENDER_ID,
              payload: {
                type: 'fetch',
                method: method.toUpperCase(),
                url: url,
                requestHeaders: reqHeaders,
                requestBody: parseOrString(reqBody),
                responseHeaders: resHeaders,
                responseBody: parseOrString(text),
                status: response.status
              }
            }, '*');
          }
        }).catch(e => { /* Abaikan jika gagal baca text */ });
      } catch (cloneErr) {
        // Abaikan jika tidak bisa clone response (misal response tipe opaque)
      }
      
      return response;
    } catch(error) {
      throw error;
    }
  };

  // --- OVERRIDE XHR ---
  const originalXhrOpen = XMLHttpRequest.prototype.open;
  const originalXhrSend = XMLHttpRequest.prototype.send;
  const originalXhrSetRequestHeader = XMLHttpRequest.prototype.setRequestHeader;

  XMLHttpRequest.prototype.open = function(method, url, ...rest) {
    this._xyzMethod = method;
    this._xyzUrl = url;
    this._xyzReqHeaders = {};
    return originalXhrOpen.call(this, method, url, ...rest);
  };

  XMLHttpRequest.prototype.setRequestHeader = function(header, value) {
    this._xyzReqHeaders[header] = value;
    return originalXhrSetRequestHeader.call(this, header, value);
  };

  XMLHttpRequest.prototype.send = function(body) {
    this._xyzReqBody = body;
    
    this.addEventListener('load', function() {
      const contentType = this.getResponseHeader('Content-Type') || '';
      let isJson = contentType.includes('application/json') || (this._xyzUrl && this._xyzUrl.includes('.json'));
      
      if (isJson) {
        let resHeadersObj = {};
        const resHeadersStr = this.getAllResponseHeaders();
        if (resHeadersStr) {
          resHeadersStr.trim().split(/[\r\n]+/).forEach(line => {
            const parts = line.split(': ');
            if(parts.length >= 2) {
              const header = parts.shift();
              const value = parts.join(': ');
              resHeadersObj[header] = value;
            }
          });
        }

        window.postMessage({
          source: SENDER_ID,
          payload: {
            type: 'xhr',
            method: (this._xyzMethod || 'GET').toUpperCase(),
            url: this._xyzUrl,
            requestHeaders: this._xyzReqHeaders,
            requestBody: parseOrString(this._xyzReqBody),
            responseHeaders: resHeadersObj,
            responseBody: parseOrString(this.responseText),
            status: this.status
          }
        }, '*');
      }
    });

    return originalXhrSend.call(this, body);
  };

})();

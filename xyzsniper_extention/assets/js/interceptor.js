(function () {
  const SENDER_ID = 'XYZ_SNIPER_INTERCEPT';
  let sequenceNo = 0;

  function makeId(prefix) {
    if (globalThis.crypto && typeof globalThis.crypto.randomUUID === 'function') return `${prefix}_${globalThis.crypto.randomUUID()}`;
    return `${prefix}_${Date.now().toString(36)}_${Math.random().toString(36).slice(2)}_${sequenceNo}`;
  }

  function headersToObject(headers) {
    if (!headers) return {};
    try {
      if (headers instanceof Headers) return Object.fromEntries(headers.entries());
      if (Array.isArray(headers)) return Object.fromEntries(headers);
      return Object.assign({}, headers);
    } catch (error) { return {}; }
  }

  function headerValue(headers, name) {
    const wanted = String(name).toLowerCase();
    const entry = Object.entries(headers || {}).find(([key]) => key.toLowerCase() === wanted);
    return entry ? String(entry[1]) : '';
  }

  function isTextContentType(contentType) {
    return /^(text\/|application\/(json|javascript|xml|graphql|x-www-form-urlencoded)|\+json\b|\+xml\b)/i.test(contentType || '');
  }

  function bytesToBase64(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    const chunkSize = 0x8000;
    for (let offset = 0; offset < bytes.length; offset += chunkSize) binary += String.fromCharCode(...bytes.subarray(offset, offset + chunkSize));
    return btoa(binary);
  }

  function decodeBytes(buffer, contentType) {
    if (isTextContentType(contentType)) {
      const text = new TextDecoder().decode(buffer);
      if (/json|graphql|javascript/i.test(contentType || '')) {
        try { return { value: JSON.parse(text), raw: text, format: 'json' }; } catch (error) { /* Preserve malformed JSON as text. */ }
      }
      return { value: text, raw: text, format: 'text' };
    }
    return {
      value: { encoding: 'base64', data: bytesToBase64(buffer), mime_type: contentType || 'application/octet-stream' },
      raw: null,
      format: 'binary'
    };
  }

  async function serializeBody(body, contentType) {
    if (body === null || body === undefined) return { value: null, raw: null, format: 'empty' };
    if (typeof body === 'string') {
      if (isTextContentType(contentType) && /json|graphql/i.test(contentType || '')) {
        try { return { value: JSON.parse(body), raw: body, format: 'json' }; } catch (error) { /* Keep text. */ }
      }
      return { value: body, raw: body, format: 'text' };
    }
    if (body instanceof URLSearchParams) return { value: body.toString(), raw: body.toString(), format: 'text' };
    if (body instanceof FormData) {
      const values = {};
      for (const [key, value] of body.entries()) {
        if (value instanceof Blob) {
          const item = decodeBytes(await value.arrayBuffer(), value.type || contentType || 'application/octet-stream');
          values[key] = { name: value.name || null, type: value.type || null, size: value.size, body: item.value };
        } else values[key] = String(value);
      }
      return { value: values, raw: null, format: 'form-data' };
    }
    if (body instanceof Blob) return decodeBytes(await body.arrayBuffer(), body.type || contentType);
    if (body instanceof ArrayBuffer) return decodeBytes(body, contentType);
    if (ArrayBuffer.isView(body)) return decodeBytes(body.buffer.slice(body.byteOffset, body.byteOffset + body.byteLength), contentType);
    if (typeof body === 'object') {
      try { return { value: body, raw: JSON.stringify(body), format: 'json' }; } catch (error) { return { value: String(body), raw: String(body), format: 'text' }; }
    }
    return { value: body, raw: String(body), format: 'text' };
  }

  async function serializeRequest(request, contentType) {
    if (!request || request.method === 'GET' || request.method === 'HEAD') return { value: null, raw: null, format: 'empty' };
    try {
      const buffer = await request.clone().arrayBuffer();
      return buffer.byteLength ? decodeBytes(buffer, contentType) : { value: '', raw: '', format: 'text' };
    } catch (error) { return { value: null, raw: null, format: 'unavailable' }; }
  }

  async function readResponse(response) {
    const contentType = response.headers.get('content-type') || '';
    if (response.status === 204 || response.status === 205 || !response.body) return { body: null, raw: null, contentType, format: 'empty', size: 0 };
    try {
      const buffer = await response.arrayBuffer();
      const decoded = decodeBytes(buffer, contentType);
      return { body: decoded.value, raw: decoded.raw, contentType, format: decoded.format, size: buffer.byteLength };
    } catch (error) { return { body: null, raw: null, contentType, format: 'unavailable', size: 0 }; }
  }

  function getHost(url) {
    try { return new URL(url, location.href).host; } catch (error) { return ''; }
  }

  function postCapture(payload) { window.postMessage({ source: SENDER_ID, payload }, '*'); }

  function baseEvent(sourceType, url, method, startedAt) {
    const absoluteUrl = (() => { try { return new URL(url, location.href).href; } catch (error) { return String(url || ''); } })();
    return {
      event_id: makeId('evt'), sequence_no: ++sequenceNo, source_type: sourceType,
      request_url: absoluteUrl, host: getHost(absoluteUrl), method: String(method || 'GET').toUpperCase(),
      url: absoluteUrl, started_at: Date.now(), started_perf: startedAt
    };
  }

  async function captureFetch(event, requestHeaders, requestBody, response, durationMs) {
    const responseHeaders = headersToObject(response.headers);
    let responseData;
    try {
      responseData = await readResponse(response.clone());
    } catch (error) {
      responseData = { body: null, raw: null, contentType: headerValue(responseHeaders, 'content-type'), format: 'unavailable', size: 0 };
    }
    postCapture({
      type: 'fetch', event_id: event.event_id, sequence_no: event.sequence_no, source_type: event.source_type,
      request_url: event.request_url, host: event.host, method: event.method, url: event.url,
      requestHeaders, requestBody: requestBody.value, requestBodyRaw: requestBody.raw, requestBodyFormat: requestBody.format,
      responseHeaders, responseBody: responseData.body, responseBodyRaw: responseData.raw, responseBodyFormat: responseData.format,
      content_type: responseData.contentType || headerValue(responseHeaders, 'content-type') || null,
      request_content_type: headerValue(requestHeaders, 'content-type') || null,
      request_size: requestBody.raw ? new TextEncoder().encode(requestBody.raw).length : null,
      response_size: responseData.size, status: response.status, duration_ms: Math.max(0, Math.round(durationMs)),
      response_url: response.url || event.request_url
    });
  }

  const originalFetch = window.fetch;
  window.fetch = async function (...args) {
    let url = '';
    let method = 'GET';
    let requestHeaders = {};
    let requestBody = { value: null, raw: null, format: 'empty' };
    try {
      if (args[0] instanceof Request) {
        const request = args[0];
        url = request.url; method = request.method; requestHeaders = headersToObject(request.headers);
        requestBody = await serializeRequest(request, headerValue(requestHeaders, 'content-type'));
      } else {
        url = String(args[0] || '');
        const init = args[1] || {};
        method = init.method || 'GET'; requestHeaders = headersToObject(init.headers);
        requestBody = await serializeBody(init.body, headerValue(requestHeaders, 'content-type'));
      }
    } catch (error) { console.warn('XYZ Sniper: fetch request metadata unavailable', error); }

    if (url && /\.(css|js|png|jpg|jpeg|gif|svg|woff|woff2|ico|webp|avif)(\?.*)?$/i.test(url)) return originalFetch.apply(this, args);
    const startedAt = performance.now();
    const event = baseEvent('fetch', url, method, startedAt);
    try {
      const response = await originalFetch.apply(this, args);
      captureFetch(event, requestHeaders, requestBody, response, performance.now() - startedAt)
        .catch(error => console.debug('XYZ Sniper: fetch response metadata unavailable', error));
      return response;
    } catch (error) {
      postCapture({
        type: 'fetch', event_id: event.event_id, sequence_no: event.sequence_no, source_type: event.source_type,
        request_url: event.request_url, host: event.host, method: event.method, url: event.url, requestHeaders,
        requestBody: requestBody.value, requestBodyRaw: requestBody.raw, requestBodyFormat: requestBody.format,
        responseHeaders: {}, responseBody: null, responseBodyRaw: null, responseBodyFormat: 'network-error',
        content_type: null, request_content_type: headerValue(requestHeaders, 'content-type') || null,
        status: 0, duration_ms: Math.max(0, Math.round(performance.now() - startedAt)), error: String(error && error.message || error)
      });
      throw error;
    }
  };

  const originalXhrOpen = XMLHttpRequest.prototype.open;
  const originalXhrSend = XMLHttpRequest.prototype.send;
  const originalXhrSetRequestHeader = XMLHttpRequest.prototype.setRequestHeader;

  XMLHttpRequest.prototype.open = function (method, url, ...rest) {
    this._xyzMethod = method; this._xyzUrl = String(url || ''); this._xyzReqHeaders = {}; this._xyzStartedAt = 0; this._xyzEvent = null;
    return originalXhrOpen.call(this, method, url, ...rest);
  };

  XMLHttpRequest.prototype.setRequestHeader = function (header, value) {
    this._xyzReqHeaders = this._xyzReqHeaders || {};
    this._xyzReqHeaders[header] = this._xyzReqHeaders[header] ? `${this._xyzReqHeaders[header]}, ${value}` : value;
    return originalXhrSetRequestHeader.call(this, header, value);
  };

  XMLHttpRequest.prototype.send = function (body) {
    this._xyzReqBody = body; this._xyzStartedAt = performance.now(); this._xyzEvent = baseEvent('xhr', this._xyzUrl, this._xyzMethod, this._xyzStartedAt);
    const xhr = this;
    this.addEventListener('loadend', async function () {
      const responseHeaders = {};
      (xhr.getAllResponseHeaders() || '').trim().split(/[\r\n]+/).forEach(line => {
        const separator = line.indexOf(':');
        if (separator > 0) {
          const name = line.slice(0, separator).trim();
          const value = line.slice(separator + 1).trim();
          responseHeaders[name] = responseHeaders[name] ? `${responseHeaders[name]}, ${value}` : value;
        }
      });
      const contentType = headerValue(responseHeaders, 'content-type');
      let responseData = { body: null, raw: null, format: 'empty', size: 0, contentType };
      try {
        if (xhr.status !== 204 && xhr.status !== 205) {
          if (xhr.responseType === 'blob' && xhr.response instanceof Blob) {
            const decoded = decodeBytes(await xhr.response.arrayBuffer(), xhr.response.type || contentType);
            responseData = { body: decoded.value, raw: decoded.raw, format: decoded.format, size: xhr.response.size, contentType: xhr.response.type || contentType };
          } else if (xhr.responseType === 'arraybuffer' && xhr.response instanceof ArrayBuffer) {
            const decoded = decodeBytes(xhr.response, contentType);
            responseData = { body: decoded.value, raw: decoded.raw, format: decoded.format, size: xhr.response.byteLength, contentType };
          } else if (xhr.responseType === 'json' && xhr.response !== null) {
            const raw = JSON.stringify(xhr.response);
            responseData = { body: xhr.response, raw, format: 'json', size: raw.length, contentType };
          } else if (typeof xhr.responseText === 'string') {
            const raw = xhr.responseText; let bodyValue = raw;
            if (/json|graphql/i.test(contentType)) { try { bodyValue = JSON.parse(raw); } catch (error) { /* Keep malformed JSON as text. */ } }
            responseData = { body: bodyValue, raw, format: bodyValue === raw ? 'text' : 'json', size: new TextEncoder().encode(raw).length, contentType };
          }
        }
      } catch (error) { responseData = { body: null, raw: null, format: 'unavailable', size: 0, contentType }; }
      const requestData = await serializeBody(xhr._xyzReqBody, headerValue(xhr._xyzReqHeaders, 'content-type'));
      const event = xhr._xyzEvent;
      postCapture({
        type: 'xhr', event_id: event.event_id, sequence_no: event.sequence_no, source_type: event.source_type,
        request_url: event.request_url, host: event.host, method: event.method, url: event.url,
        requestHeaders: xhr._xyzReqHeaders || {}, requestBody: requestData.value, requestBodyRaw: requestData.raw, requestBodyFormat: requestData.format,
        responseHeaders, responseBody: responseData.body, responseBodyRaw: responseData.raw, responseBodyFormat: responseData.format,
        content_type: responseData.contentType || contentType || null, request_content_type: headerValue(xhr._xyzReqHeaders, 'content-type') || null,
        request_size: requestData.raw ? new TextEncoder().encode(requestData.raw).length : null, response_size: responseData.size,
        status: xhr.status || 0, duration_ms: Math.max(0, Math.round(performance.now() - event.started_perf)), response_url: xhr.responseURL || event.request_url
      });
    }, { once: true });
    return originalXhrSend.call(this, body);
  };
})();

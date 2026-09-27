// Durable capture queue and project/session coordinator for XYZ Sniper.
importScripts('config.js');

const QUEUE_KEY = 'captureQueueState';
const LEGACY_QUEUE_KEY = 'pendingPayloadQueue';
const SESSION_KEY = 'captureSession';
const BATCH_SIZE = 25;
const MAX_QUEUE_SIZE = 500;
const MAX_DEAD_LETTER = 200;
const MAX_ATTEMPTS = 8;
const DEBOUNCE_MS = 1500;
const MAX_WAIT_MS = 5000;
const RETRY_BASE_MS = 2000;
const RETRY_MAX_MS = 5 * 60 * 1000;

let queueState = { pending: [], inflight: [], deadLetter: [] };
let sessionState = null;
let flushInProgress = false;
let debounceTimer = null;
let maxWaitTimer = null;
let retryTimer = null;
let stateReady = null;
let sessionSyncPromise = null;

function uuid(prefix) {
  if (globalThis.crypto && typeof globalThis.crypto.randomUUID === 'function') return `${prefix}_${globalThis.crypto.randomUUID()}`;
  return `${prefix}_${Date.now().toString(36)}_${Math.random().toString(36).slice(2)}`;
}

function storageGet(keys) {
  return new Promise(resolve => chrome.storage.local.get(keys, result => resolve(result || {})));
}

function storageSet(values) {
  return new Promise(resolve => chrome.storage.local.set(values, () => resolve()));
}

function normaliseQueue(value) {
  const candidate = value && typeof value === 'object' ? value : {};
  return {
    pending: Array.isArray(candidate.pending) ? candidate.pending : [],
    inflight: Array.isArray(candidate.inflight) ? candidate.inflight : [],
    deadLetter: Array.isArray(candidate.deadLetter) ? candidate.deadLetter : []
  };
}

function boundQueue() {
  while (queueState.pending.length > MAX_QUEUE_SIZE) {
    const dropped = queueState.pending.shift();
    queueState.deadLetter.push({ item: dropped, reason: 'queue_overflow', failed_at: Date.now() });
  }
  while (queueState.deadLetter.length > MAX_DEAD_LETTER) queueState.deadLetter.shift();
}

async function persistQueue() {
  boundQueue();
  await storageSet({ [QUEUE_KEY]: queueState, [LEGACY_QUEUE_KEY]: queueState.pending });
}

async function initialiseQueue() {
  const result = await storageGet([QUEUE_KEY, LEGACY_QUEUE_KEY]);
  queueState = normaliseQueue(result[QUEUE_KEY]);
  if (!result[QUEUE_KEY] && Array.isArray(result[LEGACY_QUEUE_KEY])) queueState.pending = result[LEGACY_QUEUE_KEY];
  // A worker can be terminated after moving a batch to inflight. Requeue it on startup.
  if (queueState.inflight.length) {
    queueState.pending = queueState.inflight.concat(queueState.pending).map(item => ({
      ...item,
      nextAttemptAt: Math.min(Number(item.nextAttemptAt) || 0, Date.now()),
      lastError: item.lastError || 'service_worker_restart'
    }));
    queueState.inflight = [];
  }
  await persistQueue();
  scheduleRetryAlarm();
}

stateReady = initialiseQueue().catch(error => console.error('XYZ Sniper queue init failed', error));

function backoff(attempt) {
  const exponential = Math.min(RETRY_MAX_MS, RETRY_BASE_MS * (2 ** Math.max(0, attempt - 1)));
  return exponential + Math.floor(Math.random() * Math.min(1000, exponential / 4));
}

function scheduleRetryAlarm() {
  if (!chrome.alarms || typeof chrome.alarms.create !== 'function') return;
  const due = queueState.pending.reduce((minimum, item) => Math.min(minimum, Number(item.nextAttemptAt) || 0), Infinity);
  if (Number.isFinite(due)) {
    chrome.alarms.create('xyz-sniper-capture-retry', { when: Math.max(Date.now() + 1000, due) });
  }
}

function scheduleRetryTimer() {
  if (retryTimer) clearTimeout(retryTimer);
  const due = queueState.pending.reduce((minimum, item) => Math.min(minimum, Number(item.nextAttemptAt) || 0), Infinity);
  if (!Number.isFinite(due)) return;
  retryTimer = setTimeout(() => { retryTimer = null; flushQueue(); }, Math.max(250, due - Date.now()));
  scheduleRetryAlarm();
}

if (chrome.alarms && chrome.alarms.onAlarm) {
  chrome.alarms.onAlarm.addListener(alarm => {
    if (alarm && alarm.name === 'xyz-sniper-capture-retry') flushQueue();
  });
}

async function generateHash(message) {
  const msgBuffer = new TextEncoder().encode(message);
  const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
  return Array.from(new Uint8Array(hashBuffer)).map(byte => byte.toString(16).padStart(2, '0')).join('').slice(0, 32);
}

function projectKey(project) {
  return String(project && (project.id || project.domain) || '');
}

async function ensureSession(activeProject) {
  const key = projectKey(activeProject);
  if (!key) return null;
  if (sessionSyncPromise) await sessionSyncPromise;
  if (!sessionState || sessionState.project_key !== key) {
    sessionSyncPromise = (async () => {
      const stored = await storageGet(SESSION_KEY);
      if (stored[SESSION_KEY] && stored[SESSION_KEY].project_key === key) sessionState = stored[SESSION_KEY];
      else {
        sessionState = {
          session_id: uuid('ses'), project_key: key, project_id: activeProject.id || null,
          session_title: activeProject.title ? `Browser audit - ${activeProject.title}` : 'Browser audit',
          session_started_at: new Date().toISOString(), started_at_ms: Date.now(), last_seen_at: new Date().toISOString()
        };
      }
      await storageSet({ [SESSION_KEY]: sessionState });
    })();
    try { await sessionSyncPromise; } finally { sessionSyncPromise = null; }
  }
  sessionState.last_seen_at = new Date().toISOString();
  return sessionState;
}

function normaliseTargetHost(domain) {
  try {
    const value = /^https?:\/\//i.test(String(domain)) ? String(domain) : `http://${domain}`;
    return new URL(value).host.replace(/^www\./i, '').toLowerCase();
  } catch (error) { return String(domain || '').replace(/^www\./i, '').toLowerCase().split('/')[0]; }
}

function hostMatches(pageUrl, domain) {
  try {
    const current = new URL(pageUrl);
    const currentHost = current.host.replace(/^www\./i, '').toLowerCase();
    const targetHost = normaliseTargetHost(domain);
    return Boolean(targetHost && (currentHost === targetHost || currentHost.endsWith(`.${targetHost}`)));
  } catch (error) { return false; }
}

function queryParams(url, pageUrl) {
  try { return new URL(url, pageUrl).search; } catch (error) { return ''; }
}

function dueItems() {
  const now = Date.now();
  return queueState.pending.filter(item => !item.nextAttemptAt || Number(item.nextAttemptAt) <= now).slice(0, BATCH_SIZE);
}

function extractAckIds(result, batch) {
  if (!result || result.status !== 'success') return null;
  const responses = result.acks || result.results || result.items || (result.data && (result.data.acks || result.data.results));
  if (!Array.isArray(responses)) return new Set(batch.map(item => item.event_id));
  const accepted = new Set();
  responses.forEach(entry => {
    const id = entry && (entry.event_id || entry.id || entry.client_id);
    const status = String(entry && entry.status || 'success').toLowerCase();
    if (id && !['error', 'failed', 'retry'].includes(status)) accepted.add(String(id));
  });
  return accepted;
}

async function postBatch(batch, token) {
  const response = await fetch(CONFIG.getApiUrl('captureBulk'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
    body: JSON.stringify({ payloads: batch.map(item => item.payload) })
  });
  const text = await response.text();
  let data = {};
  try { data = text ? JSON.parse(text) : {}; } catch (error) { data = {}; }
  if (!response.ok) throw new Error(`HTTP ${response.status}${data.message ? `: ${data.message}` : ''}`);
  if (data.status && data.status !== 'success') throw new Error(data.message || 'capture gagal');
  if (response.status === 204) return { status: 'success' };
  if (!data.status) throw new Error('capture API returned an invalid response');
  return data;
}

async function retryItems(items, reason) {
  const now = Date.now();
  items.forEach(item => {
    const attempts = Number(item.attempts || 0) + 1;
    if (attempts >= MAX_ATTEMPTS) {
      queueState.deadLetter.push({ item: { ...item, attempts, lastError: String(reason) }, reason: 'max_attempts', failed_at: now });
    } else {
      queueState.pending.push({ ...item, attempts, lastError: String(reason), nextAttemptAt: now + backoff(attempts) });
    }
  });
}

async function flushQueue() {
  await stateReady;
  if (flushInProgress) return;
  const batch = dueItems();
  if (!batch.length) { scheduleRetryTimer(); return; }
  flushInProgress = true;
  const ids = new Set(batch.map(item => item.queue_id));
  queueState.pending = queueState.pending.filter(item => !ids.has(item.queue_id));
  queueState.inflight = queueState.inflight.concat(batch);
  await persistQueue();
  try {
    const auth = await storageGet('apiToken');
    if (!auth.apiToken) {
      // Authentication may be restored later; do not consume retry attempts while offline.
      queueState.inflight = queueState.inflight.filter(item => !ids.has(item.queue_id));
      queueState.pending.push(...batch.map(item => ({ ...item, nextAttemptAt: Date.now() + 60000, lastError: 'api token unavailable' })));
      return;
    }
    const result = await postBatch(batch, auth.apiToken);
    const acknowledged = extractAckIds(result, batch);
    const remaining = batch.filter(item => !acknowledged || !acknowledged.has(item.event_id));
    queueState.inflight = queueState.inflight.filter(item => !ids.has(item.queue_id));
    if (remaining.length) await retryItems(remaining, 'server did not acknowledge event');
  } catch (error) {
    queueState.inflight = queueState.inflight.filter(item => !ids.has(item.queue_id));
    await retryItems(batch, error.message || error);
    console.warn('XYZ Sniper capture batch retry scheduled', error);
  } finally {
    flushInProgress = false;
    await persistQueue();
    scheduleRetryTimer();
  }
}

async function enqueuePayload(payload) {
  await stateReady;
  queueState.pending.push({
    queue_id: uuid('q'), event_id: payload.event_id || uuid('evt'), attempts: 0,
    queued_at: Date.now(), nextAttemptAt: 0, payload
  });
  await persistQueue();
  if (debounceTimer) clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => { debounceTimer = null; flushQueue(); }, DEBOUNCE_MS);
  if (!maxWaitTimer) maxWaitTimer = setTimeout(() => { maxWaitTimer = null; flushQueue(); }, MAX_WAIT_MS);
  scheduleRetryTimer();
}

async function processCapture(message, sender) {
  const payload = message.data || {};
  const pageUrl = message.pageUrl || sender.url || '';
  const result = await storageGet(['activeProject', 'apiToken', 'captureEnabled']);
  if (result.captureEnabled === false) return { queued: false, reason: 'capture_paused' };
  const activeProject = result.activeProject;
  if (!activeProject || !activeProject.domain || !result.apiToken) return { queued: false, reason: 'project_or_token_missing' };
  if (!hostMatches(pageUrl, activeProject.domain)) return { queued: false, reason: 'page_host_mismatch' };
  const session = await ensureSession(activeProject);
  const requestUrl = payload.request_url || payload.url || '';
  const parsed = (() => { try { return new URL(requestUrl, pageUrl); } catch (error) { return null; } })();
  const bodyForHash = payload.requestBodyRaw || (payload.requestBody && typeof payload.requestBody === 'object' ? JSON.stringify(payload.requestBody) : payload.requestBody || '');
  const hash = await generateHash(`${activeProject.id || ''}|${parsed ? parsed.host : ''}|${String(payload.method || 'GET').toUpperCase()}|${parsed ? parsed.pathname : requestUrl}${parsed ? parsed.search : ''}|${bodyForHash}`);
  const tabId = sender && sender.tab && sender.tab.id !== undefined ? String(sender.tab.id) : null;
  const frameId = sender && sender.frameId !== undefined ? String(sender.frameId) : null;
  const navigationId = message.navigation_id || (sender && sender.documentId) || null;
  const eventId = payload.event_id || uuid('evt');
  const payloadData = {
    ...payload,
    event_id: eventId,
    session_id: session && session.session_id,
    session_title: session && session.session_title,
    session_started_at: session && session.session_started_at,
    sequence_no: Number(payload.sequence_no) || null,
    tab_id: tabId,
    frame_id: frameId,
    navigation_id: navigationId,
    source_type: payload.source_type || payload.type || 'unknown',
    request_url: requestUrl,
    url: requestUrl,
    host: payload.host || (parsed && parsed.host) || '',
    content_type: payload.content_type || null,
    duration_ms: Number.isFinite(Number(payload.duration_ms)) ? Number(payload.duration_ms) : null,
    status: payload.status === undefined ? null : payload.status,
    status_code: payload.status === undefined ? null : payload.status,
    method: String(payload.method || 'GET').toUpperCase(),
    http_method: String(payload.method || 'GET').toUpperCase(),
    pageUrl,
    referer: pageUrl,
    payloadHash: hash,
    payload_hash: hash,
    queryParams: parsed ? parsed.search : queryParams(requestUrl, pageUrl),
    requestHeaders: { ...(payload.requestHeaders || {}), 'user-agent': message.userAgent || '' }
  };
  await enqueuePayload(payloadData);
  return { queued: true, event_id: eventId };
}

chrome.storage.onChanged.addListener(changes => {
  if (changes.activeProject) {
    sessionState = null;
    if (changes.activeProject.newValue) ensureSession(changes.activeProject.newValue).catch(error => console.warn('XYZ Sniper session sync failed', error));
  }
});

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.type === 'XYZ_SNIPER_QUEUE_STATUS') {
    stateReady.then(() => sendResponse({ pending: queueState.pending.length, inflight: queueState.inflight.length, deadLetter: queueState.deadLetter.length, session: sessionState }));
    return true;
  }
  if (message.type === 'XYZ_SNIPER_SET_CAPTURE_STATE') {
    storageSet({ captureEnabled: message.enabled !== false }).then(() => sendResponse({ ok: true }));
    return true;
  }
  if (message.type === 'XYZ_SNIPER_SYNC_PROJECT') {
    ensureSession(message.project || null).then(() => sendResponse({ ok: true })).catch(error => sendResponse({ ok: false, error: String(error) }));
    return true;
  }
  if (message.type !== 'XYZ_SNIPER_CAPTURED_REQUEST') return false;
  processCapture(message, sender).then(sendResponse).catch(error => {
    console.error('XYZ Sniper capture processing failed', error);
    sendResponse({ queued: false, reason: 'processing_error' });
  });
  return true;
});

console.log('XYZ Sniper background loaded with durable capture queue.');

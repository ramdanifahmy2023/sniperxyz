importScripts('config.js', 'capture-queue.js', 'privacy.js');
const queue = new CaptureQueue();
const BATCH_BYTES = 8 * 1024 * 1024;
let flushing = null;
let stateChain = Promise.resolve();
const storageGet = keys => chrome.storage.local.get(keys);
const storageSet = values => chrome.storage.local.set(values);
const uuid = prefix => `${prefix}_${crypto.randomUUID()}`;
const serial = action => { const result = stateChain.then(action); stateChain = result.catch(() => {}); return result; };
const ready = (async () => {
  const old = await storageGet(['captureQueueState', 'pendingPayloadQueue']);
  const state = old.captureQueueState;
  await queue.migrate(state ? [...(state.pending || []), ...(state.inflight || []), ...(state.deadLetter || [])] : old.pendingPayloadQueue || []);
  await chrome.storage.local.remove(['captureQueueState', 'pendingPayloadQueue']);
  await chrome.alarms.create('sniper-retry', { periodInMinutes: 0.5 });
})();
ready.catch(error => storageSet({ captureEnabled: false, captureError: error.message }));

function host(url) {
  try { return new URL(/^https?:/i.test(url) ? url : `https://${url}`).host.toLowerCase().replace(/^www\./, '').replace(/\.$/, ''); }
  catch (_) { return ''; }
}
function matches(url, domain) { const h = host(url), d = host(domain); return !!d && (h === d || h.endsWith(`.${d}`)); }
async function context(sender) {
  const stored = await storageGet(['activeProject','apiToken','captureEnabled','captureSession','captureError']);
  const pageUrl = sender.tab?.url || sender.url || '';
  const allowed = !!(stored.apiToken && stored.activeProject?.domain && matches(pageUrl, stored.activeProject.domain));
  return { ...stored, pageUrl, allowed, enabled: allowed && stored.captureEnabled !== false };
}
async function ensureSession(project) {
  const { captureSession } = await storageGet('captureSession');
  if (captureSession?.project_id === project.id) return captureSession;
  const session = { session_id: uuid('ses'), project_id: project.id, session_title: `Audit ${project.title || project.domain}`, session_started_at: new Date().toISOString() };
  await storageSet({ captureSession: session });
  return session;
}
async function processCapture(message, sender) {
  await ready;
  return serial(async () => {
    const state = await context(sender);
    if (!state.enabled) return { queued: false, reason: state.allowed ? 'capture_paused' : 'domain_or_token_missing' };
    const data = message.data;
    if (!data || typeof data !== 'object' || !['fetch','xhr','snapshot','interaction','websocket','eventsource'].includes(data.source_type)) return { queued: false, reason: 'invalid_event' };
    if (new TextEncoder().encode(JSON.stringify(data)).length > 3 * 1024 * 1024) {
      await storageSet({captureError:'Ada event melebihi 3 MiB yang tidak masuk antrean.',captureEnabled:false});
      return { queued: false, reason: 'event_too_large' };
    }
    const session = await ensureSession(state.activeProject);
    const payload = SniperPrivacy.redact({ ...data, ...session,
      event_id: /^[\w-]{1,64}$/.test(data.event_id || '') ? data.event_id : uuid('evt'),
      collector_version: '2.0.0', pageUrl: state.pageUrl,
      frame_url: sender.url || '', tab_id: String(sender.tab?.id ?? ''), frame_id: String(sender.frameId ?? 0),
      navigation_id: sender.documentId || message.navigation_id || '',
      url: data.request_url || data.url || sender.url || state.pageUrl
    });
    try {
      await queue.put({ queue_id: payload.event_id, event_id: payload.event_id, payload, state: 'pending', attempts: 0, nextAttemptAt: 0, queued_at: Date.now() });
      await storageSet({ collectorLastSeen: Date.now() });
    } catch (error) {
      await storageSet({ captureEnabled: false, captureError: error.message });
      return { queued: false, reason: error.message };
    }
    void flush();
    return { queued: true, event_id: payload.event_id };
  });
}
function ackMap(result) {
  if (result?.status !== 'success') return new Map();
  return new Map((result.data?.results || result.results || []).map(item => [item.event_id, item]));
}
async function post(path, body, token) {
  const response = await fetch(CONFIG.getApiUrl(path), { method: 'POST', headers: { 'Content-Type':'application/json', Authorization:`Bearer ${token}` }, body: JSON.stringify(body), signal: AbortSignal.timeout(25000) });
  const result = await response.json().catch(() => ({}));
  if (!response.ok || result.status !== 'success') throw Object.assign(new Error(result.message || `HTTP ${response.status}`), { status: response.status });
  return result;
}
async function deliver(batch, token) {
  try {
    const result = await post('captureBulk', { payloads: batch.map(row => row.payload) }, token);
    const acks = ackMap(result);
    await queue.transaction((rows, events, meta) => {
      for (const item of batch) {
        const ack = acks.get(item.event_id);
        if (['stored','duplicate'].includes(ack?.status)) { events.delete(item.queue_id); meta.put(Date.now(), 'lastAck'); }
        else if (ack?.status === 'rejected') events.put({ ...item, state:'failed', lastError: ack.reason || 'Server menolak event.' });
        else events.put(retry(item, 'Server belum mengakui event.'));
      }
    });
  } catch (error) {
    if (error.status === 413 && batch.length > 1) {
      const middle = Math.ceil(batch.length / 2);
      await deliver(batch.slice(0, middle), token); await deliver(batch.slice(middle), token); return;
    }
    await queue.transaction((rows, events) => {
      for (const item of batch) events.put(error.status === 413 ? { ...item, state:'failed', lastError:'Event ditolak karena ukuran. Ekspor untuk diperiksa.' } : retry(item, error.message, [401,403].includes(error.status)));
    });
  }
}
function retry(item, reason, auth = false) {
  const attempts = (item.attempts || 0) + (auth ? 0 : 1);
  return { ...item, attempts, state: attempts >= 8 ? 'failed' : 'pending', lastError: reason, nextAttemptAt: Date.now() + Math.min(300000, 2000 * 2 ** Math.min(attempts, 8)) };
}
async function runFlush() {
  await ready;
  const { apiToken } = await storageGet('apiToken');
  if (!apiToken) return;
  const rows = (await queue.all()).sort((a,b) => a.queued_at - b.queued_at);
  const due = rows.filter(row => row.state === 'pending' && row.nextAttemptAt <= Date.now());
  const batch = []; let bytes = 32;
  for (const row of due.filter(row => !row.operation)) {
    const size = new TextEncoder().encode(JSON.stringify(row.payload)).length + 1;
    if (batch.length && (bytes + size > BATCH_BYTES || batch.length >= 25)) break;
    batch.push(row); bytes += size;
  }
  if (batch.length) await deliver(batch, apiToken);
  const remaining = await queue.all();
  for (const row of due.filter(row => row.operation === 'end')) {
    if (remaining.some(other => !other.operation && other.payload?.session_id === row.session_id)) continue;
    try {
      await post('sessionEnd', { session_id:row.session_id }, apiToken);
      await queue.transaction((items, events) => events.delete(row.queue_id));
    } catch (error) { await queue.transaction((items, events) => events.put(retry(row, error.message, [401,403].includes(error.status)))); }
  }
}
function flush() {
  if (!flushing) flushing = runFlush().catch(error => storageSet({ captureError:error.message })).finally(() => { flushing = null; });
  return flushing;
}
async function endSession() {
  await ready;
  return serial(async () => {
    await storageSet({ captureEnabled: false });
    const { captureSession } = await storageGet('captureSession');
    if (captureSession) {
      await queue.put({ queue_id:`end_${captureSession.session_id}`, operation:'end', session_id:captureSession.session_id, state:'pending', attempts:0, nextAttemptAt:0, queued_at:Date.now() });
      await chrome.storage.local.remove('captureSession');
    }
    void flush(); return { ok:true };
  });
}
async function status() {
  await ready;
  const rows = await queue.all();
  const stored = await storageGet(['captureEnabled','captureSession','captureError','activeProject','collectorLastSeen']);
  return { ...stored, pending:rows.filter(r=>r.state==='pending' && !r.operation).length,
    closing:rows.filter(r=>r.operation==='end').length, failed:rows.filter(r=>r.state==='failed').length,
    bytes:rows.reduce((n,r)=>n+(r.bytes||0),0), lastAck:await queue.getMeta('lastAck'),
    errors: rows.filter(r=>r.lastError).slice(0,5).map(r=>({ event_id:r.event_id, reason:r.lastError })) };
}
chrome.alarms.onAlarm.addListener(alarm => { if (alarm.name === 'sniper-retry') void flush(); });
chrome.runtime.onMessage.addListener((message, sender, respond) => {
  const internal = sender.url?.startsWith(chrome.runtime.getURL(''));
  let result;
  if (message.type === 'XYZ_SNIPER_POLICY') result = context(sender).then(s=>({ enabled:s.enabled, reason:s.allowed?'capture_paused':'domain_or_token_missing' }));
  else if (message.type === 'XYZ_SNIPER_CAPTURED_REQUEST') result = processCapture(message, sender);
  else if (!internal) return false;
  else if (message.type === 'XYZ_SNIPER_QUEUE_STATUS') result = status();
  else if (message.type === 'XYZ_SNIPER_END_SESSION') result = endSession();
  else if (message.type === 'XYZ_SNIPER_SET_CAPTURE_STATE') result = serial(async()=>{ await storageSet({ captureEnabled:message.enabled === true, captureError:null }); return {ok:true}; });
  else if (message.type === 'XYZ_SNIPER_RETRY') result = (async()=>{ await ready; await queue.transaction((rows, events)=>rows.forEach(row=>events.put({...row,state:'pending',attempts:0,nextAttemptAt:0}))); await storageSet({captureError:null}); await flush(); return {ok:true}; })();
  else if (message.type === 'XYZ_SNIPER_EXPORT_QUEUE') result = ready.then(()=>queue.all()).then(items=>({items}));
  else if (message.type === 'XYZ_SNIPER_SYNC_PROJECT') result = Promise.resolve({ok:true});
  else return false;
  Promise.resolve(result).then(respond).catch(error=>respond({ok:false,error:error.message}));
  return true;
});

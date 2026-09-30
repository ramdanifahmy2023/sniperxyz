/* global indexedDB */
class CaptureQueue {
  constructor(limitBytes = 64 * 1024 * 1024) { this.limitBytes = limitBytes; }
  async open() {
    if (this.db) return this.db;
    this.db = await new Promise((resolve, reject) => {
      const request = indexedDB.open('sniper-capture-v2', 1);
      request.onupgradeneeded = () => {
        request.result.createObjectStore('events', { keyPath: 'queue_id' });
        request.result.createObjectStore('meta');
      };
      request.onerror = () => reject(request.error);
      request.onsuccess = () => resolve(request.result);
    });
    return this.db;
  }
  async transaction(change) {
    const db = await this.open();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(['events', 'meta'], 'readwrite');
      const events = tx.objectStore('events'), meta = tx.objectStore('meta');
      let result, failure;
      const request = events.getAll();
      request.onsuccess = () => {
        try { result = change(request.result, events, meta); }
        catch (error) { failure = error; tx.abort(); }
      };
      tx.oncomplete = () => resolve(result);
      tx.onabort = tx.onerror = () => reject(failure || tx.error || new Error('Penyimpanan antrean gagal.'));
    });
  }
  async all() {
    const db = await this.open();
    return new Promise((resolve, reject) => {
      const request = db.transaction('events').objectStore('events').getAll();
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }
  async getMeta(key) {
    const db = await this.open();
    return new Promise((resolve, reject) => {
      const request = db.transaction('meta').objectStore('meta').get(key);
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }
  async put(item) {
    item.bytes = new TextEncoder().encode(JSON.stringify(item)).length;
    return this.transaction((rows, events) => {
      const total = rows.reduce((n, row) => n + (row.queue_id === item.queue_id ? 0 : row.bytes || 0), 0);
      if (total + item.bytes > this.limitBytes) throw new Error('Antrean mencapai 64 MiB. Kirim atau ekspor data sebelum melanjutkan.');
      events.put(item);
    });
  }
  async migrate(legacy) {
    if (await this.getMeta('legacyMigrated')) return;
    await this.transaction((rows, events, meta) => {
      const ids = new Set(rows.map(row => row.event_id));
      for (const row of legacy) {
        const item = row.item || row;
        if (!item.payload || ids.has(item.event_id)) continue;
        ids.add(item.event_id);
        const migrated = { ...item, queue_id: item.queue_id || crypto.randomUUID(), state: row.reason ? 'failed' : 'pending', lastError: row.reason || item.lastError, nextAttemptAt: 0 };
        migrated.bytes = new TextEncoder().encode(JSON.stringify(migrated)).length;
        events.put(migrated);
      }
      meta.put(true, 'legacyMigrated');
    });
  }
}
if (typeof module !== 'undefined') module.exports = CaptureQueue;

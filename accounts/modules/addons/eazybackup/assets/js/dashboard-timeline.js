(() => {
  'use strict';

  // A lightweight global store for running jobs keyed by username + device friendly name
  const SEP = '||';
  const state = {
    // key -> { jobs: Map<jobId, Job>, updatedAt: number }
    byKey: new Map(),
    jobIndex: new Map() // jobId -> key
  };

  function keyOf(username, deviceName) {
    return String(username||'') + SEP + String(deviceName||'');
  }

  function normalize(job) {
    job = job || {};
    return {
      id: String(job.id || job.job_id || job.JobID || ''),
      username: String(job.username || ''),
      device: String(job.device || ''),
      device_name: String(job.device_name || job.DeviceFriendlyName || job.deviceFriendlyName || job.Device || ''),
      status: job.status || job.Status || job.StatusCode || 'Running',
      started_at: job.started_at || job.StartTime || job.started || 0,
      ended_at: job.ended_at || job.EndTime || job.ended || 0,
      protecteditem: job.protecteditem || job.ProtectedItem || job.ProtectedItemDescription || ''
    };
  }

  function upsert(jobRaw) {
    const job = normalize(jobRaw);
    const k = keyOf(job.username, job.device_name);
    if (!state.byKey.has(k)) state.byKey.set(k, { jobs: new Map(), updatedAt: Date.now() });
    state.byKey.get(k).jobs.set(job.id, job);
    state.byKey.get(k).updatedAt = Date.now();
    state.jobIndex.set(job.id, k);
  }

  function remove(jobRaw) {
    const job = normalize(jobRaw);
    const id = job.id;
    const k = state.jobIndex.get(id);
    if (!k) return;
    const bucket = state.byKey.get(k);
    if (!bucket) return;
    bucket.jobs.delete(id);
    bucket.updatedAt = Date.now();
    state.jobIndex.delete(id);
  }

  function resetFromSnapshot(jobsRunning) {
    state.byKey.clear();
    state.jobIndex.clear();
    (jobsRunning||[]).forEach(upsert);
  }

  function listFor(username, deviceName) {
    const k = keyOf(username, deviceName);
    const bucket = state.byKey.get(k);
    if (!bucket) return [];
    return Array.from(bucket.jobs.values());
  }

  // Re-emit a UI event whenever our store changes
  function changed() {
    try {
      const ev = new CustomEvent('eb:timeline-changed');
      window.dispatchEvent(ev);
    } catch (_) {}
  }

  // Wire to pulse events emitted by pulse-events.js
  window.addEventListener('eb:pulse', (ev) => {
    try {
      const d = ev.detail || {}; const job = d.job || {};
      if (d.kind === 'job:start') { upsert(job); changed(); }
      else if (d.kind === 'job:end') { remove(job); changed(); }
    } catch (_) {}
  });

  window.addEventListener('eb:pulse-snapshot', (ev) => {
    try {
      const d = ev.detail || {}; const running = d.jobsRunning || [];
      resetFromSnapshot(running); changed();
    } catch (_) {}
  });

  // Expose a tiny API
  window.__EB_TIMELINE = {
    getFor: listFor
  };
})();



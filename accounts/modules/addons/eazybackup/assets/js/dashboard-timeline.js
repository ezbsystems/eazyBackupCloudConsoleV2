(() => {
  'use strict';

  const SEP = '||';
  const state = {
    byKey: new Map(),
    jobIndex: new Map()
  };

  function keyOf(username, deviceHash) {
    return String(username || '') + SEP + String(deviceHash || '');
  }

  function compositeId(job) {
    const serverId = String(job.server_id || '');
    const jobId = String(job.job_id || job.id || '');
    if (serverId && jobId && jobId.indexOf(':') === -1) {
      return serverId + ':' + jobId;
    }
    return String(job.id || jobId || '');
  }

  function normalize(job) {
    job = job || {};
    const server_id = String(job.server_id || '');
    const job_id = String(job.job_id || job.JobID || '');
    const id = compositeId({ server_id, job_id, id: job.id });
    return {
      id,
      job_id: job_id || id,
      server_id,
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
    const k = keyOf(job.username, job.device);
    if (!state.byKey.has(k)) state.byKey.set(k, { jobs: new Map(), updatedAt: Date.now() });
    state.byKey.get(k).jobs.set(job.id, job);
    state.byKey.get(k).updatedAt = Date.now();
    state.jobIndex.set(job.id, k);
  }

  function resetFromSnapshot(jobsRunning) {
    state.byKey.clear();
    state.jobIndex.clear();
    (jobsRunning || []).forEach(upsert);
  }

  function listFor(username, deviceHash) {
    const k = keyOf(username, deviceHash);
    const bucket = state.byKey.get(k);
    const jobs = bucket ? Array.from(bucket.jobs.values()) : [];
    return jobs;
  }

  function changed() {
    try {
      window.dispatchEvent(new CustomEvent('eb:timeline-changed'));
    } catch (_) {}
  }

  window.addEventListener('eb:pulse-snapshot', (ev) => {
    try {
      const d = ev.detail || {};
      resetFromSnapshot(d.jobsRunning || []);
      changed();
    } catch (_) {}
  });

  window.__EB_TIMELINE = {
    getFor: listFor
  };
})();


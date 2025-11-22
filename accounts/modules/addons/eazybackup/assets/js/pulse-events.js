(() => {
  'use strict';

  // Endpoint configuration (set from template)
  const PULSE_ENDPOINT = (typeof window !== 'undefined' && window.EB_PULSE_ENDPOINT)
    ? window.EB_PULSE_ENDPOINT
    : 'index.php?m=eazybackup&a=pulse-events';
  const SNAPSHOT_ENDPOINT = (typeof window !== 'undefined' && window.EB_PULSE_SNAPSHOT)
    ? window.EB_PULSE_SNAPSHOT
    : 'index.php?m=eazybackup&a=pulse-snapshot';

  // Dispatch a CustomEvent in a safe way
  function emit(kind, detail) {
    try {
      const ev = new CustomEvent(kind, { detail });
      window.dispatchEvent(ev);
    } catch (_) {
      // IE11/old: very unlikely in our environment, ignore
    }
  }

  // Normalize event payloads into a compact form our UI can consume
  function normalizeJobPayload(obj) {
    obj = obj || {};
    // Accept various field shapes from SSE/backend
    const id = obj.job_id || obj.JobID || obj.id || obj.GUID || '';
    const username = obj.username || obj.UserName || obj.user || '';
    const device = obj.device || obj.Device || obj.device_id || obj.DeviceID || '';
    const status = obj.status || obj.Status || obj.StatusCode || '';
    const started_at = obj.started_at || obj.StartTime || obj.started || 0;
    const ended_at = obj.ended_at || obj.EndTime || obj.ended || 0;
    const protecteditem = obj.protecteditem || obj.ProtectedItem || obj.ProtectedItemDescription || '';
    return { id, username, device, status, started_at, ended_at, protecteditem };
  }

  function handleEvent(data) {
    if (!data || !data.kind) return;
    const kind = String(data.kind);
    if (kind === 'snapshot') {
      // Snapshot may contain arrays of recent and running jobs; normalize and emit in bulk
      const jobsRunning = Array.isArray(data.jobsRunning) ? data.jobsRunning.map(normalizeJobPayload) : [];
      const jobsRecent24h = Array.isArray(data.jobsRecent24h) ? data.jobsRecent24h.map(normalizeJobPayload) : [];
      emit('eb:pulse-snapshot', { jobsRunning, jobsRecent24h });
      return;
    }
    if (kind === 'job:start' || kind === 'job:end') {
      const job = normalizeJobPayload(data.job || data);
      emit('eb:pulse', { kind, job });
      return;
    }
    if (kind === 'device:new' || kind === 'device:removed') {
      const username = data.username || data.user || '';
      const device = data.device || data.Device || data.device_id || data.DeviceID || '';
      emit('eb:pulse-device', { kind, username, device });
      return;
    }
  }

  // Connect to SSE with simple retry/backoff
  function connect() {
    let es;
    let retryMs = 1500;
    let pollTimer = null;

    function stopPolling(){ if (pollTimer){ clearInterval(pollTimer); pollTimer=null; } }
    function startPolling(){ if (!pollTimer){ pollTimer = setInterval(loadSnapshot, 10000); } }

    function open() {
      try {
        es = new EventSource(PULSE_ENDPOINT);
      } catch (_) {
        // Older browsers / occasional construction failures
        startPolling();
        scheduleReconnect();
        return;
      }

      es.onopen = () => { try { stopPolling(); } catch(_){} };
      es.onmessage = (ev) => {
        try {
          const data = JSON.parse(ev.data || '{}');
          handleEvent(data);
          // On any successful message, reset retry a bit
          retryMs = 1500;
        } catch (_) { /* ignore parse errors */ }
      };

      es.onerror = () => {
        try { es.close(); } catch (_) {}
        startPolling();
        scheduleReconnect();
      };
    }

    function scheduleReconnect() {
      const wait = retryMs + Math.floor(Math.random() * 500);
      setTimeout(() => {
        retryMs = Math.min(15000, retryMs * 2);
        open();
      }, wait);
    }

    open();
  }

  // Initial one-shot snapshot to seed state quickly if desired
  async function loadSnapshot() {
    try {
      const res = await fetch(SNAPSHOT_ENDPOINT, { credentials: 'same-origin' });
      const data = await res.json();
      if (data && (data.jobsRunning || data.jobsRecent24h)) {
        const jobsRunning = Array.isArray(data.jobsRunning) ? data.jobsRunning.map(normalizeJobPayload) : [];
        const jobsRecent24h = Array.isArray(data.jobsRecent24h) ? data.jobsRecent24h.map(normalizeJobPayload) : [];
        emit('eb:pulse-snapshot', { jobsRunning, jobsRecent24h });
      }
    } catch (_) { /* snapshot is best-effort */ }
  }

  // Kick off
  try { loadSnapshot(); } catch (_) {}
  try { connect(); } catch (_) {}

  // Expose a tiny debug hook
  window.__EB_PULSE_DEBUG = {
    testStart: (payload) => handleEvent({ kind: 'job:start', job: payload }),
    testEnd: (payload) => handleEvent({ kind: 'job:end', job: payload })
  };
})();



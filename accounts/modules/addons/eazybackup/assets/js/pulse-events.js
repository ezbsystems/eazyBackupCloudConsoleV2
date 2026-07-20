(() => {
  'use strict';

  const SNAPSHOT_ENDPOINT = (typeof window !== 'undefined' && window.EB_PULSE_SNAPSHOT)
    ? window.EB_PULSE_SNAPSHOT
    : 'index.php?m=eazybackup&a=pulse-snapshot';

  const POLL_MS = 10000;
  const MIN_BACKOFF_MS = 1500;
  const MAX_BACKOFF_MS = 60000;

  let inFlight = false;
  let timer = null;
  let backoffMs = MIN_BACKOFF_MS;

  function emit(kind, detail) {
    try {
      window.dispatchEvent(new CustomEvent(kind, { detail }));
    } catch (_) {}
  }

  function normalizeJobPayload(obj) {
    obj = obj || {};
    const server_id = String(obj.server_id || obj.ServerID || '');
    const job_id = String(obj.job_id || obj.JobID || '');
    const composite = obj.id || (server_id && job_id ? `${server_id}:${job_id}` : job_id);
    const id = String(composite || obj.GUID || '');
    const username = String(obj.username || obj.UserName || obj.user || '');
    const device = String(obj.device || obj.Device || obj.device_id || obj.DeviceID || '');
    const device_name = String(obj.device_name || obj.DeviceFriendlyName || obj.deviceFriendlyName || '');
    const status = obj.status || obj.Status || obj.StatusCode || '';
    const status_reason = obj.status_reason || obj.statusReason || '';
    const offline_since = obj.offline_since || obj.offlineSince || '';
    const started_at = obj.started_at || obj.StartTime || obj.started || 0;
    const ended_at = obj.ended_at || obj.EndTime || obj.ended || 0;
    const protecteditem = obj.protecteditem || obj.ProtectedItem || obj.ProtectedItemDescription || '';
    return { id, job_id, server_id, username, device, device_name, status, status_reason, offline_since, started_at, ended_at, protecteditem };
  }

  function scheduleNext(delayMs) {
    if (timer) {
      clearTimeout(timer);
      timer = null;
    }
    if (document.visibilityState !== 'visible') return;
    timer = setTimeout(pollSnapshot, delayMs);
  }

  async function pollSnapshot() {
    if (inFlight || document.visibilityState !== 'visible') return;
    inFlight = true;
    let nextDelay = POLL_MS;
    try {
      const res = await fetch(SNAPSHOT_ENDPOINT, { credentials: 'same-origin' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (!data || data.status !== 'success') throw new Error('bad status');
      const jobsRunning = Array.isArray(data.jobsRunning) ? data.jobsRunning.map(normalizeJobPayload) : [];
      backoffMs = MIN_BACKOFF_MS;
      emit('eb:pulse-snapshot', { jobsRunning, jobsRecent24h: [] });
    } catch (_) {
      backoffMs = Math.min(MAX_BACKOFF_MS, Math.max(MIN_BACKOFF_MS, backoffMs * 2));
      nextDelay = backoffMs;
    } finally {
      inFlight = false;
      scheduleNext(nextDelay);
    }
  }

  function onVisibilityChange() {
    if (document.visibilityState === 'visible') {
      backoffMs = MIN_BACKOFF_MS;
      if (timer) {
        clearTimeout(timer);
        timer = null;
      }
      pollSnapshot();
      return;
    }
    if (timer) {
      clearTimeout(timer);
      timer = null;
    }
  }

  document.addEventListener('visibilitychange', onVisibilityChange);
  if (document.visibilityState === 'visible') {
    pollSnapshot();
  }

  window.__EB_PULSE_DEBUG = {
    poll: pollSnapshot,
    normalizeJobPayload
  };
})();


(function(){
  'use strict';

  function toMs(input){
    try {
      if (input === null || input === undefined || input === '') return 0;
      if (typeof input === 'number') return (input < 100000000000 ? input * 1000 : input);
      var s = String(input).trim();
      if (/^\d+$/.test(s)) { var n = parseInt(s, 10); return (n < 100000000000 ? n * 1000 : n); }
      var p = Date.parse(s); return isNaN(p) ? 0 : p;
    } catch(_) { return 0; }
  }

  function fmtTs(ts){
    try {
      var ms = toMs(ts);
      if (!ms) return '—';
      var d = new Date(ms);
      var fmt = (typeof Intl !== 'undefined' && Intl.DateTimeFormat)
        ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' })
        : null;
      return fmt ? fmt.format(d) : d.toLocaleString();
    } catch(_) { return '—'; }
  }

  function fmtBytes(n){
    try {
      var num = Number(n)||0;
      if (num <= 0) return '0 B';
      var units = ['B','KB','MB','GB','TB','PB'];
      var i = Math.floor(Math.log(num)/Math.log(1024));
      var val = num / Math.pow(1024, i);
      return (i ? val.toFixed(1) : Math.round(val)) + ' ' + units[i];
    } catch(_) { return '0 B'; }
  }

  function fmtDur(sec){
    try {
      var seconds = Number(sec)||0;
      if (seconds > 100000) { // likely ms
        seconds = Math.floor(seconds/1000);
      }
      if (seconds <= 0) return '—';
      var h = Math.floor(seconds/3600);
      var m = Math.floor((seconds%3600)/60);
      var s = Math.floor(seconds%60);
      return (h? (h+'h ') : '') + m+'m '+s+'s';
    } catch(_) { return '—'; }
  }

  // Default status map mirrors comet_HumanJobStatus
  var STATUS = {
    5000: 'Success',
    6000: 'Running',
    6001: 'Running',
    7000: 'Timeout',
    7001: 'Warning',
    7002: 'Error',
    7003: 'Error',
    7004: 'Skipped',
    7006: 'Skipped',
    7005: 'Cancelled'
  };

  // Allow PHP to override exact labels at runtime for parity
  try {
    if (window && window.EB_STATUS_MAP && typeof window.EB_STATUS_MAP === 'object') {
      STATUS = Object.assign({}, STATUS, window.EB_STATUS_MAP);
    }
  } catch(_) {}

  // Tailwind classes for status dots (single source of truth)
  var STATUS_DOT = {
    Success: 'bg-green-500',
    Running: 'bg-sky-500',
    Timeout: 'bg-amber-500',
    Warning: 'bg-amber-500',
    Error: 'bg-red-500',
    Skipped: 'bg-gray-500',
    Cancelled: 'bg-gray-500',
    Unknown: 'bg-gray-400'
  };

  function normalizeLabelFromAny(codeOrLabel){
    if (codeOrLabel === null || codeOrLabel === undefined) return 'Unknown';
    var n = Number(codeOrLabel);
    if (!isNaN(n) && String(codeOrLabel).trim() !== '') {
      return STATUS[n] || 'Unknown';
    }
    var s = String(codeOrLabel).trim();
    if (!s) return 'Unknown';
    var u = s.toUpperCase();
    if (u === 'SUCCESS') return 'Success';
    if (u === 'RUNNING' || u === 'ACTIVE' || u === 'REVIVED' || u === 'ALREADY_RUNNING') return 'Running';
    if (u === 'TIMEOUT') return 'Timeout';
    if (u === 'WARNING') return 'Warning';
    if (u === 'ERROR' || u === 'QUOTA_EXCEEDED' || u === 'ABANDONED') return 'Error';
    if (u === 'SKIPPED') return 'Skipped';
    if (u === 'CANCELLED' || u === 'CANCELED') return 'Cancelled';
    return 'Unknown';
  }

  function humanStatus(codeOrLabel){
    return normalizeLabelFromAny(codeOrLabel);
  }

  function statusDot(codeOrLabel){
    var label = normalizeLabelFromAny(codeOrLabel);
    return STATUS_DOT[label] || STATUS_DOT.Unknown;
  }

  function normalizeJob(j){
    j = j || {};
    var id = j.JobID || j.job_id || j.id || j.GUID || j.guid || '';
    var name = j.ProtectedItem || j.protecteditem || j.ProtectedItemDescription || j.item || j.name || '';
    var status = j.Status || j.status || '';
    var start = j.StartTime || j.started_at || j.start || 0;
    var end = j.EndTime || j.ended_at || j.end || 0;
    return { id: id, name: name, status: status, start: start, end: end };
  }

  var EB = {
    toMs: toMs,
    fmtTs: fmtTs,
    fmtBytes: fmtBytes,
    fmtDur: fmtDur,
    STATUS: STATUS,
    STATUS_DOT: STATUS_DOT,
    humanStatus: humanStatus,
    statusDot: statusDot,
    normalizeJob: normalizeJob
  };

  try { Object.freeze && Object.freeze(EB); } catch(_) {}
  window.EB = EB;
})();



<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>e3 Cloud Backup — User Management Redesign Guide</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════════════
   DESIGN TOKENS — eb-* token system
   ═══════════════════════════════════════════════════════════ */
:root {
  --eb-bg-chrome:    #070d1b;
  --eb-bg-base:      #0b1220;
  --eb-bg-surface:   #111d33;
  --eb-bg-card:      #172035;
  --eb-bg-raised:    #1e2d45;
  --eb-bg-overlay:   #253450;
  --eb-bg-hover:     #1a2840;
  --eb-bg-active:    #1f3050;
  --eb-bg-input:     #131e34;
  --eb-bg-input-focus: #172035;

  --eb-border-faint:     #141f35;
  --eb-border-subtle:    #1e2d45;
  --eb-border-default:   #253658;
  --eb-border-emphasis:  #304878;
  --eb-border-strong:    #3d5a8a;
  --eb-border-orange:    rgba(213,93,29,0.3);
  --eb-border-brand:     rgba(254,80,0,0.4);

  --eb-text-primary:   #eef2f9;
  --eb-text-secondary: #adbdd5;
  --eb-text-muted:     #6d88a8;
  --eb-text-disabled:  #3d5470;
  --eb-text-inverse:   #0b1220;

  --eb-brand-orange:       #fe5000;
  --eb-primary:            #d55d1d;
  --eb-primary-hover:      #c04f18;
  --eb-primary-soft:       rgba(213,93,29,0.12);
  --eb-primary-border:     rgba(213,93,29,0.28);
  --eb-accent:             #ff7a33;

  --eb-success-bg:     #091e16;
  --eb-success-border: #0e3d28;
  --eb-success-text:   #36d68a;
  --eb-success-strong: #16a34a;
  --eb-success-soft:   rgba(34,197,94,0.1);

  --eb-warning-bg:     #1e1200;
  --eb-warning-border: #5c3700;
  --eb-warning-text:   #f59e0b;
  --eb-warning-strong: #b45309;
  --eb-warning-soft:   rgba(245,158,11,0.1);

  --eb-danger-bg:      #1c0808;
  --eb-danger-border:  #5c1616;
  --eb-danger-text:    #f77070;
  --eb-danger-soft:    rgba(239,68,68,0.1);

  --eb-info-bg:        #081628;
  --eb-info-border:    #173562;
  --eb-info-text:      #60a5fa;
  --eb-info-soft:      rgba(59,130,246,0.1);

  --eb-font-display: 'Outfit', system-ui, sans-serif;
  --eb-font-body:    'DM Sans', system-ui, sans-serif;
  --eb-font-mono:    'Courier New', monospace;

  --eb-radius-sm:   6px;
  --eb-radius-md:   10px;
  --eb-radius-lg:   14px;
  --eb-radius-xl:   18px;
  --eb-radius-pill: 999px;

  --eb-shadow-sm:  0 1px 3px rgba(0,0,0,0.4);
  --eb-shadow-md:  0 4px 16px rgba(0,0,0,0.35), 0 1px 4px rgba(0,0,0,0.3);
  --eb-shadow-lg:  0 12px 40px rgba(0,0,0,0.5), 0 4px 12px rgba(0,0,0,0.35);
}

/* ═══════════════════════════════════════════════════════════
   RESET
   ═══════════════════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; background: var(--eb-bg-base); }
body {
  font-family: var(--eb-font-body);
  background: var(--eb-bg-base);
  color: var(--eb-text-secondary);
  font-size: 14px;
  line-height: 1.6;
  -webkit-font-smoothing: antialiased;
}

/* ═══════════════════════════════════════════════════════════
   GUIDE CHROME — not part of the component system
   ═══════════════════════════════════════════════════════════ */
.guide { max-width: 1440px; margin: 0 auto; padding: 48px 40px 100px; }

.guide-cover {
  padding: 56px 48px 48px;
  background: var(--eb-bg-chrome);
  border: 1px solid var(--eb-border-subtle);
  border-radius: var(--eb-radius-xl);
  margin-bottom: 56px;
  position: relative;
  overflow: hidden;
}
.guide-cover::before {
  content: '';
  position: absolute;
  top: -60px; right: -60px;
  width: 400px; height: 400px;
  background: radial-gradient(circle, rgba(254,80,0,0.08), transparent 60%);
  border-radius: 50%;
  pointer-events: none;
}
.guide-cover .eyebrow {
  font-size: 10.5px; font-weight: 700; letter-spacing: 0.22em;
  text-transform: uppercase; color: var(--eb-brand-orange);
  margin-bottom: 12px; position: relative;
}
.guide-cover h1 {
  font-family: var(--eb-font-display);
  font-size: 42px; font-weight: 800;
  color: var(--eb-text-primary);
  letter-spacing: -0.03em; line-height: 1.1; position: relative;
}
.guide-cover h1 em { font-style: normal; color: var(--eb-brand-orange); }
.guide-cover .subtitle {
  font-family: var(--eb-font-display);
  font-size: 20px; font-weight: 400;
  color: var(--eb-text-muted); margin-top: 6px; position: relative;
}
.guide-cover .desc {
  margin-top: 16px; color: var(--eb-text-muted);
  font-size: 14px; max-width: 660px; line-height: 1.65; position: relative;
}

.g-section { margin-bottom: 64px; }
.g-label {
  font-size: 10px; font-weight: 700; letter-spacing: 0.2em;
  text-transform: uppercase; color: var(--eb-brand-orange); margin-bottom: 8px;
}
.g-title {
  font-family: var(--eb-font-display);
  font-size: 28px; font-weight: 700;
  color: var(--eb-text-primary); letter-spacing: -0.02em; margin-bottom: 8px;
}
.g-desc {
  color: var(--eb-text-muted); font-size: 14px;
  max-width: 720px; line-height: 1.6; margin-bottom: 28px;
}
.g-subtitle {
  font-family: var(--eb-font-display);
  font-size: 18px; font-weight: 600;
  color: var(--eb-text-primary); margin: 32px 0 12px;
}

.g-preview {
  background: var(--eb-bg-surface);
  border: 1px solid var(--eb-border-default);
  border-radius: var(--eb-radius-xl);
  padding: 32px;
  margin-bottom: 20px;
}
.g-preview-label {
  font-size: 10.5px; font-weight: 700; letter-spacing: 0.15em;
  text-transform: uppercase; color: var(--eb-text-muted);
  margin-bottom: 20px; padding-bottom: 12px;
  border-bottom: 1px solid var(--eb-border-faint);
}

.g-note {
  background: var(--eb-bg-card);
  border: 1px solid var(--eb-border-default);
  border-left: 3px solid var(--eb-primary);
  border-radius: var(--eb-radius-md);
  padding: 16px 20px; margin-bottom: 28px;
  font-size: 13.5px; line-height: 1.65;
  color: var(--eb-text-secondary);
}
.g-note strong { color: var(--eb-text-primary); font-weight: 600; }

.g-code-block {
  background: var(--eb-bg-chrome);
  border: 1px solid var(--eb-border-default);
  border-radius: var(--eb-radius-md);
  padding: 20px;
  font-family: var(--eb-font-mono);
  font-size: 12px;
  color: var(--eb-text-muted);
  line-height: 1.7;
  overflow-x: auto;
  margin-bottom: 24px;
  white-space: pre;
}
.g-code-block .tag { color: var(--eb-info-text); }
.g-code-block .attr { color: var(--eb-warning-text); }
.g-code-block .val { color: var(--eb-success-text); }
.g-code-block .cmt { color: var(--eb-text-disabled); font-style: italic; }

.g-spec-table {
  width: 100%; border-collapse: collapse;
  font-size: 13px; margin-bottom: 28px;
}
.g-spec-table th {
  text-align: left; padding: 10px 16px;
  font-size: 10.5px; font-weight: 700; letter-spacing: 0.1em;
  text-transform: uppercase; color: var(--eb-text-muted);
  border-bottom: 1px solid var(--eb-border-default);
  background: var(--eb-bg-card);
}
.g-spec-table td {
  padding: 10px 16px; border-bottom: 1px solid var(--eb-border-faint);
  color: var(--eb-text-secondary); vertical-align: top;
}
.g-spec-table code {
  font-size: 12px; padding: 2px 6px;
  background: var(--eb-bg-overlay); border-radius: 4px;
  color: var(--eb-info-text); font-family: var(--eb-font-mono);
}

.g-cols-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }

@media (max-width: 900px) {
  .guide { padding: 24px 16px 60px; }
  .guide-cover { padding: 36px 28px 32px; }
  .guide-cover h1 { font-size: 30px; }
  .g-cols-2 { grid-template-columns: 1fr; }
  .g-preview { padding: 20px 16px; }
}


/* ═══════════════════════════════════════════════════════════
   NEW eb-* COMPONENT CLASSES
   These integrate into the existing semantic system
   ═══════════════════════════════════════════════════════════ */

/* ── eb-user-summary ─────────────────────────────────── */
/* Persistent header card at top of User Detail page */
.eb-user-summary {
  background: var(--eb-bg-card);
  border: 1px solid var(--eb-border-default);
  border-radius: var(--eb-radius-lg);
  overflow: hidden;
}

.eb-user-summary-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 24px;
  border-bottom: 1px solid var(--eb-border-faint);
  gap: 16px;
  flex-wrap: wrap;
}

.eb-user-summary-identity {
  display: flex;
  align-items: center;
  gap: 14px;
  min-width: 0;
}

.eb-user-avatar {
  width: 40px; height: 40px;
  border-radius: var(--eb-radius-md);
  background: var(--eb-bg-overlay);
  display: flex; align-items: center; justify-content: center;
  font-family: var(--eb-font-display);
  font-size: 14px; font-weight: 700;
  color: var(--eb-text-primary);
  flex-shrink: 0;
  text-transform: uppercase;
}

.eb-user-name {
  font-family: var(--eb-font-display);
  font-size: 17px; font-weight: 600;
  color: var(--eb-text-primary);
  letter-spacing: -0.01em;
}
.eb-user-meta-line {
  font-size: 12px;
  color: var(--eb-text-muted);
  margin-top: 1px;
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.eb-user-meta-line .sep {
  width: 3px; height: 3px; border-radius: 50%;
  background: var(--eb-text-disabled);
  flex-shrink: 0;
}

.eb-user-summary-stats {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 0;
  padding: 0;
}

.eb-user-stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 14px 16px;
  border-right: 1px solid var(--eb-border-faint);
  cursor: pointer;
  transition: background 0.15s ease;
  text-decoration: none;
}
.eb-user-stat:last-child { border-right: none; }
.eb-user-stat:hover { background: var(--eb-bg-hover); }
.eb-user-stat.is-clickable { cursor: pointer; }

.eb-user-stat-value {
  font-family: var(--eb-font-display);
  font-size: 22px; font-weight: 700;
  color: var(--eb-text-primary);
  line-height: 1;
}
.eb-user-stat-label {
  font-size: 10.5px; font-weight: 600;
  letter-spacing: 0.06em; text-transform: uppercase;
  color: var(--eb-text-muted);
  margin-top: 4px;
  line-height: 1;
}


/* ── eb-tab-bar ──────────────────────────────────────── */
/* Full-width tab navigation for detail pages */
.eb-tab-bar {
  display: flex;
  gap: 0;
  border-bottom: 1px solid var(--eb-border-default);
  background: var(--eb-bg-card);
  border-radius: var(--eb-radius-lg) var(--eb-radius-lg) 0 0;
  overflow-x: auto;
}
.eb-tab-bar .eb-tab {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 12px 20px;
  font-family: var(--eb-font-body);
  font-size: 13px; font-weight: 500;
  color: var(--eb-text-muted);
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  transition: color 0.15s ease, border-color 0.15s ease;
  white-space: nowrap;
  margin-bottom: -1px;
}
.eb-tab-bar .eb-tab:hover {
  color: var(--eb-text-primary);
}
.eb-tab-bar .eb-tab.is-active {
  color: var(--eb-text-primary);
  font-weight: 600;
  border-bottom-color: var(--eb-primary);
}
.eb-tab-bar .eb-tab .eb-tab-count {
  font-size: 11px; font-weight: 600;
  padding: 1px 7px;
  background: var(--eb-bg-overlay);
  border-radius: var(--eb-radius-pill);
  color: var(--eb-text-muted);
  line-height: 1.4;
}
.eb-tab-bar .eb-tab.is-active .eb-tab-count {
  background: var(--eb-primary-soft);
  color: var(--eb-accent);
}


/* ── eb-tab-body ─────────────────────────────────────── */
.eb-tab-body {
  background: var(--eb-bg-surface);
  border: 1px solid var(--eb-border-default);
  border-top: none;
  border-radius: 0 0 var(--eb-radius-lg) var(--eb-radius-lg);
  padding: 24px;
}


/* ── eb-expand-row — expandable agent table rows ──── */
.eb-expand-row {
  cursor: pointer;
  transition: background 0.15s ease;
}
.eb-expand-row:hover {
  background: var(--eb-bg-hover);
}

.eb-expand-chevron {
  width: 18px; height: 18px;
  stroke: var(--eb-text-muted); fill: none;
  stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
  transition: transform 0.2s ease;
  flex-shrink: 0;
}
.eb-expand-row.is-open .eb-expand-chevron {
  transform: rotate(90deg);
}

.eb-expand-detail {
  background: var(--eb-bg-chrome);
  border-top: 1px solid var(--eb-border-faint);
}
.eb-expand-detail td {
  padding: 0 !important;
}
.eb-expand-detail-inner {
  padding: 16px 20px 16px 56px;
}

.eb-expand-detail-header {
  font-size: 10.5px; font-weight: 700;
  letter-spacing: 0.1em; text-transform: uppercase;
  color: var(--eb-text-muted);
  margin-bottom: 10px;
  display: flex; align-items: center; gap: 8px;
}

/* ── eb-mini-job — compact inline job row ──────────── */
.eb-mini-job {
  display: grid;
  grid-template-columns: 1fr 120px 100px 90px 160px 100px;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  background: var(--eb-bg-card);
  border: 1px solid var(--eb-border-faint);
  border-radius: var(--eb-radius-sm);
  margin-bottom: 6px;
  font-size: 12.5px;
}
.eb-mini-job:last-child { margin-bottom: 0; }
.eb-mini-job:hover {
  border-color: var(--eb-border-default);
}

.eb-mini-job-name {
  font-weight: 600;
  color: var(--eb-text-primary);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.eb-mini-job-meta {
  color: var(--eb-text-muted);
  font-size: 12px;
}
.eb-mini-job-status {
  display: flex; align-items: center; gap: 5px;
  font-size: 11.5px; font-weight: 500;
}


/* ── eb-quota-grid — quota configuration controls ── */
.eb-quota-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 16px;
}

.eb-quota-card {
  background: var(--eb-bg-card);
  border: 1px solid var(--eb-border-default);
  border-radius: var(--eb-radius-md);
  padding: 18px 20px;
}
.eb-quota-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}
.eb-quota-label {
  font-size: 12px; font-weight: 600;
  color: var(--eb-text-primary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.eb-quota-badge {
  font-size: 10.5px; font-weight: 600;
  padding: 2px 8px;
  border-radius: var(--eb-radius-pill);
}
.eb-quota-badge.within {
  background: var(--eb-success-bg);
  border: 1px solid var(--eb-success-border);
  color: var(--eb-success-text);
}
.eb-quota-badge.near-limit {
  background: var(--eb-warning-bg);
  border: 1px solid var(--eb-warning-border);
  color: var(--eb-warning-text);
}
.eb-quota-badge.exceeded {
  background: var(--eb-danger-bg);
  border: 1px solid var(--eb-danger-border);
  color: var(--eb-danger-text);
}
.eb-quota-badge.unlimited {
  background: var(--eb-bg-overlay);
  border: 1px solid var(--eb-border-default);
  color: var(--eb-text-muted);
}

.eb-quota-usage {
  display: flex; align-items: baseline; gap: 6px;
  margin-bottom: 8px;
}
.eb-quota-current {
  font-family: var(--eb-font-display);
  font-size: 22px; font-weight: 700;
  color: var(--eb-text-primary);
}
.eb-quota-limit {
  font-size: 13px;
  color: var(--eb-text-muted);
}

.eb-quota-bar {
  height: 4px;
  background: var(--eb-bg-chrome);
  border-radius: 2px;
  overflow: hidden;
  margin-bottom: 10px;
}
.eb-quota-bar-fill {
  height: 100%;
  border-radius: 2px;
  transition: width 0.3s ease;
}

.eb-quota-input-row {
  display: flex; align-items: center; gap: 8px;
  margin-top: 10px;
}


/* ── eb-billing-kpi — billing metrics grid ─────────── */
.eb-billing-kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 12px;
  margin-bottom: 24px;
}

.eb-billing-kpi {
  background: var(--eb-bg-card);
  border: 1px solid var(--eb-border-default);
  border-radius: var(--eb-radius-md);
  padding: 16px;
  text-align: center;
}
.eb-billing-kpi-value {
  font-family: var(--eb-font-display);
  font-size: 26px; font-weight: 700;
  color: var(--eb-text-primary);
  line-height: 1;
}
.eb-billing-kpi-label {
  font-size: 10.5px; font-weight: 600;
  letter-spacing: 0.06em; text-transform: uppercase;
  color: var(--eb-text-muted);
  margin-top: 6px;
}


/* ── eb-vault-card — storage vault display ─────────── */
.eb-vault-card {
  background: var(--eb-bg-card);
  border: 1px solid var(--eb-border-default);
  border-radius: var(--eb-radius-md);
  padding: 18px 20px;
  transition: border-color 0.15s ease;
}
.eb-vault-card:hover {
  border-color: var(--eb-border-emphasis);
}
.eb-vault-card-header {
  display: flex; align-items: center; gap: 12px;
  margin-bottom: 12px;
}
.eb-vault-icon {
  width: 36px; height: 36px;
  border-radius: var(--eb-radius-sm);
  background: var(--eb-bg-overlay);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.eb-vault-icon svg {
  width: 18px; height: 18px;
  stroke: var(--eb-text-muted); fill: none;
  stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round;
}
.eb-vault-name {
  font-family: var(--eb-font-display);
  font-size: 14px; font-weight: 600;
  color: var(--eb-text-primary);
}
.eb-vault-provider {
  font-size: 11.5px; color: var(--eb-text-muted);
}

.eb-vault-stats {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 8px;
}
.eb-vault-stat {
  display: flex; flex-direction: column; gap: 2px;
}
.eb-vault-stat-label {
  font-size: 10px; font-weight: 600;
  text-transform: uppercase; letter-spacing: 0.06em;
  color: var(--eb-text-muted);
}
.eb-vault-stat-value {
  font-size: 13px; font-weight: 500;
  color: var(--eb-text-primary);
}


/* ── Shared helpers ──────────────────────────────────── */
.eb-status-dot {
  width: 7px; height: 7px;
  border-radius: 50%;
  display: inline-block;
  flex-shrink: 0;
}
.eb-status-dot--active { background: var(--eb-success-text); box-shadow: 0 0 6px rgba(54,214,138,0.4); }
.eb-status-dot--warning { background: var(--eb-warning-text); }
.eb-status-dot--error { background: var(--eb-danger-text); }
.eb-status-dot--inactive { background: var(--eb-text-disabled); }
.eb-status-dot--pending { background: var(--eb-info-text); animation: eb-pulse 1.5s ease-in-out infinite; }

@keyframes eb-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.35; }
}

.eb-badge {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 11.5px; font-weight: 600;
  padding: 3px 10px;
  border-radius: var(--eb-radius-pill);
  line-height: 1.3;
}
.eb-badge--success { background: var(--eb-success-bg); border: 1px solid var(--eb-success-border); color: var(--eb-success-text); }
.eb-badge--warning { background: var(--eb-warning-bg); border: 1px solid var(--eb-warning-border); color: var(--eb-warning-text); }
.eb-badge--danger { background: var(--eb-danger-bg); border: 1px solid var(--eb-danger-border); color: var(--eb-danger-text); }
.eb-badge--info { background: var(--eb-info-bg); border: 1px solid var(--eb-info-border); color: var(--eb-info-text); }
.eb-badge--default { background: var(--eb-bg-overlay); border: 1px solid var(--eb-border-default); color: var(--eb-text-muted); }
.eb-badge--dot::before {
  content: '';
  width: 6px; height: 6px;
  border-radius: 50%;
  background: currentColor;
  flex-shrink: 0;
}

/* Simulated shared eb-* button styles for guide display */
.eb-btn {
  display: inline-flex; align-items: center; gap: 6px;
  font-family: var(--eb-font-display);
  font-weight: 600;
  border: none; cursor: pointer;
  border-radius: var(--eb-radius-sm);
  transition: all 0.15s ease;
  text-decoration: none;
  line-height: 1;
}
.eb-btn-sm { font-size: 12.5px; padding: 7px 14px; }
.eb-btn-xs { font-size: 11.5px; padding: 5px 11px; }
.eb-btn-primary { background: var(--eb-primary); color: var(--eb-text-primary); }
.eb-btn-primary:hover { background: var(--eb-primary-hover); }
.eb-btn-secondary {
  background: var(--eb-bg-overlay);
  border: 1px solid var(--eb-border-default);
  color: var(--eb-text-secondary);
}
.eb-btn-secondary:hover { border-color: var(--eb-border-emphasis); color: var(--eb-text-primary); }
.eb-btn-danger-solid { background: #dc2626; color: #fff; }
.eb-btn-ghost {
  background: transparent; color: var(--eb-text-muted);
  border: none;
}
.eb-btn-ghost:hover { color: var(--eb-text-primary); background: var(--eb-bg-hover); }
.eb-btn svg { width: 14px; height: 14px; flex-shrink: 0; }
.eb-btn-icon {
  width: 32px; height: 32px;
  display: flex; align-items: center; justify-content: center;
  padding: 0; background: transparent;
  border-radius: var(--eb-radius-sm);
  color: var(--eb-text-muted);
}
.eb-btn-icon:hover { background: var(--eb-bg-hover); color: var(--eb-text-secondary); }
.eb-btn-icon svg {
  width: 15px; height: 15px;
  stroke: currentColor; fill: none;
  stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round;
}

/* Simulated form elements for guide */
.eb-input {
  width: 100%;
  padding: 8px 12px;
  font-family: var(--eb-font-body);
  font-size: 13px;
  background: var(--eb-bg-input);
  border: 1px solid var(--eb-border-default);
  border-radius: var(--eb-radius-sm);
  color: var(--eb-text-primary);
  outline: none;
  transition: border-color 0.15s ease;
}
.eb-input:focus { border-color: var(--eb-border-strong); }
.eb-input::placeholder { color: var(--eb-text-disabled); }

.eb-select {
  padding: 8px 32px 8px 12px;
  font-family: var(--eb-font-body);
  font-size: 13px;
  background: var(--eb-bg-input);
  border: 1px solid var(--eb-border-default);
  border-radius: var(--eb-radius-sm);
  color: var(--eb-text-primary);
  outline: none;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236d88a8' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
}

.eb-field-label {
  display: block;
  font-size: 12px; font-weight: 600;
  color: var(--eb-text-secondary);
  margin-bottom: 5px;
}

.eb-field-help {
  font-size: 11.5px;
  color: var(--eb-text-muted);
  margin-top: 4px;
}

/* Simulated eb-subpanel for guide */
.eb-subpanel {
  background: var(--eb-bg-card);
  border: 1px solid var(--eb-border-default);
  border-radius: var(--eb-radius-md);
  padding: 20px;
}

/* Simulated eb-table for guide */
.eb-table-shell {
  border: 1px solid var(--eb-border-default);
  border-radius: var(--eb-radius-md);
  overflow: hidden;
}
.eb-table {
  width: 100%; border-collapse: collapse;
  font-size: 13px;
}
.eb-table thead tr { background: var(--eb-bg-chrome); }
.eb-table th {
  text-align: left; padding: 10px 16px;
  font-size: 11px; font-weight: 700;
  letter-spacing: 0.06em; text-transform: uppercase;
  color: var(--eb-text-muted);
  border-bottom: 1px solid var(--eb-border-default);
}
.eb-table tbody tr {
  border-bottom: 1px solid var(--eb-border-faint);
  transition: background 0.1s ease;
}
.eb-table tbody tr:last-child { border-bottom: none; }
.eb-table td {
  padding: 11px 16px;
  vertical-align: middle;
  color: var(--eb-text-secondary);
}
.eb-table-primary {
  color: var(--eb-text-primary) !important;
  font-weight: 500;
}
.eb-table-mono {
  font-family: var(--eb-font-mono) !important;
  font-size: 12px !important;
  color: var(--eb-text-muted) !important;
}

/* ── Responsive for all new components ───────────────── */
@media (max-width: 1280px) {
  .eb-user-summary-stats { grid-template-columns: repeat(5, 1fr); }
  .eb-mini-job { grid-template-columns: 1fr 100px 80px 80px 140px 80px; font-size: 12px; }
}

@media (max-width: 768px) {
  .eb-user-summary-stats { grid-template-columns: repeat(3, 1fr); }
  .eb-user-stat:nth-child(3) { border-right: none; }
  .eb-user-stat:nth-child(4) { border-top: 1px solid var(--eb-border-faint); }
  .eb-user-stat:nth-child(5) { border-top: 1px solid var(--eb-border-faint); }

  .eb-tab-bar { overflow-x: auto; }

  .eb-mini-job {
    grid-template-columns: 1fr 1fr;
    gap: 6px 12px;
  }

  .eb-expand-detail-inner { padding-left: 20px; }
}
</style>
</head>
<body>

<div class="guide">

  <!-- ═══════════════ COVER ═══════════════ -->
  <div class="guide-cover">
    <div class="eyebrow">e3 Cloud Backup — UI Component Guide</div>
    <h1>User Management <em>Redesign</em></h1>
    <div class="subtitle">Tabbed User Detail Page — Component Specification</div>
    <p class="desc">A redesigned User Detail page that surfaces the User → Agent → Job relationship hierarchy through a tabbed interface. Includes new <code style="color:var(--eb-info-text);background:var(--eb-bg-overlay);padding:2px 6px;border-radius:4px;font-size:12px;">eb-*</code> semantic classes for the user summary header, expandable agent rows, quota controls, vault cards, and billing metrics.</p>
  </div>


  <!-- ═══════════════ §1 — DATA MODEL ═══════════════ -->
  <div class="g-section">
    <div class="g-label">Section 01 — Context</div>
    <h2 class="g-title">Relationship Model</h2>
    <p class="g-desc">The core problem: Users, Agents, and Jobs exist on separate pages with no way to see their parent/child relationships. The redesign brings them together on the User Detail page while keeping the standalone global list pages for MSP-wide views.</p>

    <div class="g-preview">
      <div class="g-preview-label">Ownership Hierarchy</div>
      <div style="display:flex;align-items:flex-start;gap:40px;flex-wrap:wrap;">
        <!-- Tree visual -->
        <div style="font-size:13px;line-height:2.2;flex:1;min-width:280px;">
          <div style="color:var(--eb-text-primary);font-weight:600;">
            <span style="display:inline-flex;align-items:center;gap:6px;">
              <span style="width:8px;height:8px;border-radius:50%;background:var(--eb-brand-orange);"></span>
              Backup User
            </span>
            <span style="color:var(--eb-text-muted);font-weight:400;margin-left:6px;">notenantuser</span>
          </div>
          <div style="margin-left:20px;border-left:2px solid var(--eb-border-subtle);padding-left:16px;">
            <div style="color:var(--eb-text-muted);font-size:10.5px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;margin-bottom:2px;">Linked Tenant</div>
            <div style="color:var(--eb-text-secondary);">Direct (No Tenant) <span style="color:var(--eb-text-muted);font-size:11px;">— or MSP billing tenant</span></div>
            <div style="color:var(--eb-text-muted);font-size:10.5px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;margin:8px 0 2px;">Agents <span style="color:var(--eb-text-disabled);font-weight:400;">(6 registered)</span></div>
            <div style="margin-left:0;">
              <div style="display:flex;align-items:center;gap:8px;color:var(--eb-text-secondary);">
                <span style="width:6px;height:6px;border-radius:50%;background:var(--eb-success-text);"></span>
                Media <span style="color:var(--eb-text-muted);font-size:11px;">— workstation</span>
              </div>
              <div style="margin-left:14px;border-left:2px solid var(--eb-border-faint);padding-left:14px;color:var(--eb-text-muted);font-size:12px;">
                <div>→ AWS – e3cloudbackup <span style="font-size:10px;">(Sync, Success)</span></div>
              </div>
              <div style="display:flex;align-items:center;gap:8px;color:var(--eb-text-secondary);margin-top:4px;">
                <span style="width:6px;height:6px;border-radius:50%;background:var(--eb-danger-text);"></span>
                DESKTOP-Q6NNHLQ <span style="color:var(--eb-text-muted);font-size:11px;">— workstation</span>
              </div>
              <div style="margin-left:14px;border-left:2px solid var(--eb-border-faint);padding-left:14px;color:var(--eb-text-muted);font-size:12px;">
                <div>→ <span style="color:var(--eb-text-disabled);">No jobs configured</span></div>
              </div>
            </div>
            <div style="color:var(--eb-text-muted);font-size:10.5px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;margin:8px 0 2px;">Vaults <span style="color:var(--eb-text-disabled);font-weight:400;">(1 location)</span></div>
            <div style="color:var(--eb-text-secondary);">e3cloudbackup-1231 <span style="color:var(--eb-text-muted);font-size:11px;">— eazyBackup Cloud</span></div>
          </div>
        </div>

        <!-- Summary -->
        <div style="flex:1;min-width:260px;">
          <div style="font-size:10.5px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--eb-text-muted);margin-bottom:10px;">Page Structure</div>
          <div style="display:flex;flex-direction:column;gap:8px;font-size:13px;">
            <div style="display:flex;align-items:center;gap:8px;">
              <span style="width:24px;height:24px;border-radius:var(--eb-radius-sm);background:var(--eb-primary-soft);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--eb-accent);">H</span>
              <span style="color:var(--eb-text-primary);font-weight:500;">User Summary Header</span>
              <span style="color:var(--eb-text-muted);font-size:11px;">— persistent</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
              <span style="width:24px;height:24px;border-radius:var(--eb-radius-sm);background:var(--eb-info-soft);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--eb-info-text);">T</span>
              <span style="color:var(--eb-text-primary);font-weight:500;">Tab Bar</span>
              <span style="color:var(--eb-text-muted);font-size:11px;">— Overview · Agents · Jobs · Vaults · Billing</span>
            </div>
            <div style="border-left:2px solid var(--eb-border-subtle);margin-left:11px;padding-left:20px;display:flex;flex-direction:column;gap:6px;padding-top:4px;">
              <div style="color:var(--eb-text-secondary);"><strong style="color:var(--eb-text-primary);">Overview</strong> — Edit form, password, quotas, delete</div>
              <div style="color:var(--eb-text-secondary);"><strong style="color:var(--eb-text-primary);">Agents</strong> — Expandable rows → inline jobs</div>
              <div style="color:var(--eb-text-secondary);"><strong style="color:var(--eb-text-primary);">Jobs</strong> — Full job cards (grouped by agent)</div>
              <div style="color:var(--eb-text-secondary);"><strong style="color:var(--eb-text-primary);">Vaults</strong> — Storage locations for this user</div>
              <div style="color:var(--eb-text-secondary);"><strong style="color:var(--eb-text-primary);">Billing</strong> — Tenant link, storage, agent/backup counts</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>


  <!-- ═══════════════ §2 — USER SUMMARY HEADER ═══════════════ -->
  <div class="g-section">
    <div class="g-label">Section 02 — Components</div>
    <h2 class="g-title">User Summary Header</h2>
    <p class="g-desc">Persistent header above the tab bar. Shows identity, tenant, status, and clickable stat tiles. Clicking a stat tile switches to its corresponding tab.</p>

    <div class="g-preview">
      <div class="g-preview-label">eb-user-summary — live example</div>

      <div class="eb-user-summary">
        <div class="eb-user-summary-header">
          <div class="eb-user-summary-identity">
            <div class="eb-user-avatar">NT</div>
            <div>
              <div class="eb-user-name">notenantuser</div>
              <div class="eb-user-meta-line">
                <span>support@eazybackup.ca</span>
                <span class="sep"></span>
                <span>Tenant: Direct</span>
                <span class="sep"></span>
                <span class="eb-badge eb-badge--success eb-badge--dot" style="padding:2px 8px;font-size:10.5px;">Active</span>
              </div>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:8px;">
            <button class="eb-btn eb-btn-secondary eb-btn-sm">
              <svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
              Login as User
            </button>
            <button class="eb-btn eb-btn-secondary eb-btn-sm">Back to Users</button>
          </div>
        </div>
        <div class="eb-user-summary-stats">
          <div class="eb-user-stat is-clickable">
            <div class="eb-user-stat-value">1</div>
            <div class="eb-user-stat-label">Vaults</div>
          </div>
          <div class="eb-user-stat is-clickable">
            <div class="eb-user-stat-value">1</div>
            <div class="eb-user-stat-label">Jobs</div>
          </div>
          <div class="eb-user-stat is-clickable">
            <div class="eb-user-stat-value">6</div>
            <div class="eb-user-stat-label">Agents</div>
          </div>
          <div class="eb-user-stat">
            <div class="eb-user-stat-value">0</div>
            <div class="eb-user-stat-label">Online</div>
          </div>
          <div class="eb-user-stat">
            <div class="eb-user-stat-value" style="font-size:14px;">4/3/2026</div>
            <div class="eb-user-stat-label">Last Backup</div>
          </div>
        </div>
      </div>
    </div>

    <div class="g-note">
      <strong>Clickable stats.</strong> The Vaults, Jobs, and Agents stat tiles act as shortcuts — clicking them switches to the corresponding tab. The cursor changes to pointer and the tile gets a hover highlight to signal interactivity. Online and Last Backup are display-only.
    </div>

    <h3 class="g-subtitle">HTML Structure</h3>
    <div class="g-code-block"><span class="tag">&lt;div</span> <span class="attr">class</span>=<span class="val">"eb-user-summary"</span><span class="tag">&gt;</span>
  <span class="tag">&lt;div</span> <span class="attr">class</span>=<span class="val">"eb-user-summary-header"</span><span class="tag">&gt;</span>
    <span class="tag">&lt;div</span> <span class="attr">class</span>=<span class="val">"eb-user-summary-identity"</span><span class="tag">&gt;</span>
      <span class="tag">&lt;div</span> <span class="attr">class</span>=<span class="val">"eb-user-avatar"</span><span class="tag">&gt;</span>NT<span class="tag">&lt;/div&gt;</span>
      <span class="tag">&lt;div&gt;</span>
        <span class="tag">&lt;div</span> <span class="attr">class</span>=<span class="val">"eb-user-name"</span><span class="tag">&gt;</span>notenantuser<span class="tag">&lt;/div&gt;</span>
        <span class="tag">&lt;div</span> <span class="attr">class</span>=<span class="val">"eb-user-meta-line"</span><span class="tag">&gt;</span>
          <span class="tag">&lt;span&gt;</span>support@eazybackup.ca<span class="tag">&lt;/span&gt;</span>
          <span class="tag">&lt;span</span> <span class="attr">class</span>=<span class="val">"sep"</span><span class="tag">&gt;&lt;/span&gt;</span>
          <span class="tag">&lt;span&gt;</span>Tenant: Direct<span class="tag">&lt;/span&gt;</span>
          <span class="tag">&lt;span</span> <span class="attr">class</span>=<span class="val">"sep"</span><span class="tag">&gt;&lt;/span&gt;</span>
          <span class="tag">&lt;span</span> <span class="attr">class</span>=<span class="val">"eb-badge eb-badge--success eb-badge--dot"</span><span class="tag">&gt;</span>Active<span class="tag">&lt;/span&gt;</span>
        <span class="tag">&lt;/div&gt;</span>
      <span class="tag">&lt;/div&gt;</span>
    <span class="tag">&lt;/div&gt;</span>
    <span class="cmt">&lt;!-- action buttons --&gt;</span>
  <span class="tag">&lt;/div&gt;</span>
  <span class="tag">&lt;div</span> <span class="attr">class</span>=<span class="val">"eb-user-summary-stats"</span><span class="tag">&gt;</span>
    <span class="tag">&lt;div</span> <span class="attr">class</span>=<span class="val">"eb-user-stat is-clickable"</span> <span class="attr">@click</span>=<span class="val">"activeTab='vaults'"</span><span class="tag">&gt;</span>
      <span class="tag">&lt;div</span> <span class="attr">class</span>=<span class="val">"eb-user-stat-value"</span><span class="tag">&gt;</span>1<span class="tag">&lt;/div&gt;</span>
      <span class="tag">&lt;div</span> <span class="attr">class</span>=<span class="val">"eb-user-stat-label"</span><span class="tag">&gt;</span>Vaults<span class="tag">&lt;/div&gt;</span>
    <span class="tag">&lt;/div&gt;</span>
    <span class="cmt">&lt;!-- repeat for Jobs, Agents, Online, Last Backup --&gt;</span>
  <span class="tag">&lt;/div&gt;</span>
<span class="tag">&lt;/div&gt;</span></div>
  </div>


  <!-- ═══════════════ §3 — TAB BAR ═══════════════ -->
  <div class="g-section">
    <h2 class="g-title">Tab Bar</h2>
    <p class="g-desc">Full-width tab navigation sitting between the user summary and the tab content area. Uses a bottom-border active indicator with the brand primary colour.</p>

    <div class="g-preview">
      <div class="g-preview-label">eb-tab-bar — all five tabs</div>

      <div class="eb-tab-bar">
        <button class="eb-tab is-active">
          <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
          Overview
        </button>
        <button class="eb-tab">
          <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
          Agents
          <span class="eb-tab-count">6</span>
        </button>
        <button class="eb-tab">
          <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
          Jobs
          <span class="eb-tab-count">1</span>
        </button>
        <button class="eb-tab">
          <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2z"/></svg>
          Vaults
          <span class="eb-tab-count">1</span>
        </button>
        <button class="eb-tab">
          <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
          Billing
        </button>
      </div>
    </div>
  </div>


  <!-- ═══════════════ §4 — OVERVIEW TAB ═══════════════ -->
  <div class="g-section">
    <div class="g-label">Section 03 — Tab Content</div>
    <h2 class="g-title">Overview Tab</h2>
    <p class="g-desc">Contains the Update User form, Reset Password, Quota controls, and the Delete User danger zone. Quotas are the main new addition — they let MSPs limit agents, storage, and guest VMs per user.</p>

    <div class="g-preview">
      <div class="g-preview-label">Quota Controls — eb-quota-grid</div>

      <div style="margin-bottom:20px;">
        <div style="font-family:var(--eb-font-display);font-size:15px;font-weight:600;color:var(--eb-text-primary);margin-bottom:4px;">User Quotas</div>
        <div style="font-size:12px;color:var(--eb-text-muted);margin-bottom:16px;">Set resource limits for this backup user. Leave blank for unlimited.</div>
      </div>

      <div class="eb-quota-grid">
        <!-- Agents quota -->
        <div class="eb-quota-card">
          <div class="eb-quota-card-header">
            <span class="eb-quota-label">Agents</span>
            <span class="eb-quota-badge within">Within Limit</span>
          </div>
          <div class="eb-quota-usage">
            <span class="eb-quota-current">6</span>
            <span class="eb-quota-limit">/ 10</span>
          </div>
          <div class="eb-quota-bar">
            <div class="eb-quota-bar-fill" style="width:60%;background:linear-gradient(to right, var(--eb-success-strong), var(--eb-success-text));"></div>
          </div>
          <div class="eb-quota-input-row">
            <label class="eb-field-label" style="margin:0;white-space:nowrap;font-size:11px;">Limit:</label>
            <input class="eb-input" type="number" value="10" style="width:80px;padding:5px 8px;font-size:12px;">
            <button class="eb-btn eb-btn-secondary eb-btn-xs">Save</button>
          </div>
        </div>

        <!-- Storage quota -->
        <div class="eb-quota-card">
          <div class="eb-quota-card-header">
            <span class="eb-quota-label">Storage</span>
            <span class="eb-quota-badge near-limit">Near Limit</span>
          </div>
          <div class="eb-quota-usage">
            <span class="eb-quota-current">847</span>
            <span class="eb-quota-limit">GB / 1 TB</span>
          </div>
          <div class="eb-quota-bar">
            <div class="eb-quota-bar-fill" style="width:85%;background:linear-gradient(to right, var(--eb-warning-strong), var(--eb-warning-text));"></div>
          </div>
          <div class="eb-quota-input-row">
            <label class="eb-field-label" style="margin:0;white-space:nowrap;font-size:11px;">Limit:</label>
            <input class="eb-input" type="number" value="1024" style="width:80px;padding:5px 8px;font-size:12px;">
            <span style="font-size:11px;color:var(--eb-text-muted);">GB</span>
            <button class="eb-btn eb-btn-secondary eb-btn-xs">Save</button>
          </div>
        </div>

        <!-- Guest VMs quota -->
        <div class="eb-quota-card">
          <div class="eb-quota-card-header">
            <span class="eb-quota-label">Guest VMs</span>
            <span class="eb-quota-badge unlimited">Unlimited</span>
          </div>
          <div class="eb-quota-usage">
            <span class="eb-quota-current">3</span>
            <span class="eb-quota-limit">/ ∞</span>
          </div>
          <div class="eb-quota-bar">
            <div class="eb-quota-bar-fill" style="width:0%;"></div>
          </div>
          <div class="eb-quota-input-row">
            <label class="eb-field-label" style="margin:0;white-space:nowrap;font-size:11px;">Limit:</label>
            <input class="eb-input" type="number" placeholder="∞" style="width:80px;padding:5px 8px;font-size:12px;">
            <button class="eb-btn eb-btn-secondary eb-btn-xs">Save</button>
          </div>
        </div>
      </div>
    </div>

    <div class="g-note">
      <strong>Quota badge states.</strong> Four variants: <code>within</code> (green, usage under 75%), <code>near-limit</code> (amber, 75–99%), <code>exceeded</code> (red, over 100%), and <code>unlimited</code> (neutral grey, no limit set). The progress bar colour gradient matches the badge.
    </div>
  </div>


  <!-- ═══════════════ §5 — AGENTS TAB ═══════════════ -->
  <div class="g-section">
    <h2 class="g-title">Agents Tab — Expandable Rows</h2>
    <p class="g-desc">Lists all agents owned by this user. Each row is expandable — clicking it reveals the agent's jobs inline using the <code style="color:var(--eb-info-text);background:var(--eb-bg-overlay);padding:2px 6px;border-radius:4px;font-size:12px;">eb-mini-job</code> compact format. Since agents have no configuration of their own, there's no separate agent detail page.</p>

    <div class="g-preview">
      <div class="g-preview-label">Agents table with one row expanded</div>

      <div class="eb-table-shell">
        <table class="eb-table">
          <thead>
            <tr>
              <th style="width:32px;"></th>
              <th>Hostname</th>
              <th>Agent UUID</th>
              <th>Type</th>
              <th>Connection</th>
              <th>Last Seen</th>
              <th>Jobs</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <!-- Row 1 — expanded -->
            <tr class="eb-expand-row is-open" style="background:var(--eb-bg-hover);">
              <td style="padding:11px 8px 11px 14px;">
                <svg class="eb-expand-chevron" viewBox="0 0 24 24" style="transform:rotate(90deg);"><polyline points="9 18 15 12 9 6"/></svg>
              </td>
              <td class="eb-table-primary">Media</td>
              <td class="eb-table-mono">17475e6e-8cf2…</td>
              <td><span class="eb-badge eb-badge--default" style="font-size:10.5px;">workstation</span></td>
              <td>
                <span style="display:flex;align-items:center;gap:6px;">
                  <span class="eb-status-dot eb-status-dot--error"></span>
                  <span style="color:var(--eb-danger-text);font-size:12px;">Offline</span>
                  <span style="color:var(--eb-text-disabled);font-size:11px;">(37d)</span>
                </span>
              </td>
              <td style="font-size:12px;">2026-02-25 17:07</td>
              <td style="text-align:center;">1</td>
              <td><span class="eb-badge eb-badge--success" style="font-size:10.5px;">active</span></td>
            </tr>

            <!-- Expanded detail row -->
            <tr class="eb-expand-detail">
              <td colspan="8">
                <div class="eb-expand-detail-inner">
                  <div class="eb-expand-detail-header">
                    <svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    Jobs on this agent (1)
                  </div>
                  <div class="eb-mini-job">
                    <div class="eb-mini-job-name">AWS – e3cloudbackup</div>
                    <div class="eb-mini-job-meta">e3cloudbackup-1231</div>
                    <div class="eb-mini-job-meta">Sync</div>
                    <div class="eb-mini-job-meta">Manual</div>
                    <div class="eb-mini-job-meta">Apr 03, 2026, 08:37 PM</div>
                    <div class="eb-mini-job-status">
                      <span class="eb-status-dot eb-status-dot--active"></span>
                      <span style="color:var(--eb-success-text);">Success</span>
                    </div>
                  </div>
                </div>
              </td>
            </tr>

            <!-- Row 2 — collapsed -->
            <tr class="eb-expand-row">
              <td style="padding:11px 8px 11px 14px;">
                <svg class="eb-expand-chevron" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
              </td>
              <td class="eb-table-primary">DESKTOP-Q6NNHLQ</td>
              <td class="eb-table-mono">aa7c65e1-51b1…</td>
              <td><span class="eb-badge eb-badge--default" style="font-size:10.5px;">workstation</span></td>
              <td>
                <span style="display:flex;align-items:center;gap:6px;">
                  <span class="eb-status-dot eb-status-dot--error"></span>
                  <span style="color:var(--eb-danger-text);font-size:12px;">Offline</span>
                  <span style="color:var(--eb-text-disabled);font-size:11px;">(37d)</span>
                </span>
              </td>
              <td style="font-size:12px;">2026-02-25 17:07</td>
              <td style="text-align:center;color:var(--eb-text-disabled);">0</td>
              <td><span class="eb-badge eb-badge--success" style="font-size:10.5px;">active</span></td>
            </tr>

            <!-- Row 3 — collapsed -->
            <tr class="eb-expand-row">
              <td style="padding:11px 8px 11px 14px;">
                <svg class="eb-expand-chevron" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
              </td>
              <td class="eb-table-primary">WEB-SERVER-01</td>
              <td class="eb-table-mono">f3a1b2c8-9d4e…</td>
              <td><span class="eb-badge eb-badge--default" style="font-size:10.5px;">server</span></td>
              <td>
                <span style="display:flex;align-items:center;gap:6px;">
                  <span class="eb-status-dot eb-status-dot--active"></span>
                  <span style="color:var(--eb-success-text);font-size:12px;">Online</span>
                </span>
              </td>
              <td style="font-size:12px;">2026-04-04 09:12</td>
              <td style="text-align:center;">2</td>
              <td><span class="eb-badge eb-badge--success" style="font-size:10.5px;">active</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="g-note">
      <strong>Expand interaction.</strong> Clicking anywhere on the row toggles expansion. The chevron rotates 90° on open. The detail area uses <code>eb-bg-chrome</code> (the darkest surface) to visually nest it below the parent row. If an agent has no jobs, the expanded area shows an <code>eb-app-empty</code> state: "No jobs configured on this agent."
    </div>
  </div>


  <!-- ═══════════════ §6 — VAULTS TAB ═══════════════ -->
  <div class="g-section">
    <h2 class="g-title">Vaults Tab</h2>
    <p class="g-desc">Displays storage vault locations assigned to this user. Each vault card shows the provider, bucket path, storage used, and creation date.</p>

    <div class="g-preview">
      <div class="g-preview-label">eb-vault-card — storage locations</div>

      <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(300px, 1fr));gap:14px;">
        <div class="eb-vault-card">
          <div class="eb-vault-card-header">
            <div class="eb-vault-icon">
              <svg viewBox="0 0 24 24"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2z"/></svg>
            </div>
            <div>
              <div class="eb-vault-name">e3cloudbackup-1231</div>
              <div class="eb-vault-provider">eazyBackup Cloud — Canadian Storage</div>
            </div>
          </div>
          <div class="eb-vault-stats">
            <div class="eb-vault-stat">
              <div class="eb-vault-stat-label">Storage Used</div>
              <div class="eb-vault-stat-value">847 GB</div>
            </div>
            <div class="eb-vault-stat">
              <div class="eb-vault-stat-label">Bucket Path</div>
              <div class="eb-vault-stat-value">/e3cloudbackup3</div>
            </div>
            <div class="eb-vault-stat">
              <div class="eb-vault-stat-label">Created</div>
              <div class="eb-vault-stat-value">2026-01-15</div>
            </div>
            <div class="eb-vault-stat">
              <div class="eb-vault-stat-label">Jobs Using</div>
              <div class="eb-vault-stat-value">1</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>


  <!-- ═══════════════ §7 — BILLING / TENANT TAB ═══════════════ -->
  <div class="g-section">
    <h2 class="g-title">Billing / Tenant Tab</h2>
    <p class="g-desc">Surfaces the tenant relationship for MSP billing. Shows storage consumption, agent counts, and per-type backup counts used for billing (disk images, Hyper-V guests, Proxmox guests).</p>

    <div class="g-preview">
      <div class="g-preview-label">Tenant relationship + billing KPIs</div>

      <!-- Tenant card -->
      <div class="eb-subpanel" style="margin-bottom:20px;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
          <div>
            <div style="font-size:10.5px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--eb-text-muted);margin-bottom:4px;">Linked Tenant</div>
            <div style="font-family:var(--eb-font-display);font-size:17px;font-weight:600;color:var(--eb-text-primary);">GenX Communications</div>
            <div style="font-size:12px;color:var(--eb-text-muted);margin-top:2px;">MSP billing tenant — billed monthly</div>
          </div>
          <div style="display:flex;align-items:center;gap:8px;">
            <button class="eb-btn eb-btn-secondary eb-btn-xs">View Tenant</button>
            <button class="eb-btn eb-btn-ghost eb-btn-xs">Change Tenant</button>
          </div>
        </div>
      </div>

      <!-- KPI grid -->
      <div style="font-size:10.5px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--eb-text-muted);margin-bottom:10px;">Billable Resources</div>

      <div class="eb-billing-kpi-grid">
        <div class="eb-billing-kpi">
          <div class="eb-billing-kpi-value">6</div>
          <div class="eb-billing-kpi-label">Agents</div>
        </div>
        <div class="eb-billing-kpi">
          <div class="eb-billing-kpi-value">847 <span style="font-size:14px;color:var(--eb-text-muted);font-weight:400;">GB</span></div>
          <div class="eb-billing-kpi-label">Storage Used</div>
        </div>
        <div class="eb-billing-kpi">
          <div class="eb-billing-kpi-value">2</div>
          <div class="eb-billing-kpi-label">Disk Image Jobs</div>
        </div>
        <div class="eb-billing-kpi">
          <div class="eb-billing-kpi-value">3</div>
          <div class="eb-billing-kpi-label">Hyper-V Guests</div>
        </div>
        <div class="eb-billing-kpi">
          <div class="eb-billing-kpi-value">0</div>
          <div class="eb-billing-kpi-label">Proxmox Guests</div>
        </div>
      </div>

      <div class="g-note" style="margin-top:16px;margin-bottom:0;">
        <strong>Billing context.</strong> MSPs are billed a fixed price per agent, and additionally for each Disk Image backup, Hyper-V guest, and Proxmox guest VM. These KPI tiles give the MSP an at-a-glance summary of what they're paying for on this user's account.
      </div>
    </div>
  </div>


  <!-- ═══════════════ §8 — STANDALONE PAGE UPDATES ═══════════════ -->
  <div class="g-section">
    <div class="g-label">Section 04 — Standalone Pages</div>
    <h2 class="g-title">Agents & Jobs Page Updates</h2>
    <p class="g-desc">The standalone Agents and Jobs sidebar pages remain as global views for MSPs. Both pages need a new "User" column so admins can see which user owns each agent or job.</p>

    <div class="g-preview">
      <div class="g-preview-label">Agents page — new User column added</div>

      <div class="eb-table-shell">
        <table class="eb-table">
          <thead>
            <tr>
              <th>Connection</th>
              <th>Hostname</th>
              <th style="background:var(--eb-primary-soft);color:var(--eb-accent);border-bottom-color:var(--eb-primary-border);">User ✦ new</th>
              <th>Tenant</th>
              <th>Type</th>
              <th>Status</th>
              <th>Last Seen</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>
                <span style="display:flex;align-items:center;gap:6px;">
                  <span class="eb-status-dot eb-status-dot--error"></span>
                  <span style="color:var(--eb-danger-text);font-size:12px;">Offline</span>
                </span>
              </td>
              <td class="eb-table-primary">Media</td>
              <td><a href="#" style="color:var(--eb-accent);text-decoration:none;font-size:12.5px;">notenantuser</a></td>
              <td>Direct</td>
              <td><span class="eb-badge eb-badge--default" style="font-size:10.5px;">workstation</span></td>
              <td><span class="eb-badge eb-badge--success" style="font-size:10.5px;">active</span></td>
              <td style="font-size:12px;">2026-02-25</td>
            </tr>
            <tr>
              <td>
                <span style="display:flex;align-items:center;gap:6px;">
                  <span class="eb-status-dot eb-status-dot--active"></span>
                  <span style="color:var(--eb-success-text);font-size:12px;">Online</span>
                </span>
              </td>
              <td class="eb-table-primary">PROD-SERVER-01</td>
              <td><a href="#" style="color:var(--eb-accent);text-decoration:none;font-size:12.5px;">willywonka</a></td>
              <td>GenX Communications</td>
              <td><span class="eb-badge eb-badge--default" style="font-size:10.5px;">server</span></td>
              <td><span class="eb-badge eb-badge--success" style="font-size:10.5px;">active</span></td>
              <td style="font-size:12px;">2026-04-04</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="g-note">
      <strong>User column links.</strong> The User column shows the username as an <code>eb-link</code> (orange accent on hover). Clicking it navigates to that user's detail page with the tabbed view. The same pattern applies to the Jobs page — each job card should show its owning user and agent in a subtle meta line.
    </div>
  </div>


  <!-- ═══════════════ §9 — NEW CLASS REFERENCE ═══════════════ -->
  <div class="g-section">
    <div class="g-label">Section 05 — Reference</div>
    <h2 class="g-title">New <code style="color:var(--eb-info-text);background:var(--eb-bg-overlay);padding:2px 6px;border-radius:4px;font-size:18px;">eb-*</code> Class Reference</h2>
    <p class="g-desc">All new semantic classes introduced by this redesign, ready for addition to <code style="color:var(--eb-info-text);background:var(--eb-bg-overlay);padding:2px 6px;border-radius:4px;font-size:12px;">tailwind.src.css</code>.</p>

    <h3 class="g-subtitle">User Summary</h3>
    <table class="g-spec-table">
      <thead><tr><th>Class</th><th>Element</th><th>Purpose</th></tr></thead>
      <tbody>
        <tr><td><code>eb-user-summary</code></td><td>div</td><td>Outer container. Card background + border + radius.</td></tr>
        <tr><td><code>eb-user-summary-header</code></td><td>div</td><td>Top row: identity left, actions right. Bottom border.</td></tr>
        <tr><td><code>eb-user-summary-identity</code></td><td>div</td><td>Flex row: avatar + name/meta.</td></tr>
        <tr><td><code>eb-user-avatar</code></td><td>div</td><td>40×40 square with initials. <code>--eb-bg-overlay</code>.</td></tr>
        <tr><td><code>eb-user-name</code></td><td>div</td><td>Outfit 17px 600. Primary text.</td></tr>
        <tr><td><code>eb-user-meta-line</code></td><td>div</td><td>12px muted text. Uses <code>.sep</code> dot separators.</td></tr>
        <tr><td><code>eb-user-summary-stats</code></td><td>div</td><td>5-column grid of stat tiles.</td></tr>
        <tr><td><code>eb-user-stat</code></td><td>div / a</td><td>Single stat tile. Add <code>.is-clickable</code> for pointer.</td></tr>
        <tr><td><code>eb-user-stat-value</code></td><td>div</td><td>Outfit 22px 700. Primary text.</td></tr>
        <tr><td><code>eb-user-stat-label</code></td><td>div</td><td>10.5px uppercase muted label.</td></tr>
      </tbody>
    </table>

    <h3 class="g-subtitle">Tab Bar</h3>
    <table class="g-spec-table">
      <thead><tr><th>Class</th><th>Element</th><th>Purpose</th></tr></thead>
      <tbody>
        <tr><td><code>eb-tab-bar</code></td><td>div</td><td>Full-width flex container with bottom border. Rounds top corners.</td></tr>
        <tr><td><code>eb-tab</code> (inside <code>eb-tab-bar</code>)</td><td>button</td><td>Tab item. Add <code>is-active</code> for active state (primary bottom border).</td></tr>
        <tr><td><code>eb-tab-count</code></td><td>span</td><td>Count badge next to tab label. Orange-tinted when parent tab is active.</td></tr>
        <tr><td><code>eb-tab-body</code></td><td>div</td><td>Content area below tab bar. Surface bg, border (no top), bottom radius.</td></tr>
      </tbody>
    </table>

    <h3 class="g-subtitle">Expandable Rows</h3>
    <table class="g-spec-table">
      <thead><tr><th>Class</th><th>Element</th><th>Purpose</th></tr></thead>
      <tbody>
        <tr><td><code>eb-expand-row</code></td><td>tr</td><td>Clickable table row with hover state. Add <code>is-open</code> when expanded.</td></tr>
        <tr><td><code>eb-expand-chevron</code></td><td>svg</td><td>18×18 chevron-right icon. Rotates 90° when <code>.is-open</code>.</td></tr>
        <tr><td><code>eb-expand-detail</code></td><td>tr</td><td>Hidden detail row. Chrome background.</td></tr>
        <tr><td><code>eb-expand-detail-inner</code></td><td>div</td><td>Padded content inside the detail row (left-indented to align with content).</td></tr>
        <tr><td><code>eb-expand-detail-header</code></td><td>div</td><td>Uppercase label above the expanded content.</td></tr>
      </tbody>
    </table>

    <h3 class="g-subtitle">Mini Job (Compact Inline)</h3>
    <table class="g-spec-table">
      <thead><tr><th>Class</th><th>Element</th><th>Purpose</th></tr></thead>
      <tbody>
        <tr><td><code>eb-mini-job</code></td><td>div</td><td>6-column grid row. Card bg with faint border. Used inside expanded agent rows.</td></tr>
        <tr><td><code>eb-mini-job-name</code></td><td>div</td><td>Job name. Primary text, 600 weight, truncated.</td></tr>
        <tr><td><code>eb-mini-job-meta</code></td><td>div</td><td>Muted 12px metadata value.</td></tr>
        <tr><td><code>eb-mini-job-status</code></td><td>div</td><td>Flex row: status dot + label.</td></tr>
      </tbody>
    </table>

    <h3 class="g-subtitle">Quotas</h3>
    <table class="g-spec-table">
      <thead><tr><th>Class</th><th>Element</th><th>Purpose</th></tr></thead>
      <tbody>
        <tr><td><code>eb-quota-grid</code></td><td>div</td><td>Auto-fill grid, min 260px per card.</td></tr>
        <tr><td><code>eb-quota-card</code></td><td>div</td><td>Individual quota card. Card bg + border.</td></tr>
        <tr><td><code>eb-quota-card-header</code></td><td>div</td><td>Flex row: label + badge.</td></tr>
        <tr><td><code>eb-quota-label</code></td><td>span</td><td>12px uppercase resource name.</td></tr>
        <tr><td><code>eb-quota-badge</code></td><td>span</td><td>Status pill. Variants: <code>.within</code> <code>.near-limit</code> <code>.exceeded</code> <code>.unlimited</code>.</td></tr>
        <tr><td><code>eb-quota-bar</code></td><td>div</td><td>4px progress track.</td></tr>
        <tr><td><code>eb-quota-bar-fill</code></td><td>div</td><td>Coloured fill bar inside track.</td></tr>
        <tr><td><code>eb-quota-input-row</code></td><td>div</td><td>Flex row for the limit input + save button.</td></tr>
      </tbody>
    </table>

    <h3 class="g-subtitle">Billing KPIs</h3>
    <table class="g-spec-table">
      <thead><tr><th>Class</th><th>Element</th><th>Purpose</th></tr></thead>
      <tbody>
        <tr><td><code>eb-billing-kpi-grid</code></td><td>div</td><td>Auto-fill grid, min 180px per tile.</td></tr>
        <tr><td><code>eb-billing-kpi</code></td><td>div</td><td>Single KPI tile. Centered text.</td></tr>
        <tr><td><code>eb-billing-kpi-value</code></td><td>div</td><td>Outfit 26px 700.</td></tr>
        <tr><td><code>eb-billing-kpi-label</code></td><td>div</td><td>10.5px uppercase muted label.</td></tr>
      </tbody>
    </table>

    <h3 class="g-subtitle">Vault Cards</h3>
    <table class="g-spec-table">
      <thead><tr><th>Class</th><th>Element</th><th>Purpose</th></tr></thead>
      <tbody>
        <tr><td><code>eb-vault-card</code></td><td>div</td><td>Card container with hover emphasis border.</td></tr>
        <tr><td><code>eb-vault-card-header</code></td><td>div</td><td>Flex row: icon + name/provider.</td></tr>
        <tr><td><code>eb-vault-icon</code></td><td>div</td><td>36×36 icon box. Overlay bg, muted stroke icons.</td></tr>
        <tr><td><code>eb-vault-name</code></td><td>div</td><td>Outfit 14px 600.</td></tr>
        <tr><td><code>eb-vault-provider</code></td><td>div</td><td>11.5px muted provider label.</td></tr>
        <tr><td><code>eb-vault-stats</code></td><td>div</td><td>2-column grid of stat pairs.</td></tr>
        <tr><td><code>eb-vault-stat-label</code></td><td>div</td><td>10px uppercase muted label.</td></tr>
        <tr><td><code>eb-vault-stat-value</code></td><td>div</td><td>13px 500 primary value.</td></tr>
      </tbody>
    </table>
  </div>


  <!-- ═══════════════ §10 — IMPLEMENTATION NOTES ═══════════════ -->
  <div class="g-section">
    <div class="g-label">Section 06 — Implementation</div>
    <h2 class="g-title">Implementation Notes</h2>

    <div class="g-note">
      <strong>Alpine.js tab switching.</strong> The tab bar should use Alpine.js <code>x-data="{ tab: 'overview' }"</code> with <code>x-show</code> on each tab body. The stat tiles in the summary header use <code>@click="tab = 'agents'"</code> etc. to navigate to the corresponding tab.
    </div>

    <div class="g-note">
      <strong>Expandable rows.</strong> Each <code>eb-expand-row</code> uses Alpine.js <code>x-data="{ open: false }"</code>. The detail row uses <code>x-show="open"</code> with <code>x-collapse</code> for smooth animation. The chevron rotation is handled by toggling the <code>is-open</code> class.
    </div>

    <div class="g-note">
      <strong>Jobs tab.</strong> The Jobs tab should reuse the full <code>job-card</code> component from the Job Card Redesign guide, grouped by agent with a subheading for each agent. This tab provides the detailed view; the Agents tab expansion provides the quick-glance view.
    </div>

    <div class="g-note">
      <strong>CSS integration.</strong> Add all new <code>eb-*</code> classes to <code>templates/eazyBackup/css/tailwind.src.css</code> using the <code>@layer components { }</code> directive, following the same pattern as existing <code>eb-*</code> classes. All colours must use <code>var(--eb-*)</code> tokens — no raw hex values.
    </div>

    <div class="g-note">
      <strong>Standalone page changes.</strong> Both the Agents page and Jobs page need a new "User" column added to their tables. The value should be the username rendered as an <code>eb-link</code> that navigates to <code>/users/{username}</code>. This column should be included in the Columns dropdown for optional visibility.
    </div>
  </div>

</div>

</body>
</html>
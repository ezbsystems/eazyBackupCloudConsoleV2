<style>
[x-cloak] { display: none !important; }

/* Global dark slim scrollbar (Chrome/Edge/Safari) */
::-webkit-scrollbar { width: 8px; height: 8px; }
::-webkit-scrollbar-track { background: rgba(15, 23, 42, 0.6); }
::-webkit-scrollbar-thumb { background: rgba(51, 65, 85, 0.8); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: rgba(71, 85, 105, 0.9); }
::-webkit-scrollbar-corner { background: rgba(15, 23, 42, 0.6); }
* { scrollbar-width: thin; scrollbar-color: rgba(51, 65, 85, 0.8) rgba(15, 23, 42, 0.6); }

:root,
[data-theme="light"] {
  --eb-font-display: 'Outfit', system-ui, sans-serif;
  --eb-font-body: 'DM Sans', system-ui, sans-serif;
  --eb-font-mono: 'Courier New', monospace;

  --eb-brand-orange: #fe5000;
  --eb-primary: #d55d1d;
  --eb-primary-hover: #c04f18;
  --eb-accent: #ff7a33;
  --eb-accent-2: #ff924d;

  --eb-bg-chrome: #2b2b2b;
  --eb-bg-base: #fff4dd;
  --eb-bg-surface: #fff5e3;
  --eb-bg-card: #ffffff;
  --eb-bg-raised: #fff9f2;
  --eb-bg-overlay: #ffffff;
  --eb-bg-hover: rgba(213, 93, 29, 0.08);
  --eb-bg-active: rgba(213, 93, 29, 0.14);
  --eb-bg-input: rgba(255, 255, 255, 0.96);
  --eb-bg-input-focus: #ffffff;

  --eb-surface-page: var(--eb-bg-base);
  --eb-surface-panel: var(--eb-bg-surface);
  --eb-surface-subpanel: var(--eb-bg-card);
  --eb-surface-elevated: var(--eb-bg-raised);
  --eb-surface-overlay: var(--eb-bg-overlay);
  --eb-surface-input: var(--eb-bg-input);
  --eb-surface-nav: var(--eb-bg-chrome);
  --eb-surface-hover: var(--eb-bg-hover);
  --eb-surface-active: var(--eb-bg-active);

  --eb-text-primary: #2b2b2b;
  --eb-text-secondary: #444444;
  --eb-text-muted: #6b6b6b;
  --eb-text-disabled: #8b8b8b;
  --eb-text-inverse: #f8fafc;

  --eb-border-faint: #f1e3d3;
  --eb-border-subtle: #ffe3cc;
  --eb-border-default: #ffbe91;
  --eb-border-emphasis: #e9a06c;
  --eb-border-strong: #d55d1d;
  --eb-border-orange: rgba(213, 93, 29, 0.22);
  --eb-border-brand: rgba(254, 80, 0, 0.32);
  --eb-border-muted: var(--eb-border-subtle);
  --eb-ring: rgba(213, 93, 29, 0.14);
  --eb-ring-danger: rgba(239, 68, 68, 0.12);

  --eb-primary-soft: rgba(213, 93, 29, 0.12);
  --eb-primary-border: rgba(213, 93, 29, 0.28);
  --eb-brand-orange-soft: rgba(254, 80, 0, 0.1);
  --eb-brand-orange-glow: rgba(254, 80, 0, 0.06);

  --eb-success-bg: rgba(34, 197, 94, 0.1);
  --eb-success-border: rgba(34, 197, 94, 0.28);
  --eb-success-text: #166534;
  --eb-success-icon: #16a34a;
  --eb-success-soft: rgba(34, 197, 94, 0.1);
  --eb-success-strong: #16a34a;

  --eb-warning-bg: rgba(245, 158, 11, 0.1);
  --eb-warning-border: rgba(217, 119, 6, 0.28);
  --eb-warning-text: #92400e;
  --eb-warning-icon: #d97706;
  --eb-warning-soft: rgba(245, 158, 11, 0.1);
  --eb-warning-strong: #b45309;

  --eb-danger-bg: rgba(239, 68, 68, 0.1);
  --eb-danger-border: rgba(220, 38, 38, 0.28);
  --eb-danger-text: #991b1b;
  --eb-danger-icon: #ef4444;
  --eb-danger-soft: rgba(239, 68, 68, 0.1);
  --eb-danger-strong: #dc2626;

  --eb-info-bg: rgba(59, 130, 246, 0.1);
  --eb-info-border: rgba(37, 99, 235, 0.28);
  --eb-info-text: #1d4ed8;
  --eb-info-icon: #2563eb;
  --eb-info-soft: rgba(59, 130, 246, 0.1);
  --eb-info-strong: #2563eb;

  --eb-premium-bg: rgba(139, 92, 246, 0.08);
  --eb-premium-border: rgba(139, 92, 246, 0.24);
  --eb-premium-text: #7c3aed;
  --eb-premium-icon: #8b5cf6;
  --eb-premium-soft: rgba(139, 92, 246, 0.1);
  --eb-premium-strong: #7c3aed;

  --eb-type-hero-size: 48px;
  --eb-type-h2-size: 30px;
  --eb-type-h3-size: 17px;
  --eb-type-h4-size: 14px;
  --eb-type-eyebrow-size: 10.5px;
  --eb-type-body-size: 14px;
  --eb-type-body-lg-size: 15px;
  --eb-type-caption-size: 12px;
  --eb-type-button-size: 13.5px;
  --eb-type-mono-size: 12px;
  --eb-type-stat-size: 40px;

  --eb-radius-sm: 6px;
  --eb-radius-md: 10px;
  --eb-radius-lg: 14px;
  --eb-radius-xl: 18px;
  --eb-radius-pill: 999px;

  --eb-shadow-sm: 0 1px 3px rgba(43, 43, 43, 0.08);
  --eb-shadow-md: 0 4px 16px rgba(43, 43, 43, 0.12), 0 1px 4px rgba(43, 43, 43, 0.1);
  --eb-shadow-lg: 0 12px 40px rgba(43, 43, 43, 0.16), 0 4px 12px rgba(43, 43, 43, 0.12);
  --eb-shadow-modal: 0 24px 80px rgba(43, 43, 43, 0.22), 0 8px 24px rgba(43, 43, 43, 0.16);
  --eb-shadow-panel: var(--eb-shadow-lg);

  --eb-backdrop-modal: rgba(5, 10, 20, 0.28);
  --eb-backdrop-drawer: rgba(5, 10, 20, 0.18);
  --eb-sidebar-width: 220px;
  --eb-drawer-width-narrow: 320px;
  --eb-drawer-width-wide: 480px;
  --eb-drawer-width-panel: min(80rem, 100vw);
  --eb-modal-width-standard: 480px;
  --eb-modal-width-confirm: 400px;

  /* Compatibility aliases for existing token-based templates */
  --bg-page: 255 244 221;
  --bg-card: 255 255 255;
  --bg-input: 255 255 255 / 0.96;
  --text-primary: 43 43 43;
  --text-secondary: 68 68 68;
  --accent: 213 93 29;
  --ring-neutral: 255 255 255;
  --success: 22 163 74;
  --success-ring: 34 197 94;
  --danger: 220 38 38;
  --danger-ring: 248 113 113;
  --shadow-1: var(--eb-shadow-sm);
  --shadow-2: var(--eb-shadow-md);
  --shadow-3: var(--eb-shadow-lg);
}

[data-theme="dark"] {
  --eb-font-display: 'Outfit', system-ui, sans-serif;
  --eb-font-body: 'DM Sans', system-ui, sans-serif;
  --eb-font-mono: 'Courier New', monospace;

  --eb-brand-orange: #fe5000;
  --eb-primary: #d55d1d;
  --eb-primary-hover: #c04f18;
  --eb-accent: #ff7a33;
  --eb-accent-2: #ff924d;

  --eb-bg-chrome: #070d1b;
  --eb-bg-base: #0b1220;
  --eb-bg-surface: #111d33;
  --eb-bg-card: #172035;
  --eb-bg-raised: #1e2d45;
  --eb-bg-overlay: #253450;
  --eb-bg-hover: #1a2840;
  --eb-bg-active: #1f3050;
  --eb-bg-input: #131e34;
  --eb-bg-input-focus: #172035;

  --eb-surface-page: var(--eb-bg-base);
  --eb-surface-panel: var(--eb-bg-surface);
  --eb-surface-subpanel: var(--eb-bg-card);
  --eb-surface-elevated: var(--eb-bg-raised);
  --eb-surface-overlay: var(--eb-bg-overlay);
  --eb-surface-input: var(--eb-bg-input);
  --eb-surface-nav: var(--eb-bg-chrome);
  --eb-surface-hover: var(--eb-bg-hover);
  --eb-surface-active: var(--eb-bg-active);

  --eb-text-primary: #eef2f9;
  --eb-text-secondary: #adbdd5;
  --eb-text-muted: #6d88a8;
  --eb-text-disabled: #3d5470;
  --eb-text-inverse: #0b1220;

  --eb-border-faint: #141f35;
  --eb-border-subtle: #1e2d45;
  --eb-border-default: #253658;
  --eb-border-emphasis: #304878;
  --eb-border-strong: #3d5a8a;
  --eb-border-orange: rgba(213, 93, 29, 0.3);
  --eb-border-brand: rgba(254, 80, 0, 0.4);
  --eb-border-muted: var(--eb-border-subtle);
  --eb-ring: rgba(213, 93, 29, 0.12);
  --eb-ring-danger: rgba(239, 68, 68, 0.12);

  --eb-primary-soft: rgba(213, 93, 29, 0.12);
  --eb-primary-border: rgba(213, 93, 29, 0.28);
  --eb-brand-orange-soft: rgba(254, 80, 0, 0.1);
  --eb-brand-orange-glow: rgba(254, 80, 0, 0.06);

  --eb-success-bg: #091e16;
  --eb-success-border: #0e3d28;
  --eb-success-text: #36d68a;
  --eb-success-icon: #22c55e;
  --eb-success-soft: rgba(34, 197, 94, 0.1);
  --eb-success-strong: #16a34a;

  --eb-warning-bg: #1e1200;
  --eb-warning-border: #5c3700;
  --eb-warning-text: #f59e0b;
  --eb-warning-icon: #d97706;
  --eb-warning-soft: rgba(245, 158, 11, 0.1);
  --eb-warning-strong: #b45309;

  --eb-danger-bg: #1c0808;
  --eb-danger-border: #5c1616;
  --eb-danger-text: #f77070;
  --eb-danger-icon: #ef4444;
  --eb-danger-soft: rgba(239, 68, 68, 0.1);
  --eb-danger-strong: #dc2626;

  --eb-info-bg: #081628;
  --eb-info-border: #173562;
  --eb-info-text: #60a5fa;
  --eb-info-icon: #3b82f6;
  --eb-info-soft: rgba(59, 130, 246, 0.1);
  --eb-info-strong: #2563eb;

  --eb-premium-bg: #120e24;
  --eb-premium-border: #3a2278;
  --eb-premium-text: #a78bfa;
  --eb-premium-icon: #8b5cf6;
  --eb-premium-soft: rgba(139, 92, 246, 0.1);
  --eb-premium-strong: #7c3aed;

  --eb-type-hero-size: 48px;
  --eb-type-h2-size: 30px;
  --eb-type-h3-size: 17px;
  --eb-type-h4-size: 14px;
  --eb-type-eyebrow-size: 10.5px;
  --eb-type-body-size: 14px;
  --eb-type-body-lg-size: 15px;
  --eb-type-caption-size: 12px;
  --eb-type-button-size: 13.5px;
  --eb-type-mono-size: 12px;
  --eb-type-stat-size: 40px;

  --eb-radius-sm: 6px;
  --eb-radius-md: 10px;
  --eb-radius-lg: 14px;
  --eb-radius-xl: 18px;
  --eb-radius-pill: 999px;

  --eb-shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.4);
  --eb-shadow-md: 0 4px 16px rgba(0, 0, 0, 0.35), 0 1px 4px rgba(0, 0, 0, 0.3);
  --eb-shadow-lg: 0 12px 40px rgba(0, 0, 0, 0.5), 0 4px 12px rgba(0, 0, 0, 0.35);
  --eb-shadow-modal: 0 24px 80px rgba(0, 0, 0, 0.65), 0 8px 24px rgba(0, 0, 0, 0.5);
  --eb-shadow-panel: var(--eb-shadow-lg);

  --eb-backdrop-modal: rgba(5, 10, 20, 0.72);
  --eb-backdrop-drawer: rgba(5, 10, 20, 0.5);
  --eb-sidebar-width: 220px;
  --eb-drawer-width-narrow: 320px;
  --eb-drawer-width-wide: 480px;
  --eb-drawer-width-panel: min(80rem, 100vw);
  --eb-modal-width-standard: 480px;
  --eb-modal-width-confirm: 400px;

  /* Compatibility aliases for existing token-based templates */
  --bg-page: 11 18 32;
  --bg-card: 23 32 53;
  --bg-input: 19 30 52;
  --text-primary: 238 242 249;
  --text-secondary: 173 189 213;
  --accent: 213 93 29;
  --ring-neutral: 255 255 255;
  --success: 22 197 94;
  --success-ring: 54 214 138;
  --danger: 239 68 68;
  --danger-ring: 247 112 112;
  --shadow-1: var(--eb-shadow-sm);
  --shadow-2: var(--eb-shadow-md);
  --shadow-3: var(--eb-shadow-lg);
}

html,
body {
  font-family: var(--eb-font-body);
}

body[data-theme] {
  background: var(--eb-surface-page);
  color: var(--eb-text-secondary);
}

.eb-shell-body {
  display: flex;
  min-height: 100vh;
  background: var(--eb-surface-page);
  color: var(--eb-text-secondary);
}

.card {
  background:
    linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.015)),
    rgb(var(--bg-card));
  border: 1px solid rgba(255,255,255,.10);     /* hairline frame */
  border-radius: 1rem;                         /* rounded-2xl feel */
  box-shadow: var(--shadow-2);                 /* softer, wider */
}

.card--subtle { box-shadow: var(--shadow-1); } /* use for dense lists */
.card--prominent { box-shadow: var(--shadow-3); } /* dashboards/hero blocks */

.card__header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid rgba(255,255,255,.10);
}

.card__body { padding: 1.5rem; }

/* Optional accent rail on important sections */
.card--accented { position: relative; }
.card--accented::before {
  content:""; position:absolute; inset:0 0 auto 0; height:2px;
  background:
    linear-gradient(90deg, rgba(255,255,255,.08), transparent 35%),
    linear-gradient(90deg, rgb(var(--accent)), transparent 65%);
  opacity:.8; border-top-left-radius:inherit; border-top-right-radius:inherit;
}

.input {
  width:100%;
  color: rgb(var(--text-primary));
  background: rgb(var(--bg-input));
  border: 1px solid rgba(255,255,255,.10);
  border-radius: .75rem;
  padding: .625rem .875rem;
  outline: none;
  transition: box-shadow .15s ease, border-color .15s ease, background-color .15s ease;
}
.input::placeholder { color: rgba(255,255,255,.35); }
.input:focus {
  border-color: rgb(var(--accent));
  box-shadow: 0 0 0 2px rgba(255,255,255,.06),
              0 0 0 4px rgb(var(--accent));
}

.table-head {
  background: color-mix(in srgb, rgb(var(--bg-card)), black 6%);
  border-bottom: 1px solid rgba(255,255,255,.08);
}
.table-row {
  border-top: 1px solid rgba(255,255,255,.06);
  transition: background-color .15s ease, border-color .15s ease;
}
.table-row:hover {
  background: color-mix(in srgb, rgb(var(--bg-card)), white 3%);
  border-color: rgba(255,255,255,.09);
}


  /* Button system (shared base + variants) */
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.75rem; /* rounded-xl */
    padding: 0.625rem 1rem; /* py-2.5 px-4 */
    font-weight: 500; /* font-medium */
    border: 1px solid transparent;
    text-decoration: none;
    cursor: pointer;
    transition: background-color .15s ease, color .15s ease, box-shadow .15s ease, opacity .15s ease;
    outline: none;
  }
  .btn:focus-visible {
    /* ring-offset-2 (bg-card) + ring-2 (accent) */
    box-shadow: 0 0 0 2px rgb(var(--bg-card)), 0 0 0 4px rgb(var(--accent));
  }
  .btn:disabled,
  .btn[disabled],
  .btn[aria-disabled="true"],
  .btn.btn-disabled {
    opacity: .5;
    cursor: not-allowed;
    pointer-events: none;
  }

  /* Primary */
  .btn-primary {
    background-color: rgb(var(--accent));
    color: #fff;
  }
  .btn-primary:hover {
    background-color: rgb(var(--accent) / .9);
  }
  .btn-primary:focus-visible {
    box-shadow: 0 0 0 2px rgb(var(--bg-card)), 0 0 0 4px rgb(var(--accent));
  }

  /* Secondary (ghost) */
  .btn-secondary {
  color: rgba(255,255,255,.92);
  background-color: transparent;
  border: 1px solid rgba(255,255,255,.16);  /* was .12 */
}
.btn-secondary:hover { background-color: rgba(255,255,255,.08); } /* was .06 */

  .btn-secondary:focus-visible {
    box-shadow: 0 0 0 2px rgb(var(--bg-card)), 0 0 0 4px rgb(var(--accent));
  }

  /* Affirm/Save (success) */
  .btn-affirm {
    background-color: rgb(var(--success) / .9);
    color: #fff;
    border-color: rgb(var(--success-ring) / .2);
  }
  .btn-affirm:hover {
    background-color: rgb(var(--success) / .85);
  }
  .btn-affirm:focus-visible {
    box-shadow: 0 0 0 2px rgb(var(--bg-card)), 0 0 0 4px rgb(var(--success-ring));
  }

  /* Danger */
.btn-danger {
  background-color: rgb(var(--danger) / .9);
  color: #fff;
  border-color: rgb(var(--danger-ring) / .2);
  }
  .btn-danger:hover {
    background-color: rgb(var(--danger) / .85);
  }
  .btn-danger:focus-visible {
    box-shadow: 0 0 0 2px rgb(var(--bg-card)), 0 0 0 4px rgb(var(--danger-ring));
  }

  /* eazyBackup utility helpers (avoid relying on Tailwind arbitrary values) */
  .eb-bg-page { background-color: rgb(var(--bg-page)); }
  .eb-bg-card { background-color: rgb(var(--bg-card)); }
  .eb-bg-input { background-color: rgb(var(--bg-input)); }
  .eb-text-primary { color: rgb(var(--text-primary)); }
  .eb-text-secondary { color: rgb(var(--text-secondary)); }
</style>


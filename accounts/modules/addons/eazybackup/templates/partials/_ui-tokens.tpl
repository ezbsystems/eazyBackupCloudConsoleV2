<style>
:root {
  /* Surfaces (RGB triples) */
  --bg-page: 30 41 59;     /* #1E293B  base canvas */
  --bg-card: 36 49 71;     /* #243147  raised containers (lighter than page) */
  --bg-input: 24 34 49 / 0.42;    /* #182231  inset controls (darker than page) */

  /* Text */
  --text-primary: 229 231 235;  /* slate-200 */
  --text-secondary: 148 163 184;/* slate-400 */

  /* Accent (brand) */
  --accent: 7 89 133;      /* #075985 sky-800 */

  /* Neutrals */
  --ring-neutral: 255 255 255;

  /* Semantic tones */
  --success: 16 185 129;       /* emerald-500 */
  --success-ring: 52 211 153;  /* emerald-400 */
  --danger: 244 63 94;         /* rose-500 */
  --danger-ring: 251 113 133;  /* rose-400 */

  /* Elevation shadows (soft + wide) */
  --shadow-1: 0 6px 16px rgba(0,0,0,.22), 0 1px 0 rgba(255,255,255,.04) inset;
  --shadow-2: 0 10px 28px rgba(0,0,0,.26), 0 1px 0 rgba(255,255,255,.05) inset;
  --shadow-3: 0 16px 40px rgba(0,0,0,.30), 0 1px 0 rgba(255,255,255,.06) inset;
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


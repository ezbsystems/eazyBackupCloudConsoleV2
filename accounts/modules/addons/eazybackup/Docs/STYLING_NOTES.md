eazyBackup Dark UI Playbook (Client Area)

Use three surfaces, one accent, neutral separators, and consistent spacing/typography. No new background colors—structure comes from rings, borders, and layout.

1) Tokens (drop-in partial)

We have created a partial that can be included on any page (templates/partials/_ui-tokens.tpl). This avoids touching Tailwind config.

---

## Page structure

Every template must use the same outer container, inner container, and content card. The page heading and description always go inside the content card. If the page has a horizontal navbar, add the navbar container as the first child of the content card.

Reference: `accounts/templates/eazyBackup/clientareaproducts.tpl`.

### Outer container (required)

**Every full-page template must start with this wrapper.** Wrap the entire page in:

```html
<div class="min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden">
  <!-- inner container + content -->
</div>
```

### Inner container (required)

**Direct child of the outer container.** Every full-page template must have this as the first child of the outer container:

```html
<div class="container mx-auto max-w-full px-4 pb-8 pt-6">
  <!-- content card (required, see below) -->
</div>
```

When adding or updating a template, always apply outer container, then inner container, then content card. Do not use `p-6` alone, or `max-w-5xl` / `max-w-6xl` / `px-6 py-8` for the inner container—use the exact classes above.

### Content card (required)

**Every full-page template must wrap all page content in this card.** The heading block and main content go inside this div. It is the direct child of the inner container.

```html
<div class="w-full max-w-full min-w-0 overflow-hidden rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
  <!-- optional: navbar strip (first child, only when template has a navbar; see below) -->
  <!-- required: breadcrumb (if navbar) + heading block + main content -->
</div>
```

- **Without a navbar:** content card contains only the heading block and main content.
- **With a navbar:** content card's first child is the navbar container; then the breadcrumb (if used), heading block, and main content.

### Horizontal navbar (optional)

Use when the template has multiple sections or tabs (e.g. Backup Services, Billing Report, e3 Object Storage). When present, the navbar is the **first child** of the content card so it visually sits in the card's top border area.

**Navbar container:**

```html
<div class="-mx-6 -mt-6 mb-6 rounded-t-3xl border-b border-slate-800/80 bg-slate-900/50 px-6 py-3">
  <nav class="flex flex-wrap items-center gap-1" aria-label="…">
    <!-- nav links -->
  </nav>
</div>
```

**Each nav link:**

- **Wrapper:** `<a href="…" class="…">` with the classes below.
- **Base (all links):** `flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200`
- **Active state:** `bg-white/10 text-white ring-1 ring-white/20`
- **Inactive state:** `text-slate-400 hover:text-white hover:bg-white/5`
- **Icon:** Every nav item must include an icon. Use an SVG with `class="w-5 h-5 flex-shrink-0"`.
- **Label:** `<span class="text-sm font-medium">Label</span>` after the icon.

Example (one active, one inactive):

```html
<a href="…" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 bg-white/10 text-white ring-1 ring-white/20">
  <svg class="w-5 h-5 flex-shrink-0" …>…</svg>
  <span class="text-sm font-medium">Active Tab</span>
</a>
<a href="…" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 text-slate-400 hover:text-white hover:bg-white/5">
  <svg class="w-5 h-5 flex-shrink-0" …>…</svg>
  <span class="text-sm font-medium">Other Tab</span>
</a>
```

### Breadcrumb (when the template has a navbar)

When a navbar is present, show a breadcrumb-style trail above the page heading. Place it in the same block as the heading and description.

**Wrapper:** `flex items-center gap-2 mb-1`

- **Ancestor link:** `text-slate-400 hover:text-white text-sm`
- **Separator:** `<span class="text-slate-600">/</span>`
- **Current page (no link):** `<span class="text-white text-sm font-medium">Current Page</span>`

```html
<div class="flex items-center gap-2 mb-1">
  <a href="…" class="text-slate-400 hover:text-white text-sm">Parent</a>
  <span class="text-slate-600">/</span>
  <span class="text-white text-sm font-medium">Current Page</span>
</div>
```

### Page heading (required)

Every template must have a single main heading:

```html
<h2 class="text-2xl font-semibold text-white">Page Title</h2>
```

### Page description (required)

Place a short description directly under the heading:

```html
<p class="text-xs text-slate-400 mt-1">
  Short description of what this page is for.
</p>
```

**Heading + description block layout:** Use a flex row when you need primary actions on the right (e.g. “Export”); otherwise a single column is fine.

```html
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
  <div>
    <div class="flex items-center gap-2 mb-1"><!-- breadcrumb, if navbar --></div>
    <h2 class="text-2xl font-semibold text-white">…</h2>
    <p class="text-xs text-slate-400 mt-1">…</p>
  </div>
  <div class="shrink-0"><!-- optional action buttons --></div>
</div>
```

---

2) Typography + Spacing scale

**Page title (main H2):** `text-2xl font-semibold text-white` — use for the single main heading on each template (see Page structure above).

H2/section title (in-content): text-lg font-medium

Body: default

Label: text-sm text-[rgb(var(--text-secondary))]

Helper: text-xs text-white/50

Vertical rhythm: use space-y-5 within sections, gap-6 for grids

Card radius: rounded-2xl (outer), rounded-xl (inner/controls)

3) Components (copy/paste patterns)
Card (single source of structure)


## Number stepper (increment/decrement)

Use this Alpine/Tailwind stepper for numeric inputs to keep controls consistent across the app. It is fully keyboard accessible, keeps the neutral input shell, and uses our accent for focus.

Markup (wrap your input):

```html
<div class="mt-2 flex rounded-xl overflow-hidden ring-1 ring-white/10 bg-[rgb(var(--bg-input))]" x-data="ebStepper({ min: 0, step: 1 })">
  <button type="button" class="px-3 py-2.5 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[rgb(var(--accent))]" aria-label="Decrease" @click="dec">−</button>
  <input x-ref="input" x-model.number="value" type="number" name="fieldName" min="0" step="1" class="flex-1 text-center bg-transparent text-white/90 placeholder-white/30 focus:outline-none focus:ring-0 px-3.5 py-2.5" />
  <button type="button" class="px-3 py-2.5 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[rgb(var(--accent))]" aria-label="Increase" @click="inc">+</button>
  </div>
```

Behavior (add once on the page—already present in client pages near other JS):

```html
<script>
  // Alpine helper: numeric stepper with min/max/step and safe coercion
  window.ebStepper = function(opts){
    return {
      value: 0,
      min: isFinite(opts && opts.min) ? Number(opts.min) : -Infinity,
      max: isFinite(opts && opts.max) ? Number(opts.max) : Infinity,
      step: isFinite(opts && opts.step) && Number(opts.step) > 0 ? Number(opts.step) : 1,
      dec(){ var v = Number(this.value)||0; v -= this.step; if (isFinite(this.min) && v < this.min) v = this.min; this.value = v; if (this.$refs && this.$refs.input) this.$refs.input.dispatchEvent(new Event('input')); },
      inc(){ var v = Number(this.value)||0; v += this.step; if (isFinite(this.max) && v > this.max) v = this.max; this.value = v; if (this.$refs && this.$refs.input) this.$refs.input.dispatchEvent(new Event('input')); }
    };
  };
</script>
```

Notes:
- Keep the neutral shell: `bg-[rgb(var(--bg-input))] ring-1 ring-white/10` and rounded-xl.
- Buttons sit inside the input shell; they use hover `bg-white/10` and accent focus ring.
- Center text in the input for stepper fields to reinforce the single-value interaction.

Bespoke money stepper (0.01) — recommended pattern

Use this for currency fields with 0.01 increments. It hides the native spinners and keeps +/- inside the neutral shell. Hover highlight is state-driven to avoid bleed.

```html
<style>
  .eb-no-spinner::-webkit-outer-spin-button,
  .eb-no-spinner::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
  .eb-no-spinner { -moz-appearance: textfield; appearance: textfield; }
</style>

<div class="mt-2 flex items-center rounded-xl overflow-hidden ring-1 ring-white/10 bg-[rgb(var(--bg-input))]"
     x-data="ebPriceStepper(0.01)"
     x-init="value = Number(model||0)"
     x-effect="model = Number(value||0)">
  <button type="button" class="shrink-0 w-8 flex items-center justify-center py-2.5 text-white/80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[rgb(var(--accent))]" :class="hovered==='dec' ? 'bg-white/10' : ''" aria-label="Decrease" @mouseenter="hovered='dec'" @mouseleave="hovered=''" @click.stop="dec">−</button>
  <input x-ref="input" x-model.number="value" type="number" step="0.01" class="eb-no-spinner flex-1 min-w-0 text-center bg-transparent text-white/90 placeholder-white/30 focus:outline-none focus:ring-0 px-3.5 py-2.5" />
  <button type="button" class="shrink-0 w-8 flex items-center justify-center py-2.5 text-white/80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[rgb(var(--accent))]" :class="hovered==='inc' ? 'bg-white/10' : ''" aria-label="Increase" @mouseenter="hovered='inc'" @mouseleave="hovered=''" @click.stop="inc">+</button>
</div>
```

JS wrapper (include once)

```html
<script>
  window.ebPriceStepper = window.ebPriceStepper || function(step){
    var base = window.ebStepper({ min: 0, step: (isFinite(step)? Number(step): 0.01) || 0.01 });
    base.hovered = '';
    return base;
  };
</script>
```

Tips:
- Keep buttons `shrink-0` and set input `min-w-0` to prevent overflow.
- Use `@click.stop` so parent handlers don’t interfere.
- Prefer state-driven hover (`hovered`) over Tailwind `hover:` utilities on the container.

Banners (Info / Success / Warning)

Keep the shell consistent; change only hue and icon.

<!-- Neutral info -->
<div class="rounded-xl bg-white/5 ring-1 ring-white/10 px-4 py-3 flex items-center gap-3">
  <svg class="h-5 w-5 text-white/70"></svg>
  <p class="text-sm text-white/80">Message text…</p>
</div>

<!-- Success -->
<div class="rounded-xl bg-emerald-500/10 ring-1 ring-emerald-400/20 px-4 py-3 text-sm text-emerald-200">
  Success message…
</div>

<!-- Warning (soft, not neon) -->
<div class="rounded-xl bg-amber-500/10 ring-1 ring-amber-400/20 px-4 py-3 text-sm text-amber-200">
  Heads-up…
</div>


---

## Slide Drawers & Modals with Transitions

Use these standardized patterns for all slide-out drawers and modal overlays to ensure consistent animations and styling across the application.

### Color Tokens (Drawers & Modals)

| Element | Classes |
|---------|---------|
| Drawer background | `bg-slate-950/95` |
| Drawer border | `border-l border-slate-800` |
| Drawer shadow | `shadow-2xl` |
| Modal overlay (wrapper) | `fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/70 backdrop-blur-xs` |
| Modal container/panel | `rounded-xl border border-slate-700 bg-slate-900 shadow-2xl` |
| Modal background | `bg-slate-900` (use on panel; see Modal container above) |
| Modal border | `border border-slate-700` |
| Modal shadow | `shadow-2xl` |
| Backdrop | `bg-black/50` (drawers) or `bg-gray-950/70 backdrop-blur-xs` (modals) |
| Header border | `border-b border-slate-800` |
| Footer border | `border-t border-slate-800` |
| Section dividers | `border-slate-700` or `border-slate-800` |

### Text Colors

| Element | Classes |
|---------|---------|
| Title | `text-slate-100` or `text-lg font-semibold text-slate-100` |
| Subtitle/helper | `text-xs text-slate-400` |
| Body text | `text-sm text-slate-300` |
| Accent text (links, usernames) | `text-sky-400` |
| Muted text | `text-slate-500` |

### Slide Drawer (Right Panel)

Standard drawer that slides in from the right edge. Use `z-[10060]` for proper stacking above other content.

```html
{* Drawer Container *}
<div x-data="myDrawer()" 
     @my-event.window="openDrawer($event.detail)"
     class="fixed inset-0 z-[10060] pointer-events-none">
  
  {* Backdrop overlay *}
  <div x-show="open"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="transition ease-in duration-150"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0"
       @click="closeDrawer()"
       class="absolute inset-0 bg-black/50 pointer-events-auto"></div>
  
  {* Drawer Panel *}
  <div x-show="open"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="translate-x-full opacity-0"
       x-transition:enter-end="translate-x-0 opacity-100"
       x-transition:leave="transition ease-in duration-200"
       x-transition:leave-start="translate-x-0 opacity-100"
       x-transition:leave-end="translate-x-full opacity-80"
       class="fixed inset-y-0 right-0 z-[10060] w-full sm:max-w-[440px] bg-slate-950/95 border-l border-slate-800 shadow-2xl pointer-events-auto">
    
    <div class="h-full flex flex-col">
      {* Header with staggered fade-in *}
      <div class="px-5 py-4 border-b border-slate-800"
           x-show="open"
           x-transition:enter="transition ease-out duration-300 delay-100"
           x-transition:enter-start="opacity-0 -translate-y-2"
           x-transition:enter-end="opacity-100 translate-y-0"
           x-transition:leave="transition ease-in duration-150"
           x-transition:leave-start="opacity-100"
           x-transition:leave-end="opacity-0">
        <!-- header content -->
      </div>
      
      {* Content with staggered fade-in *}
      <div class="flex-1 overflow-y-auto px-5 py-5">
        <div x-show="open"
             x-transition:enter="transition ease-out duration-300 delay-150"
             x-transition:enter-start="opacity-0 translate-y-3"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
          <!-- main content -->
        </div>
      </div>
      
      {* Footer with staggered fade-in *}
      <div class="px-5 py-4 border-t border-slate-800"
           x-show="open"
           x-transition:enter="transition ease-out duration-300 delay-[350ms]"
           x-transition:enter-start="opacity-0 translate-y-2"
           x-transition:enter-end="opacity-100 translate-y-0"
           x-transition:leave="transition ease-in duration-100"
           x-transition:leave-start="opacity-100"
           x-transition:leave-end="opacity-0">
        <!-- footer buttons -->
      </div>
    </div>
  </div>
</div>
```

### Transition Timing Reference

| Element | Enter Duration | Enter Delay | Leave Duration |
|---------|---------------|-------------|----------------|
| Backdrop | 200ms | 0 | 150ms |
| Drawer panel | 200ms | 0 | 200ms |
| Header | 300ms | 100ms | 150ms |
| Content | 300ms | 150ms | 100ms |
| Form fields (staggered) | 300ms | 200ms, 250ms, 300ms... | 100ms |
| Footer | 300ms | 350ms | 100ms |

### Staggered Content Animation

For form fields or content items that should appear one after another, increment the delay:

```html
{* First field *}
<div x-show="open"
     x-transition:enter="transition ease-out duration-300 delay-200"
     x-transition:enter-start="opacity-0 translate-y-2"
     x-transition:enter-end="opacity-100 translate-y-0">
  <!-- field 1 -->
</div>

{* Second field *}
<div x-show="open"
     x-transition:enter="transition ease-out duration-300 delay-[250ms]"
     x-transition:enter-start="opacity-0 translate-y-2"
     x-transition:enter-end="opacity-100 translate-y-0">
  <!-- field 2 -->
</div>

{* Third field *}
<div x-show="open"
     x-transition:enter="transition ease-out duration-300 delay-300"
     x-transition:enter-start="opacity-0 translate-y-2"
     x-transition:enter-end="opacity-100 translate-y-0">
  <!-- field 3 -->
</div>
```

### Drawer Close Button

Standard close button for drawer headers:

```html
<button type="button"
        class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-slate-800 bg-slate-900/40 text-slate-300 hover:bg-slate-900/70 hover:text-white transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50"
        @click="closeDrawer()"
        aria-label="Close">
  <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
  </svg>
</button>
```

### Modal with Transitions

Centered modal overlay with fade and scale animation. Use the canonical overlay and panel classes below.

**Modal overlay (backdrop + wrapper):** `fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/70 backdrop-blur-xs`

**Modal container/panel:** `rounded-xl border border-slate-700 bg-slate-900 shadow-2xl`

```html
<div x-show="open"
     @click.self="open = false"
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/70 backdrop-blur-xs">
  {* Modal Panel *}
  <div x-show="open"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0 scale-95"
       x-transition:enter-end="opacity-100 scale-100"
       x-transition:leave="transition ease-in duration-150"
       x-transition:leave-start="opacity-100 scale-100"
       x-transition:leave-end="opacity-0 scale-95"
       class="relative w-full max-w-md rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
    
    {* Header *}
    <div class="px-5 py-4 border-b border-slate-700 flex items-center justify-between">
      <h3 class="text-slate-100 text-lg font-semibold">Modal Title</h3>
      <button class="text-slate-400 hover:text-slate-200" @click="open=false">
        <svg class="h-5 w-5" ...></svg>
      </button>
    </div>
    
    {* Content *}
    <div class="px-5 py-5">
      <!-- content -->
    </div>
    
    {* Footer *}
    <div class="px-5 py-4 border-t border-slate-700 flex justify-end gap-3">
      <button class="px-4 py-2.5 rounded-lg border border-slate-800 bg-transparent hover:bg-slate-900/60 text-slate-200 text-sm">Cancel</button>
      <button class="px-5 py-2.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-sm font-medium">Confirm</button>
    </div>
  </div>
</div>
```

### Primary Action Button (Gradient)

For primary actions in drawers/modals, use this gradient button:

```html
<button type="button"
        class="inline-flex items-center justify-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold shadow-sm ring-1 ring-sky-500/40 bg-gradient-to-r from-sky-500 to-sky-400 text-white transition hover:from-sky-600 hover:to-sky-500 disabled:opacity-50 disabled:cursor-not-allowed"
        :disabled="saving"
        @click="submit()">
  <svg x-show="saving" class="animate-spin h-4 w-4" ...></svg>
  <span x-text="saving ? 'Saving…' : 'Save'"></span>
</button>
```

### Secondary/Cancel Button

```html
<button type="button"
        class="px-4 py-2.5 rounded-lg border border-slate-800 bg-transparent hover:bg-slate-900/60 text-slate-200 text-sm transition"
        @click="closeDrawer()">
  Cancel
</button>
```

### Success/Confirmation Callout (inside modals)

```html
<div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4">
  <div class="text-xs uppercase tracking-wide text-emerald-400 mb-2">Success Title</div>
  <div class="text-slate-100 font-mono text-lg">Content here</div>
</div>
```

### Warning Callout (inside modals)

```html
<div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4">
  <div class="flex items-start gap-3">
    <svg class="h-5 w-5 shrink-0 text-amber-400 mt-0.5" ...></svg>
    <div>
      <div class="font-medium text-amber-300">Warning Title</div>
      <p class="mt-1 text-sm text-slate-300">Warning message...</p>
    </div>
  </div>
</div>
```

### Z-Index Stacking Reference

| Element | Z-Index |
|---------|---------|
| Page content | default |
| Dropdowns/popovers | `z-10` |
| Fixed headers | `z-40` |
| Standard modals | `z-50` |
| Slide drawers | `z-[10060]` |
| Overlay modals (above drawers) | `z-[10070]` |
| Toast notifications | `z-[10080]` |

---

## Tables

All data tables in the client area must follow the same structure and styling so they look and behave consistently. Use the pattern from `accounts/templates/eazyBackup/clientareaproducts.tpl` as the reference.

### Table outer container

Wrap the entire table block (toolbar, table, and footer) in a single container:

```html
<div class="w-full max-w-full min-w-0 rounded-2xl border border-slate-800/80 bg-slate-900/70 p-5 shadow-lg">
  <!-- toolbar, table, footer -->
</div>
```

- **Width:** `w-full max-w-full min-w-0` so the table can shrink correctly in flex layouts.
- **Surface:** `rounded-2xl border border-slate-800/80 bg-slate-900/70 p-5 shadow-lg`.

### Toolbar (above the table)

Use a single row with **Alpine.js dropdowns** for options and a **search field** on the right.

**Layout:**

```html
<div class="mb-4 flex flex-col xl:flex-row xl:items-center gap-3">
  <!-- Left: dropdowns (entries, filters, columns) -->
  <!-- Spacer: flex-1 -->
  <!-- Right: search input -->
</div>
```

**Number of entries dropdown (Alpine):**

- Button: `inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80`
- Chevron: `w-4 h-4` with `:class="isOpen ? 'rotate-180' : ''"` for open state.
- Panel: `absolute left-0 mt-2 w-40 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden`
- Option items: `w-full px-4 py-2 text-left text-sm transition` with selected state `bg-slate-800/70 text-white`, default `text-slate-200 hover:bg-slate-800/60`.

**Filter / column visibility dropdowns:** Use the same button and panel styling. Panel width can be `w-56` or `w-64` as needed (e.g. for “Columns” with checkboxes).

**Search field:**

- Position: right side of toolbar (after `flex-1` spacer).
- Classes: `w-full xl:w-80 rounded-full bg-slate-900/70 border border-slate-700 px-4 py-2 text-sm text-slate-200 placeholder:text-slate-500 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80`
- Use a context-specific placeholder (e.g. “Search username, plan, or amount”).

### Table structure

**Scroll wrapper:**

```html
<div class="overflow-x-auto rounded-lg border border-slate-800">
  <table class="min-w-full divide-y divide-slate-800 text-sm">
```

**Header (thead):**

- Row: `bg-slate-900/80 text-slate-300`
- Cell: `px-4 py-3 text-left font-medium`

**Sortable columns:**

- Use a `<button type="button">` inside each sortable `<th>` with `data-col-index="0"` (or the column index).
- Button classes: `inline-flex items-center gap-1 hover:text-white`
- Sort indicator: a `<span class="sort-indicator" data-col="0">` (or same index). Drive the visible arrow (↑ / ↓) via JS (e.g. DataTables `order` API and a single active column). All tables must use sortable columns; only the “Actions” column (if present) is non-sortable.

**Body (tbody):**

- Row divider: `divide-y divide-slate-800` on `<tbody>`.
- Row: `hover:bg-slate-800/50 cursor-default`.
- Cell: `px-4 py-3 text-left`.
- **Cell text:** default body cells `text-slate-300`, primary/lead column (e.g. name) `font-medium text-slate-100`. Font size is `text-sm` from the table.

**Actions column:**

- If the table has an actions column, use the **“Manage” button** style:
  - `px-3 py-1.5 text-xs bg-slate-700 rounded text-white hover:bg-slate-600 cursor-pointer`
- Actions can open an Alpine dropdown; panel: `rounded-md border border-slate-700 bg-slate-800 shadow-lg` (e.g. `w-48 origin-top-right`).

### Table footer (below the table)

**Layout:**

```html
<div class="mt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-slate-400">
  <div><!-- bottom left: entry summary --></div>
  <div class="flex items-center gap-2"><!-- bottom right: pagination --></div>
</div>
```

- **Bottom left:** “Showing X–Y of Z [items]” (or equivalent). Same container as above; no extra classes. Keep text size `text-xs text-slate-400` to match the footer row.
- **Bottom right:** Pagination controls and page info.
  - Prev/Next buttons: `px-3 py-1.5 rounded border border-slate-700 bg-slate-900/70 hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed`
  - Page label between them: `text-slate-300` (e.g. “Page 1 / 5”).

### Checklist for new tables

- Outer container: `w-full max-w-full min-w-0 rounded-2xl border border-slate-800/80 bg-slate-900/70 p-5 shadow-lg`
- Toolbar: Alpine dropdowns for “Show N” and any filters/column toggles; search on the right with the rounded-full input style
- Table: `min-w-full divide-y divide-slate-800 text-sm`; thead `bg-slate-900/80 text-slate-300`; tbody rows `hover:bg-slate-800/50`; cells `text-slate-300` / primary column `text-slate-100`
- Sortable headers: button + `sort-indicator` span; column sort arrows match the example (↑/↓ in the active column only)
- Actions: “Manage” button styling when an actions column is present
- Footer: entry summary bottom left, pagination and page info bottom right, `text-xs text-slate-400` and button styles as above

---

4) Layout patterns
Two-column form
<div class="grid grid-cols-1 md:grid-cols-12 gap-6">
  <div class="md:col-span-6 space-y-5">…</div>
  <div class="md:col-span-6 space-y-5">…</div>
</div>

Stacked content with dividers
<div class="space-y-6">
  <div>Block A</div>
  <div class="border-t border-white/10"></div>
  <div>Block B</div>
</div>

5) Interaction rules (keep it classy)

Hover: neutral surfaces use hover:bg-white/10; primary uses hover:bg-[rgb(var(--accent))]/90.

Focus: every interactive element gets:
focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[rgb(var(--accent))] focus-visible:ring-offset-2 focus-visible:ring-offset-[rgb(var(--bg-card))]

Rings and borders: prefer ring-1 ring-white/10 and border-white/10 over adding new background colors.

Icons: keep small (h-5 w-5) and use opacity text-white/70.

6) Accessibility checklist

Text on dark backgrounds: text-white/90 or text-white/80; placeholders placeholder-white/30.

Ensure focus is always visible (see focus rule above).

Do not rely solely on color for state; pair colored badges with text or icons.

Maintain tap targets ≥ 40px tall (py-2.5 or more on touch-heavy actions).

7) Page migration checklist (repeat for every template)

Include tokens partial (_ui-tokens.tpl) near the top.

Use the canonical page structure: outer container (`min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden`), inner container (`container mx-auto max-w-full px-4 pb-8 pt-6`), then content card (`w-full max-w-full min-w-0 overflow-hidden rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6`) with heading block and main content inside. If the template has a navbar, add the navbar container as the first child of the content card and use the breadcrumb + heading block (see Page structure).

Every template must have a page heading (`<h2 class="text-2xl font-semibold text-white">`) and a page description (`<p class="text-xs text-slate-400 mt-1">`). If there is a navbar, add the breadcrumb above the heading.

Replace multiple nested panels with one card + dividers.

Convert all inputs/buttons to the universal classes.

Standardize banners and status pills.

Ensure in-content headings/labels follow the typography scale (page title uses the page heading style above).

Simplify hovers/focus per interaction rules.

Verify responsive grid: stacks on mobile, 6/6 split on md+.

Keyboard test: tab through everything; accent ring shows.

Quick contrast check: no extra solid backgrounds beyond the three surfaces.

8) Optional: tiny helpers for Smarty includes

Create a couple of lightweight includes to keep templates dry:

templates/partials/card-open.tpl

<section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-hidden">
  <div class="px-6 py-5"><h2 class="text-lg font-medium">{$title}</h2></div>
  <div class="border-t border-white/10"></div>
  <div class="px-6 py-6">


templates/partials/card-close.tpl

  </div>
</section>

## Form fields (canonical classes)

### Text inputs, textareas, and native selects

Use this class for all text inputs, textareas, and (when styled as a single control) native selects so spacing, colors, and focus match across the app:

```
w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-sky-700 transition
```

- **Placeholder:** `placeholder:text-gray-400` for soft contrast.
- **Labels:** Use `text-sm text-slate-400` (or your label style) and wrap each field in a `<label class="block">` (or associate via `for`/`id`).
- Add `mt-2` (or your spacing) on the input when it follows a label.

### Dropdown (select-style) trigger button

For Alpine (or other) dropdowns that replace a `<select>`, style the trigger button like a form field so it matches text inputs:

**Trigger button:**

```
w-full inline-flex items-center justify-between gap-2 px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-sky-700 transition
```

- **Selected value:** Wrap in `<span class="truncate">` so long labels don’t break layout.
- **Chevron icon:** Use a down chevron SVG with `class="w-4 h-4 transition-transform"` and `:class="isOpen ? 'rotate-180' : ''"` when the dropdown is open/closed.

Example (Alpine):

```html
<button type="button"
        @click="isOpen = !isOpen"
        class="w-full inline-flex items-center justify-between gap-2 px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-sky-700 transition">
  <span class="truncate" x-text="selectedLabel"></span>
  <svg class="w-4 h-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
  </svg>
</button>
```

Dropdown panel styling (for the open list): use `rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50` (e.g. `absolute left-0 mt-2 w-full ...`). See Tables section for toolbar dropdown panel styling.


templates/partials/banner.tpl (params: type=neutral|success|warning, text=…).

This keeps page files focused on content while the look stays consistent.
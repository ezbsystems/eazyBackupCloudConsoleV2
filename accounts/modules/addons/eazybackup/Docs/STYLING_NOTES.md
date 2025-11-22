eazyBackup Dark UI Playbook (Client Area)

Use three surfaces, one accent, neutral separators, and consistent spacing/typography. No new background colors—structure comes from rings, borders, and layout.

1) Tokens (drop-in partial)

We have created a partial that can be included on any page (templates/partials/_ui-tokens.tpl). This avoids touching Tailwind config.


Page wrapper (add to each page)
<div class="min-h-screen bg-[rgb(var(--bg-page))] text-[rgb(var(--text-primary))]">
  <div class="mx-auto max-w-none px-6 py-8"> <!-- page container -->
    <!-- your content -->
  </div>
</div>

2) Typography + Spacing scale

H1: text-2xl font-semibold tracking-tight

H2/section title: text-lg font-medium

Body: default

Label: text-sm text-[rgb(var(--text-secondary))]

Helper: text-xs text-white/50

Vertical rhythm: use space-y-5 within sections, gap-6 for grids

Card radius: rounded-2xl (outer), rounded-xl (inner/controls)

3) Components (copy/paste patterns)
Card (single source of structure)

Use one card per major block; separate sections with borders—not more background colors.

<section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-hidden">
  <div class="px-6 py-5">
    <h2 class="text-lg font-medium">Section Title</h2>
  </div>
  <div class="border-t border-white/10"></div>
  <div class="px-6 py-6">
    <!-- content -->
  </div>
</section>

Form field (universal)
<label class="block">
  <span class="text-sm text-[rgb(var(--text-secondary))]">Label</span>
  <input type="text"
    class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30
           ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none
           px-3.5 py-2.5" placeholder="Placeholder">
</label>

Textarea / Select

Same classes as inputs. For select, add appearance-none pr-10 and a tiny chevron absolutely positioned.

Inline field group (input + button)
<div class="mt-2 flex rounded-xl overflow-hidden">
  <input class="flex-1 rounded-l-xl bg-[rgb(var(--bg-input))] text-white/90
               ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))]
               focus:outline-none px-3.5 py-2.5">
  <button class="rounded-r-xl px-3 ring-1 ring-l-0 ring-white/10 bg-white/5 hover:bg-white/10
                 focus:outline-none focus:ring-2 focus:ring-[rgb(var(--accent))]">
    <!-- icon -->
  </button>
</div>

File input (styled label shell)
<div>
  <span class="block text-sm text-[rgb(var(--text-secondary))]">Upload</span>
  <label class="mt-2 flex items-center justify-between rounded-xl bg-[rgb(var(--bg-input))]
                 ring-1 ring-white/10 cursor-pointer hover:ring-[rgb(var(--accent))]/50">
    <span class="px-3.5 py-2.5 text-white/70">Choose file…</span>
    <input type="file" class="hidden">
    <span class="px-3.5 py-2.5 text-xs text-white/40">PNG, JPG, SVG</span>
  </label>
</div>

Buttons
<!-- Primary -->
<button class="rounded-xl px-4 py-2 font-medium text-white
               bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90
               focus-visible:outline-none focus-visible:ring-2
               focus-visible:ring-[rgb(var(--accent))]">
  Save
</button>

<!-- Secondary / Ghost -->
<button class="rounded-xl px-4 py-2 text-white/80 ring-1 ring-white/10
               hover:bg-white/5 focus-visible:outline-none focus-visible:ring-2
               focus-visible:ring-[rgb(var(--accent))]">
  Cancel
</button>

Number stepper (increment/decrement)

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

Tables (compact, dark)
<div class="mt-6 rounded-2xl overflow-hidden ring-1 ring-white/10">
  <table class="w-full text-sm">
    <thead class="bg-white/5 text-white/70">
      <tr class="text-left">
        <th class="px-4 py-3 font-medium">Name</th>
        <th class="px-4 py-3 font-medium">Status</th>
        <th class="px-4 py-3 font-medium text-right">Actions</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-white/10">
      <tr class="hover:bg-white/5">
        <td class="px-4 py-3">Acme</td>
        <td class="px-4 py-3">
          <span class="inline-flex items-center gap-2 rounded-md bg-white/5 ring-1 ring-white/10 px-2 py-1">
            <span class="h-2 w-2 rounded-full bg-emerald-400"></span> Active
          </span>
        </td>
        <td class="px-4 py-3 text-right">
          <button class="rounded-lg px-3 py-1.5 ring-1 ring-white/10 hover:bg-white/10">Manage</button>
        </td>
      </tr>
    </tbody>
  </table>
</div>

Tabs (underline style—no extra backgrounds)
<div class="flex gap-6 border-b border-white/10">
  <button class="py-3 -mb-px border-b-2 border-transparent text-white/70 hover:text-white"
          x-bind:class="active==='overview' ? 'border-white/60 text-white' : ''">Overview</button>
  <button class="py-3 -mb-px border-b-2 border-transparent text-white/70 hover:text-white"
          x-bind:class="active==='settings' ? 'border-white/60 text-white' : ''">Settings</button>
</div>

Modals (overlay + card)
<div class="fixed inset-0 z-50 flex items-center justify-center">
  <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
  <div class="relative w-full max-w-lg rounded-2xl bg-slate-900
              ring-1 ring-white/10 shadow-xl shadow-black/30">
    <div class="px-6 py-5">
      <h3 class="text-lg font-medium">Modal title</h3>
    </div>
    <div class="border-t border-white/10"></div>
    <div class="px-6 py-6">
      <!-- content -->
    </div>
    <div class="border-t border-white/10"></div>
    <div class="px-6 py-4 flex justify-end gap-3">
      <!-- buttons -->
    </div>
  </div>
</div>

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

Wrap with page wrapper + container.

Replace multiple nested panels with one card + dividers.

Convert all inputs/buttons to the universal classes.

Standardize banners and status pills.

Ensure headings/labels follow the typography scale.

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

Form fields (canonical classes)

Use this class for all inputs, selects, and textareas to ensure consistent spacing, colors, and focus rings:

class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5"

Notes:
- Add `placeholder-white/30` to provide soft placeholder contrast.
- For selects, add `appearance-none pr-10` and position a chevron if needed.
- Keep labels with `text-sm text-[rgb(var(--text-secondary))]` and wrap each field in a `<label class="block">`.


templates/partials/banner.tpl (params: type=neutral|success|warning, text=…).

This keeps page files focused on content while the look stays consistent.
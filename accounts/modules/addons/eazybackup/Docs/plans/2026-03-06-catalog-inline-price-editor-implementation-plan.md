# Catalog Inline Price Editor Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace the separate price slide-over in the Partner Hub catalog product drawer with inline-editable price cards that support edit, remove, validation, and toast-based save feedback.

**Architecture:** Keep the existing product drawer and `productPanelFactory` as the single source of truth for product and price state. Remove the secondary `pricePanelFactory` dependency and move price editing controls inline in the product drawer so all actions operate directly on `items[]` without cross-panel coordination.

**Tech Stack:** Smarty templates, Alpine.js, plain browser JavaScript, existing shared `window.showToast()` helper.

---

### Task 1: Inline price editor markup

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/catalog-products.tpl`

**Step 1: Write the failing test**

Manual regression target:
- Open the product drawer.
- Add a price.
- Click `Edit`.
- Expected before fix: nothing useful happens because editing depends on the removed/broken second panel path.

**Step 2: Run test to verify it fails**

Run in browser:
- Open `index.php?m=eazybackup&a=ph-catalog-products`
- Trigger the drawer flow above
- Expected: edit path is broken and remove affordance is not obvious/direct

**Step 3: Write minimal implementation**

- Replace the read-only price summary card with inline form controls for label, amount, interval, billing type, and unit label/unit selector as appropriate for the current product type.
- Add a direct `Remove` button on every price card.
- Remove the separate price panel markup from the template.

**Step 4: Run test to verify it passes**

Run in browser:
- Add a price, edit its fields inline, remove a price, and confirm the drawer state updates immediately.

**Step 5: Commit**

Do not commit unless explicitly requested by the user.

### Task 2: Simplify product drawer state management

**Files:**
- Modify: `accounts/modules/addons/eazybackup/assets/js/catalog-products.js`

**Step 1: Write the failing test**

Manual regression target:
- `openPrice(i)` currently relies on `window.ebPricePanel`.
- Expected before fix: if the second panel is unavailable or removed, edit cannot work.

**Step 2: Run test to verify it fails**

Run in browser console/dev flow:
- Click `Edit` on a price card
- Expected: no inline editing behavior occurs

**Step 3: Write minimal implementation**

- Remove `pricePanelFactory` usage and any `openPrice(i)` dependency on a second panel.
- Add helpers on `productPanelFactory` for inline editing, per-row defaults, validation, remove safety, and toast-backed save/error reporting.
- Keep successful saves in-place with toast feedback instead of reloading immediately.

**Step 4: Run test to verify it passes**

Run in browser:
- Create/edit a product, change product type, add/edit/remove price rows, and save.
- Expected: the drawer remains open and shows success/error toasts.

**Step 5: Commit**

Do not commit unless explicitly requested by the user.

### Task 3: Verification

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/catalog-products.tpl`
- Modify: `accounts/modules/addons/eazybackup/assets/js/catalog-products.js`

**Step 1: Write the failing test**

Verification checklist:
- Product type selection seeds compatible pricing rows
- Inline edits update row summaries/fields
- Remove works for any row
- Save success and failure both use toasts

**Step 2: Run test to verify it fails**

Run:
- Browser/manual interaction for drawer workflow
- `node -e "const fs=require('fs'); new Function(fs.readFileSync('accounts/modules/addons/eazybackup/assets/js/catalog-products.js','utf8')); console.log('catalog-products.js syntax OK');"`

Expected before implementation:
- Browser workflow is incomplete even if syntax parses

**Step 3: Write minimal implementation**

- Finalize any validation and feedback gaps revealed during manual verification.

**Step 4: Run test to verify it passes**

Run:
- Same browser/manual drawer workflow
- Same `node -e ...` syntax command
- Recent-file lint check

Expected:
- Inline drawer flow works end-to-end
- JS syntax command exits `0`
- No new lints in edited files

**Step 5: Commit**

Do not commit unless explicitly requested by the user.

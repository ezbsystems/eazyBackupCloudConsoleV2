# eazyBackup Client Area Theme Migration Plan

## Purpose

This document defines the migration process for moving the WHMCS client area theme from inline, template-by-template Tailwind styling to a reusable design-system-based implementation aligned with the following source documents:

- `modules/addons/eazybackup/Docs/StyleGuides/eazybackup-dark-system.html`
- `modules/addons/eazybackup/Docs/StyleGuides/eazyBackup-StyleGuide.html`

The goal is to make the client area styling:

- reusable across all templates
- easier to maintain
- compatible with both dark and light themes
- consistent for future development

This is a development migration plan, not just a visual design reference.

## Current State Summary

The current `templates/eazyBackup` theme already has some shared styling infrastructure:

- shared Tailwind bundle loaded in `templates/eazyBackup/includes/head.tpl`
- shared UI token partial included in `templates/eazyBackup/header.tpl`
- existing reusable dark UI notes in `modules/addons/eazybackup/Docs/STYLING_NOTES.md`

However, the client area is still mostly implemented with long inline Tailwind class strings inside Smarty templates. This causes several problems:

- visual rules are repeated across many files
- dark theme values are hard-coded in templates
- light theme cannot be introduced cleanly
- new templates can drift from the design system
- updating global styling requires editing many templates

## Migration Goals

By the end of this migration, the client area should have:

1. A single source of truth for design tokens.
2. Shared semantic component classes for core UI patterns.
3. Shared Smarty partials for repeated markup patterns.
4. A standard page shell structure used by all full-page templates.
5. A theme switch mechanism that supports dark and light values.
6. A documented process for converting legacy templates.
7. Guardrails so all future templates follow the same rules.

## Guiding Rules

- Do not rewrite the entire theme in one pass.
- Migrate in small, testable batches.
- Preserve WHMCS template behavior while changing presentation structure.
- Prefer semantic classes and shared partials over repeated utility bundles.
- Keep utility classes for layout where appropriate.
- Move colors, borders, typography, shadows, radius, and interactive states into shared abstractions.
- Avoid introducing a second parallel styling system.

## Target Architecture

The migration should produce three styling layers.

### Layer 1: Design Tokens

Create a shared token source for both themes.

Recommended direction:

- keep tokens in a shared file loaded globally
- define light values on `:root` or `[data-theme="light"]`
- define dark values on `[data-theme="dark"]`

Token categories:

- brand colors
- surfaces
- text colors
- borders
- focus rings
- semantic states
- radius
- shadows
- typography
- spacing

Example direction:

```css
:root,
[data-theme="light"] {
  --surface: #fff5e3;
  --foreground: #2b2b2b;
  --primary: #d55d1d;
}

[data-theme="dark"] {
  --surface: #0f172a;
  --foreground: #f8fafc;
  --primary: #d55d1d;
}
```

### Layer 2: Semantic Component Classes

Build reusable classes in the Tailwind source file for common client-area UI patterns.

Examples:

- `.eb-page`
- `.eb-page-inner`
- `.eb-panel`
- `.eb-subpanel`
- `.eb-page-header`
- `.eb-page-title`
- `.eb-page-description`
- `.eb-sidebar-link`
- `.eb-tab`
- `.eb-btn-primary`
- `.eb-btn-secondary`
- `.eb-btn-danger`
- `.eb-input`
- `.eb-select`
- `.eb-text-muted`
- `.eb-alert-success`
- `.eb-alert-warning`
- `.eb-alert-danger`
- `.eb-table-shell`
- `.eb-badge`

These classes should represent design decisions, not layout-only utilities.

### Layer 3: Shared Smarty Partials

Build partials for repeated markup structures.

Recommended first set:

- `includes/ui/page-shell.tpl`
- `includes/ui/page-header.tpl`
- `includes/ui/card.tpl`
- `includes/ui/button.tpl`
- `includes/ui/alert.tpl`
- `includes/ui/table-toolbar.tpl`
- `includes/ui/form-field.tpl`
- `includes/ui/breadcrumb.tpl`

The rule is simple:

- shared structure belongs in partials
- shared visual rules belong in semantic classes
- page-specific layout can still use utility classes

## Migration Phases

## Phase 0: Preparation and Audit

### Objective

Establish a safe migration baseline before editing templates.

### Tasks

1. Inventory all templates in `templates/eazyBackup`.
2. Identify the highest-traffic client area templates.
3. Group templates by UI pattern rather than by filename.
4. Identify repeated utility bundles already used across templates.
5. Confirm how `tailwind.css` is currently generated and deployed.
6. Confirm whether theme switching will be user-driven, account-driven, or system-default-only.

### Deliverables

- template inventory
- grouped migration list
- list of shared UI patterns
- confirmed Tailwind build/update workflow

### Exit Criteria

- The team knows which templates will be migrated first.
- The team agrees on the theme-switching approach.
- The build path for shared CSS is understood.

## Phase 1: Define the Token System

### Objective

Translate the style guides into implementation-ready design tokens.

### Tasks

1. Extract the color, typography, border, radius, and shadow values from both style guides.
2. Normalize the token naming so the same semantic token exists in both themes.
3. Split tokens into:
   - global brand tokens
   - semantic UI tokens
   - theme-specific surface tokens
4. Update or replace the current `_ui-tokens.tpl` implementation so it supports both themes.
5. Decide where the theme state is stored:
   - body attribute
   - html attribute
   - server-rendered class or data attribute

### Suggested Output

- `--brand-orange`
- `--primary`
- `--primary-hover`
- `--surface-page`
- `--surface-panel`
- `--surface-subpanel`
- `--surface-hover`
- `--surface-input`
- `--text-primary`
- `--text-secondary`
- `--text-muted`
- `--border-default`
- `--border-strong`
- `--ring`
- `--success-*`
- `--warning-*`
- `--danger-*`
- `--info-*`

### Deliverables

- finalized token map
- theme attribute strategy
- global token file loaded by all client area pages

### Exit Criteria

- The same semantic token names work in both dark and light modes.
- No template needs to know a raw hex value for standard UI styling.

## Phase 2: Build the Core Component Layer

### Objective

Create reusable semantic classes in the Tailwind source so templates stop repeating long inline class strings.

### Tasks

1. Expand `templates/eazyBackup/css/tailwind.src.css`.
2. Add component classes for:
   - page wrappers
   - headings
   - cards and panels
   - buttons
   - form fields
   - alerts
   - tabs
   - sidebars
   - tables
   - dropdown menus
   - badges
3. Keep layout utilities outside the component layer where flexibility is needed.
4. Compile and verify the generated CSS output.

### Priority Components

Build these first because they affect the largest number of templates:

1. page shell
2. panel/card system
3. button system
4. input/select/textarea system
5. alert/status system
6. sidebar link states
7. tabs/subnav
8. table shell and toolbar

### Deliverables

- updated Tailwind source
- compiled client area CSS
- first-pass semantic class set

### Exit Criteria

- New templates can be assembled using shared semantic classes without copying long color/border/shadow utility strings.

## Phase 3: Create the Shared Template Partials

### Objective

Stop repeating common page markup across templates.

### Tasks

1. Create a standard page shell partial.
2. Create a standard page header partial.
3. Create reusable alert and button partials if they reduce repeated markup.
4. Create shared breadcrumb and tab-strip partials where structure is repeated enough to justify it.
5. Document required parameters for each partial.

### Example First Partials

#### Page shell

Used for full-page client area templates:

- outer page wrapper
- inner container
- main panel

#### Page header

Used for:

- breadcrumb
- page title
- page description
- right-side actions

#### Card partial

Used for:

- standard card
- raised card
- accent card

### Deliverables

- reusable partial set
- usage examples for each partial

### Exit Criteria

- Repeated top-of-page structure no longer needs to be hand-written in every template.

## Phase 4: Standardize the Global Theme Shell

### Objective

Make the global client area frame align with the dark/light system.

### Scope

- `templates/eazyBackup/header.tpl`
- `templates/eazyBackup/includes/head.tpl`
- shared sidebar
- body theme attribute
- global typography/font loading

### Tasks

1. Move global theme responsibility into the main shell.
2. Attach the active theme to the root element.
3. Ensure the font stack from the style guides is loaded consistently if approved for the client area.
4. Replace hard-coded dark values in the shell with token-driven or semantic classes.
5. Make sure mobile and desktop navigation use the same component rules.

### Deliverables

- token-aware root shell
- theme-aware sidebar and top-level frame

### Exit Criteria

- The shell can switch between dark and light without template-specific hacks.

## Phase 5: Convert Templates in Batches

### Objective

Refactor legacy templates into the new design system in manageable, low-risk groups.

### Conversion Strategy

Convert by UI family, not randomly.

Recommended batch order:

1. dashboard and home views
2. services and service detail views
3. billing and invoices
4. support and tickets
5. account settings and security pages
6. login, registration, password reset
7. knowledgebase and content pages
8. store/order templates

### Per-Template Conversion Checklist

For each template:

1. Replace the outer page shell with the standard shell structure.
2. Replace repeated header markup with the shared page header pattern.
3. Replace raw color and border utilities with semantic classes.
4. Replace raw button bundles with button classes or button partials.
5. Replace form field styling with shared field classes.
6. Replace alert styling with shared alert classes.
7. Replace table wrappers and toolbar controls with shared patterns.
8. Remove one-off style blocks where the new system already covers the same behavior.
9. Verify dark and light rendering.
10. Verify responsive behavior.

### Deliverables

- migrated template batch
- visual verification notes
- list of remaining templates

### Exit Criteria

- Each completed batch fully uses the shared page shell and core component layer.

## Phase 6: Handle Special-Case UI Patterns

### Objective

Standardize complex interactive patterns after the core system is stable.

### Special Cases

- Alpine drawers
- modal overlays
- data tables
- status chips
- multi-step forms
- product cards
- usage graphs or metrics cards
- custom dropdowns
- mobile-only navigation states

### Tasks

1. Convert each special case into a documented pattern.
2. Reuse existing Alpine behavior where possible.
3. Keep JS behavior separate from theme styling.
4. Ensure overlays, focus states, hover states, and z-index values are standardized.

### Deliverables

- pattern library entries for interactive components
- stable class names for advanced components

### Exit Criteria

- Complex UI patterns no longer need custom visual logic per template.

## Phase 7: Add Quality Gates

### Objective

Prevent regression and styling drift after migration starts.

### Tasks

1. Extend the existing contract-test idea to the client area theme.
2. Add a script that checks for required markers in migrated templates.
3. Define banned or discouraged patterns.
4. Create a new-template starter structure.
5. Update internal docs so developers know the required page shell and component usage.

### Recommended Contract Checks

Examples:

- required shared token include
- required page shell class or partial
- required panel wrapper on full-page templates
- no new raw hex colors in templates for standard UI
- no copy-pasted button bundles when semantic button classes exist

### Deliverables

- migration contract test
- template starter
- updated developer documentation

### Exit Criteria

- New templates cannot quietly bypass the design system without review noise.

## Phase 8: Light Theme Activation

### Objective

Enable the light theme once the shared system is stable enough to support it.

### Tasks

1. Confirm all critical components use semantic tokens.
2. Test representative templates in both themes.
3. Fix any component still relying on dark-only utility values.
4. Decide whether light theme launches:
   - internally only
   - per-client preference
   - globally
5. Add any persistence logic required for theme preference.

### Deliverables

- working light theme
- cross-theme verification on priority templates

### Exit Criteria

- The client area renders correctly in both dark and light without per-page overrides.

## Recommended Work Breakdown

This is the suggested implementation sequence for development.

### Sprint 1

- complete audit
- define tokens
- choose theme attribute strategy
- build page shell classes
- build button and input classes

### Sprint 2

- create shared partials
- update header and head integration
- migrate dashboard and services pages

### Sprint 3

- migrate billing, invoices, and support
- standardize alerts, tables, dropdowns, and tabs

### Sprint 4

- migrate account/auth/content pages
- add quality gates
- begin light theme verification

### Sprint 5

- complete light theme polish
- migrate remaining edge-case templates
- finalize docs and starter templates

## Definition of Done

The migration is complete when all of the following are true:

1. All primary client area templates use the shared page shell.
2. Shared tokens control theme colors and states.
3. Core UI components use semantic classes or shared partials.
4. No high-traffic template relies on raw one-off color styling for standard UI.
5. The global shell supports both dark and light themes.
6. New templates have a documented starter pattern.
7. Contract checks exist for core design-system requirements.

## Risks and Controls

### Risk: Large-scale template churn

Control:

- migrate in batches
- avoid changing logic and styling in the same pass when possible

### Risk: Tailwind build uncertainty

Control:

- confirm the build path before component-layer work starts
- do not rely on undocumented manual compilation steps

### Risk: Mixed old/new styling during migration

Control:

- allow temporary coexistence
- require each migrated page to use the full page shell contract

### Risk: Theme switching blocked by hard-coded dark classes

Control:

- prioritize tokenizing surfaces, text, border, and buttons first

### Risk: Future drift

Control:

- contract tests
- starter templates
- updated docs

## Developer Rules for New Work During Migration

Until migration is complete, all new client area work should follow these rules:

1. Use the shared token include.
2. Use the standard page shell for full-page templates.
3. Use semantic button, card, input, and alert classes where available.
4. Do not introduce new raw hex colors into templates for standard UI.
5. Do not create new one-off button systems.
6. If a reusable pattern is missing, add it to the shared layer first when practical.

## Recommended Next Implementation Step

Start with a small but foundational implementation slice:

1. finalize token naming
2. extend `_ui-tokens.tpl` or replace it with a theme-aware token file
3. expand `tailwind.src.css` with page shell, panel, button, and input classes
4. create a reusable page shell partial
5. migrate one representative template such as `clientareaproducts.tpl`

That first slice will validate the architecture before the broader template rollout begins.

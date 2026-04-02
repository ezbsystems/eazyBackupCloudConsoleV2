# eazyBackup Semantic Theme Reference

This document is the single source of truth for the eazyBackup `eb-*` semantic styling system. Use it when migrating legacy templates or building new ones. Every design decision (color, border, shadow, radius, typography, interactive state) should use semantic classes or token variables -- not raw hex values or copy-pasted Tailwind utility bundles.

## Authoritative Files

| Purpose | Path |
|---------|------|
| Design tokens (CSS custom properties) | `modules/addons/eazybackup/templates/partials/_ui-tokens.tpl` |
| Component classes (Tailwind source) | `templates/eazyBackup/css/tailwind.src.css` |
| Compiled CSS | `templates/eazyBackup/css/tailwind.css` |
| Global CSS load | `templates/eazyBackup/includes/head.tpl` |
| Root theme shell | `templates/eazyBackup/header.tpl` |
| Shared UI partials | `templates/eazyBackup/includes/ui/` |

---

## 1. Foundational Rules

### Token Include

Every addon template that renders outside the main WHMCS theme shell must include the token file:

```html
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}
```

Templates rendered inside the main theme shell (`header.tpl` / `footer.tpl`) inherit tokens automatically.

### When to Use Semantic Classes vs Tailwind Utilities

- **Semantic `eb-*` classes** -- for design decisions: colors, borders, shadows, radius, typography, interactive states, depth.
- **Tailwind utilities** -- for layout only: flexbox, grid, spacing, responsive breakpoints, width/height, overflow, positioning.

Example of correct mixing:

```html
<div class="eb-card flex items-start gap-4 p-4">
    <span class="eb-icon-box eb-icon-box--success eb-icon-box--sm">...</span>
    <div class="min-w-0 flex-1">
        <div class="eb-card-title">Title</div>
        <p class="eb-card-subtitle">Subtitle text</p>
    </div>
</div>
```

### Forbidden Patterns

1. **No raw hex colors** in templates for standard UI styling. Use `var(--eb-*)` tokens or `eb-*` classes.
2. **No copy-pasted button utility bundles.** Use `eb-btn eb-btn-primary` etc.
3. **No template-local `<style>` blocks** for standard UI elements already covered by the component layer.
4. **No `bg-slate-*` / `text-slate-*` / `border-slate-*`** for themed surfaces. Use token-driven classes.
5. **No `dark:` Tailwind variants.** The token system handles theming via `[data-theme]`.

### Text Color Access

When you need a text color outside of a semantic class, use CSS custom properties:

```html
<span style="color: var(--eb-text-primary)">Primary text</span>
<span style="color: var(--eb-text-secondary)">Secondary text</span>
<span style="color: var(--eb-text-muted)">Muted text</span>
<span style="color: var(--eb-text-disabled)">Disabled text</span>
```

Or use the utility class `.eb-text-muted` where available.

---

## 2. Page Structure and Shells

### Standard Full-Page Template

Every full-page template uses this wrapper structure:

```html
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="eb-page">
    <div class="eb-page-inner">
        <div class="eb-panel">
            <!-- page header -->
            <div class="eb-page-header">
                <div>
                    <div class="eb-breadcrumb">
                        <a href="..." class="eb-breadcrumb-link">Home</a>
                        <span class="eb-breadcrumb-separator">/</span>
                        <span class="eb-breadcrumb-current">Current Page</span>
                    </div>
                    <h1 class="eb-page-title">Page Title</h1>
                    <p class="eb-page-description">Brief description of this page.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="..." class="eb-btn eb-btn-primary eb-btn-sm">Action</a>
                </div>
            </div>

            <!-- page content -->
            ...
        </div>
    </div>
</div>
```

| Class | Purpose |
|-------|---------|
| `eb-page` | Full-viewport wrapper. Sets page background and text color. |
| `eb-page-inner` | Centered container with horizontal padding. |
| `eb-panel` | Main content panel with border, radius, shadow, and surface color. |
| `eb-panel-nav` | Optional top nav bar inside a panel (negative margin to flush-fit). |

### App Shell (Sidebar + Main)

For application-style pages with a sidebar:

```html
<div class="eb-page">
    <div class="eb-page-inner">
        <div class="eb-panel !p-0">
            <div class="eb-app-shell">
                <!-- sidebar (see Section 5) -->
                <aside class="eb-sidebar">...</aside>

                <main class="eb-app-main">
                    <div class="eb-app-header">
                        <div class="eb-app-header-copy">
                            <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange">...</span>
                            <div>
                                <h1 class="eb-app-header-title">Page Title</h1>
                                <p class="eb-page-description !mt-1">Description</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="..." class="eb-btn eb-btn-primary eb-btn-sm">Action</a>
                        </div>
                    </div>
                    <div class="eb-app-body">
                        <!-- main content -->
                    </div>
                </main>
            </div>
        </div>
    </div>
</div>
```

| Class | Purpose |
|-------|---------|
| `eb-app-shell` | Flex container for sidebar + main. |
| `eb-app-main` | Flexible main content area. |
| `eb-app-header` | Header row with title and actions; has bottom border. |
| `eb-app-header-copy` | Left side of header (icon + title group). |
| `eb-app-header-title` | Header title text (Outfit, 20px, bold). |
| `eb-app-body` | Padded body area below the header. |

### Shell Partial Pattern (Capture + Include)

Addon pages use `{capture}` blocks to pass content into a shell partial:

```smarty
{capture assign=ebE3Actions}
    <a href="..." class="eb-btn eb-btn-primary eb-btn-sm">Action</a>
{/capture}

{capture assign=ebE3Description}
    Page description text.
{/capture}

{capture assign=ebE3Content}
    <!-- main page content -->
{/capture}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='dashboard'
    ebE3Title='Dashboard'
    ebE3Description=$ebE3Description
    ebE3Actions=$ebE3Actions
    ebE3Content=$ebE3Content
}
```

### Auth Shell (Login/Signup Pages)

```html
<div class="eb-auth-shell">
    <div class="eb-auth-bg"></div>
    <div class="eb-auth-wrap">
        <div class="eb-auth-glow"></div>
        <div class="eb-auth-card">
            <h1 class="eb-auth-title">Sign In</h1>
            <p class="eb-auth-description">Enter your credentials to continue.</p>
            <form class="mt-6 space-y-4">
                ...
            </form>
        </div>
    </div>
</div>
```

---

## 3. Typography

All typography classes use the token-driven font families and sizes.

| Class | Font | Size | Weight | Color | Use For |
|-------|------|------|--------|-------|---------|
| `eb-type-hero` | Outfit | 48px | 800 | primary | Landing page hero headings |
| `eb-type-h2` | Outfit | 30px | 700 | primary | Page titles, section headings |
| `eb-type-h3` | Outfit | 17px | 700 | primary | Card titles, sub-section headings |
| `eb-type-h4` | Outfit | 14px | 600 | secondary | Small headings, labels |
| `eb-type-eyebrow` | Body | 10.5px | 700 | muted | Uppercase category labels |
| `eb-type-body` | Body | 14px | normal | secondary | Body text paragraphs |
| `eb-type-caption` | Body | 12px | normal | muted | Help text, timestamps |
| `eb-type-disabled` | Body | 12px | normal | disabled | Disabled/inactive text |
| `eb-type-button` | Outfit | 13.5px | 600 | inherit | Button label text |
| `eb-type-mono` | Courier New | 12px | normal | muted | Code, UUIDs, hashes |
| `eb-type-stat` | Outfit | 40px | 800 | primary | Large stat numbers |

### Page Header Typography

```html
<h1 class="eb-page-title">Page Title</h1>
<p class="eb-page-description">A brief description below the title.</p>
```

- `eb-page-title` -- Outfit, 30px, semibold, primary color, tight letter-spacing.
- `eb-page-description` -- 12px caption size, muted color.

### Section Typography

```html
<div class="eb-section-intro">
    <h2 class="eb-section-title">Section Heading</h2>
    <p class="eb-section-description">Explanation of this section.</p>
</div>
```

---

## 4. Cards, Panels, and Depth

### Panel (Main Page Container)

```html
<div class="eb-panel">
    <div class="eb-panel-nav">
        <!-- optional top navigation row -->
    </div>
    <!-- panel content -->
</div>
```

### Subpanel (Nested Container)

```html
<div class="eb-subpanel">
    <h3 class="eb-type-h3">Sub-section</h3>
    <p class="eb-type-body">Content inside a nested panel.</p>
</div>
```

### Card Variants

```html
<!-- Standard card (border, hover highlight) -->
<div class="eb-card">
    <div class="eb-card-header">
        <div>
            <div class="eb-card-title">Card Title</div>
            <p class="eb-card-subtitle">Optional subtitle</p>
        </div>
        <a href="..." class="eb-btn eb-btn-secondary eb-btn-xs">Action</a>
    </div>
    <div>Card body content</div>
</div>

<!-- Raised card (elevated background + shadow) -->
<div class="eb-card-raised">
    ...
</div>

<!-- Card with divided header (full-width border-bottom header) -->
<div class="eb-card-raised !p-0 overflow-hidden">
    <div class="eb-card-header eb-card-header--divided !mb-0">
        <div>
            <h2 class="eb-card-title">Section Title</h2>
            <p class="eb-card-subtitle">Description</p>
        </div>
    </div>
    <div class="p-6">
        <!-- card body -->
    </div>
</div>

<!-- Glass card (frosted blur effect) -->
<div class="eb-card-glass">...</div>

<!-- Orange-tinted card (brand accent) -->
<div class="eb-card-orange">...</div>
```

### Stat Card (KPI Display)

```html
<div class="eb-stat-card">
    <div class="eb-stat-label">Active Agents</div>
    <div class="eb-stat-value">42</div>
</div>
```

The stat card has an automatic gradient top-border (orange brand gradient).

### Depth Ladder

Apply these to any element to place it at a specific elevation:

| Class | Background | Border | Shadow |
|-------|-----------|--------|--------|
| `eb-depth-surface` | `--eb-bg-surface` | subtle | none |
| `eb-depth-card` | `--eb-bg-card` | default | sm |
| `eb-depth-raised` | `--eb-bg-raised` | default | md |
| `eb-depth-overlay` | `--eb-bg-overlay` | emphasis | lg |

### Key-Value List

```html
<div class="eb-kv-list">
    <div class="eb-kv-row">
        <span class="eb-kv-label">Status</span>
        <span class="eb-kv-value">Active</span>
    </div>
    <div class="eb-kv-row">
        <span class="eb-kv-label">Created</span>
        <span class="eb-kv-value">2026-03-15</span>
    </div>
</div>
```

---

## 5. Sidebar and Navigation

### Sidebar Links

```html
<aside class="eb-sidebar">
    <nav class="space-y-1 px-3 py-3">
        <div class="eb-sidebar-section-label">Main</div>

        <a href="..." class="eb-sidebar-link is-active">
            <svg><!-- 15x15 icon --></svg>
            <span>Dashboard</span>
        </a>

        <a href="..." class="eb-sidebar-link">
            <svg><!-- icon --></svg>
            <span>Users</span>
            <span class="eb-sidebar-badge">12</span>
        </a>

        <a href="..." class="eb-sidebar-link is-disabled">
            <svg><!-- icon --></svg>
            <span>Disabled Link</span>
        </a>

        <div class="eb-sidebar-divider"></div>

        <a href="..." class="eb-sidebar-link eb-sidebar-link-danger">
            <svg><!-- icon --></svg>
            <span>Delete Account</span>
        </a>
    </nav>
</aside>
```

| Class | Purpose |
|-------|---------|
| `eb-sidebar` | Sidebar container (fixed width, chrome background, right border). |
| `eb-sidebar-section-label` | Uppercase tiny label for nav groups. |
| `eb-sidebar-link` | Nav link with icon + text. Add `is-active` for current page. |
| `eb-sidebar-link.is-disabled` | Grayed-out non-clickable link. |
| `eb-sidebar-link-danger` | Red-tinted destructive link. |
| `eb-sidebar-badge` | Count badge auto-pushed to the right. |
| `eb-sidebar-divider` | 1px horizontal separator. |

### Sidebar Sub-navigation

```html
<a href="..." class="eb-sidebar-link is-active">
    <svg><!-- icon --></svg>
    <span>Billing</span>
    <svg class="eb-sidebar-chevron"><!-- chevron-down --></svg>
</a>
<div class="eb-sidebar-subnav">
    <a href="..." class="eb-sidebar-sublink is-active">
        <svg><!-- icon --></svg>
        <span>Invoices</span>
    </a>
    <a href="..." class="eb-sidebar-sublink">
        <svg><!-- icon --></svg>
        <span>Payments</span>
    </a>
</div>
```

### Sidebar User Block

```html
<div class="eb-sidebar-user">
    <span class="eb-avatar">JD</span>
    <div class="eb-sidebar-user-meta">
        <div class="eb-sidebar-user-name">John Doe</div>
        <div class="eb-sidebar-user-role">Administrator</div>
    </div>
</div>
```

### Tabs

```html
<div class="flex flex-wrap gap-2">
    <button class="eb-tab is-active">Overview</button>
    <button class="eb-tab">Settings</button>
    <button class="eb-tab">Logs</button>
</div>
```

### Breadcrumbs

```html
<div class="eb-breadcrumb">
    <a href="..." class="eb-breadcrumb-link">Home</a>
    <span class="eb-breadcrumb-separator">/</span>
    <a href="..." class="eb-breadcrumb-link">Services</a>
    <span class="eb-breadcrumb-separator">/</span>
    <span class="eb-breadcrumb-current">Detail</span>
</div>
```

---

## 6. Buttons

Every button must use the `eb-btn` base class plus a variant and optionally a size modifier.

### Composition Rule

```
eb-btn + [variant] + [size modifier (optional)]
```

### Size Modifiers

| Class | Font Size | Padding |
|-------|----------|---------|
| `eb-btn-xs` | 11.5px | 5px 11px |
| `eb-btn-sm` | 12.5px | 7px 14px |
| `eb-btn-md` | 13.5px | 9px 18px |
| `eb-btn-lg` | 14.5px | 12px 24px |
| *(none)* | Default from variant | Default from variant |

### All Variants

```html
<!-- Primary (solid orange, white text, lifted hover) -->
<button class="eb-btn eb-btn-primary eb-btn-sm">Save Changes</button>

<!-- Secondary (bordered, overlay background) -->
<button class="eb-btn eb-btn-secondary eb-btn-sm">Cancel</button>

<!-- Outline (transparent background, strong border) -->
<button class="eb-btn eb-btn-outline eb-btn-sm">Details</button>

<!-- Ghost (no border, no background, muted text) -->
<button class="eb-btn eb-btn-ghost eb-btn-sm">Skip</button>

<!-- Orange (soft orange background, orange text) -->
<button class="eb-btn eb-btn-orange eb-btn-sm">Upgrade</button>

<!-- Success (green-tinted) -->
<button class="eb-btn eb-btn-success eb-btn-sm">Approve</button>

<!-- Warning (amber-tinted) -->
<button class="eb-btn eb-btn-warning eb-btn-sm">Caution</button>

<!-- Danger (red-tinted, soft background) -->
<button class="eb-btn eb-btn-danger eb-btn-sm">Remove</button>

<!-- Danger Solid (solid red background, white text) -->
<button class="eb-btn eb-btn-danger-solid eb-btn-sm">Delete Permanently</button>

<!-- Info (blue-tinted) -->
<button class="eb-btn eb-btn-info eb-btn-sm">Learn More</button>

<!-- Premium (purple-tinted) -->
<button class="eb-btn eb-btn-premium eb-btn-sm">Unlock Feature</button>

<!-- Upgrade (gradient orange, white text, glow shadow) -->
<button class="eb-btn eb-btn-upgrade eb-btn-sm">Upgrade Plan</button>
```

### Icon Button (Circular)

```html
<button class="eb-btn eb-btn-icon">
    <svg class="w-4 h-4"><!-- icon --></svg>
</button>

<!-- Icon button with size -->
<button class="eb-btn eb-btn-icon eb-btn-sm">
    <svg class="w-3.5 h-3.5"><!-- icon --></svg>
</button>

<!-- Icon button with semantic hover -->
<button class="eb-btn eb-btn-icon is-danger">
    <svg class="w-4 h-4"><!-- trash icon --></svg>
</button>

<button class="eb-btn eb-btn-icon is-success">
    <svg class="w-4 h-4"><!-- check icon --></svg>
</button>
```

### Copy Button

```html
<button class="eb-btn eb-btn-copy">
    <svg class="w-3 h-3"><!-- clipboard icon --></svg>
    Copy
</button>
```

### Disabled State

```html
<button class="eb-btn eb-btn-primary eb-btn-sm" disabled>Disabled</button>
<!-- or -->
<button class="eb-btn eb-btn-primary eb-btn-sm disabled">Disabled</button>
```

---

## 7. Forms and Inputs

### Standard Text Input

```html
<div>
    <label class="eb-field-label">Email Address</label>
    <input type="email" class="eb-input" placeholder="you@example.com" />
    <p class="eb-field-help">We'll never share your email.</p>
</div>
```

### Input with Error

```html
<div>
    <label class="eb-field-label">Password</label>
    <input type="password" class="eb-input is-error" />
    <p class="eb-field-error">
        <svg class="w-3.5 h-3.5"><!-- alert-circle icon --></svg>
        Password must be at least 8 characters.
    </p>
</div>
```

### Input with Success

```html
<input type="text" class="eb-input is-success" value="Valid input" />
```

### Input with Icon

```html
<div class="eb-input-wrap">
    <div class="eb-input-icon">
        <svg class="w-4 h-4"><!-- search icon --></svg>
    </div>
    <input type="text" class="eb-input eb-input-has-icon" placeholder="Search..." />
</div>
```

### Select Dropdown

```html
<label class="eb-field-label">Region</label>
<select class="eb-select">
    <option value="">Select a region...</option>
    <option value="us-east">US East</option>
    <option value="eu-west">EU West</option>
</select>
```

### Textarea

```html
<label class="eb-field-label">Notes</label>
<textarea class="eb-textarea" placeholder="Enter notes..."></textarea>
```

### Disabled Inputs

```html
<input type="text" class="eb-input" disabled value="Read-only value" />
<select class="eb-select" disabled><option>Locked</option></select>
```

### Toggle Switch

```html
<div class="eb-toggle" x-data="{ on: false }" @click="on = !on">
    <div class="eb-toggle-track" :class="on && 'is-on'">
        <div class="eb-toggle-thumb"></div>
    </div>
    <span class="eb-toggle-label">Enable notifications</span>
</div>
```

| Class | Purpose |
|-------|---------|
| `eb-toggle` | Outer flex container (inline-flex, gap). |
| `eb-toggle-track` | The pill-shaped track. Add `is-on` when active. |
| `eb-toggle-thumb` | The sliding circle inside the track. |
| `eb-toggle-label` | Text label next to the toggle. |

### Checkbox

```html
<div class="eb-checkbox" :class="checked && 'is-checked'">
    <svg x-show="checked" class="w-3 h-3 text-white"><!-- check icon --></svg>
</div>
```

### Native Checkbox / Radio

```html
<label class="eb-inline-choice">
    <input type="checkbox" class="eb-check-input" />
    <span>Accept terms and conditions</span>
</label>

<label class="eb-inline-choice">
    <input type="radio" name="plan" class="eb-radio-input" />
    <span>Monthly billing</span>
</label>
```

### Choice Card (Selectable Card)

```html
<div class="eb-choice-card" :class="selected && 'is-selected'" @click="selected = !selected">
    <div class="eb-choice-card-control">
        <input type="radio" class="eb-radio-input" />
    </div>
    <div>
        <div class="eb-choice-card-title">Standard Plan</div>
        <div class="eb-choice-card-description">Best for individuals. Includes 100 GB storage.</div>
    </div>
</div>
```

### Numeric Stepper

```html
<div class="eb-stepper group relative" :class="disabled && 'is-disabled'">
    <input type="number" class="eb-input eb-stepper-input" value="5" min="1" max="100" />
    <div class="eb-stepper-buttons">
        <button type="button" class="eb-stepper-button" @click="increment">
            <svg class="w-3 h-3"><!-- chevron-up --></svg>
        </button>
        <div class="eb-stepper-divider"></div>
        <button type="button" class="eb-stepper-button" @click="decrement">
            <svg class="w-3 h-3"><!-- chevron-down --></svg>
        </button>
    </div>
</div>
```

### File Upload

```html
<!-- Simple file input -->
<input type="file" class="eb-file-input" />

<!-- Styled file field with custom button -->
<label class="eb-file-field" :class="fileName && 'is-filled'">
    <span class="eb-field-label">Upload Logo</span>
    <div class="eb-file-field__control">
        <input type="file" class="eb-file-field__input" @change="fileName = $event.target.files[0]?.name" />
        <div class="eb-file-field__main">
            <span class="eb-file-field__button">Choose file</span>
            <span class="eb-file-field__name" :class="!fileName && 'is-placeholder'"
                  x-text="fileName || 'No file chosen'"></span>
            <span class="eb-file-field__meta" x-show="fileSize" x-text="fileSize"></span>
        </div>
    </div>
</label>
```

---

## 8. Tables

### Complete Table Example

```html
<!-- Optional toolbar above the table -->
<div class="eb-table-toolbar">
    <div class="eb-input-wrap flex-1">
        <div class="eb-input-icon">
            <svg class="w-4 h-4"><!-- search icon --></svg>
        </div>
        <input type="text" class="eb-input eb-input-has-icon" placeholder="Search..." />
    </div>
    <div class="flex items-center gap-2">
        <select class="eb-select w-auto">
            <option>All statuses</option>
        </select>
        <button class="eb-btn eb-btn-primary eb-btn-sm">Add New</button>
    </div>
</div>

<!-- Table wrapper (provides border, radius, horizontal scroll) -->
<div class="eb-table-shell">
    <table class="eb-table">
        <thead>
            <tr>
                <th>
                    <button class="eb-table-sort-button">
                        Name <span class="eb-sort-indicator">↑</span>
                    </button>
                </th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="eb-table-primary">Agent Alpha</td>
                <td><span class="eb-badge eb-badge--success">Active</span></td>
                <td>2026-03-10</td>
                <td>
                    <button class="eb-btn eb-btn-secondary eb-btn-xs">Edit</button>
                    <button class="eb-btn eb-btn-icon eb-btn-sm is-danger">
                        <svg class="w-3.5 h-3.5"><!-- trash icon --></svg>
                    </button>
                </td>
            </tr>
            <tr class="is-selected">
                <td class="eb-table-primary">Agent Beta</td>
                <td><span class="eb-badge eb-badge--warning">Paused</span></td>
                <td>2026-02-28</td>
                <td>
                    <button class="eb-btn eb-btn-secondary eb-btn-xs">Edit</button>
                </td>
            </tr>
            <tr>
                <td class="eb-table-primary">Server Gamma</td>
                <td><span class="eb-badge eb-badge--danger">Offline</span></td>
                <td class="eb-table-mono">a1b2-c3d4-e5f6</td>
                <td>
                    <button class="eb-btn eb-btn-secondary eb-btn-xs">Edit</button>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Pagination below the table -->
<div class="eb-table-pagination">
    <span>Showing 1-10 of 47 results</span>
    <div class="flex items-center gap-2">
        <button class="eb-table-pagination-button" disabled>Previous</button>
        <button class="eb-table-pagination-button">Next</button>
    </div>
</div>
```

### Table Class Reference

| Class | Apply To | Purpose |
|-------|----------|---------|
| `eb-table-shell` | `<div>` wrapper | Rounded border, horizontal scroll. |
| `eb-table` | `<table>` | Full-width collapsed table, 13px text. |
| *(auto)* | `thead tr` | Chrome background, bottom border. |
| *(auto)* | `thead th` | 13px semibold, muted color. |
| *(auto)* | `tbody tr` | Faint bottom border, hover background. |
| *(auto)* | `td` | 11px padding, vertical-align middle. |
| `eb-table-primary` | `<td>` | Primary text color, font-weight 500. |
| `eb-table-mono` | `<td>` | Monospace font, 12px, muted. |
| `eb-table-toolbar` | `<div>` | Flex row for search/filter controls. |
| `eb-table-pagination` | `<div>` | Flex row for page info + buttons. |
| `eb-table-pagination-button` | `<button>` | Bordered pagination button with disabled state. |
| `eb-table-sort-button` | `<button>` inside `<th>` | Inline flex button for sortable columns. |
| `eb-sort-indicator` | `<span>` | Arrow indicator (↑ or ↓), low opacity. |
| `tr.is-selected` | `<tr>` | Soft orange highlight on selected rows. |

### Empty State (No Table Rows)

```html
<div class="eb-app-empty">
    <div class="eb-app-empty-title">No agents found</div>
    <p class="eb-app-empty-copy">Deploy your first agent to get started.</p>
</div>
```

---

## 9. Alerts, Toasts, and Badges

### Alerts

```html
<div class="eb-alert eb-alert--success">
    <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
    </svg>
    <div>
        <div class="eb-alert-title">Success</div>
        <p>Your changes have been saved successfully.</p>
    </div>
</div>

<div class="eb-alert eb-alert--warning">
    <svg class="eb-alert-icon"><!-- exclamation-triangle --></svg>
    <div><p>Your subscription is expiring soon.</p></div>
</div>

<div class="eb-alert eb-alert--danger">
    <svg class="eb-alert-icon"><!-- x-circle --></svg>
    <div><p>Failed to connect to the backup server.</p></div>
</div>

<div class="eb-alert eb-alert--info">
    <svg class="eb-alert-icon"><!-- information-circle --></svg>
    <div><p>A new version of the agent is available.</p></div>
</div>
```

### Toasts

Same semantic color families, but designed for floating notifications with shadow:

```html
<div class="eb-toast eb-toast--success">
    <svg class="w-4 h-4 flex-shrink-0 mt-0.5"><!-- check-circle --></svg>
    <span>Settings saved.</span>
</div>
```

Variants: `eb-toast--success`, `eb-toast--danger`, `eb-toast--warning`, `eb-toast--info`.

### Badges

```html
<!-- Default (neutral) -->
<span class="eb-badge">Default</span>
<span class="eb-badge eb-badge--default">Default</span>

<!-- Semantic colors -->
<span class="eb-badge eb-badge--success">Active</span>
<span class="eb-badge eb-badge--warning">Pending</span>
<span class="eb-badge eb-badge--danger">Failed</span>
<span class="eb-badge eb-badge--info">Processing</span>
<span class="eb-badge eb-badge--premium">Pro</span>
<span class="eb-badge eb-badge--orange">Featured</span>
<span class="eb-badge eb-badge--neutral">Archived</span>

<!-- Solid variants (white text on solid background) -->
<span class="eb-badge eb-badge--solid-success">Online</span>
<span class="eb-badge eb-badge--solid-danger">Critical</span>

<!-- Dot variant (colored dot before text) -->
<span class="eb-badge eb-badge--success eb-badge--dot">Connected</span>

<!-- Custom color via CSS variable -->
<span class="eb-badge eb-badge--custom" style="--eb-badge-accent: #8b5cf6;">Custom</span>
```

### Status Dots

Standalone colored dots for inline status indicators:

```html
<span class="eb-status-dot eb-status-dot--active"></span>   <!-- green with glow -->
<span class="eb-status-dot eb-status-dot--warning"></span>   <!-- amber -->
<span class="eb-status-dot eb-status-dot--error"></span>     <!-- red -->
<span class="eb-status-dot eb-status-dot--inactive"></span>  <!-- gray -->
<span class="eb-status-dot eb-status-dot--pending"></span>   <!-- blue with pulse animation -->
```

### Icon Boxes

Bordered icon containers with semantic color tints:

```html
<span class="eb-icon-box eb-icon-box--orange">
    <svg><!-- 18x18 icon --></svg>
</span>

<!-- Size variants -->
<span class="eb-icon-box eb-icon-box--sm eb-icon-box--success"><!-- 28x28 --></span>
<span class="eb-icon-box eb-icon-box--lg eb-icon-box--info"><!-- 48x48 --></span>
<span class="eb-icon-box eb-icon-box--xl eb-icon-box--danger"><!-- 60x60 --></span>

<!-- Color variants -->
<span class="eb-icon-box eb-icon-box--default">...</span>   <!-- muted -->
<span class="eb-icon-box eb-icon-box--orange">...</span>    <!-- brand orange -->
<span class="eb-icon-box eb-icon-box--success">...</span>   <!-- green -->
<span class="eb-icon-box eb-icon-box--danger">...</span>    <!-- red -->
<span class="eb-icon-box eb-icon-box--info">...</span>      <!-- blue -->
<span class="eb-icon-box eb-icon-box--premium">...</span>   <!-- purple -->
```

### Pills (Filter Chips)

```html
<div class="flex flex-wrap gap-2">
    <button class="eb-pill is-active">
        <span class="eb-pill-dot"></span>
        All
    </button>
    <button class="eb-pill">Running</button>
    <button class="eb-pill">Completed</button>
</div>
```

### Progress Bar

```html
<div class="eb-progress-track">
    <div class="eb-progress-fill" style="width: 65%; background: var(--eb-success-strong);"></div>
</div>
```

---

## 10. Menus and Dropdowns

### Dropdown Menu

```html
<div class="eb-dropdown-menu" x-show="open" x-transition @click.outside="open = false">
    <div class="eb-menu-label">Actions</div>

    <button class="eb-menu-item">
        <svg><!-- edit icon --></svg>
        Edit
    </button>

    <button class="eb-menu-item is-active">
        <svg><!-- star icon --></svg>
        Favorite
        <span class="eb-kbd">⌘F</span>
    </button>

    <div class="eb-menu-divider"></div>

    <button class="eb-menu-item is-danger">
        <svg><!-- trash icon --></svg>
        Delete
    </button>
</div>
```

| Class | Purpose |
|-------|---------|
| `eb-menu` / `eb-dropdown-menu` | Container with border, radius, shadow, raised background. |
| `eb-menu-item` | Flex row with icon + label. Hover and active states built in. |
| `eb-menu-item.is-active` | Orange-tinted active state. |
| `eb-menu-item.is-danger` | Red-tinted destructive action. |
| `eb-menu-label` | Tiny uppercase section header inside the menu. |
| `eb-menu-divider` | 1px horizontal line separator. |
| `eb-kbd` | Keyboard shortcut badge (auto-pushed right via `margin-left: auto`). |

### Menu Trigger Button

```html
<button class="eb-menu-trigger" @click="open = !open">
    <span>Select option...</span>
    <svg class="w-4 h-4"><!-- chevron-down --></svg>
</button>
```

### Menu Options (Selectable List)

```html
<div class="eb-dropdown-menu">
    <button class="eb-menu-option is-active">Option A</button>
    <button class="eb-menu-option">Option B</button>
    <button class="eb-menu-option">Option C</button>
</div>
```

---

## 11. Modals and Drawers

### Modal

```html
<div x-show="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <!-- Backdrop -->
    <div class="eb-modal-backdrop fixed inset-0" @click="modalOpen = false"></div>

    <!-- Modal box -->
    <div class="eb-modal relative z-10">
        <div class="eb-modal-header">
            <div>
                <h2 class="eb-modal-title">Confirm Action</h2>
                <p class="eb-modal-subtitle">This cannot be undone.</p>
            </div>
            <button class="eb-modal-close" @click="modalOpen = false">&times;</button>
        </div>
        <div class="eb-modal-body">
            <p class="eb-type-body">Are you sure you want to delete this item?</p>
        </div>
        <div class="eb-modal-footer">
            <button class="eb-btn eb-btn-secondary eb-btn-sm" @click="modalOpen = false">Cancel</button>
            <button class="eb-btn eb-btn-danger-solid eb-btn-sm" @click="doDelete()">Delete</button>
        </div>
    </div>
</div>
```

For confirmation dialogs, add `eb-modal--confirm` to the modal element for a narrower max-width (400px vs 480px).

### Drawer

```html
<div x-show="drawerOpen" class="fixed inset-0 z-50 flex justify-end">
    <!-- Backdrop -->
    <div class="eb-drawer-backdrop fixed inset-0" @click="drawerOpen = false"></div>

    <!-- Drawer panel -->
    <div class="eb-drawer relative z-10"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full">

        <div class="eb-drawer-header">
            <h2 class="eb-drawer-title">Drawer Title</h2>
            <button class="eb-btn eb-btn-secondary eb-btn-xs" @click="drawerOpen = false">Close</button>
        </div>
        <div class="eb-drawer-body">
            <!-- drawer content -->
        </div>
        <div class="eb-drawer-footer">
            <button class="eb-btn eb-btn-secondary eb-btn-sm">Cancel</button>
            <button class="eb-btn eb-btn-primary eb-btn-sm">Save</button>
        </div>
    </div>
</div>
```

| Width Class | Token | Value |
|-------------|-------|-------|
| *(default)* | `--eb-drawer-width-narrow` | 320px |
| `eb-drawer--narrow` | `--eb-drawer-width-narrow` | 320px |
| `eb-drawer--wide` | `--eb-drawer-width-wide` | 480px |
| `eb-drawer--panel` | `--eb-drawer-width-panel` | min(80rem, 100vw) |

---

## 12. Links and Text Utilities

### Links

```html
<a href="..." class="eb-link">Orange link with underline on hover</a>
<a href="..." class="eb-link-subtle">Primary-colored link, orange on hover</a>
<a href="..." class="eb-link-muted">Muted link, brightens on hover</a>
```

### Metadata Text

```html
<p class="eb-meta-line">
    Created by <span class="eb-meta-strong">John Doe</span>
    on <span class="eb-meta-muted">March 15, 2026</span>
</p>
```

### Detail Value

```html
<label class="eb-field-label">Hostname</label>
<div class="eb-detail-value">server-alpha.eazybackup.ca</div>
```

### Rich Text Block

For rendered HTML content (knowledgebase articles, ticket replies):

```html
<div class="eb-richtext">
    {$content}
</div>
```

The `eb-richtext` class styles nested `<a>`, `<p>`, etc. with proper theme colors.

---

## 13. Specialized Components

### Loading Overlay

```html
<div class="eb-loading-overlay">
    <div class="eb-loading-card">
        <div class="eb-loading-spinner"></div>
        <span class="eb-type-caption">Loading...</span>
    </div>
</div>
```

### Loader Pill (Inline)

```html
<span class="eb-loader-pill">
    <svg class="w-4 h-4 animate-spin"><!-- spinner icon --></svg>
    Loading data...
</span>
```

### Home Dashboard Grid

```html
<div class="eb-home-grid">
    <a href="..." class="eb-home-tile">
        <div class="eb-home-tile-icon">
            <i class="fas fa-server"></i>
        </div>
        <div class="eb-home-tile-stat">24</div>
        <div class="eb-home-tile-title">Active Services</div>
    </a>
    <!-- repeat tiles -->
</div>
```

### Home Dashboard Panels

```html
<div class="eb-home-panels">
    <div class="eb-home-panel">
        <div class="eb-home-panel-header">
            <h3 class="eb-home-panel-title">Recent Invoices</h3>
            <a href="..." class="eb-btn eb-btn-secondary eb-btn-xs">View All</a>
        </div>
        <div class="eb-home-panel-list">
            <div class="eb-home-panel-item">
                <span>Invoice #1234</span>
                <span class="eb-badge eb-badge--success">Paid</span>
            </div>
        </div>
    </div>
</div>
```

### Order Flow Components

| Class | Purpose |
|-------|---------|
| `eb-order-shell` | Centered max-width container for order pages. |
| `eb-order-stage` | Bordered stage card with subtle background. |
| `eb-order-grid` | Two-column flex layout (main + side). |
| `eb-order-main` | Left column. Use `eb-order-main--wide` for full-width. |
| `eb-order-side` | Right sidebar column. |
| `eb-order-side-card` | Card inside the order sidebar. |
| `eb-order-segmented` | Segmented button group (tabs). |
| `eb-order-segmented-btn` | Individual segment button. Add `is-active`. |
| `eb-order-dropdown-btn` | Trigger button for order dropdowns. |
| `eb-order-dropdown-menu` | Dropdown menu for order selectors. |
| `eb-order-dropdown-item` | Item inside the dropdown. |
| `eb-order-field-row` | Grid row for label + input pairs. |
| `eb-order-static-box` | Read-only display box. Add `is-muted` for disabled look. |
| `eb-order-chip` | Removable chip/tag. |
| `eb-order-pill` | Info-colored pill. |
| `eb-order-pane` | Bordered inner pane. |
| `eb-order-progress` | Progress bar track. |
| `eb-order-progress-bar` | Filled gradient bar. |
| `eb-order-table` | Lightweight table for order summaries. |
| `eb-order-modal` | Order-specific modal variant. |

---

## 14. Token Quick Reference

### Surface Tokens

| Token | Dark Value | Use For |
|-------|-----------|---------|
| `--eb-bg-chrome` | `#070d1b` | Sidebar, table headers, darkest surfaces |
| `--eb-bg-base` | `#0b1220` | Page background |
| `--eb-bg-surface` | `#111d33` | Panel background |
| `--eb-bg-card` | `#172035` | Card background |
| `--eb-bg-raised` | `#1e2d45` | Elevated cards, raised sections |
| `--eb-bg-overlay` | `#253450` | Dropdowns, tooltips, overlays |
| `--eb-bg-hover` | `#1a2840` | Hover states on interactive elements |
| `--eb-bg-active` | `#1f3050` | Active/pressed states |
| `--eb-bg-input` | `#131e34` | Form input backgrounds |
| `--eb-bg-input-focus` | `#172035` | Focused input backgrounds |

### Surface Aliases

| Token | Maps To | Use For |
|-------|---------|---------|
| `--eb-surface-page` | `--eb-bg-base` | `eb-page` background |
| `--eb-surface-panel` | `--eb-bg-surface` | `eb-panel` background |
| `--eb-surface-subpanel` | `--eb-bg-card` | `eb-subpanel` background |
| `--eb-surface-elevated` | `--eb-bg-raised` | Raised content |
| `--eb-surface-overlay` | `--eb-bg-overlay` | Menus, modals |
| `--eb-surface-input` | `--eb-bg-input` | Form fields |
| `--eb-surface-nav` | `--eb-bg-chrome` | Navigation chrome |

### Text Tokens

| Token | Dark Value | Use For |
|-------|-----------|---------|
| `--eb-text-primary` | `#eef2f9` | Headings, strong labels, primary content |
| `--eb-text-secondary` | `#adbdd5` | Body text, default paragraph color |
| `--eb-text-muted` | `#6d88a8` | Help text, captions, timestamps |
| `--eb-text-disabled` | `#3d5470` | Disabled elements, inactive controls |
| `--eb-text-inverse` | `#0b1220` | Text on bright backgrounds |

### Border Tokens

| Token | Dark Value | Use For |
|-------|-----------|---------|
| `--eb-border-faint` | `#141f35` | Table row dividers, subtle separators |
| `--eb-border-subtle` | `#1e2d45` | Panel internal dividers, sidebar borders |
| `--eb-border-default` | `#253658` | Card borders, input borders, table shell |
| `--eb-border-emphasis` | `#304878` | Hover state borders, emphasized sections |
| `--eb-border-strong` | `#3d5a8a` | Focus state borders, active element borders |
| `--eb-border-orange` | `rgba(213,93,29,0.3)` | Brand-accent borders |
| `--eb-border-brand` | `rgba(254,80,0,0.4)` | Stronger brand borders |

### Brand Tokens

| Token | Value | Use For |
|-------|-------|---------|
| `--eb-brand-orange` | `#fe5000` | Brand color, icon accents |
| `--eb-primary` | `#d55d1d` | Primary buttons, active indicators |
| `--eb-primary-hover` | `#c04f18` | Primary button hover |
| `--eb-accent` | `#ff7a33` | Link hover, secondary accents |
| `--eb-accent-2` | `#ff924d` | Lighter accent |
| `--eb-primary-soft` | `rgba(213,93,29,0.12)` | Soft orange backgrounds |
| `--eb-primary-border` | `rgba(213,93,29,0.28)` | Soft orange borders |

### Semantic Color Families

Each family has: `-bg`, `-border`, `-text`, `-icon`, `-soft`, `-strong`.

| Family | Prefix | Color Meaning |
|--------|--------|---------------|
| Success | `--eb-success-*` | Green -- confirmations, active states |
| Warning | `--eb-warning-*` | Amber -- caution states, expiring items |
| Danger | `--eb-danger-*` | Red -- errors, destructive actions |
| Info | `--eb-info-*` | Blue -- informational, processing |
| Premium | `--eb-premium-*` | Purple -- premium features, upgrades |

Usage pattern for any family:

```css
background: var(--eb-success-bg);
border-color: var(--eb-success-border);
color: var(--eb-success-text);
```

### Radius Tokens

| Token | Value |
|-------|-------|
| `--eb-radius-sm` | 6px |
| `--eb-radius-md` | 10px |
| `--eb-radius-lg` | 14px |
| `--eb-radius-xl` | 18px |
| `--eb-radius-pill` | 999px |

### Shadow Tokens

| Token | Use For |
|-------|---------|
| `--eb-shadow-sm` | Subtle depth (cards, stat cards) |
| `--eb-shadow-md` | Moderate depth (raised cards, buttons) |
| `--eb-shadow-lg` | Strong depth (panels, sidebars) |
| `--eb-shadow-modal` | Modal overlays |
| `--eb-shadow-panel` | Main page panel (alias for `--eb-shadow-lg`) |

### Typography Size Tokens

| Token | Value | Used By |
|-------|-------|---------|
| `--eb-type-hero-size` | 48px | `eb-type-hero` |
| `--eb-type-h2-size` | 30px | `eb-type-h2`, `eb-page-title` |
| `--eb-type-h3-size` | 17px | `eb-type-h3` |
| `--eb-type-h4-size` | 14px | `eb-type-h4` |
| `--eb-type-eyebrow-size` | 10.5px | `eb-type-eyebrow` |
| `--eb-type-body-size` | 14px | `eb-type-body` |
| `--eb-type-body-lg-size` | 15px | Larger body text |
| `--eb-type-caption-size` | 12px | `eb-type-caption` |
| `--eb-type-button-size` | 13.5px | `eb-type-button`, `eb-btn` |
| `--eb-type-mono-size` | 12px | `eb-type-mono` |
| `--eb-type-stat-size` | 40px | `eb-type-stat` |

### Font Family Tokens

| Token | Value |
|-------|-------|
| `--eb-font-display` | `'Outfit', system-ui, sans-serif` |
| `--eb-font-body` | `'DM Sans', system-ui, sans-serif` |
| `--eb-font-mono` | `'Courier New', monospace` |

### Layout Tokens

| Token | Value |
|-------|-------|
| `--eb-sidebar-width` | 220px |
| `--eb-drawer-width-narrow` | 320px |
| `--eb-drawer-width-wide` | 480px |
| `--eb-drawer-width-panel` | min(80rem, 100vw) |
| `--eb-modal-width-standard` | 480px |
| `--eb-modal-width-confirm` | 400px |
| `--eb-backdrop-modal` | `rgba(5,10,20,0.72)` |
| `--eb-backdrop-drawer` | `rgba(5,10,20,0.5)` |

### When to Use Tokens Directly vs Semantic Classes

- **Use a semantic class** when one exists for the pattern (button, card, alert, table, input, badge, etc.).
- **Use `var(--eb-*)` in `style=""` or in a one-off Tailwind arbitrary value** only when:
  - You need a color on a custom/unique element with no matching semantic class.
  - You are setting a dynamic value (e.g., progress bar width with inline style).
  - Example: `text-[var(--eb-text-muted)]` or `border-[var(--eb-border-default)]`.

---

## 15. Migration Checklist

Use this checklist for every template you convert. Do not skip steps.

### Pre-Migration

- [ ] Read this reference document.
- [ ] Identify all UI patterns in the legacy template (tables, forms, buttons, cards, alerts, modals, etc.).
- [ ] Note any page-specific JavaScript behavior that must be preserved.

### Page Shell

- [ ] Replace any inline page wrapper markup with `eb-page` > `eb-page-inner` > `eb-panel`.
- [ ] If the page has a sidebar, use the `eb-app-shell` structure.
- [ ] If the page is a login/signup form, use `eb-auth-shell` > `eb-auth-card`.
- [ ] Include `_ui-tokens.tpl` if rendering outside the main WHMCS shell.

### Page Header

- [ ] Replace raw heading markup with `eb-page-header`, `eb-page-title`, `eb-page-description`.
- [ ] Add `eb-breadcrumb` if the page has breadcrumb navigation.

### Buttons

- [ ] Replace every `<button>` and `<a>` styled as a button with `eb-btn` + appropriate variant.
- [ ] Check that all buttons have a size modifier (`eb-btn-xs`, `eb-btn-sm`, `eb-btn-md`, or `eb-btn-lg`).
- [ ] Replace submit buttons: `<button type="submit" class="eb-btn eb-btn-primary eb-btn-sm">`.
- [ ] Replace cancel buttons: `<button class="eb-btn eb-btn-secondary eb-btn-sm">`.
- [ ] Replace delete/destructive buttons: use `eb-btn-danger` or `eb-btn-danger-solid`.
- [ ] Replace icon-only buttons with `eb-btn eb-btn-icon`.

### Tables

- [ ] Wrap `<table>` in `<div class="eb-table-shell">`.
- [ ] Add `class="eb-table"` to the `<table>` element.
- [ ] Remove any manual `<thead>` styling -- it is automatic.
- [ ] Remove any manual `<tr>` hover styling -- it is automatic.
- [ ] Use `eb-table-primary` on cells that should be emphasized.
- [ ] Use `eb-table-mono` on cells containing UUIDs, hashes, or code.
- [ ] Add `eb-table-toolbar` wrapper if there are filter/search controls above the table.
- [ ] Add `eb-table-pagination` wrapper if there is pagination below the table.
- [ ] For sortable headers, use `eb-table-sort-button` + `eb-sort-indicator`.
- [ ] For empty states, use `eb-app-empty` with `eb-app-empty-title` and `eb-app-empty-copy`.

### Forms

- [ ] Replace all `<input type="text|email|password|number|url|tel">` with `class="eb-input"`.
- [ ] Replace all `<select>` with `class="eb-select"`.
- [ ] Replace all `<textarea>` with `class="eb-textarea"`.
- [ ] Add `eb-field-label` to all `<label>` elements.
- [ ] Add `eb-field-help` to help/description text below inputs.
- [ ] Add `eb-field-error` to error messages below inputs.
- [ ] For icon-prefixed inputs, use `eb-input-wrap` > `eb-input-icon` + `eb-input eb-input-has-icon`.
- [ ] Replace toggle switches with `eb-toggle` > `eb-toggle-track` > `eb-toggle-thumb`.
- [ ] Replace custom checkboxes with `eb-checkbox` (or `eb-check-input` for native).

### Alerts

- [ ] Replace all alert/notification blocks with `eb-alert` + semantic variant.
- [ ] Always include an `eb-alert-icon` SVG.
- [ ] Use `eb-alert-title` for the alert heading if one exists.

### Badges and Status

- [ ] Replace all status labels/pills with `eb-badge` + appropriate variant.
- [ ] Map status values: active/success = `eb-badge--success`, pending/queued = `eb-badge--warning`, failed/error = `eb-badge--danger`, processing = `eb-badge--info`.
- [ ] Replace inline status dots with `eb-status-dot` + variant.

### Cards

- [ ] Replace custom card markup with `eb-card`, `eb-card-raised`, or `eb-subpanel`.
- [ ] Use `eb-card-header` / `eb-card-title` / `eb-card-subtitle` for card headers.
- [ ] Use `eb-card-header--divided` for cards with a full-width header border.
- [ ] Replace stat/KPI blocks with `eb-stat-card` > `eb-stat-label` + `eb-stat-value`.

### Modals

- [ ] Replace modal backdrops with `eb-modal-backdrop`.
- [ ] Replace modal containers with `eb-modal` (or `eb-modal--confirm`).
- [ ] Use `eb-modal-header` > `eb-modal-title` + `eb-modal-close`.
- [ ] Use `eb-modal-body` and `eb-modal-footer`.

### Drawers

- [ ] Replace drawer backdrops with `eb-drawer-backdrop`.
- [ ] Replace drawer containers with `eb-drawer` (+ width modifier).
- [ ] Use `eb-drawer-header` > `eb-drawer-title`.
- [ ] Use `eb-drawer-body` and `eb-drawer-footer`.

### Menus / Dropdowns

- [ ] Replace dropdown containers with `eb-dropdown-menu`.
- [ ] Replace dropdown items with `eb-menu-item`.
- [ ] Use `eb-menu-label` for section headers, `eb-menu-divider` for separators.

### Navigation

- [ ] Replace sidebar links with `eb-sidebar-link` (add `is-active` for current page).
- [ ] Replace tab controls with `eb-tab` (add `is-active` for selected tab).

### Colors and Typography

- [ ] Remove all raw hex color values for standard UI elements.
- [ ] Remove all `bg-slate-*`, `text-slate-*`, `border-slate-*` for themed surfaces.
- [ ] Remove all `dark:` Tailwind variants.
- [ ] Replace color utilities with token-based values or semantic classes.
- [ ] Replace raw font-size declarations with `eb-type-*` classes where applicable.

### Final Verification

- [ ] Visual check: does the page look consistent with other migrated pages?
- [ ] Responsive check: does the layout work on mobile and desktop?
- [ ] Interactive check: do hover states, focus rings, and transitions work?
- [ ] No `<style>` blocks remain for standard UI patterns.
- [ ] No raw hex colors remain for standard UI elements.

### Common LLM Migration Mistakes to Avoid

1. **Missing `eb-table-shell` wrapper.** The `eb-table` class alone does not provide the border and radius -- you must wrap it in `eb-table-shell`.

2. **Forgetting button size modifiers.** Every `eb-btn` should have a size: `eb-btn-xs`, `eb-btn-sm`, `eb-btn-md`, or `eb-btn-lg`.

3. **Using `eb-btn-primary` without `eb-btn`.** The base class `eb-btn` is always required for font-family, transitions, focus ring, and disabled state.

4. **Skipping toggle switch migration.** Legacy templates often have custom toggle markup. Always use `eb-toggle` > `eb-toggle-track` (with `is-on`) > `eb-toggle-thumb`.

5. **Leaving `text-white` / `text-gray-*` on themed elements.** Replace with `text-[var(--eb-text-primary)]` or the appropriate semantic class.

6. **Not migrating `<select>` elements.** They need `class="eb-select"` just like inputs need `class="eb-input"`.

7. **Missing alert icons.** Every `eb-alert` should have an `eb-alert-icon` SVG.

8. **Using wrong badge variant.** Match status semantics: green for success/active, red for failed/error, amber for warning/pending, blue for info/processing.

9. **Forgetting empty states.** When a table or list can be empty, include an `eb-app-empty` block.

10. **Hard-coding `border-radius` and `box-shadow`.** Use `var(--eb-radius-*)` and `var(--eb-shadow-*)` tokens, or let the semantic class handle it.

11. **Not handling disabled states.** Buttons use `disabled` attribute or `.disabled` class. Inputs use the `disabled` attribute. Both are styled automatically.

12. **Ignoring the `eb-breadcrumb` pattern.** If the legacy template has breadcrumb navigation, migrate it to the `eb-breadcrumb` system.

13. **Leaving `<style>` blocks that duplicate the shared system.** Check if scrollbar styles, card styles, button styles, or table styles are already in `_ui-tokens.tpl` or `tailwind.src.css` before keeping a local `<style>` block.

---

## Appendix: Before/After Examples

### A. Inline Button to Semantic Button

**Before:**
```html
<button class="inline-flex items-center gap-2 rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-orange-500 transition">
    Save Changes
</button>
```

**After:**
```html
<button class="eb-btn eb-btn-primary eb-btn-sm">Save Changes</button>
```

### B. Raw Table to Semantic Table

**Before:**
```html
<div class="overflow-x-auto rounded-lg border border-gray-700">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-800 text-xs uppercase text-gray-400">
                <th class="px-4 py-3 text-left">Name</th>
                <th class="px-4 py-3 text-left">Status</th>
            </tr>
        </thead>
        <tbody>
            <tr class="border-t border-gray-700 hover:bg-gray-800/50">
                <td class="px-4 py-3 text-white font-medium">Agent A</td>
                <td class="px-4 py-3">
                    <span class="rounded-full bg-green-500/10 border border-green-500/30 px-2 py-0.5 text-xs text-green-400">Active</span>
                </td>
            </tr>
        </tbody>
    </table>
</div>
```

**After:**
```html
<div class="eb-table-shell">
    <table class="eb-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="eb-table-primary">Agent A</td>
                <td><span class="eb-badge eb-badge--success">Active</span></td>
            </tr>
        </tbody>
    </table>
</div>
```

### C. Custom Toggle to Semantic Toggle

**Before:**
```html
<div class="flex items-center gap-3" x-data="{ enabled: false }" @click="enabled = !enabled">
    <div class="relative w-10 h-5 rounded-full transition"
         :class="enabled ? 'bg-orange-600' : 'bg-gray-600'">
        <div class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white transition-transform"
             :class="enabled && 'translate-x-5'"></div>
    </div>
    <span class="text-sm text-gray-300">Enable feature</span>
</div>
```

**After:**
```html
<div class="eb-toggle" x-data="{ on: false }" @click="on = !on">
    <div class="eb-toggle-track" :class="on && 'is-on'">
        <div class="eb-toggle-thumb"></div>
    </div>
    <span class="eb-toggle-label">Enable feature</span>
</div>
```

### D. Custom Alert to Semantic Alert

**Before:**
```html
<div class="mb-4 flex items-start gap-3 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-400">
    <svg class="mt-0.5 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
    </svg>
    <div>Connection failed. Check your credentials.</div>
</div>
```

**After:**
```html
<div class="eb-alert eb-alert--danger">
    <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
    </svg>
    <div>Connection failed. Check your credentials.</div>
</div>
```

### E. Form Field Migration

**Before:**
```html
<div class="mb-4">
    <label class="mb-1 block text-sm font-medium text-gray-300">Email</label>
    <input type="email" class="w-full rounded-lg border border-gray-600 bg-gray-800 px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30" placeholder="you@example.com" />
    <p class="mt-1 text-xs text-gray-500">We'll never share your email.</p>
</div>
```

**After:**
```html
<div class="mb-4">
    <label class="eb-field-label">Email</label>
    <input type="email" class="eb-input" placeholder="you@example.com" />
    <p class="eb-field-help">We'll never share your email.</p>
</div>
```

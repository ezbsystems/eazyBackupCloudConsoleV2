# Partner Hub Sidebar Group Spacing — Design

**Goal:** Add a small, consistent vertical gap to grouped Partner Hub submenu lists so active and hover backgrounds do not visually touch the group header above or adjacent submenu items.

**Architecture:** Update the grouped submenu containers in `templates/whitelabel/partials/sidebar_partner_hub.tpl` to apply the same spacing pattern across `Catalog`, `Billing`, `Money`, `Stripe Account`, and `Settings`. The active and hover styles remain unchanged; only the layout spacing between the parent button and child links, and between child links themselves, is adjusted.

**Tech stack:** Smarty templates, Alpine.js, Tailwind CSS, lightweight PHP contract tests for template markers.

---

## Scope

Apply the same spacing treatment to grouped submenu lists for:

- `Catalog`
- `Billing`
- `Money`
- `Stripe Account`
- `Settings`

Out of scope:

- Changing active-state colors, rings, or hover styles
- Changing ungrouped top-level links
- Changing route/controller behavior

## Current Problem

Grouped submenu child rows are visually flush with the section header and each other. When a child row is active, its background and ring appear to touch or overlap the header above. When hovering a sibling row, the hover background also appears to touch the active row above.

## Proposed Behavior

Each grouped submenu list should have:

- a small top gap below the section header button
- a small vertical gap between submenu links
- the same spacing treatment across all grouped sections

This preserves the current visual hierarchy while making active and hover states read as separate rows.

## Implementation Notes

### 1. Submenu container spacing

For each grouped submenu content wrapper, add:

- a small top margin such as `mt-2`
- a small vertical stacking gap such as `space-y-1`

These classes should live on the submenu container so all child links inherit the spacing consistently.

### 2. Keep child link styles unchanged

Do not change the existing active-state or hover-state classes on child links. The bug is spacing, not color or ring behavior.

### 3. Preserve current indentation

Keep the existing left-indent and border styling:

- `ml-4`
- `pl-4`
- `border-l border-slate-700/50`

Only add the new separation above and between items.

## Testing

Add a focused contract test covering:

- grouped submenu wrappers include the shared `mt-2 space-y-1` spacing treatment
- the change is applied to `Catalog`, `Billing`, `Money`, `Stripe Account`, and `Settings`

Manual verification:

- open each grouped section and confirm the first child row no longer touches the section header
- hover a non-active child row while another child row is active and confirm the backgrounds do not touch

---

*Implementation plan: see `2026-03-16-partner-hub-sidebar-group-spacing-implementation-plan.md`.*

# Catalog Products Header Design

**Date:** 2026-03-16

**Goal:** Bring `catalog-products.tpl` fully in line with the newer Partner Hub page-shell pattern used by the updated billing, catalog, and settings templates.

## Approved Direction

Use the existing page title, subtitle, and `New Product` button, but normalize the surrounding layout to match the newer header/content shell exactly.

## Scope

- Keep the existing top header row in `catalog-products.tpl`
- Keep the existing heading text and `New Product` action
- Keep the existing content sections, product cards, Stripe-connected products area, and product slide-over behavior
- Remove the extra top margin from the first content section so content starts directly inside the page `p-6` area, matching the newer Partner Hub templates

## Rationale

The template already has the correct header content, so a full structural rewrite would be unnecessary. The visual inconsistency comes from the first section still carrying an additional `mt-6`, which creates spacing that other updated pages no longer use.

## Expected Result

- Header row sits at the top of the main content shell
- Content begins immediately below the shared `p-6` wrapper
- The page visually matches the newer Partner Hub templates without changing product functionality

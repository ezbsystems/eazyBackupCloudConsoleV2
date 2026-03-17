# Billing Payment New Header Design

## Summary

Align `templates/whitelabel/billing-payment-new.tpl` with the updated Partner Hub page shell used by `templates/whitelabel/tenants.tpl`.

## UX Direction

- Add a full-width page header directly inside the Partner Hub `<main>` region.
- Keep the page title and helper copy in the header area.
- Move the page-level navigation action `Back to Payments` into the header action slot.
- Remove the duplicated heading block from inside the payment form card.
- Keep `Cancel` and `Create and Pay` inside the form footer because they act on the form itself.

## Structure

- Reuse the tenants-style header row with a bottom border and horizontal padding.
- Place the payment form in the content area beneath the header.
- Preserve the existing field layout, Stripe card mount point, and JavaScript payment flow.

## Verification

- Add a lightweight contract test for the standalone payment page.
- Confirm the template contains the Partner Hub header structure and header-level `Back to Payments` action.
- Confirm the duplicate in-form page heading is removed.

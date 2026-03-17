# Billing Payment Modal Design

## Summary

Replace the standalone Partner Hub "New One-time Payment" page with an inline modal launched from `templates/whitelabel/billing-payments.tpl`. The modal keeps the user on the Payments list, improves the form structure, and converts tenant selection into an Alpine-powered searchable picker.

## UX Direction

- Keep the existing Payments page shell and table intact.
- Replace navigation links to `ph-billing-payment-new` with a modal trigger.
- Move the payment form into `templates/whitelabel/partials/billing-payment-modal.tpl` so the modal UI is isolated and reusable.
- Organize the form into clearer sections: customer, charge details, payment method, and payment summary.
- Support both saved cards and a new card flow inside the modal.

## Data Flow

- `eb_ph_billing_payments()` must provide tenant data to the Payments template so the modal can render the searchable picker without a page reload.
- The modal requests saved payment methods for the selected tenant through a lightweight JSON endpoint.
- The backend uses the MSP's connected Stripe account and the tenant's Stripe customer to list saved card payment methods.
- Payment creation continues to use `ph-billing-create-payment`, adding `payment_method_id` when the user chooses a saved card.

## Validation

- Require tenant selection before payment submission.
- Require a positive amount.
- When saved cards exist, allow choosing either a saved card or a new card.
- Keep Stripe Elements mounted only for the new-card path.

## Verification

- Contract test confirms:
  - the Payments page includes the inline modal partial,
  - the modal trigger no longer redirects to the standalone page,
  - the billing controller exposes tenant data and a saved-card lookup action,
  - the modal partial includes searchable tenant and payment-method sections.

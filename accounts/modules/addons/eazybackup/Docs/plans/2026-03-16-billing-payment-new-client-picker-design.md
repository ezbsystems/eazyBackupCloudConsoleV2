# Billing Payment New Client Picker Design

## Summary

Replace the native Client `<select>` in `templates/whitelabel/billing-payment-new.tpl` with an Alpine-powered searchable menu.

## UX Direction

- Keep the current standalone payment page and page header intact.
- Replace the Client select field with a compact dropdown trigger that opens a searchable option list.
- Match tenant filtering behavior from `templates/whitelabel/partials/billing-payment-modal.tpl` by searching both tenant name and contact email.
- Show the selected tenant name and contact email in the trigger area after selection.
- Show an empty state when no tenants match the current search.

## Data Flow

- Reuse the existing `$tenants` template data and expose it to Alpine with JSON encoding.
- Keep a hidden `#np-tenant` input synced to the selected tenant ID so the existing submit script can keep reading the same DOM field.
- Leave the rest of the payment form and Stripe flow unchanged.

## Verification

- Add a contract test for the standalone payment page.
- Confirm the native `<select id="np-tenant">` is removed.
- Confirm the page contains the searchable picker container, search placeholder, and contact-email filter marker.
- Confirm the hidden `#np-tenant` input remains present for the current submit workflow.

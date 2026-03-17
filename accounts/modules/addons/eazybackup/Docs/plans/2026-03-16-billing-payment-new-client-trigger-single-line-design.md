# Billing Payment New Client Trigger Single-Line Design

## Summary

Update the closed Client picker trigger in `templates/whitelabel/billing-payment-new.tpl` so its text stays on one line.

## UX Direction

- Keep the searchable Alpine client picker and dropdown behavior unchanged.
- Collapse the closed trigger to a single-line label.
- Remove the secondary helper line from the closed state.
- Use truncation and no-wrap styling so long tenant names do not wrap.
- Keep search guidance inside the open menu placeholder.

## Verification

- Confirm the closed trigger contains a single-line text container.
- Confirm the old helper line is removed from the closed trigger.
- Confirm the client picker contract test still passes after the update.

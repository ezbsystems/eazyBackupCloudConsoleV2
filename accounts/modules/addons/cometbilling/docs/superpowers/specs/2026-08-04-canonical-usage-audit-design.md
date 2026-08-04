# Canonical Usage and Audit Confirmation Design

## Problem

The first occurrence-aware portal pull inserted 193,839 canonical billing-history rows while retaining 192,418 legacy rows. Legacy and canonical rows have different fingerprints because the legacy records did not populate `packs_used_raw`. Financial readers currently scan both sets, doubling many charges.

Historical Reconcile also uses a report-wide source-coverage flag to grade every finding. Production has Active Services snapshots beginning on August 4, so a 90-day report downgrades an August 4 finding to `probable` even when that finding has exact identity, high-confidence lifecycle and cadence evidence, a recorded pack debit, and no reversal.

## Design

### Canonical usage scope

Introduce one shared query scope for `cb_credit_usage`:

- If no successful portal pull manifest exists, retain legacy behavior and read all rows.
- If a portal pull manifest exists, read only rows where `is_present_in_latest_pull = 1`.
- Preserve `occurrence_number`; multiple identical occurrences in the current Comet response remain separate auditable ledger entries.
- Keep stale rows in storage for provenance. Do not delete or merge them.

Apply the scope to Historical Reconcile, reversal searches, cadence inference, ledger rebuilding and summaries, portal-pull summaries, standard reconciliation, usage views, and dashboard counts.

### Finding-specific confirmation

Confirmation will depend on evidence attached to the individual finding:

- positive amount and pack-debit evidence;
- exact, unique-prefix, or account-disambiguated identity;
- medium/high-confidence lifecycle stop;
- medium/high-confidence billing cadence from a nearby portal snapshot or observed history;
- charge after expected billing end;
- no offsetting reversal.

The report-wide coverage panel remains visible as an audit warning. It does not downgrade a finding whose own evidence is complete.

### Ledger repair

After deployment, rebuild the production local ledger over its normal purchase/usage coverage. The rebuild reads only canonical usage rows, replacing totals previously calculated from both legacy and current records.

## Verification

- Regression test: when a manifest exists, stale usage rows are excluded and separate current occurrences remain.
- Regression test: a finding with complete row-level evidence is confirmed even when report-wide coverage is incomplete.
- Existing test suite and PHP syntax checks pass.
- Production sample verification:
  - GrandViewDental and IMQBackup appear once.
  - Their August 4 findings are `confirmed`.
  - `mmf_admin` retains two current occurrences because Comet returned two.
- Production ledger rebuild completes and aggregate usage equals canonical usage totals.

## Deployment and rollback

Commit and push the tested change, update production from the committed source, run syntax checks, then rebuild the ledger. No usage rows are deleted. Rollback consists of reverting the code; retained stale rows make the change storage-safe.

# MS365 Backup — Phase 3 PRD (summary)

> **Superseded for planning purposes by [PRODUCT_ROADMAP.md](PRODUCT_ROADMAP.md).**  
> This file retains a short summary of Phase 3 “product maturity” epics; use the roadmap for full phases 0–6, goals, and feature checklist.

## Product goal

Customer-facing Microsoft 365 backup in **e3 Cloud Backup**, backups in **dedicated e3 RGW buckets**, hundreds–thousands of WHMCS clients. **No Comet.**

## Phase 3 epics (customer maturity)

| Epic | Description |
|------|-------------|
| P3-1 | Storage abstraction (local + cloud adapter) |
| P3-2 | Multi-tenant `ms365_tenant_records` |
| P3-3 | Entra consent + bucket bootstrap — [CUSTOMER_ONBOARDING.md](CUSTOMER_ONBOARDING.md) |
| P3-4 | e3 UI + APIs (`view=ms365`, `ms365_*` APIs) |
| P3-5 | Job queue hardening |
| P3-6 | Ops: search, retry, health |
| P3-7 | Restore baseline — full restore in [PRODUCT_ROADMAP.md](PRODUCT_ROADMAP.md) Phase 5 |
| P4b | Unified e3 M365 UX — [MS365_E3_UI_SPEC.md](MS365_E3_UI_SPEC.md) (before Phase 5 restore depth) |

**Status:** See [PROGRESS.md](PROGRESS.md).

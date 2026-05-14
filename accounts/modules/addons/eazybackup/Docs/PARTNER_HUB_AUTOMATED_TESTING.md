# Partner Hub Automated Testing

This document is the canonical reference for the eazyBackup addon's automated test suite. It covers what the test layers are, how to run them, where to add new tests, the seams the suite uses to mock external systems (Stripe, SMTP), and how everything wires into the existing release gate.

The suite is the safety net for the Partner Hub MSP billing system, the white-label tenant pipeline, and the canonical Stripe Connect integration. Every PR that touches `lib/PartnerHub/`, `pages/partnerhub/`, or `pages/whitelabel/` should keep `composer test:all` green.

---

## TL;DR

```bash
cd accounts/modules/addons/eazybackup
composer install              # one-time: installs PHPUnit + Mockery
composer test:all             # runs everything: gate -> unit -> integration
```

Expected output (warm cache, ~12s total):

```
MSP_BILLING_RELEASE_GATE_PASS
PHPUnit unit suite: OK (123 tests, 327 assertions)
PHPUnit integration suite: OK (96 tests, 305 assertions)
```

---

## Test layers

The addon ships with three layered test surfaces:

| Layer | Lives in | What it protects |
|---|---|---|
| **Static contract tests** | `bin/dev/*_contract_test.php` | Grep-for-marker scripts that fail when critical routes, schema declarations, CSRF wiring, or test seams disappear. Cheap to run, high ROI for "did someone delete the line that…" questions. Aggregated by `bin/dev/msp_billing_release_gate.php`. |
| **PHPUnit unit suite** | `tests/Unit/` | Pure-logic + service-layer tests. Cover `lib/PartnerHub/` math (fee cascade, metered usage), parameter shaping for all Stripe API calls, settings JSON round-trip, SMTP password encryption idempotency, MailService rendering & sanitisation, TenantCustomerService idempotency. |
| **PHPUnit integration suite** | `tests/Integration/` | Stripe webhook coverage. Drives the dispatcher with fixture events and asserts DB writes, idempotency, signature verification, the orchestration glue, and email dispatch. |

All three are runnable independently and aggregated by `composer test:all`.

---

## Setup

### Install dev dependencies

```bash
cd accounts/modules/addons/eazybackup
composer install
```

`composer install` pulls dev deps (PHPUnit 10, Mockery) by default. Pass `--no-dev` only on production hosts.

### Production database guard (recommended)

If the host running tests has any access to a production database, set:

```bash
export EB_TEST_PROD_GUARD_URL="https://accounts.eazybackup.ca"
```

When this is set, the test bootstrap reads `tblconfiguration.SystemURL` and aborts with exit code 2 if it matches the configured URL. This is the last line of defence against accidentally pointing tests at production credentials.

### Test environment flags

The test bootstrap automatically forces the following addon settings into a test-safe state for the duration of the test run (no DB writes — just an in-memory override the helpers honour):

- `whitelabel_dev_mode=1`
- `whitelabel_dev_skip_dns=1`
- `whitelabel_dev_skip_nginx=1`
- `whitelabel_dev_skip_cert=1`

This guarantees the white-label tenant pipeline never tries to write nginx vhosts, hit Route 53, or run certbot from inside a unit test.

---

## Running the suites

```bash
cd accounts/modules/addons/eazybackup

composer test             # PHPUnit unit suite only (fast, ~3-5s warm)
composer test:integration # PHPUnit integration suite only (Stripe webhook fixtures, ~3-4s)
composer test:full        # Both PHPUnit suites in one run
composer test:contract    # Static contract tests + the release gate (also runs PHPUnit unit)
composer test:all         # Contract gate -> unit -> integration, in sequence
composer test:coverage    # PHPUnit unit suite with HTML coverage report at tests/_coverage/
```

The release gate (`bin/dev/msp_billing_release_gate.php`) invokes the PHPUnit unit suite at the end, so a single `composer test:contract` validates everything the gate considers blocking. The integration suite is exercised separately by `composer test:all` (it relies on dev-mode addon settings and is designed for richer behavioural coverage).

Set `EB_GATE_SKIP_PHPUNIT=1` to skip the inner PHPUnit invocation. This is used internally to avoid recursion when the gate is itself called from inside a PHPUnit test (see `tests/Unit/MspBillingReleaseGateRunsCleanTest.php`).

---

## Test inventory (current state)

| File | Tests | Surface |
|---|---|---|
| **Unit suite** (`tests/Unit/`) | | |
| `MeteredUsageBillableComputationTest.php` | 9 | `computeBillableMeteredUsage()` math (bill_all vs cap_at_default, edge cases) |
| `SettingsServiceTaxJsonTest.php` | 4 | Tax settings JSON round-trip + deep-merge behaviour |
| `SettingsServiceEmailSmtpEncryptionTest.php` | 4 | SMTP password encrypt/decrypt idempotency |
| `SettingsServiceTaxRegistrationsCrudTest.php` | 7 | Registrations CRUD + tenancy + audit-trail shape |
| `MspBillingReleaseGateRunsCleanTest.php` | 1 | Smoke check that `msp_billing_release_gate.php` still passes |
| `StripeServiceFeeCascadeTest.php` | 8 | Application fee cascade (override → plan price → MSP → module) |
| `StripeServiceCustomerEnsureTest.php` | 5 | `ensureStripeCustomerFor` idempotency + persistence + Stripe-Account header |
| `StripeServiceParameterShapingTest.php` | 14 | Parameter shaping for createSubscription, updateCustomer, pause/resume, refund, usage record, etc. |
| `CatalogServiceParameterShapingTest.php` | 14 | Parameter shaping for product/price CRUD, multi-item subscriptions, idempotency keys |
| `TenantCustomerServiceIdempotencyTest.php` | 8 | `ensureCustomerForTenant` sequential idempotency + error branches |
| `MailServiceRenderingTest.php` | 13 | Token replacement + HTML sanitisation + template body wrapping |
| `UsageHelpersTest.php` | 13 | Idempotency-key stability, period-bound clamping, metered-item picker, usage timestamp clamp |
| `PlanAssignmentModeTest.php` | 6 | `eb_ph_plan_assignment_mode` (E3 vs comet_user classification, mixed metrics, normalisation) |
| `PublicSignupValidationTest.php` | 9 | `eb_signup_validate_basic_input` for the public signup form (every error code) |
| `PublicSignupAbuseControlsTest.php` | 9 | `eb_signup_check_domain_filters` (allow/deny lists, casing, whitespace, both-list precedence) |
| **Integration suite** (`tests/Integration/`) | | |
| `StripeWebhookSignatureTest.php` | 6 | HMAC verification: valid, invalid, tampered, replayed, missing, garbage |
| `StripeWebhookIdempotencyTest.php` | 4 | `eb_stripe_events` stamping + duplicate detection |
| `StripeWebhookEntryPointTest.php` | 6 | `eb_ph_stripe_webhook_handle()` orchestration glue |
| `StripeWebhookAccountTest.php` | 5 | `account.updated` + `account.application.deauthorized` |
| `StripeWebhookCapabilityTest.php` | 4 | `capability.updated` (with injected StripeService) |
| `StripeWebhookSubscriptionTest.php` | 6 | `customer.subscription.*` + plan_instance sync + trial_will_end notice lifecycle |
| `StripeWebhookInvoiceChargeTest.php` | 7 | `invoice.*`, `charge.*`, `payment_intent.*` cache writes |
| `StripeWebhookPayoutDisputeTest.php` | 6 | `payout.*` + `charge.dispute.*` + dispute notice lifecycle |
| `StripeWebhookCustomerTest.php` | 5 | `customer.deleted` + `payment_method.attached` |
| `StripeWebhookEmailDispatchTest.php` | 9 | Email dispatches via `MailService` transport seam |
| `UsagePushOrchestrationTest.php` | 9 | `eb_ph_usage_push_for_tenant` end-to-end (allowance + Stripe push + ledger + retry) |
| `PlanAssignmentTest.php` | 11 | `eb_ph_plan_assign_for_msp` (scope, plan-active, ownership, duplicate guard, e3_storage path) |
| `PlanSubscriptionCancelTest.php` | 5 | `eb_ph_plan_subscription_cancel_for_msp` (scope, already-canceled, Stripe failure recovery) |
| `PublicSignupRateLimitTest.php` | 13 | `eb_signup_check_rate_limits` + `eb_signup_existing_event_state` (per-tenant scope, 1-hour window, idempotency states) |

**Totals:** unit suite 123 tests / 327 assertions, integration suite 96 tests / 305 assertions, **219 PHPUnit tests, 632 assertions** total — plus ~30 static contract tests aggregated by the release gate.

---

## Where to add new tests

| You want to test | Put it in | Extend |
|---|---|---|
| A pure function (no DB, no HTTP) | `tests/Unit/*Test.php` | `EazyBackup\Tests\Support\UnitTestCase` |
| A service that touches the DB | `tests/Unit/*Test.php` | `EazyBackup\Tests\Support\DatabaseTestCase` (auto rolls back) |
| Parameter shaping for a Stripe API call | `tests/Unit/*Test.php` | `UnitTestCase`; instantiate `EazyBackup\Tests\Support\TestableStripeService` or `TestableCatalogService` |
| A Stripe webhook event handler | `tests/Integration/*Test.php` | `DatabaseTestCase`; drive `eb_ph_webhook_dispatch_event($event)` with `EazyBackup\Tests\Support\StripeWebhookFixture::load(...)` |
| Email dispatch from a webhook / business event | `tests/Integration/*Test.php` | `DatabaseTestCase`; install a transport spy via `MailService::setTransport(...)` in `setUp()`; clear in `tearDown()` |
| Multi-step integration (DB + Stripe sandbox) | `tests/Integration/*Test.php` | `DatabaseTestCase` |

`DatabaseTestCase` wraps every test in a transaction it always rolls back, so it is safe to point at the dev WHMCS database. A tripwire in `tearDown()` fails loudly if any test commits the outer transaction.

---

## Testing patterns

### Pure functions (no DB)

```php
<?php
namespace EazyBackup\Tests\Unit;

use EazyBackup\Tests\Support\UnitTestCase;
use function PartnerHub\computeBillableMeteredUsage;

final class MyMathTest extends UnitTestCase
{
    public function test_overage_subtracts_default_qty(): void
    {
        self::assertSame(50, computeBillableMeteredUsage(150, 100, 'bill_all'));
    }
}
```

### DB-touching tests with transactional rollback

```php
<?php
namespace EazyBackup\Tests\Unit;

use EazyBackup\Tests\Support\DatabaseTestCase;
use EazyBackup\Tests\Support\Seeder;
use PartnerHub\SettingsService;

final class MyDbTest extends DatabaseTestCase
{
    public function test_save_then_get_round_trips(): void
    {
        $mspId = Seeder::seedMsp();
        SettingsService::saveTaxSettings($mspId, ['tax_mode' => ['stripe_tax_enabled' => true]]);

        $settings = SettingsService::getTaxSettings($mspId);
        self::assertTrue($settings['tax_mode']['stripe_tax_enabled']);
    }
}
```

`Seeder` is a static helper at `tests/Support/Seeder.php` that creates deterministic Partner Hub fixture rows (MSPs, tenants, products, plans, etc.) tagged with `EB_PHASE_A_SEED::` so they are easy to identify and clean up.

### Mocking Stripe API calls

`StripeService` and `CatalogService` both expose `protected function request()` so tests can subclass and intercept. Two ready-made test doubles are provided:

```php
<?php
use EazyBackup\Tests\Support\TestableStripeService;

$stripe = new TestableStripeService();
$stripe->queueResponse(['id' => 'cus_test', 'email' => 'foo@bar.test']);
$stripe->throwOnNext(new \RuntimeException('Stripe down'));  // optional: simulate failures

// Drive your code under test...
$stripe->ensureStripeCustomerFor($tenantId, 'acct_msp');

// Inspect what was sent to Stripe
$call = $stripe->lastCall();
self::assertSame('POST', $call->method);
self::assertSame('/v1/customers', $call->path);
self::assertSame('acct_msp', $call->stripeAccount);
self::assertSame('foo@bar.test', $call->params['email']);
```

The same pattern works for `TestableCatalogService`. Each captured call is a `RecordedRequest` value object with `method`, `path`, `params`, `apiKey`, `stripeAccount`, and `extraHeaders` properties.

### Stripe webhook event tests

Sample Stripe events live under `tests/fixtures/stripe_webhooks/<type>.json`. The `StripeWebhookFixture` helper loads them with deterministic ids and "now"-aligned timestamps:

```php
<?php
use EazyBackup\Tests\Support\StripeWebhookFixture;

$event = StripeWebhookFixture::load('invoice.paid', [
    'account' => 'acct_test',
    'data.object.id' => 'in_test_123',
    'data.object.customer' => 'cus_test',
]);

eb_ph_webhook_dispatch_event($event);  // dispatches without going through curl/HMAC

// For end-to-end signature checks:
$signed = StripeWebhookFixture::loadSigned('invoice.paid', $secret);
$result = eb_ph_stripe_webhook_handle($signed['payload'], $signed['signature'], $secret);
self::assertSame(200, $result['status']);
self::assertSame('ok', $result['body']);
```

When a handler needs to call back into Stripe (currently only `capability.updated`, which retrieves a fresh account snapshot), pass an injected `TestableStripeService` as the second argument to `eb_ph_webhook_dispatch_event($event, $stripe)`.

### Capturing email sends

`MailService` exposes a static transport seam so tests can spy on outgoing emails without touching SMTP or PHP `mail()`:

```php
<?php
use PartnerHub\MailService;

protected function setUp(): void
{
    parent::setUp();
    $this->sentMessages = [];
    MailService::setTransport(function (array $message): array {
        $this->sentMessages[] = $message;
        return ['ok' => true, 'spy' => true];
    });
}

protected function tearDown(): void
{
    MailService::clearTransport();  // ALWAYS clear — leaks across files otherwise
    parent::tearDown();
}

public function test_payment_failed_emails_the_tenant(): void
{
    // ... drive the dispatcher with an invoice.payment_failed event ...

    self::assertCount(1, $this->sentMessages);
    $msg = $this->sentMessages[0];
    self::assertSame('payment_failed', $msg['key']);
    self::assertSame('billing@acme.test', $msg['to']);
    self::assertSame('in_test_123', $msg['vars']['invoice']['id']);
}
```

Each captured `$message` array contains: `msp_id`, `key`, `to`, `from_name`, `from_address`, `reply_to`, `cc`, `subject`, `html`, `alt_body`, `settings`, `vars`. When no transport is set, `MailService::sendTemplate()` falls back to the production PHPMailer / `mail()` path — so this seam is invisible in production.

---

## Manual QA fixtures

Two CLI scripts manage a deterministic Partner Hub fixture set you can interact with via the WHMCS UI:

```bash
# Create the fixture set (commits to dev DB; every row tagged with EB_PHASE_A_SEED::)
php accounts/modules/addons/eazybackup/bin/dev/seed_phase_a.php

# Optionally reuse an existing MSP instead of creating a new one:
php accounts/modules/addons/eazybackup/bin/dev/seed_phase_a.php --reuse-msp-id=17

# Create a new MSP under a specific WHMCS client:
php accounts/modules/addons/eazybackup/bin/dev/seed_phase_a.php --whmcs-client-id=42

# Tear it back down (looks for the EB_PHASE_A_SEED:: tag)
php accounts/modules/addons/eazybackup/bin/dev/reset_phase_a.php
php accounts/modules/addons/eazybackup/bin/dev/reset_phase_a.php --dry-run
```

Both scripts share the test bootstrap, so the production-DB guard above also applies. After seeding you'll see the fixture ids printed to stdout — paste those into the Partner Hub UI for QA.

---

## Release-gate integration

The release gate at `bin/dev/msp_billing_release_gate.php` is the canonical "is the codebase mergeable?" check. It does three things:

1. Walks every static contract test under `bin/dev/*_contract_test.php` and asserts each prints its expected success marker.
2. Greps a hand-curated list of source files for sentinel strings that pin critical wiring (e.g. that `StripeService::request()` is `protected` so tests can override it; that `eb_ph_webhook_dispatch_event` accepts the optional `StripeService` injection seam).
3. Runs the PHPUnit unit suite (skipped when `EB_GATE_SKIP_PHPUNIT=1`).

A green release gate prints `MSP_BILLING_RELEASE_GATE_PASS` and exits 0. Any failure prints individual `FAIL:` lines and exits 1.

When you add a new test seam to production code (e.g. making something `protected` so tests can override it, or adding a new helper function that tests depend on), add a sentinel marker to the gate so the seam can't be silently re-tightened in a future refactor. Convention: group markers under a "Phase X markers" key in the gate's `$checks` array.

---

## Production code seams added for testability

These seams exist purely so tests can drive the code without external dependencies. They are *deliberate* and pinned by the release gate.

| Seam | Where | Why |
|---|---|---|
| `protected function request()` on `StripeService` | `lib/PartnerHub/StripeService.php` | Lets `TestableStripeService` override and capture the raw HTTP call |
| `protected function request()` on `CatalogService` | `lib/PartnerHub/CatalogService.php` | Same pattern for catalog ops |
| `StripeService::resolveApplicationFeePercent()` static helper | `lib/PartnerHub/StripeService.php` | Centralised the 4-tier fee cascade so it can be unit-tested in isolation; controllers delegate to it |
| `eb_ph_webhook_verify_signature()` / `record_idempotent()` / `dispatch_event()` | `pages/partnerhub/StripeWebhookController.php` | Three independently-testable functions extracted from the monolithic webhook entry point |
| `eb_ph_webhook_dispatch_event($event, ?StripeService $svc = null)` | `pages/partnerhub/StripeWebhookController.php` | Optional service injection so `capability.updated` can use a stubbed Stripe API |
| `eb_ph_stripe_webhook_handle($payload, $sig, $secret, ?StripeService)` | `pages/partnerhub/StripeWebhookController.php` | Pure-function entry-point handler that returns `['status'=>int, 'body'=>string]` instead of touching `php://input` and `http_response_code()` |
| `MailService::setTransport(?callable)` / `clearTransport()` | `lib/PartnerHub/MailService.php` | Test-only spy seam; production path unchanged when no transport is set |
| `eb_signup_validate_basic_input()` / `eb_signup_check_domain_filters()` / `eb_signup_check_rate_limits()` / `eb_signup_existing_event_state()` | `pages/whitelabel/PublicSignupController.php` | Four pure helpers extracted from `eazybackup_public_signup` so abuse controls can be unit-tested without going through `$_POST` / `$_SERVER` / Turnstile / `localAPI` (Phase F) |
| `eb_ph_plan_assign_for_msp(int, int, array, ?StripeService, ?CatalogService)` | `pages/partnerhub/CatalogPlansController.php` | Pure-function backend for plan assignment; HTTP wrapper delegates and renders JSON (Phase G) |
| `eb_ph_plan_subscription_cancel_for_msp(int, int, string, ?StripeService)` | `pages/partnerhub/CatalogPlansController.php` | Pure-function backend for cancel-subscription; takes an injected service for the Stripe DELETE call (Phase G) |
| `eb_ph_usage_push_for_tenant(int, int, string, string, int, int, int, ?StripeService)` | `pages/partnerhub/UsageController.php` | Pure-function backend for usage push; ledger writes + Stripe createUsageRecord both inside, with a return shape the HTTP wrapper can `json_encode` (Phase H) |

Whenever a test needs a new seam, prefer:

1. **Marking a private method `protected`** with a comment explaining the test seam intent.
2. **Adding an optional dependency-injection parameter** with a sensible default that preserves production behaviour.
3. **Extracting a pure-function helper** that the existing entry point delegates to.

Avoid PHP magic (`uopz`, runkit, stream wrappers, monkey-patching) — every seam in this suite is plain PHP that future developers can read.

---

## Phase roadmap

The test suite has been built out in phases. Each phase is a self-contained increment with its own design notes captured in the chat history at the time it was implemented.

| Phase | Status | Scope |
|---|---|---|
| **A** | done | Foundation: composer wiring, PHPUnit config, bootstrap, `DatabaseTestCase` + `UnitTestCase`, `Seeder`, manual QA scripts, first 5 unit tests, release-gate integration |
| **B** | done | Service-layer unit coverage: `StripeService`, `CatalogService`, `TenantCustomerService`, `MailService`. Introduced `Testable*Service` doubles + `RecordedRequest` value object. Extracted fee cascade into a static helper |
| **C** | done | Stripe webhook integration: extracted verify/idempotent/dispatch helpers, fixture loader, signed payload helper, coverage for ~13 event families |
| **C2** | done | Webhook follow-ups: `capability.updated` coverage via injected `StripeService`, pure-function entry-point handler, `MailService` transport seam, email dispatch coverage |
| **F** | done | Public Signup integration coverage — extracted `eb_signup_validate_basic_input` / `eb_signup_check_domain_filters` / `eb_signup_check_rate_limits` / `eb_signup_existing_event_state`; tests cover validation, abuse controls, rate-limit windows, and idempotency state classification |
| **G** | done | Plan assignment + cancel lifecycle — extracted `eb_ph_plan_assign_for_msp` and `eb_ph_plan_subscription_cancel_for_msp`; tests cover scope, plan-active gate, comet_user / e3_storage modes, duplicate guard, plan_version snapshot, usage_map seed, cancel-with-Stripe-failure resilience |
| **H** | done | Metered usage push orchestration — extracted `eb_ph_usage_push_for_tenant`; tests cover idempotency-key stability, allowance + overage_mode application, recorded-only branch, Stripe failure leaving ledger retryable, live-vs-stale subscription item override |
| **D** | pending | Playwright E2E for 6 critical flows (MSP onboarding, catalog CRUD, plan assignment, public signup, Add Card + pay invoice, Tenant Portal + impersonation) |
| **E** | pending | CI integration — run `composer test:all` on every push, route failures to Slack/email |
| **I** | pending | Tenant Portal + Impersonation — `accounts/portal/auth.php` against canonical tables, single-use impersonation token lifecycle, branded layout overrides, portal API endpoints |

---

## Common gotchas

- **A test calls `Capsule::table(...)->update(...)` but the change doesn't persist between assertions.** Probably running inside a `DatabaseTestCase` whose transaction rolls back at `tearDown`. That's by design — every test gets a clean slate. If you need to commit (e.g. for a manual QA seed), use the seed CLI scripts instead.
- **`composer test:integration` passes locally but fails in CI complaining about `Stripe\Webhook` / signature verification.** Most likely the addon's vendor directory or the WHMCS root vendor isn't on the path. The bootstrap loads both — confirm `accounts/init.php` and `accounts/modules/addons/eazybackup/vendor/autoload.php` exist on the CI host.
- **Email tests pass individually but fail when run together with another test class.** A previous test installed a `MailService` transport spy and didn't call `clearTransport()` in `tearDown`. Always pair `setTransport` + `clearTransport`.
- **A new contract marker fails the release gate after a legitimate refactor.** Update the marker — the gate is meant to track *intentional* code structure, not block all change. Just make sure the new shape still preserves the test seam (e.g. method is still `protected`, helper is still callable).
- **A unit test inserts into `tbladdonmodules` and conflicts with the dev's local config.** Use a per-test unique `module` name (e.g. `eb_phb_<random>`) and pass it as the `$moduleName` parameter where the helper accepts one. See `StripeServiceFeeCascadeTest` for the pattern.

---

## Files of interest

| Path | Role |
|---|---|
| `accounts/modules/addons/eazybackup/composer.json` | `require-dev` + composer scripts (`test`, `test:integration`, `test:contract`, `test:all`, `test:full`, `test:coverage`) |
| `accounts/modules/addons/eazybackup/phpunit.xml` | PHPUnit config; declares `unit` and `integration` test suites |
| `accounts/modules/addons/eazybackup/tests/bootstrap.php` | Loads addon vendor autoload, WHMCS `init.php`, the addon entrypoint, the webhook controller, and applies the production-DB guard |
| `accounts/modules/addons/eazybackup/tests/Support/` | Test base classes, fixture seeder, test doubles, and the Stripe webhook fixture helper |
| `accounts/modules/addons/eazybackup/tests/fixtures/stripe_webhooks/` | One JSON per Stripe event family the dispatcher handles |
| `accounts/modules/addons/eazybackup/bin/dev/msp_billing_release_gate.php` | The release gate; protects test seams + invokes the unit suite |
| `accounts/modules/addons/eazybackup/bin/dev/seed_phase_a.php` | Manual QA fixture seeder (commits) |
| `accounts/modules/addons/eazybackup/bin/dev/reset_phase_a.php` | Manual QA fixture cleanup (`EB_PHASE_A_SEED::` tag) |

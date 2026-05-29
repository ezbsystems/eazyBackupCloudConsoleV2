I'm continuing development on the e3 Cloud Backup product's first-run onboarding experience. The initial implementation is complete and in production on the dev server; I want to extend the tour, refine the Getting Started page, and improve the onboarding flow.

Please read these docs before doing anything else — they are the authoritative reference for this subsystem:

accounts/modules/addons/cloudstorage/docs/E3_CLOUD_BACKUP_ONBOARDING.md — canonical reference for the onboarding system (Getting Started page, driver.js tour, 4-step model, persistent pill, sidebar trim, schema, APIs, file inventory, how to extend).
accounts/modules/addons/cloudstorage/docs/BETA_ONBOARDING.md — external customer-facing cheat sheet.
accounts/modules/addons/cloudstorage/docs/E3_CLOUD_BACKUP_BILLING.md — adjacent billing/trial subsystem the onboarding state interacts with.
accounts/modules/addons/cloudstorage/docs/CLOUD_STORAGE_README.md — top-level addon reference.
accounts/modules/addons/eazybackup/Docs/StyleGuides/SEMANTIC-THEME-REFERENCE.md — all UI work must use eb-* semantic classes and var(--eb-*) tokens; do not introduce raw hex colors, slate utilities, or dark: variants.
Quick architecture recap so you know what's already built:

Post-provision landing: Provisioner::provisionE3CloudBackup() redirects new customers to index.php?m=cloudstorage&page=e3backup&view=getting_started, not the user_detail Agents tab.
Four-step model: download, agent_online, first_job, first_run. Three of four steps are derived live from s3_cloudbackup_agents / s3_cloudbackup_jobs / s3_cloudbackup_runs. Only download_clicked is explicitly tracked.
State table: s3_e3backup_onboarding_state (write-once timestamps for download_clicked_at, tour_started_at, tour_completed_at, tour_dismissed_at, last_visited_getting_started_at).
Helper: WHMCS\Module\Addon\CloudStorage\Client\OnboardingState with compute() / recordEvent() / touchVisit().
APIs: api/e3backup_onboarding_status.php (GET payload) and api/e3backup_onboarding_event.php (POST event). The Getting Started page polls status every 5s and listens for the eb-e3-onboarding-event window event for instant updates.
Tour: vendored driver.js v1.3.1 (assets/vendor/driver/) wrapped by assets/js/e3backup_tour.js exposing window.ebE3Tour.{start, destroy, maybeAutoStart}. 6 steps anchored via data-tour="..." attributes. Tour is themed with eb-* tokens in the shell partial's <style> block.
Restart paths: (1) "Replay tour" button on Getting Started (always visible, label switches dynamically), (2) persistent "Setup: X of 4" pill in every e3backup page's app header, (3) "Getting Started" sidebar link with X/Y badge.
Sidebar trim: Recovery / Media Builder / Cloud NAS get eb-sidebar-link is-disabled when $ebE3HasAgents is false, with a tooltip explaining why.
Quick Enroll panel on user_detail Agents tab is admin-only ({if $ebIsAdminSession}).
Shared view vars: cloudstorage.php injects $ebE3OnboardingShared (ebE3HasAgents, ebE3OnboardingCompleted, ebE3OnboardingTotal, ebE3OnboardingComplete, ebE3OnboardingHidden, ebIsAdminSession) into every e3backup view automatically.
Critical conventions to follow:

Smarty templates: any inline <script> or <style> block in a .tpl file must be wrapped in {literal}...{/literal} — Smarty parses {...} as tags otherwise. Inline onclick="" attributes must call a brace-free window.* wrapper defined in a {literal}-wrapped script block (we have ebE3OpenDownload() / ebE3RecordOnboardingEvent(event) as the pattern).
Compiled vs source CSS: edits to accounts/templates/eazyBackup/css/tailwind.src.css must also be mirrored in the compiled accounts/templates/eazyBackup/css/tailwind.css (the file actually served to the browser). There is no build step running automatically.
Theme: use semantic eb-* classes and var(--eb-*) tokens from SEMANTIC-THEME-REFERENCE.md. Tailwind utilities are for layout only (flex, grid, spacing, responsive). The popover/tour styling is in templates/partials/e3backup_shell.tpl under the eb-e3-tour-popover class.
Always Smarty-compile-test after editing a .tpl: cd accounts && php -r '$s=new \WHMCS\Smarty(); $s->setTemplateDir(__DIR__); $s->fetch("modules/addons/cloudstorage/templates/<path>.tpl"); echo "OK\n";'
Always lint-test after editing PHP/JS: php -l <file> and ReadLints.
For QA reset loops: DeprovisionHelper::resetOnboarding($clientId) wipes onboarding state + trial state + provisioning bookkeeping so the same client can re-run the flow.
Working environment: WHMCS-based PHP app at /var/www/eazybackup.ca/. The cloudstorage addon lives at accounts/modules/addons/cloudstorage/. Dev URL is https://dev.eazybackup.ca. The beta gate (lib/Beta/BetaGate.php) makes the e3 Cloud Backup product card visible on dev by default and on prod only for clients in the allowlist.
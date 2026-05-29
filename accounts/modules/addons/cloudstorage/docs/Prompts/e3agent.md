You are continuing development on the e3 Backup Agent, the cross-platform Go backup agent (with a Windows focus) for the eazyBackup "e3 Cloud Backup" product. The work this session targets the Windows agent. Before doing anything else, read the authoritative docs and code below to build a mental model; do not start changing code until you've read them.

Working environment

WHMCS-based PHP app at /var/www/eazybackup.ca/. Dev URL: https://dev.eazybackup.ca.
The Go agent lives at /var/www/eazybackup.ca/e3-backup-agent/.
The server-side product (WHMCS addon) that the agent talks to lives at /var/www/eazybackup.ca/accounts/modules/addons/cloudstorage/.
Agent codebase orientation (e3-backup-agent/)

cmd/agent/main.go — service entrypoint + Windows service lifecycle (install/uninstall/start/stop/restart via kardianos/service). Foreground vs. SCM-managed modes.
cmd/tray/main_windows.go — Windows tray helper + local enrollment/recovery HTTP UI; sc.exe service control; enrollment flow (authenticateForEnroll / completeEnrollment).
internal/agent/ — the core: runner.go (main loop, config load, enrollment gating), config.go/identity.go, api_client.go, backup engines (kopia*.go, disk_image*.go, hyperv*/), restore, NAS, logging (internal/applog).
installer/e3-backup-agent.iss — Inno Setup installer (Pascal Script in [Code]). Note: TNewCheckBox does not support WordWrap/AutoSize; only TNewStaticText does.
Makefile — make build-windows cross-compiles bin/e3-backup-agent.exe + bin/e3-backup-tray.exe for Windows.
Authoritative docs (read these first)

e3-backup-agent/README.md and e3-backup-agent/installer/README.md — agent + installer overview.
accounts/modules/addons/cloudstorage/docs/LOCAL_AGENT_OVERVIEW.md — what the local agent is and how it fits the product.
accounts/modules/addons/cloudstorage/docs/LOCAL_AGENT_ARCHITECTURE_UPDATE_DESTINATION_TENANT_CRYPTO_UPDATE.md — agent architecture, destinations, tenant crypto.
accounts/modules/addons/cloudstorage/docs/LOCAL_AGENT_BUILD.md and LOCAL_AGENT_DISK_IMAGE.md — build flow and disk-image engine.
accounts/modules/addons/cloudstorage/docs/HYPERV_BACKUP_ENGINE.md, HYPERV_RESTORE_PLAN.md, HYPERV_INTEGRATION_PROJECT.md — Hyper-V backup/restore.
accounts/modules/addons/cloudstorage/docs/KOPIA_RETENTION_ARCHITECTURE.md — repository/retention model.
accounts/modules/addons/cloudstorage/docs/CLOUDBACKUP_AGENT_LOGGING_CUTOVER.md, AGENT_TESTING.md, WATCHDOG_SETUP.md, BETA_KNOWN_LIMITATIONS.md — logging, testing, watchdog, known limits.
accounts/modules/addons/cloudstorage/docs/E3_CLOUD_BACKUP_ONBOARDING.md — first-run onboarding (Getting Started page, tour); §15.7 documents the installer text-scaling/WordWrap gotcha.
accounts/modules/addons/cloudstorage/docs/CLOUD_STORAGE_README.md — top-level addon reference.
Build & signing pipeline (important — there are two repo checkouts)

The WHMCS admin Addons → Cloud Storage → Agent Builds page drives the build. Step classes: accounts/modules/addons/cloudstorage/lib/Admin/AgentBuild/Steps/ (GitSync, WindowsBuild, WindowsStage, InnoCompile, AzureSign, WindowsFetch, Publish, …).
Critical: the build does not use the working tree at /var/www/eazybackup.ca. GitSync operates on a separate clone configured by the agent_build_git_root / agent_build_repo_path module settings (currently /srv/agent-build/eazyBackupCloudConsoleV2[/e3-backup-agent]) and resets it to origin/main via git fetch + git checkout main + git pull --ff-only. A change only reaches the build after it is committed and pushed to origin/main (remote: git@github.com:ezbsystems/eazyBackupCloudConsoleV2.git). Editing the working tree alone will not change build output.
WindowsStage scp's the source (incl. installer/e3-backup-agent.iss) to the Windows host (192.168.92.210), InnoCompile runs ISCC.exe, AzureSign signs via AzureSignTool, WindowsFetch pulls artifacts back, Publish copies to accounts/client_installer/.
Critical conventions

Go: keep changes go build-clean. The tray is Windows-only — verify with GOOS=windows go build ./cmd/tray/ and go build ./cmd/agent/. Run go vet but note there's a pre-existing non-constant format string vet warning in cmd/tray/main_windows.go unrelated to new work.
The agent loads config/credentials once at process start; it only re-reads agent.conf while waiting for enrollment. Any change to enrollment/config that must take effect immediately requires a service restart (-service restart, or the tray's restart helper).
Inno Setup [Code]: only TNewStaticText supports WordWrap/AutoSize; keep TNewCheckBox captions single-line.
Don't commit or push unless explicitly asked; if a change needs to reach the build, call that out so it can be committed+pushed to origin/main.
If you touch server-side PHP, lint with php -l and check ReadLints.
After you've read the above and can summarize the agent's architecture and the build pipeline back to me, I'll give you the specific task details.


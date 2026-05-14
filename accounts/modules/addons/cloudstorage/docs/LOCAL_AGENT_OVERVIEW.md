# e3 Local Backup Agent — Overview

The e3 local agent is a Go service (and Windows tray helper) shipped as part of
the cloudstorage addon. It backs up files and disk images from customer
endpoints into the customer's e3 object storage bucket, polling the WHMCS API
for jobs, run state, and configuration.

This document is the high-level entry point for everything related to the
agent: source layout, build pipeline, deployment, and operational tooling.

## Source repositories

| Repo | Path | Purpose |
| ---- | ---- | ------- |
| Agent | `/var/www/eazybackup.ca/e3-backup-agent` | Go code (service, tray, recovery, recovery-media-creator). Contains Inno Setup script under `installer/`. |
| WHMCS addon | `/var/www/eazybackup.ca/accounts/modules/addons/cloudstorage` | Server-side API endpoints, admin UI, schema, and the new automated build pipeline. |
| Worker assets | `/var/www/eazybackup.ca/e3-cloudbackup-worker/assets` | Tray icon assets consumed by the Inno Setup script. |
| Lab harness | `/var/www/eazybackup.ca/e3-agent-lab` | SSH-driven test lab (Linux, Server 2025, Server 2019). |

## Build pipeline (managed from WHMCS)

The "Agent Builds" page in the WHMCS admin (Addons -> Cloud Storage -> Agent
Builds) is the single entry point for producing customer-facing artifacts. A
build is a row in `s3_agent_build_jobs` and progresses through these steps,
each tracked in `s3_agent_build_steps`:

```
git_sync -> go_test -> linux_build -> windows_build -> recovery_build
         -> windows_stage -> windows_inno -> windows_sign
         -> windows_fetch -> verify -> publish
```

The runner (`crons/agent_build_runner.php`, scheduled by systemd or WHMCS cron)
holds an exclusive flock and processes one queued job at a time. Each step
streams stdout/stderr to `storage/builds/<job_id>/<step>.log`. The admin Build
Detail page polls `api/admin_agent_build_status.php` and tails logs via
`api/admin_agent_build_log_tail.php` so problems are visible step-by-step.

Code signing uses **AzureSignTool** running on the Windows build host (default
Server 2025, `192.168.92.210`). The Azure AD client secret is stored encrypted
in `tbladdonmodules` and is redacted from build logs.

Successful builds publish versioned artifacts plus "latest" aliases into
`/var/www/eazybackup.ca/accounts/client_installer/` and record an
`s3_agent_releases` row.

See `LOCAL_AGENT_BUILD.md` for full setup instructions, signing prerequisites,
and the systemd unit/timer template.

## Customer download paths

| Artifact | URL |
| -------- | --- |
| Windows installer | `https://accounts.eazybackup.ca/client_installer/e3-backup-agent-setup.exe` |
| Linux binary | `https://accounts.eazybackup.ca/client_installer/e3-backup-agent-linux` |
| Recovery media creator | `https://accounts.eazybackup.ca/client_installer/e3-recovery-media-creator.exe` |

Versioned filenames (e.g. `e3-backup-agent-setup-2026.05.03-103412.exe`) are
also kept on disk. The Releases tab lets admins promote any older versioned
file back to "latest".

## Schema reference (build subsystem)

| Table | Role |
| ----- | ---- |
| `s3_agent_build_jobs` | One row per build attempt (status, platform, git ref/commit, version label, flags, error). |
| `s3_agent_build_steps` | One row per pipeline step per job (status, exit code, log path, byte count). |
| `s3_agent_releases` | One row per published artifact (platform, sha256, size, signed metadata, latest flag, download URL). |

## Files added by the build subsystem

```
accounts/modules/addons/cloudstorage/
  crons/agent_build_runner.php
  pages/admin/agent_builds.php
  templates/admin/agent_builds.tpl
  lib/Admin/AgentBuild/
    bootstrap.php
    Settings.php          - module setting accessors + Azure secret decrypt
    JobStore.php          - DB access for jobs/steps/releases
    ProcRunner.php        - process executor with line-level secret redaction
    WindowsRemote.php     - SSH/SCP wrapper for the Windows build host
    AzureSigner.php       - AzureSignTool command builder
    BuildRunner.php       - orchestrator
    Steps/{GitSync,GoTest,LinuxBuild,WindowsBuild,RecoveryBuild,
            WindowsStage,InnoCompile,AzureSign,WindowsFetch,Verify,Publish}.php
  api/
    admin_agent_build_create.php
    admin_agent_build_status.php
    admin_agent_build_log_tail.php
    admin_agent_build_cancel.php
    admin_agent_build_release_publish.php
    admin_agent_build_settings_test.php
  storage/builds/         - per-job working directory, log files, fetched artifacts
```

## Related documents

- `LOCAL_AGENT_BUILD.md` — manual + automated build instructions, including
  Azure / signing setup.
- `LOCAL_AGENT_DISK_IMAGE.md` — disk-image backup design and APIs.
- `CLOUD_STORAGE_README.md` — the broader cloudstorage addon.

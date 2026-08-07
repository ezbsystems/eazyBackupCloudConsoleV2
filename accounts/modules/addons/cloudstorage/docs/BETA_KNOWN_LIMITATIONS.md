# e3 Backup Agent — Beta Known Limitations

_Audience: beta customers + first-line support._
_Pair this doc with the Beta Onboarding guide (`BETA_ONBOARDING.md`)._

The following capabilities are **not in the beta wave** and will land in
the General Availability release. If you need any of them today, hold off
on the agent and reach out to support so we can capture your use case.

## Recovery
- **Bare-metal recovery (R1, attended)** — The Windows recovery agent
  exists but is not part of the beta verification matrix. The
  `127.0.0.1:8088` listener path is GA-only.
- **Bare-metal recovery (R3, unattended WinPE)** — The bootable recovery
  ISO can be built (`./bin/e3-lab build-recovery-media …`) but the
  end-to-end "boot WinPE → pull manifest → write disk → reboot" path is
  not validated for beta.
- **Cross-platform BMR** — There is no Linux BMR equivalent today.

## Disk-image restores
- **Linux block-level restore** is validated (`linux-disk-image-cycle` passes). Restorable disk-image points can be restored to a target device when `is_restorable` is true.
- **Windows full block-level disk-image restore** has a residual content-hash mismatch tracked in VALIDATION_REPORT_V2 and remains GA-only for beta.
- **Mount snapshot** (file-level restore via FUSE mount) is not implemented (`mount not implemented`). This applies to file backups on Linux and disk-image points on all platforms. The portal hides mount restore for Linux agents.
- Linux disk-image backup is fully validated; captured snapshots are durable and cycle-tested.
- For beta customers who need a single file from a disk-image snapshot without block restore, contact support for an out-of-band manual procedure.

## Operations
- **Encryption-key rotation** — No documented rotation flow. Treat the
  initial repository password as permanent for the beta wave.
- **Corrupt-restore admin path** — There is no admin button to repair
  a corrupt manifest or restart a stuck restore. If a restore fails,
  open a support ticket and re-run the backup to produce a fresh
  restore point.
- **Retention/prune verification** — Retention rules apply, but the
  pruner has not been load-tested against in-flight restores; defer
  aggressive prune policies until GA.
- **Overlapping schedule fires** — A new run is rejected with
  `code=ALREADY_RUNNING` if the previous run is still in flight. The
  customer scheduler does not yet automatically re-queue; the next
  scheduled fire will pick up.
- **Restore-point readiness re-check** — A restore point left in
  `metadata_incomplete` due to a transient gather-time failure can be
  promoted via `POST
  /modules/addons/cloudstorage/api/cloudbackup_restore_point_rerun_readiness.php`
  with `restore_point_id`. A self-service admin button is GA-only.

## Observability
- **Per-run structured log aggregation** — `stats_json.restore_readiness`
  is the supported view for "why did this run end the way it did". A
  rolled-up "this run had N permission-denied files" surface is GA-only.

## Installer (Linux)
- **Installer script and .deb** — `e3-backup-agent-linux-install.sh` and `e3-backup-agent-linux.deb` install the binary, systemd unit, and `agent.conf`. Enrollment uses a portal token (`--token`, `TOKEN=` env, or debconf prompt). The installer calls the enrollment API during install and **fails** if the token is invalid, revoked, or exhausted.
- **API endpoint** — Published installers default to production (`https://accounts.eazybackup.ca/modules/addons/cloudstorage/api`). Override with `API_BASE=` or `install.sh --api` for lab/dev testing.
- **Upgrade path** — Re-running the install script or installing a newer `.deb` preserves enrolled credentials. Not yet covered by an automated lab scenario.
- **Distro support** — Ubuntu 22.04 lab-validated. Debian-family expected compatible. RHEL/Rocky not yet tested.

## Installer (Windows)
- **Upgrade path** — Installing a new build over an old build is
  expected to preserve the service + `agent.conf`, but the upgrade is
  not yet covered by an automated lab scenario. Verify by hand on a
  staging host before pushing to a customer.
- **SmartScreen / Defender reputation** — Until a code-signing
  certificate is available, expect the SmartScreen "unrecognized
  publisher" warning on first install.
- **Firewall rules** — The installer adds two rules (outbound for
  `e3-backup-agent.exe`, inbound loopback :8088 for
  `e3-recovery-agent.exe`). They are removed on uninstall.

## Hyper-V
- **Recommended host platform: Windows Server 2022.** End-to-end
  backup → restore → attach is validated against Windows Server 2022
  (`windows-hyperv-cycle` passes). Windows Server 2019 hosts can take
  backups successfully but a same-host restore currently produces a
  VHDX that Hyper-V refuses to attach with "The file or directory is
  corrupted and unreadable" — under investigation, suspected to be
  the safety-net zero-fill on the trailing 1 GiB of a live VHDX
  interacting with NTFS on 2019. Beta customers running 2019 should
  restore to a 2022 host or wait for the GA fix.
- **VMs with checkpoints disabled** are backed up
  crash-consistently (no quiesce). Read timeouts within the trailing
  1 GiB of a live VHDX are zero-filled to keep the run from failing
  — documented best-effort behaviour. Timeouts before that region
  fail the run rather than risk structural corruption.
- **Multi-VM partial outcomes** are recorded as
  `status=partial_success` with per-VM details in `error_summary`. The
  customer dashboard renders this as warning-amber.


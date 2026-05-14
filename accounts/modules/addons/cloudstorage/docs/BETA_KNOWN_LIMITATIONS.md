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
- **Disk-image is BACKUP-ONLY for beta.** Restore (whether full
  block-level or file-level via `kopia mount`) is GA-only:
  - Full block-level Windows disk-image restore has the residual
    content-hash mismatch tracked in VALIDATION_REPORT_V2.
  - File-level (`kopia mount`) restore from a disk-image snapshot is
    not yet implemented in the agent (`mount not implemented` is
    returned for `disk_image` restore points). Phase 1C surfaced this
    against `linux-disk-image-selective-restore`,
    `windows-disk-image-selective-restore`, and
    `windows2019-disk-image-selective-restore` and they are flagged
    `beta_scope: false, deferred_to: GA` accordingly.
- Linux disk-image backup is fully validated; the captured snapshots
  are durable and cycle-tested (see `linux-disk-image-cycle`). They
  are **safe to keep** through beta and will become restorable when
  the GA agent ships.
- For beta customers who hit a real-world need to restore a single
  file from a disk-image snapshot, contact support — we have an
  out-of-band manual procedure.

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


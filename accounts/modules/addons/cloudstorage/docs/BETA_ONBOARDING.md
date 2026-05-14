# e3 Backup Agent — Beta Onboarding (one page)

_Pair this guide with `BETA_KNOWN_LIMITATIONS.md`._

## 1. Enroll an agent
- Linux: download `e3-backup-agent-linux`, drop into `/usr/local/bin`,
  run `e3-backup-agent -enroll -token <one-time-token>` then
  `systemctl enable --now e3-backup-agent`.
- Windows: run `e3-backup-agent-setup.exe`, accept the SmartScreen
  warning if unsigned, paste the one-time token when prompted, finish
  the wizard. The service starts automatically and the tray helper
  launches at next login.

## 2. First backup
- Sign in to the customer portal → Cloud Backup → Jobs → New Job.
- Pick the source (Files, Disk Image, or Hyper-V VMs), the
  destination bucket, and the schedule. Save.
- Click **Run now** to verify enrollment + credentials before the
  scheduler picks it up.
- A successful run renders **Success** (green). A multi-VM Hyper-V
  run with one bad VM renders **Partial Success** (amber); see the
  per-VM details in the run log.

## 3. File restore
- Cloud Backup → Restore Points → pick a row marked **Success** →
  **Restore files**.
- Browse the snapshot, tick the files/folders you want, choose a
  target directory on the agent host, click **Restore**.
- The job appears in **Jobs → Restores** until it finishes.

## 4. Disk-image selective restore (beta)
- Same flow as file restore, but pick a `disk_image` snapshot and
  choose **Mount snapshot** instead of **Copy files**. The captured
  volume image is FUSE-mounted read-only on the agent host so you can
  cherry-pick files out of it without restoring the whole volume.
- Full block-level disk-image restore is GA-only for this beta.

## 5. Hyper-V backup + restore
- Backup is configured per-VM at job-create time. The agent picks the
  most consistent checkpoint mode the VM allows (production →
  reference → crash) and falls back automatically.
- Restore writes `<vm-name>-restored` VHDX files into a target
  directory on the agent host; attach them to a new VM in Hyper-V
  Manager to bring the VM back.

## 6. Support ticket template
> Subject: e3 beta — `<one-line summary>`
>
> - Tenant: `<your tenant>`
> - Agent UUID: `<from the agents page>`
> - Job name: `<…>`
> - Run UUID (if applicable): `<…>`
> - Restore point ID (if applicable): `<…>`
> - Symptom: `<what you saw and when>`
> - Expected: `<what you expected>`
> - Reproduction: `<steps>`
> - Logs: please attach `agent.log` from the host's
>   `C:\ProgramData\E3Backup\logs\` (Windows) or
>   `/var/log/e3-backup-agent/` (Linux).


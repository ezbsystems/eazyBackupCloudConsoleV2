# e3 Backup Agent — Beta Onboarding (one page)

_Pair this guide with `BETA_KNOWN_LIMITATIONS.md`._

## 0. First sign in

After verifying your email, the Welcome page will prompt you to **create
your portal password** before you can pick a product. This is the same
password your e3 Cloud Backup agent will use to sign in from your
Windows or Linux machine — choose something you can remember.

Once your password is set, the product picker unlocks. Selecting
**e3 Cloud Backup** opens a short drawer that only asks for the
**backup agent username** you want (your portal password from the
previous step is automatically reused as the backup agent password).

## 1. Enroll an agent
- Linux: download `e3-backup-agent-linux`, drop into `/usr/local/bin`,
  run `e3-backup-agent -enroll -token <one-time-token>` then
  `systemctl enable --now e3-backup-agent`.
- Windows: run `e3-backup-agent-setup.exe`, accept the SmartScreen
  warning if unsigned, paste the one-time token when prompted, finish
  the wizard. The service starts automatically and the tray helper
  launches at next login.

### Quick-enroll (recommended for testers)

The user detail page (Cloud Backup -> Users -> *click row* -> Agents tab)
now exposes a **Generate token** button. It mints a 60-minute single-use
token and renders ready-to-paste install snippets for:

- Linux
- Windows Server 2019
- Windows Server 2025

Copy the snippet and paste it into the test box. The agent will appear in
the Agents table on the same page within ~10 seconds.

## 2. First backup
- Sign in to the customer portal → e3 Cloud Backup → Users → click your
  username to open the user detail page.
- Click **Create Job → e3 Cloud Backup** (Files, Folders, Disk Image,
  Virtual Machines). A guided tour highlights the Job Name, Backup
  Engine, and Agent fields the first time through. Pick the source
  (File Backup, Disk Image, or Hyper-V VMs), the destination, and the
  schedule. Save.
- Click **Run now** to verify enrollment + credentials before the
  scheduler picks it up.
- A successful run renders **Success** (green). A multi-VM Hyper-V
  run with one bad VM renders **Partial Success** (amber); see the
  per-VM details in the run log.
- The second menu option, **SaaS Backup (Cloud-to-Cloud)**, is for
  protecting data that already lives in another cloud (Google Drive,
  Dropbox, SFTP, S3, AWS). It does not require a local agent.

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


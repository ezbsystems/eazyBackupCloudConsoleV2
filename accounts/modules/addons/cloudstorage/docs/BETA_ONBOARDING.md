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

Generate an enrollment token from **Enrollment Tokens** in the portal, then use the OS-specific install command shown after generation.

- **Linux (recommended):**
  ```bash
  curl -fsSL https://accounts.eazybackup.ca/client_installer/e3-backup-agent-linux-install.sh | sudo bash -s -- --token <one-time-token>
  ```
  Or install the `.deb` package (silent, recommended for scripts):
  ```bash
  curl -fsSL -o e3-backup-agent.deb https://accounts.eazybackup.ca/client_installer/e3-backup-agent-linux.deb
  sudo TOKEN=<one-time-token> dpkg -i e3-backup-agent.deb
  ```
  In an interactive terminal, run `sudo dpkg -i e3-backup-agent.deb` without `TOKEN=` — a debconf dialog (whiptail/dialog) prompts for your enrollment token during install.
  The installer writes `/etc/e3-backup-agent/agent.conf`, installs the systemd service, and enrolls automatically.

- **Windows:** download `e3-backup-agent-setup.exe`, then run silently:
  ```text
  e3-backup-agent-setup.exe /VERYSILENT /TOKEN=<one-time-token>
  ```
  Or run the wizard and sign in with your portal credentials. The service starts automatically and the tray helper launches at next login.

### Quick-enroll (recommended for testers)

The **Enrollment Tokens** page exposes a **Generate Token** button. It mints a single-use token and shows ready-to-paste install commands for Windows and Linux.

Copy the command for your OS and run it on the target host. The agent will appear in the Agents table within ~10 seconds.

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

## 4. Disk-image restore (beta)
- **Linux block-level restore** is available for restorable disk-image points (lab-validated).
- **Mount snapshot** (file-level restore from a disk-image point) is not yet available on Linux agents.
- **Windows** full block-level disk-image restore via the portal is GA-only for this beta.
- For a single file from a disk-image snapshot, contact support for an out-of-band procedure until mount restore ships.

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


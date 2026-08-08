Place the approved x64-capable WinFsp MSI here as `winfsp.msi`. The MSI is a
build input and is intentionally excluded from Git.

Agent Builds (`windows_build_cloudnas`) will download and SHA-256-verify this
file automatically when missing, then cache a copy under
`/var/cache/e3-agent-build/winfsp.msi`.

Current candidate fetch URL:

<https://github.com/winfsp/winfsp/releases/download/v2.2B3/winfsp-2.2.26194.msi>

Expected SHA-256: `7b41020618cdcc33d699d0e15c1df660f0762a09b57080049c565857ac00bd9d`

MSI Feature note (WinFsp 2.2 / v2.2B3):

- Core feature **ID** is `F.User` (Title "Core"). Do **not** pass
  `ADDLOCAL=Core` — msiexec fails with 1603.
- Developer feature **ID** is `F.Developer` (headers under `inc\fuse`).
  Required on the Windows **build** host for CGO/`cgofuse`.

Silent Core install (end-user agents):

```text
msiexec /i winfsp.msi /qn /norestart
```

Build-host headers (do **not** use ADDLOCAL=F.Developer when Core is already
installed — SecureRepair often fails with 1603). Prefer administrative extract:

```text
msiexec /a winfsp.msi /qn TARGETDIR=C:\E3Build\winfsp-headers
```

Headers land under `TARGETDIR\DYNAMIC\inc\fuse`. `windows_build_cloudnas`
does this automatically when `fuse.h` is missing. Agent installer logs go to
`%ProgramData%\E3Backup\logs\winfsp-msi.log`.

Place the approved x64-capable WinFsp MSI here as `winfsp.msi`. The MSI is a
build input and is intentionally excluded from Git.

Agent Builds (`windows_build_cloudnas`) will download and SHA-256-verify this
file automatically when missing, then cache a copy under
`/var/cache/e3-agent-build/winfsp.msi`.

Current candidate fetch URL:

<https://github.com/winfsp/winfsp/releases/download/v2.2B3/winfsp-2.2.26194.msi>

Expected SHA-256: `7b41020618cdcc33d699d0e15c1df660f0762a09b57080049c565857ac00bd9d`

MSI Feature note (WinFsp 2.2 / v2.2B3): the Core feature **ID** is `F.User`
(the Title is "Core"). Do **not** pass `ADDLOCAL=Core` — msiexec fails with
1603. Prefer the default INSTALLLEVEL (omit ADDLOCAL), or use `ADDLOCAL=F.User`.

Silent Core install:

```text
msiexec /i winfsp.msi /qn /norestart
```

Installer logs are written to `%ProgramData%\E3Backup\logs\winfsp-msi.log`.

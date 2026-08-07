# Cloud NAS WinFsp Sidecar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace Cloud NAS WebDAV mounts with a GPLv3 `e3-cloudnas` WinFsp sidecar so Windows reports real drive capacity (not C:) without linking WinFsp into the proprietary agent.

**Architecture:** Optional `e3-cloudnas` process (user session) exposes loopback HTTP (`/health`, `/status`, `/mount`, `/unmount`). Proprietary agent keeps WHMCS/temp-key logic and calls the sidecar instead of starting WebDAV. Inno optionally installs sidecar + WinFsp Core silently.

**Tech Stack:** Go 1.24, rclone v1.67 VFS/S3, WinFsp + `github.com/winfsp/cgofuse`, Inno Setup, WHMCS PHP/Smarty (error UX only).

## Global Constraints

- Spec: `accounts/modules/addons/cloudstorage/docs/superpowers/specs/2026-08-07-cloudnas-winfsp-sidecar-design.md`
- Proprietary agent/tray: **`CGO_ENABLED=0`**, **no** WinFsp/`cgofuse` imports
- Sidecar: **GPLv3**, Windows amd64, **`CGO_ENABLED=1`**, built on Windows build host
- Sidecar location: monorepo top-level **`e3-cloudnas/`** (not a separate repo for v1)
- Inno Cloud NAS checkbox: **checked by default**
- Capacity: use rclone VFS default (~1 PiB free when backend has no About) — no fixed marketing size in v1
- Loopback only (`127.0.0.1`); auth header `X-E3-CloudNAS-Token`
- Discovery file: `%ProgramData%\E3Backup\cloudnas.discovery` (JSON: port, pid, version) + token file `%ProgramData%\E3Backup\cloudnas.token` (ACL: SYSTEM + Administrators + Users read for agent service)
- Stable agent error codes: `cloudnas_sidecar_missing`, `cloudnas_sidecar_not_running`, `winfsp_missing`
- Do not remove debug WebDAV instrumentation until post-fix capacity verification on a signed build; then delete WebDAV NAS path
- Client must not manually install WinFsp
- Counsel must confirm WinFsp redistribution before production publish (track as release gate, not a code task)

---

## File structure (locked)

| Path | Responsibility |
|------|----------------|
| `e3-cloudnas/LICENSE` | GPLv3 text |
| `e3-cloudnas/README.md` | Build, license, how agent talks to sidecar |
| `e3-cloudnas/go.mod` | Module `github.com/ezbsystems/e3-cloudnas` |
| `e3-cloudnas/cmd/e3-cloudnas/main.go` | Entrypoint: discovery, HTTP server, idle |
| `e3-cloudnas/internal/api/types.go` | Request/response JSON types |
| `e3-cloudnas/internal/api/server.go` | HTTP mux + token auth |
| `e3-cloudnas/internal/api/server_test.go` | Auth + mount dispatch tests (fake mounter) |
| `e3-cloudnas/internal/mount/mounter.go` | `Mounter` interface |
| `e3-cloudnas/internal/mount/manager.go` | In-memory mount registry |
| `e3-cloudnas/internal/mount/winfsp_windows.go` | S3 VFS + cgofuse/WinFsp mount (`//go:build windows,cgo`) |
| `e3-cloudnas/internal/mount/stub_other.go` | Non-Windows stub |
| `e3-cloudnas/internal/discovery/write.go` | Write discovery + token files |
| `e3-cloudnas/installer/e3-cloudnas.iss` | Sidecar + WinFsp MSI silent install |
| `e3-backup-agent/internal/agent/nas_sidecar.go` | Shared types + error sentinels (all OS) |
| `e3-backup-agent/internal/agent/nas_sidecar_windows.go` | Discover + HTTP client to sidecar |
| `e3-backup-agent/internal/agent/nas_sidecar_stub.go` | Non-Windows stubs |
| `e3-backup-agent/internal/agent/nas.go` | Replace `startNASWebDAV` with sidecar mount path |
| `e3-backup-agent/cmd/tray/cloudnas_control_windows.go` | Remove WebDAV map; ensure sidecar process |
| `e3-backup-agent/installer/e3-backup-agent.iss` | Optional Cloud NAS nested install |
| `accounts/.../WindowsStage.php` | Stage sidecar binary + WinFsp MSI + iss assets |
| `accounts/.../templates/...` + `CLOUD_NAS.md` | Sidecar-missing UX + docs |

---

### Task 1: Scaffold `e3-cloudnas` + control API (fake mounter)

**Files:**
- Create: `e3-cloudnas/LICENSE` (copy GPLv3)
- Create: `e3-cloudnas/README.md`
- Create: `e3-cloudnas/go.mod`
- Create: `e3-cloudnas/internal/api/types.go`
- Create: `e3-cloudnas/internal/api/server.go`
- Create: `e3-cloudnas/internal/api/server_test.go`
- Create: `e3-cloudnas/internal/mount/mounter.go`
- Create: `e3-cloudnas/internal/mount/manager.go`
- Create: `e3-cloudnas/internal/discovery/write.go`
- Create: `e3-cloudnas/cmd/e3-cloudnas/main.go` (HTTP only; mount backend = fake for this task)

**Interfaces:**
- Produces: `api.MountRequest` with fields matching spec (`mount_id`, `drive_letter`, `bucket`, `prefix`, `endpoint`, `region`, `access_key`, `secret_key`, `cache_mode`, `read_only`, `volume_label`)
- Produces: `mount.Mounter` interface:
  - `Mount(ctx context.Context, req api.MountRequest) error`
  - `Unmount(ctx context.Context, driveLetter string) error`
  - `List() []api.MountStatus`
- Produces: `discovery.Write(programData string, port int, version string) (token string, err error)` writing `cloudnas.discovery` + `cloudnas.token`
- Produces: header constant `X-E3-CloudNAS-Token`

- [ ] **Step 1: Create module + LICENSE + README**

```bash
mkdir -p e3-cloudnas/{cmd/e3-cloudnas,internal/{api,mount,discovery}}
# Copy GPLv3 into e3-cloudnas/LICENSE
cd e3-cloudnas && go mod init github.com/ezbsystems/e3-cloudnas
```

README must state: GPLv3; separate from proprietary e3-backup-agent; contact for corresponding source.

- [ ] **Step 2: Write failing API tests**

```go
// e3-cloudnas/internal/api/server_test.go
package api_test

func TestHealthRequiresToken(t *testing.T) { /* GET /health without token → 401 */ }
func TestMountUnmountWithFakeMounter(t *testing.T) {
  // POST /mount with token → fake records drive
  // GET /status → one mount
  // POST /unmount → empty
}
func TestMountRejectsMissingDrive(t *testing.T) { /* 400 */ }
```

- [ ] **Step 3: Run tests — expect fail**

```bash
cd e3-cloudnas && go test ./internal/api/ -count=1
```

Expected: FAIL (package/types missing)

- [ ] **Step 4: Implement types, fake-capable manager, server, discovery writer, main**

`manager.go` holds `map[string]*activeMount` mutex-protected; `FakeMounter` in tests implements `Mounter` by mutating a map only (no WinFsp).

`server.go` binds `127.0.0.1:0`, uses token from constructor, routes:
- `GET /health` → `{ok, version, winfsp: false}` for now
- `GET /status`, `POST /mount`, `POST /unmount`

`main.go` for this task: listen, write discovery under `os.Getenv("ProgramData")` or `-program-data` flag, serve until signal.

- [ ] **Step 5: Run tests — expect pass**

```bash
cd e3-cloudnas && go test ./... -count=1
```

Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add e3-cloudnas
git commit -m "$(cat <<'EOF'
Add GPLv3 e3-cloudnas scaffold with authenticated loopback API.

EOF
)"
```

---

### Task 2: WinFsp/rclone mount backend in sidecar

**Files:**
- Create: `e3-cloudnas/internal/mount/winfsp_windows.go`
- Create: `e3-cloudnas/internal/mount/stub_other.go`
- Modify: `e3-cloudnas/cmd/e3-cloudnas/main.go` (wire real mounter on windows,cgo)
- Modify: `e3-cloudnas/go.mod` (rclone v1.67.0, cgofuse)
- Create: `e3-cloudnas/Makefile` with `build` (linux stub) and `build-windows` notes

**Interfaces:**
- Consumes: `api.MountRequest`, `mount.Mounter`
- Produces: `mount.NewWinFspMounter(version string) (Mounter, error)` on `windows,cgo`
- Produces: `/health.winfsp == true` when WinFsp DLL/driver detectable

- [ ] **Step 1: Add dependencies**

```bash
cd e3-cloudnas
go get github.com/rclone/rclone@v1.67.0
go get github.com/winfsp/cgofuse@latest
```

- [ ] **Step 2: Implement `winfsp_windows.go`**

Mirror rclone `cmd/cmount` pattern:

1. Build S3 `fs.Fs` with Ceph-compatible options (same keys as current agent `startNASWebDAV`: `provider=Ceph`, `force_path_style=true`, `no_check_bucket=true`, chunk settings).
2. `vfs.New(f, &vfsOpt)` from `cache_mode` / `read_only`.
3. Mount with cgofuse host to `driveLetter + ":"` (no trailing slash), `VolumeName` = `volume_label`.
4. Store cancel/unmount func in manager keyed by drive letter.
5. Never log `secret_key` or full `access_key`.

Detection helper: check `C:\Program Files (x86)\WinFsp\bin\winfsp-x64.dll` or registry uninstall key; surface in `/health`.

- [ ] **Step 3: `stub_other.go`**

```go
//go:build !windows || !cgo

func NewWinFspMounter(version string) (Mounter, error) {
  return nil, fmt.Errorf("WinFsp mount only supported on windows with cgo")
}
```

- [ ] **Step 4: Document Windows build**

In `e3-cloudnas/README.md` and `Makefile`:

```makefile
# Must run on Windows with WinFsp Developer package installed:
#   set CGO_ENABLED=1
#   go build -trimpath -ldflags="-s -w" -o bin/e3-cloudnas.exe ./cmd/e3-cloudnas
```

- [ ] **Step 5: Manual smoke on Windows build host (checklist in commit message)**

1. Install WinFsp Core+Developer on build VM.
2. Build `e3-cloudnas.exe`.
3. Run exe; confirm `cloudnas.discovery` appears.
4. `curl` `/health` with token → `winfsp: true`.
5. Mount a test bucket to `Z:`; Explorer free space ≠ C:; copy a file.

- [ ] **Step 6: Commit**

```bash
git add e3-cloudnas
git commit -m "$(cat <<'EOF'
Implement WinFsp/rclone VFS mounts in e3-cloudnas sidecar.

EOF
)"
```

---

### Task 3: Agent sidecar client (discover + mount/unmount)

**Files:**
- Create: `e3-backup-agent/internal/agent/nas_sidecar.go`
- Create: `e3-backup-agent/internal/agent/nas_sidecar_windows.go`
- Create: `e3-backup-agent/internal/agent/nas_sidecar_stub.go`
- Create: `e3-backup-agent/internal/agent/nas_sidecar_test.go` (httptest fake server)

**Interfaces:**
- Produces: `var ErrCloudNASSidecarMissing = errors.New("cloudnas_sidecar_missing")` (and message helpers)
- Produces: `func (r *Runner) sidecarHealth(ctx context.Context) error`
- Produces: `func (r *Runner) sidecarMount(ctx context.Context, payload MountNASPayload, volumeLabel string) error`
- Produces: `func (r *Runner) sidecarUnmount(ctx context.Context, driveLetter string) error`
- Discovery paths: `filepath.Join(programData, "E3Backup", "cloudnas.discovery")` and `cloudnas.token`

- [ ] **Step 1: Write failing client tests**

```go
func TestSidecarMountPostsJSON(t *testing.T) {
  // httptest returns 200 for /mount; assert Authorization header and body fields
}
func TestSidecarMissingDiscovery(t *testing.T) {
  // empty ProgramData → error wrapping cloudnas_sidecar_missing
}
```

- [ ] **Step 2: Run — expect fail**

```bash
cd e3-backup-agent && go test ./internal/agent/ -run Sidecar -count=1
```

- [ ] **Step 3: Implement client**

Read discovery JSON `{ "listen_addr": "127.0.0.1:PORT", "pid", "version" }` and token file; HTTP client with 60s timeout for mount; map HTTP 503/`winfsp` failures to `winfsp_missing`.

Do **not** import rclone cmount or cgofuse in this package.

- [ ] **Step 4: Tests pass + commit**

```bash
git add e3-backup-agent/internal/agent/nas_sidecar*.go
git commit -m "$(cat <<'EOF'
Add agent HTTP client for e3-cloudnas sidecar discovery and mounts.

EOF
)"
```

---

### Task 4: Switch agent Cloud NAS path from WebDAV to sidecar

**Files:**
- Modify: `e3-backup-agent/internal/agent/nas.go` (`ensurePreparedNASMount`, `stopPreparedNASMount`, `startNASWebDAV` → replace/remove)
- Modify: `e3-backup-agent/internal/agent/nas_tray_control_windows.go` (stop requiring WebDAV TargetURL/port for success path)
- Keep debug NDJSON helpers until Task 8 verification passes; stop calling them from the live mount path once WebDAV is gone

**Interfaces:**
- Consumes: `sidecarMount` / `sidecarUnmount` from Task 3
- `NASMount` struct: drop `WebDAV *http.Server`, `VFS`, `ServerPort`, `TargetURL` or leave unused fields cleared; track `MountID`, `DriveLetter`, `BucketName`, etc.

- [ ] **Step 1: Change `ensurePreparedNASMount`**

Replace `startNASWebDAV` with:

```go
if err := r.sidecarHealth(ctx); err != nil {
  return nil, err // wrap as cloudnas_sidecar_missing / not_running
}
label := fmt.Sprintf("Cloud NAS (%s)", payload.Bucket)
if err := r.sidecarMount(ctx, payload, label); err != nil {
  return nil, err
}
// record NASMount without WebDAV server
```

- [ ] **Step 2: Change `stopPreparedNASMount`**

Call `sidecarUnmount` instead of WebDAV shutdown + tray WebDAV unmap. Keep WHMCS status updates elsewhere unchanged.

- [ ] **Step 3: Map errors to `UpdateNASMountStatus`**

When mount fails, status error string must start with stable code prefix, e.g. `cloudnas_sidecar_missing: Cloud NAS component is not installed or not running`.

- [ ] **Step 4: Compile agent (linux + windows cross)**

```bash
cd e3-backup-agent
go test ./internal/agent/ -count=1
make build-windows
```

Expected: success, `CGO_ENABLED=0` unchanged in Makefile.

- [ ] **Step 5: Commit**

```bash
git add e3-backup-agent/internal/agent/nas.go e3-backup-agent/internal/agent/nas_*.go
git commit -m "$(cat <<'EOF'
Route Cloud NAS mounts through e3-cloudnas sidecar instead of WebDAV.

EOF
)"
```

---

### Task 5: Tray — ensure sidecar running; remove WebDAV mapping

**Files:**
- Modify: `e3-backup-agent/cmd/tray/cloudnas_control_windows.go`
- Modify: `e3-backup-agent/cmd/tray/main_windows.go` (menu copy if needed)

**Interfaces:**
- Produces: `ensureCloudNASSidecarRunning() error` — if `e3-cloudnas.exe` exists under `{autopf}\E3Backup\CloudNAS\` (or install path from registry) and discovery stale/missing, `exec.Command` start (hidden window) then wait up to 10s for discovery file
- Removes: `mapCloudNASDrive` WebDAV UNC / `DavWWWRoot` / MountPoints2 WebDAV label paths from the success path

- [ ] **Step 1: Implement ensure-running**

Use same ProgramData discovery; if health fails and binary present, start process as current user (not elevated).

- [ ] **Step 2: Strip WebDAV mount/unmount**

Register/unregister menu items may remain as “status only” or be removed; unmount must call agent-facing flow or sidecar `/unmount` directly with token (prefer agent owns unmount via existing command path).

- [ ] **Step 3: Manual tray check on Windows**

Kill sidecar → tray ensure restarts it → agent mount succeeds.

- [ ] **Step 4: Commit**

```bash
git add e3-backup-agent/cmd/tray/
git commit -m "$(cat <<'EOF'
Start Cloud NAS sidecar from tray; remove WebDAV drive mapping.

EOF
)"
```

---

### Task 6: Installers — bundle WinFsp + optional Cloud NAS

**Files:**
- Create: `e3-cloudnas/installer/e3-cloudnas.iss`
- Modify: `e3-backup-agent/installer/e3-backup-agent.iss`
- Modify: `e3-backup-agent/installer/README.md`
- Modify: `accounts/modules/addons/cloudstorage/lib/Admin/AgentBuild/Steps/WindowsStage.php`
- Add build-host note for fetching WinFsp MSI into `e3-cloudnas/installer/redist/winfsp.msi` (git-lfs or CI cache; do not commit multi-MB MSI if policy forbids — document fetch URL + checksum in README)

**Interfaces:**
- Sidecar install dir: `{autopf}\E3Backup\CloudNAS\`
- HKCU Run value `E3CloudNAS` → `e3-cloudnas.exe`
- Nested from agent: `[Tasks] Name: "cloudnas"; Description: "Install Cloud NAS (maps cloud buckets as drives)"; Flags: checkedonce`
- `[Run]` when task selected: install WinFsp Core `msiexec /i ... /qn` then install sidecar files / run sidecar iss with `/VERYSILENT`

- [ ] **Step 1: Write `e3-cloudnas.iss`**

Files: `e3-cloudnas.exe`, `LICENSE`, WinFsp MSI under `{tmp}` then `msiexec`. Ignore failure if WinFsp already installed (log and continue). Document reboot-required case in README.

- [ ] **Step 2: Wire agent `.iss` optional task**

Only include Cloud NAS sources if staged (`#ifdef` or always ship redist folder).

- [ ] **Step 3: Update `WindowsStage.php`**

Upload `e3-cloudnas/bin/e3-cloudnas.exe`, `e3-cloudnas/installer/*`, WinFsp MSI when present; create remote `CloudNAS\` staging dirs.

- [ ] **Step 4: Extend Agent Builds Windows build**

Add pipeline step or Makefile target run **on Windows host** to `CGO_ENABLED=1 go build` sidecar (cannot cross-compile from Linux). If current `WindowsBuild` only SCPs Linux-cross agent binaries, add `WindowsBuildCloudNAS` remote compile step.

- [ ] **Step 5: Commit**

```bash
git add e3-cloudnas/installer e3-backup-agent/installer accounts/modules/addons/cloudstorage/lib/Admin/AgentBuild/
git commit -m "$(cat <<'EOF'
Bundle optional Cloud NAS sidecar and WinFsp Core in agent installer.

EOF
)"
```

---

### Task 7: WHMCS UI + docs for sidecar missing

**Files:**
- Modify: `accounts/modules/addons/cloudstorage/templates/partials/cloudnas_drives.tpl` (and/or mount wizard)
- Modify: `accounts/modules/addons/cloudstorage/docs/CLOUD_NAS.md`
- Modify: spec status line to Approved
- Optionally: `BETA_KNOWN_LIMITATIONS.md` — remove “WebDAV capacity mirrors C:” once fixed; until then note “fixed in sidecar builds”

- [ ] **Step 1: Detect error prefix in Alpine/UI**

If mount `error` contains `cloudnas_sidecar_missing` or `winfsp_missing`, show banner:

“Cloud NAS requires the optional Cloud NAS component. Re-run the E3 Backup Agent installer and keep “Install Cloud NAS” checked.”

- [ ] **Step 2: Rewrite CLOUD_NAS.md**

Replace WebDAV/WinFsp-not-required sections with sidecar architecture, installer option, Statfs capacity behavior, license split.

- [ ] **Step 3: Commit**

```bash
git add accounts/modules/addons/cloudstorage/templates accounts/modules/addons/cloudstorage/docs
git commit -m "$(cat <<'EOF'
Document Cloud NAS sidecar and surface install guidance in UI.

EOF
)"
```

---

### Task 8: End-to-end verification (debug mode gate)

**Files:**
- Modify: remove WebDAV debug instrumentation from `nas.go` only after verification
- Use: `e3-backup-agent/scripts/cloudnas-capacity-debug.ps1` (adapt for non-WebDAV: compare C: vs Y: free; expect **mismatch**)

- [ ] **Step 1: Install signed/dev build with Cloud NAS checked on Windows 10 test PC**

- [ ] **Step 2: Mount bucket to Y:**

- [ ] **Step 3: Run capacity probe**

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File C:\temp\cloudnas-capacity-debug.ps1 Y
```

Update script: for WinFsp drives, `Get-PSDrive Y` should return free/total; assert `free_bytes_match == false` and `total_bytes_match == false` vs C:.

- [ ] **Step 4: Copy a file larger than C: free space onto Y:**

Expected: copy **succeeds**.

- [ ] **Step 5: Append verification NDJSON to debug log / chat**

Include Explorer screenshots or probe JSON proving Y: ≠ C:.

- [ ] **Step 6: Remove obsolete WebDAV server code + debug wrappers; commit**

```bash
git commit -m "$(cat <<'EOF'
Remove Cloud NAS WebDAV path after WinFsp sidecar verification.

EOF
)"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| WinFsp mount, not WebDAV | 2, 4 |
| GPLv3 sidecar separate process | 1–2 |
| Agent CGO=0 / no WinFsp link | 3–4 |
| Loopback API + token | 1, 3 |
| User-session mount | 2, 5, 6 (HKCU Run) |
| Bundled WinFsp, no manual install | 6 |
| Optional Inno checkbox default on | 6 |
| Sidecar missing errors + UI | 4, 7 |
| Docs update | 7 |
| Capacity ≠ C: + large write | 8 |
| Remove WebDAV after verify | 8 |
| Monorepo `e3-cloudnas/` | 1 |
| ~1 PiB default capacity | 2 |

## Placeholder / consistency self-review

- No TBD left for open product decisions (resolved in Global Constraints).
- Error code strings consistent: `cloudnas_sidecar_missing`, `cloudnas_sidecar_not_running`, `winfsp_missing`.
- Discovery paths consistent: `%ProgramData%\E3Backup\cloudnas.discovery` + `cloudnas.token`.
- Header name matches tray-era constant: `X-E3-CloudNAS-Token`.

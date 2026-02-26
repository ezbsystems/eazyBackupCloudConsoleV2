//go:build windows

package agent

import (
	"archive/zip"
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"time"
)

type driverBundleUploadMeta struct {
	Profile      string `json:"profile"`
	ArtifactURL  string `json:"artifact_url"`
	ArtifactPath string `json:"artifact_path"`
	DestBucketID int64  `json:"dest_bucket_id"`
	DestPrefix   string `json:"dest_prefix"`
	S3UserID     int64  `json:"s3_user_id"`
	SHA256       string `json:"sha256"`
	SizeBytes    int64  `json:"size_bytes"`
}

const maxDriverBundleUploadBytes = 100 << 20 // 100 MiB

func (r *Runner) captureAndUploadDriverBundles(ctx context.Context, run *NextRunResponse, runID string, finishedAt string) map[string]any {
	if r == nil || r.client == nil {
		return nil
	}
	results := map[string]any{}
	profiles := []struct {
		Name     string
		MaxBytes int64
	}{{Name: "essential", MaxBytes: maxDriverBundleUploadBytes}}
	if strings.EqualFold(strings.TrimSpace(os.Getenv("E3_CAPTURE_FULL_DRIVER_BUNDLE")), "1") {
		profiles = append(profiles, struct {
			Name     string
			MaxBytes int64
		}{Name: "full", MaxBytes: maxDriverBundleUploadBytes})
	}

	for _, p := range profiles {
		select {
		case <-ctx.Done():
			return results
		default:
		}
		existsResp, existsErr := r.client.DriverBundleExists(runID, p.Name)
		if existsErr == nil && existsResp != nil && existsResp.Exists {
			r.pushEvents(runID, RunEvent{
				Type:      "info",
				Level:     "info",
				MessageID: "DISK_IMAGE_DRIVER_BUNDLE_PRESENT",
				ParamsJSON: map[string]any{
					"profile":    p.Name,
					"object_key": existsResp.ObjectKey,
				},
			})
			continue
		}
		meta, err := r.captureAndUploadDriverBundleProfile(runID, p.Name, p.MaxBytes, finishedAt)
		if err != nil {
			reason := err.Error()
			if existsErr != nil {
				reason = "presence check failed; capture attempt failed: " + err.Error()
			}
			log.Printf("agent: driver bundle capture [%s] skipped: %v", p.Name, err)
			r.pushEvents(runID, RunEvent{
				Type:      "error",
				Level:     "error",
				MessageID: "DISK_IMAGE_DRIVER_BUNDLE_SKIPPED",
				ParamsJSON: map[string]any{
					"profile": p.Name,
					"reason":  reason,
					"summary": "Driver bundle capture failed - contact support.",
				},
			})
			_ = r.client.UpdateRun(RunUpdate{
				RunID:      runID,
				LogExcerpt: "Driver bundle capture failed - contact support.",
			})
			continue
		}
		results[p.Name] = meta
		r.pushEvents(runID, RunEvent{
			Type:      "info",
			Level:     "info",
			MessageID: "DISK_IMAGE_DRIVER_BUNDLE_READY",
			ParamsJSON: map[string]any{
				"profile":      meta.Profile,
				"artifact_url": meta.ArtifactURL,
				"size_bytes":   meta.SizeBytes,
			},
		})
	}
	if len(results) == 0 {
		return nil
	}
	return results
}

func (r *Runner) captureAndUploadDriverBundleProfile(runID string, profile string, maxBytes int64, finishedAt string) (*driverBundleUploadMeta, error) {
	workDir, err := os.MkdirTemp("", "e3-driver-bundle-*")
	if err != nil {
		return nil, err
	}
	defer os.RemoveAll(workDir)

	exportDir := filepath.Join(workDir, "export")
	if err := os.MkdirAll(exportDir, 0o755); err != nil {
		return nil, err
	}
	if err := exportDriverProfile(profile, exportDir); err != nil {
		return nil, err
	}
	zipBytes, err := zipDirectory(exportDir)
	if err != nil {
		return nil, err
	}
	if int64(len(zipBytes)) > maxBytes {
		return nil, fmt.Errorf("bundle too large (%d bytes > %d bytes)", len(zipBytes), maxBytes)
	}
	artifactName := fmt.Sprintf("drivers-%s-%d.zip", profile, time.Now().Unix())
	uploaded, err := r.client.UploadDriverBundle(runID, profile, artifactName, zipBytes, finishedAt)
	if err != nil {
		return nil, err
	}
	return &driverBundleUploadMeta{
		Profile:      uploaded.Profile,
		ArtifactURL:  uploaded.ArtifactURL,
		ArtifactPath: uploaded.ArtifactPath,
		DestBucketID: uploaded.DestBucketID,
		DestPrefix:   uploaded.DestPrefix,
		S3UserID:     uploaded.S3UserID,
		SHA256:       uploaded.SHA256,
		SizeBytes:    uploaded.SizeBytes,
	}, nil
}

func exportDriverProfile(profile, exportDir string) error {
	profile = strings.ToLower(strings.TrimSpace(profile))
	psFilter := `$drivers = Get-CimInstance Win32_PnPSignedDriver -ErrorAction SilentlyContinue | Where-Object { $_.InfName -and $_.InfName -ne '' }`
	if profile == "essential" {
		psFilter = `$targetClasses = @('NET','SCSIAdapter','HDC','System','NetService')
$targetClassGuids = @(
  '{4d36e972-e325-11ce-bfc1-08002be10318}', # Net
  '{4d36e97b-e325-11ce-bfc1-08002be10318}', # SCSIAdapter
  '{4d36e96a-e325-11ce-bfc1-08002be10318}', # HDC
  '{4d36e97d-e325-11ce-bfc1-08002be10318}'  # System
)

# 1) Collect currently installed/bound PnP drivers for core network/storage classes.
$drivers = @(Get-CimInstance Win32_PnPSignedDriver -ErrorAction SilentlyContinue | Where-Object {
  $_.InfName -and (
    ($targetClasses -contains $_.DeviceClass) -or
    ($_.ClassGuid -and ($targetClassGuids -contains $_.ClassGuid.ToLower()))
  )
})

# 2) Also include staged third-party packages from driver store for these classes.
# This helps when the active adapter is not currently bound (eg. alternate Intel NIC model),
# but the OEM package exists and should be available in recovery media.
$oemStore = @()
try {
  $oemStore = @(Get-WindowsDriver -Online -ErrorAction SilentlyContinue | Where-Object {
    $_.OriginalFileName -and
    $_.OriginalFileName -match '^oem\d+\.inf$' -and
    $_.ClassName -and
    ($targetClasses -contains $_.ClassName)
  })
} catch {}

$infs = @{}
foreach ($d in $drivers) {
  if ($d.InfName) { $infs[$d.InfName.ToLower()] = $true }
}
foreach ($d in $oemStore) {
  if ($d.OriginalFileName) { $infs[$d.OriginalFileName.ToLower()] = $true }
}
$drivers = @($infs.Keys | ForEach-Object { [pscustomobject]@{ InfName = $_ } })`
	}

	script := fmt.Sprintf(`
$ErrorActionPreference = 'Stop'
$outDir = %q
New-Item -ItemType Directory -Path $outDir -Force | Out-Null
%s
$infs = @($drivers | Select-Object -ExpandProperty InfName -Unique | Where-Object { $_ -and $_ -match '\.inf$' })
if ($infs.Count -eq 0) { throw 'No matching installed INF packages found' }
foreach ($inf in $infs) {
  try {
    pnputil /export-driver $inf $outDir | Out-Null
  } catch {}
}
$copied = @(Get-ChildItem -Path $outDir -Recurse -File -Filter *.inf -ErrorAction SilentlyContinue)
if ($copied.Count -eq 0) { throw 'No INF files exported for selected profile' }
Write-Output ('exported=' + $copied.Count)
`, exportDir, psFilter)

	cmd := exec.Command("powershell.exe", "-NoProfile", "-Command", script)
	var out bytes.Buffer
	cmd.Stdout = &out
	cmd.Stderr = &out
	if err := cmd.Run(); err != nil {
		return fmt.Errorf("export drivers failed: %v (%s)", err, strings.TrimSpace(out.String()))
	}
	return nil
}

func zipDirectory(root string) ([]byte, error) {
	buf := &bytes.Buffer{}
	zw := zip.NewWriter(buf)
	err := filepath.Walk(root, func(path string, info os.FileInfo, walkErr error) error {
		if walkErr != nil {
			return walkErr
		}
		if info == nil || info.IsDir() {
			return nil
		}
		rel, err := filepath.Rel(root, path)
		if err != nil {
			return err
		}
		rel = filepath.ToSlash(rel)
		h, err := zip.FileInfoHeader(info)
		if err != nil {
			return err
		}
		h.Name = rel
		h.Method = zip.Deflate
		w, err := zw.CreateHeader(h)
		if err != nil {
			return err
		}
		f, err := os.Open(path)
		if err != nil {
			return err
		}
		defer f.Close()
		if _, err := io.Copy(w, f); err != nil {
			return err
		}
		return nil
	})
	if err != nil {
		_ = zw.Close()
		return nil, err
	}
	if err := zw.Close(); err != nil {
		return nil, err
	}
	return buf.Bytes(), nil
}

func marshalDriverBundlesForStats(data map[string]any) map[string]any {
	if len(data) == 0 {
		return nil
	}
	// round-trip to guarantee map is JSON-safe primitives.
	raw, err := json.Marshal(data)
	if err != nil {
		return nil
	}
	out := map[string]any{}
	if err := json.Unmarshal(raw, &out); err != nil {
		return nil
	}
	return out
}

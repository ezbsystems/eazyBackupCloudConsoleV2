// Package selfupdate implements the agent side of the remote-update mechanism.
//
// The flow is intentionally simple and matches the existing out-of-process
// restart pattern used by reset_agent: the running service can never reliably
// replace its own executable or restart itself, so the actual swap+restart is
// delegated to an external orchestrator (a SYSTEM scheduled task running the
// signed Inno installer on Windows, or a detached `systemctl restart` on
// Linux). This package downloads and verifies the artifact, then hands off to
// that orchestrator.
//
// This package must not import internal/agent (the agent package imports this
// one), so it stays self-contained and decoupled via the ProgressFunc callback.
package selfupdate

import (
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"io"
	"net/http"
	"os"
	"path/filepath"
	"runtime"
	"strings"
	"time"
)

// Spec describes a single update to apply, as delivered by the server in the
// agent_update command payload.
type Spec struct {
	JobID       int64  // s3_agent_update_jobs.id, used for progress reporting
	Version     string // target version_label
	DownloadURL string // absolute URL to the artifact (installer .exe / linux binary)
	SHA256      string // expected lowercase hex sha256 of the artifact
	SizeBytes   int64  // expected size in bytes (0 = unknown / skip check)
	Platform    string // "windows" | "linux"
}

// ProgressFunc receives update lifecycle states (downloading|verifying|applying)
// with a short human-readable detail string. Implementations should be
// non-blocking and tolerant of being called from the update goroutine.
type ProgressFunc func(state, detail string)

func (s Spec) progress(p ProgressFunc, state, detail string) {
	if p != nil {
		p(state, detail)
	}
}

// Run downloads and verifies the artifact described by spec, then applies it.
//
// On success, the apply step schedules (Windows) or performs (Linux) a swap and
// service restart that will terminate this process; callers should report the
// "applying" state to the server and then exit promptly. A non-nil error means
// the update did not start and the caller should mark the job failed.
func Run(spec Spec, progress ProgressFunc) error {
	if strings.TrimSpace(spec.DownloadURL) == "" {
		return fmt.Errorf("selfupdate: empty download URL")
	}
	if strings.TrimSpace(spec.SHA256) == "" {
		return fmt.Errorf("selfupdate: missing expected sha256")
	}

	dir, err := stagingDir()
	if err != nil {
		return fmt.Errorf("selfupdate: staging dir: %w", err)
	}

	artifactPath := filepath.Join(dir, artifactFilename(spec))
	spec.progress(progress, "downloading", "Downloading update "+spec.Version)
	if err := download(spec.DownloadURL, artifactPath); err != nil {
		return fmt.Errorf("selfupdate: download: %w", err)
	}

	spec.progress(progress, "verifying", "Verifying download integrity")
	if err := verify(artifactPath, spec); err != nil {
		_ = os.Remove(artifactPath)
		return fmt.Errorf("selfupdate: verify: %w", err)
	}

	spec.progress(progress, "applying", "Applying update and restarting service")
	if err := apply(artifactPath, spec); err != nil {
		return fmt.Errorf("selfupdate: apply: %w", err)
	}
	return nil
}

// stagingDir returns a writable per-OS directory used to hold the downloaded
// artifact before it is applied.
func stagingDir() (string, error) {
	var dir string
	if runtime.GOOS == "windows" {
		pd := os.Getenv("ProgramData")
		if pd == "" {
			pd = `C:\ProgramData`
		}
		dir = filepath.Join(pd, "E3Backup", "update")
	} else {
		dir = "/var/lib/e3-backup-agent/update"
	}
	if err := os.MkdirAll(dir, 0o755); err != nil {
		return "", err
	}
	return dir, nil
}

func artifactFilename(spec Spec) string {
	if runtime.GOOS == "windows" {
		return "e3-backup-agent-setup.exe"
	}
	return "e3-backup-agent-linux"
}

// download streams the URL to dst, replacing any existing file. A generous
// timeout is used because the installer can be tens of MB.
func download(url, dst string) error {
	client := &http.Client{Timeout: 15 * time.Minute}
	req, err := http.NewRequest(http.MethodGet, url, nil)
	if err != nil {
		return err
	}
	req.Header.Set("User-Agent", "e3-backup-agent-updater")

	resp, err := client.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("unexpected status %d downloading %s", resp.StatusCode, url)
	}

	tmp := dst + ".part"
	f, err := os.OpenFile(tmp, os.O_CREATE|os.O_TRUNC|os.O_WRONLY, 0o755)
	if err != nil {
		return err
	}
	if _, err := io.Copy(f, resp.Body); err != nil {
		_ = f.Close()
		_ = os.Remove(tmp)
		return err
	}
	if err := f.Close(); err != nil {
		_ = os.Remove(tmp)
		return err
	}
	if err := os.Rename(tmp, dst); err != nil {
		_ = os.Remove(tmp)
		return err
	}
	return nil
}

// verify checks the downloaded artifact's size (when known) and sha256 against
// the values the server published for the release.
func verify(path string, spec Spec) error {
	info, err := os.Stat(path)
	if err != nil {
		return err
	}
	if spec.SizeBytes > 0 && info.Size() != spec.SizeBytes {
		return fmt.Errorf("size mismatch: got %d want %d", info.Size(), spec.SizeBytes)
	}

	f, err := os.Open(path)
	if err != nil {
		return err
	}
	defer f.Close()

	h := sha256.New()
	if _, err := io.Copy(h, f); err != nil {
		return err
	}
	got := hex.EncodeToString(h.Sum(nil))
	want := strings.ToLower(strings.TrimSpace(spec.SHA256))
	if !strings.EqualFold(got, want) {
		return fmt.Errorf("sha256 mismatch: got %s want %s", got, want)
	}
	return nil
}

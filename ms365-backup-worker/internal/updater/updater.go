package updater

import (
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"io"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"syscall"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
)

const (
	legacyInstallPath     = "/usr/local/bin/ms365-backup-worker"
	defaultInstallPath    = "/var/lib/ms365-backup-worker/bin/ms365-backup-worker"
	installPathEnv        = "MS365_WORKER_INSTALL_PATH"
	stagingFileName       = "ms365-backup-worker.new"
	defaultUpdateReserveMiB = 256
)

type Offer struct {
	Version            string
	Sha256             string
	DownloadURL        string
	ArtifactSizeBytes  int64
	UpdateReserveMiB   int64
}

// ResolveInstallPath returns the canonical binary path (does not require it to be writable).
func ResolveInstallPath(preferred string) string {
	candidates := []string{}
	if preferred != "" {
		candidates = append(candidates, preferred)
	}
	if exe, err := os.Executable(); err == nil && exe != "" {
		candidates = append(candidates, strings.TrimSuffix(exe, ".new"))
	}
	candidates = append(candidates, defaultInstallPath, legacyInstallPath)

	seen := map[string]struct{}{}
	for _, path := range candidates {
		if path == "" {
			continue
		}
		if _, ok := seen[path]; ok {
			continue
		}
		seen[path] = struct{}{}
		if _, err := os.Stat(path); err == nil {
			return path
		}
	}
	return defaultInstallPath
}

func stagingDirs(canonicalPath string) []string {
	seen := map[string]struct{}{}
	var dirs []string
	for _, dir := range []string{
		filepath.Dir(canonicalPath),
		filepath.Dir(filepath.Dir(canonicalPath)),
		"/var/lib/ms365-backup-worker",
		os.TempDir(),
	} {
		if dir == "" {
			continue
		}
		if _, ok := seen[dir]; ok {
			continue
		}
		seen[dir] = struct{}{}
		dirs = append(dirs, dir)
	}
	return dirs
}

func dirWritable(dir string) bool {
	if st, err := os.Stat(dir); err != nil || !st.IsDir() {
		return false
	}
	probe := filepath.Join(dir, ".ms365-write-test")
	f, err := os.OpenFile(probe, os.O_CREATE|os.O_WRONLY, 0644)
	if err != nil {
		return false
	}
	_ = f.Close()
	_ = os.Remove(probe)
	return true
}

func stagingPathForInstall(canonicalPath string) (string, error) {
	for _, dir := range stagingDirs(canonicalPath) {
		if !dirWritable(dir) {
			continue
		}
		return filepath.Join(dir, stagingFileName), nil
	}
	return "", fmt.Errorf("no writable staging directory for update (tried %v)", stagingDirs(canonicalPath))
}

func stagingFreeBytes(stagingPath string) (int64, error) {
	var st syscall.Statfs_t
	if err := syscall.Statfs(filepath.Dir(stagingPath), &st); err != nil {
		return 0, err
	}
	return int64(st.Bavail) * int64(st.Bsize), nil
}

func requiredStagingBytes(offer Offer) int64 {
	reserve := offer.UpdateReserveMiB
	if reserve <= 0 {
		reserve = defaultUpdateReserveMiB
	}
	return offer.ArtifactSizeBytes + (reserve << 20)
}

func checkStagingHeadroom(stagingPath string, offer Offer) error {
	free, err := stagingFreeBytes(stagingPath)
	if err != nil {
		return fmt.Errorf("stat staging filesystem: %w", err)
	}
	required := requiredStagingBytes(offer)
	if free < required {
		return fmt.Errorf("insufficient staging headroom: need %d bytes, have %d free", required, free)
	}
	return nil
}

func Apply(token string, offer Offer, installPath string) error {
	if offer.DownloadURL == "" || offer.Sha256 == "" {
		return fmt.Errorf("incomplete update offer")
	}
	canonical := ResolveInstallPath(installPath)
	stagingPath, err := stagingPathForInstall(canonical)
	if err != nil {
		return err
	}
	if err := checkStagingHeadroom(stagingPath, offer); err != nil {
		_ = os.Remove(stagingPath)
		return err
	}

	req, err := http.NewRequest(http.MethodGet, offer.DownloadURL, nil)
	if err != nil {
		return err
	}
	req.Header.Set("X-MS365-Worker-Token", token)

	client := &http.Client{Timeout: 10 * time.Minute}
	resp, err := client.Do(req)
	if err != nil {
		_ = os.Remove(stagingPath)
		return err
	}
	defer resp.Body.Close()
	if resp.StatusCode >= 400 {
		body, _ := io.ReadAll(io.LimitReader(resp.Body, 4096))
		_ = os.Remove(stagingPath)
		return fmt.Errorf("artifact download http %d: %s", resp.StatusCode, strings.TrimSpace(string(body)))
	}

	sum, err := writeStagingArtifact(stagingPath, resp.Body)
	if err != nil {
		_ = os.Remove(stagingPath)
		return err
	}
	if !strings.EqualFold(sum, offer.Sha256) {
		os.Remove(stagingPath)
		return fmt.Errorf("sha256 mismatch: got %s want %s", sum, offer.Sha256)
	}
	if err := os.Chmod(stagingPath, 0755); err != nil {
		os.Remove(stagingPath)
		return err
	}

	_ = os.Setenv(installPathEnv, canonical)
	return RestartSelf(stagingPath)
}

func writeStagingArtifact(stagingPath string, body io.Reader) (string, error) {
	f, err := os.OpenFile(stagingPath, os.O_CREATE|os.O_TRUNC|os.O_WRONLY, 0755)
	if err != nil {
		return "", err
	}
	hasher := sha256.New()
	writer := io.MultiWriter(f, hasher)
	if _, err := io.Copy(writer, body); err != nil {
		f.Close()
		os.Remove(stagingPath)
		return "", err
	}
	if err := f.Close(); err != nil {
		os.Remove(stagingPath)
		return "", err
	}
	return hex.EncodeToString(hasher.Sum(nil)), nil
}

// FinalizePendingInstall swaps a staged .new binary into the canonical install path after exec.
func FinalizePendingInstall() {
	exe, err := os.Executable()
	if err != nil || !strings.HasSuffix(exe, ".new") {
		return
	}
	canonical := strings.TrimSpace(os.Getenv(installPathEnv))
	if canonical == "" {
		canonical = defaultInstallPath
	}
	if err := os.MkdirAll(filepath.Dir(canonical), 0755); err != nil {
		fmt.Fprintf(os.Stderr, "finalize install mkdir: %v\n", err)
		return
	}
	_ = os.Remove(canonical)
	if err := os.Rename(exe, canonical); err != nil {
		if err := copyFile(exe, canonical); err != nil {
			fmt.Fprintf(os.Stderr, "finalize install swap: %v\n", err)
			return
		}
		_ = os.Remove(exe)
	}
	_ = os.Chmod(canonical, 0755)
}

func RestartSelf(installPath string) error {
	exe := installPath
	if exe == "" {
		var err error
		exe, err = os.Executable()
		if err != nil {
			return err
		}
	}
	args := os.Args
	env := os.Environ()
	return syscallExec(exe, args, env)
}

func OfferFromAPI(u *api.UpdateOffer, updateReserveMiB int) Offer {
	if u == nil {
		return Offer{}
	}
	reserve := u.UpdateReserveMiB
	if reserve <= 0 {
		reserve = int64(updateReserveMiB)
	}
	return Offer{
		Version:           u.Version,
		Sha256:            u.Sha256,
		DownloadURL:       u.DownloadURL,
		ArtifactSizeBytes: u.ArtifactSizeBytes,
		UpdateReserveMiB:  reserve,
	}
}

func copyFile(src, dst string) error {
	in, err := os.Open(src)
	if err != nil {
		return err
	}
	defer in.Close()
	out, err := os.OpenFile(dst, os.O_CREATE|os.O_TRUNC|os.O_WRONLY, 0755)
	if err != nil {
		return err
	}
	defer out.Close()
	_, err = io.Copy(out, in)
	return err
}

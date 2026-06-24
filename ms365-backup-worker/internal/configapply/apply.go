package configapply

import (
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"os"
	"path/filepath"
	"strconv"
	"strings"

	"github.com/eazybackup/ms365-backup-worker/internal/config"
	"gopkg.in/yaml.v3"
)

// writableStateDir is where applied-version markers and config staging files live.
// Fleet nodes keep config.yaml under /etc (directory not writable by ms365worker) while
// /var/lib/ms365-backup-worker is writable.
func writableStateDir(configPath string) string {
	if dir := strings.TrimSpace(os.Getenv("MS365_WORKER_STATE_DIR")); dir != "" {
		return dir
	}
	_ = configPath
	return "/var/lib/ms365-backup-worker"
}

func AppliedVersionPath(configPath string) string {
	return filepath.Join(writableStateDir(configPath), "config.applied_version")
}

func legacyAppliedVersionPath(configPath string) string {
	return configPath + ".applied_version"
}

func ReadAppliedVersion(configPath string) int {
	for _, path := range []string{AppliedVersionPath(configPath), legacyAppliedVersionPath(configPath)} {
		data, err := os.ReadFile(path)
		if err != nil {
			continue
		}
		v, err := strconv.Atoi(strings.TrimSpace(string(data)))
		if err != nil || v < 0 {
			continue
		}
		return v
	}
	return 0
}

func WriteAppliedVersion(configPath string, version int) error {
	if version <= 0 {
		return fmt.Errorf("invalid config version %d", version)
	}
	path := AppliedVersionPath(configPath)
	dir := filepath.Dir(path)
	if err := os.MkdirAll(dir, 0755); err != nil {
		return err
	}
	tmp, err := os.CreateTemp(dir, ".config-version-*")
	if err != nil {
		return err
	}
	tmpPath := tmp.Name()
	if _, err := fmt.Fprintf(tmp, "%d\n", version); err != nil {
		tmp.Close()
		os.Remove(tmpPath)
		return err
	}
	if err := tmp.Close(); err != nil {
		os.Remove(tmpPath)
		return err
	}
	return os.Rename(tmpPath, path)
}

// Apply validates YAML, verifies sha256, atomically writes configPath, and records version.
func Apply(configPath string, version int, wantSHA256 string, yamlBytes []byte) error {
	if version <= 0 {
		return fmt.Errorf("invalid config version %d", version)
	}
	if strings.TrimSpace(wantSHA256) == "" {
		return fmt.Errorf("missing config sha256")
	}
	got := sha256.Sum256(yamlBytes)
	gotHex := hex.EncodeToString(got[:])
	if !strings.EqualFold(gotHex, strings.TrimSpace(wantSHA256)) {
		return fmt.Errorf("sha256 mismatch: got %s want %s", gotHex, wantSHA256)
	}

	merged, err := mergeFleetYAML(configPath, yamlBytes)
	if err != nil {
		return err
	}

	if err := writeConfigFile(configPath, merged); err != nil {
		return err
	}
	if err := WriteAppliedVersion(configPath, version); err != nil {
		return fmt.Errorf("record applied version: %w", err)
	}
	return nil
}

func writeConfigFile(configPath string, data []byte) error {
	// Prefer same-directory atomic rename when the config dir is writable.
	dir := filepath.Dir(configPath)
	if err := os.MkdirAll(dir, 0755); err == nil {
		if tmp, err := os.CreateTemp(dir, ".config-*.yaml"); err == nil {
			tmpPath := tmp.Name()
			cleanup := func() { _ = os.Remove(tmpPath) }
			if _, err := tmp.Write(data); err != nil {
				tmp.Close()
				cleanup()
				return err
			}
			if err := tmp.Close(); err != nil {
				cleanup()
				return err
			}
			if err := os.Rename(tmpPath, configPath); err == nil {
				return nil
			}
			cleanup()
		}
	}

	// Fleet LXC: /etc/ms365-backup-worker is not writable but the existing config
	// file is group-writable — overwrite in place after staging in state dir.
	stagingDir := writableStateDir(configPath)
	if err := os.MkdirAll(stagingDir, 0755); err != nil {
		return err
	}
	tmp, err := os.CreateTemp(stagingDir, ".config-*.yaml")
	if err != nil {
		return err
	}
	tmpPath := tmp.Name()
	cleanup := func() { _ = os.Remove(tmpPath) }
	if _, err := tmp.Write(data); err != nil {
		tmp.Close()
		cleanup()
		return err
	}
	if err := tmp.Close(); err != nil {
		cleanup()
		return err
	}
	mode := os.FileMode(0640)
	if info, err := os.Stat(configPath); err == nil {
		mode = info.Mode().Perm()
	}
	if err := os.WriteFile(configPath, data, mode); err != nil {
		cleanup()
		return err
	}
	cleanup()
	return nil
}

// mergeFleetYAML overlays per-node identity and local secrets from the existing file
// so fleet templates without token/base_url/node identity still validate and apply.
func mergeFleetYAML(configPath string, fleetYAML []byte) ([]byte, error) {
	var incoming config.Config
	if err := yaml.Unmarshal(fleetYAML, &incoming); err != nil {
		return nil, fmt.Errorf("parse fleet yaml: %w", err)
	}
	if existing, err := config.Load(configPath); err == nil && existing != nil {
		if incoming.Worker.NodeID == "" {
			incoming.Worker.NodeID = existing.Worker.NodeID
		}
		if incoming.Worker.Hostname == "" {
			incoming.Worker.Hostname = existing.Worker.Hostname
		}
		if incoming.Worker.Token == "" {
			incoming.Worker.Token = existing.Worker.Token
		}
		if incoming.Worker.ProxmoxVmid <= 0 {
			incoming.Worker.ProxmoxVmid = existing.Worker.ProxmoxVmid
		}
		if incoming.API.BaseURL == "" {
			incoming.API.BaseURL = existing.API.BaseURL
		}
	}
	out, err := yaml.Marshal(&incoming)
	if err != nil {
		return nil, err
	}
	stagingDir := writableStateDir(configPath)
	if err := os.MkdirAll(stagingDir, 0755); err != nil {
		return nil, err
	}
	tmp, err := os.CreateTemp(stagingDir, ".config-merge-*.yaml")
	if err != nil {
		return nil, err
	}
	tmpPath := tmp.Name()
	if _, err := tmp.Write(out); err != nil {
		tmp.Close()
		os.Remove(tmpPath)
		return nil, err
	}
	if err := tmp.Close(); err != nil {
		os.Remove(tmpPath)
		return nil, err
	}
	defer os.Remove(tmpPath)
	if _, err := config.Load(tmpPath); err != nil {
		return nil, fmt.Errorf("merged config invalid: %w", err)
	}
	return out, nil
}

package agent

import (
	"crypto/rand"
	"fmt"
	"encoding/hex"
	"os"
	"strings"
)

// ensureDeviceIdentity populates stable identity fields if missing and persists them.
// This enables server-side re-enroll/rekey/reuse on the same device.
func (r *Runner) ensureDeviceIdentity() {
	changed := false

	if strings.TrimSpace(r.cfg.DeviceID) == "" {
		if id, err := newUUIDv4(); err == nil {
			r.cfg.DeviceID = id
			changed = true
		}
	}
	if strings.TrimSpace(r.cfg.InstallID) == "" {
		if id, err := newUUIDv4(); err == nil {
			r.cfg.InstallID = id
			changed = true
		}
	}
	if strings.TrimSpace(r.cfg.DeviceName) == "" {
		if hn, err := os.Hostname(); err == nil && strings.TrimSpace(hn) != "" {
			r.cfg.DeviceName = hn
			changed = true
		}
	}

	if !changed {
		return
	}

	// Persist identity early so it is stable even if enrollment fails.
	if r.configPath != "" {
		if err := r.cfg.Save(r.configPath); err != nil {
			// Non-fatal: continue with in-memory identity.
			// (Installer permissions, AV locks, etc. can prevent early writes.)
		}
	}

	// Rebuild client so enrollment calls include device fields.
	r.client = NewClient(r.cfg)
}

func newUUIDv4() (string, error) {
	var b [16]byte
	if _, err := rand.Read(b[:]); err != nil {
		return "", err
	}
	// Set version (4) and variant (RFC 4122).
	b[6] = (b[6] & 0x0f) | 0x40
	b[8] = (b[8] & 0x3f) | 0x80
	s := hex.EncodeToString(b[:])
	// 8-4-4-4-12
	return fmt.Sprintf("%s-%s-%s-%s-%s", s[0:8], s[8:12], s[12:16], s[16:20], s[20:32]), nil
}



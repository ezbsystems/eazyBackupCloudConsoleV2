package mount

import (
	"context"
	"fmt"
	"strings"
	"sync"

	"github.com/ezbsystems/e3-cloudnas/internal/api"
)

type activeMount struct {
	request api.MountRequest
	state   string
	errMsg  string
}

type Manager struct {
	mu     sync.RWMutex
	mounts map[string]*activeMount
}

func NewManager() *Manager {
	return &Manager{mounts: make(map[string]*activeMount)}
}

func normalizeDriveLetter(letter string) (string, error) {
	letter = strings.TrimSpace(letter)
	letter = strings.TrimSuffix(strings.ToUpper(letter), ":")
	if len(letter) != 1 || letter[0] < 'A' || letter[0] > 'Z' {
		return "", fmt.Errorf("invalid drive letter %q", letter)
	}
	return letter, nil
}

func (m *Manager) Mount(_ context.Context, req api.MountRequest) error {
	letter, err := normalizeDriveLetter(req.DriveLetter)
	if err != nil {
		return err
	}

	m.mu.Lock()
	defer m.mu.Unlock()
	m.mounts[letter] = &activeMount{
		request: req,
		state:   "mounted",
	}
	return nil
}

func (m *Manager) Unmount(_ context.Context, driveLetter string) error {
	letter, err := normalizeDriveLetter(driveLetter)
	if err != nil {
		return err
	}

	m.mu.Lock()
	defer m.mu.Unlock()
	delete(m.mounts, letter)
	return nil
}

func (m *Manager) List() []api.MountStatus {
	m.mu.RLock()
	defer m.mu.RUnlock()

	out := make([]api.MountStatus, 0, len(m.mounts))
	for letter, active := range m.mounts {
		out = append(out, api.MountStatus{
			MountID:     active.request.MountID,
			DriveLetter: letter,
			State:       active.state,
			Error:       active.errMsg,
		})
	}
	return out
}

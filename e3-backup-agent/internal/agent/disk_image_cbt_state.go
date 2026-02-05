package agent

import (
	"encoding/json"
	"os"
	"path/filepath"
	"strings"
	"time"
)

func loadCBTState(baseDir string, jobID int64, volumeKey string) *CBTState {
	path := cbtStatePath(baseDir, jobID, volumeKey)
	_ = os.MkdirAll(filepath.Dir(path), 0o755)
	data, err := os.ReadFile(path)
	if err != nil {
		return &CBTState{Path: path, JobID: jobID, Volume: volumeKey}
	}
	var state CBTState
	if jsonErr := json.Unmarshal(data, &state); jsonErr != nil {
		return &CBTState{Path: path, JobID: jobID, Volume: volumeKey}
	}
	if strings.TrimSpace(state.Volume) == "" {
		state.Volume = volumeKey
	}
	state.Path = path
	state.JobID = jobID
	return &state
}

func (s *CBTState) Save() error {
	if s == nil {
		return nil
	}
	s.UpdatedAt = time.Now().UTC().Format(time.RFC3339)
	if s.Path == "" {
		return nil
	}
	if s.Volume == "" {
		return nil
	}
	if s.LastUSN == 0 && s.JournalID == 0 {
		return nil
	}
	_ = os.MkdirAll(filepath.Dir(s.Path), 0o755)
	payload, err := json.MarshalIndent(s, "", "  ")
	if err != nil {
		return err
	}
	return os.WriteFile(s.Path, payload, 0o600)
}

func cbtStatePath(baseDir string, jobID int64, volumeKey string) string {
	key := normalizeCBTVolumeKey(volumeKey)
	if baseDir == "" {
		return filepath.Join(os.TempDir(), "e3backup_cbt_"+key+".json")
	}
	return filepath.Join(baseDir, "cache", "job_cache", "job_"+intToString(jobID)+"_cbt_"+key+".json")
}

func normalizeCBTVolumeKey(raw string) string {
	s := strings.TrimSpace(raw)
	s = strings.TrimSuffix(s, "\\")
	s = strings.TrimSuffix(s, "/")
	s = strings.TrimPrefix(strings.ToLower(s), `\\.\`)
	s = strings.ReplaceAll(s, ":", "")
	s = strings.ReplaceAll(s, "\\", "")
	s = strings.ReplaceAll(s, "/", "")
	if s == "" {
		s = "volume"
	}
	return s
}

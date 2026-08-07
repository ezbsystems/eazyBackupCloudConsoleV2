package discovery

import (
	"crypto/rand"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
)

type discoveryFile struct {
	Port    int    `json:"port"`
	PID     int    `json:"pid"`
	Version string `json:"version"`
}

func Write(programData string, port int, version string) (string, error) {
	if programData == "" {
		return "", fmt.Errorf("program data directory is required")
	}

	token, err := generateToken()
	if err != nil {
		return "", err
	}

	dir := filepath.Join(programData, "E3Backup")
	if err := os.MkdirAll(dir, 0o755); err != nil {
		return "", fmt.Errorf("create discovery directory: %w", err)
	}

	discovery := discoveryFile{
		Port:    port,
		PID:     os.Getpid(),
		Version: version,
	}
	discoveryBytes, err := json.Marshal(discovery)
	if err != nil {
		return "", fmt.Errorf("marshal discovery: %w", err)
	}

	discoveryPath := filepath.Join(dir, "cloudnas.discovery")
	if err := os.WriteFile(discoveryPath, discoveryBytes, 0o644); err != nil {
		return "", fmt.Errorf("write discovery file: %w", err)
	}

	tokenPath := filepath.Join(dir, "cloudnas.token")
	if err := os.WriteFile(tokenPath, []byte(token), 0o600); err != nil {
		return "", fmt.Errorf("write token file: %w", err)
	}

	return token, nil
}

func generateToken() (string, error) {
	buf := make([]byte, 32)
	if _, err := rand.Read(buf); err != nil {
		return "", fmt.Errorf("generate token: %w", err)
	}
	return hex.EncodeToString(buf), nil
}

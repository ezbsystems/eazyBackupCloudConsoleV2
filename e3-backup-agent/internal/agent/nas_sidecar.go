package agent

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"path/filepath"
	"strings"
	"time"
)

const sidecarTokenHeader = "X-E3-CloudNAS-Token"

const (
	sidecarMountTimeout   = 60 * time.Second
	sidecarDefaultTimeout = 10 * time.Second
)

var (
	ErrCloudNASSidecarMissing    = errors.New("cloudnas_sidecar_missing")
	ErrCloudNASSidecarNotRunning = errors.New("cloudnas_sidecar_not_running")
	ErrWinFspMissing             = errors.New("winfsp_missing")
)

type cloudNASSidecarDiscovery struct {
	ListenAddr string `json:"listen_addr"`
	Port       int    `json:"port"`
	PID        int    `json:"pid"`
	Version    string `json:"version"`
}

type cloudNASSidecarEndpoint struct {
	BaseURL string
	Token   string
	PID     int
}

type sidecarMountRequest struct {
	MountID     int64  `json:"mount_id"`
	DriveLetter string `json:"drive_letter"`
	Bucket      string `json:"bucket"`
	Prefix      string `json:"prefix"`
	Endpoint    string `json:"endpoint"`
	Region      string `json:"region"`
	AccessKey   string `json:"access_key"`
	SecretKey   string `json:"secret_key"`
	CacheMode   string `json:"cache_mode"`
	ReadOnly    bool   `json:"read_only"`
	VolumeLabel string `json:"volume_label"`
}

type sidecarUnmountRequest struct {
	DriveLetter string `json:"drive_letter"`
	MountID     int64  `json:"mount_id,omitempty"`
}

type sidecarHealthResponse struct {
	OK      bool   `json:"ok"`
	Version string `json:"version"`
	WinFsp  bool   `json:"winfsp"`
}

type sidecarErrorResponse struct {
	Error string `json:"error"`
}

func cloudNASErrorMessage(err error) string {
	switch {
	case errors.Is(err, ErrCloudNASSidecarMissing):
		return "Cloud NAS component is not installed or not running."
	case errors.Is(err, ErrCloudNASSidecarNotRunning):
		return "Cloud NAS component is installed but not running."
	case errors.Is(err, ErrWinFspMissing):
		return "WinFsp is not installed or not available. Re-run the Cloud NAS installer."
	default:
		return err.Error()
	}
}

func formatCloudNASError(err error) string {
	var code string
	switch {
	case errors.Is(err, ErrCloudNASSidecarMissing):
		code = ErrCloudNASSidecarMissing.Error()
	case errors.Is(err, ErrCloudNASSidecarNotRunning):
		code = ErrCloudNASSidecarNotRunning.Error()
	case errors.Is(err, ErrWinFspMissing):
		code = ErrWinFspMissing.Error()
	default:
		return err.Error()
	}
	return fmt.Sprintf("%s: %s", code, cloudNASErrorMessage(err))
}

func cloudNASSidecarDiscoveryPath(programData string) string {
	return filepath.Join(programData, "E3Backup", "cloudnas.discovery")
}

func cloudNASSidecarTokenPath(programData string) string {
	return filepath.Join(programData, "E3Backup", "cloudnas.token")
}

func loadCloudNASSidecarEndpoint(programData string) (*cloudNASSidecarEndpoint, error) {
	if strings.TrimSpace(programData) == "" {
		return nil, wrapCloudNASError(ErrCloudNASSidecarMissing, fmt.Errorf("program data directory is empty"))
	}

	discoveryPath := cloudNASSidecarDiscoveryPath(programData)
	tokenPath := cloudNASSidecarTokenPath(programData)

	discoveryBody, err := readSidecarFile(discoveryPath)
	if err != nil {
		return nil, err
	}
	tokenBody, err := readSidecarFile(tokenPath)
	if err != nil {
		return nil, err
	}

	var discovery cloudNASSidecarDiscovery
	if err := json.Unmarshal(discoveryBody, &discovery); err != nil {
		return nil, fmt.Errorf("invalid cloudnas discovery file: %w", err)
	}

	token := strings.TrimSpace(string(tokenBody))
	if token == "" {
		return nil, wrapCloudNASError(ErrCloudNASSidecarMissing, fmt.Errorf("cloudnas token file is empty"))
	}

	baseURL, err := discoveryBaseURL(discovery)
	if err != nil {
		return nil, wrapCloudNASError(ErrCloudNASSidecarMissing, err)
	}

	return &cloudNASSidecarEndpoint{
		BaseURL: baseURL,
		Token:   token,
		PID:     discovery.PID,
	}, nil
}

func readSidecarFile(path string) ([]byte, error) {
	body, err := osReadFile(path)
	if err != nil {
		if osIsNotExist(err) {
			return nil, wrapCloudNASError(ErrCloudNASSidecarMissing, fmt.Errorf("sidecar file not found: %s", path))
		}
		return nil, fmt.Errorf("read sidecar file %s: %w", path, err)
	}
	return body, nil
}

func discoveryBaseURL(discovery cloudNASSidecarDiscovery) (string, error) {
	if addr := strings.TrimSpace(discovery.ListenAddr); addr != "" {
		if strings.HasPrefix(addr, "http://") || strings.HasPrefix(addr, "https://") {
			return strings.TrimSuffix(addr, "/"), nil
		}
		return "http://" + strings.TrimSuffix(addr, "/"), nil
	}
	if discovery.Port > 0 {
		return fmt.Sprintf("http://127.0.0.1:%d", discovery.Port), nil
	}
	return "", fmt.Errorf("discovery metadata missing listen_addr and port")
}

func wrapCloudNASError(code error, cause error) error {
	return fmt.Errorf("%w: %w", code, cause)
}

func sidecarDoHealth(ctx context.Context, ep *cloudNASSidecarEndpoint) error {
	var health sidecarHealthResponse
	status, body, err := sidecarRequest(ctx, ep, http.MethodGet, "/health", nil, sidecarDefaultTimeout)
	if err != nil {
		return err
	}
	if status == http.StatusUnauthorized {
		return wrapCloudNASError(ErrCloudNASSidecarNotRunning, fmt.Errorf("sidecar rejected auth token"))
	}
	if status != http.StatusOK {
		if mapped := mapSidecarHTTPError(status, body); mapped != nil {
			return mapped
		}
		return fmt.Errorf("sidecar health returned HTTP %d: %s", status, strings.TrimSpace(body))
	}
	if err := json.Unmarshal([]byte(body), &health); err != nil {
		return fmt.Errorf("decode sidecar health: %w", err)
	}
	if !health.OK {
		return wrapCloudNASError(ErrCloudNASSidecarNotRunning, fmt.Errorf("sidecar health reported not ok"))
	}
	if !health.WinFsp {
		return wrapCloudNASError(ErrWinFspMissing, fmt.Errorf("sidecar health reported winfsp unavailable"))
	}
	return nil
}

func sidecarDoMount(ctx context.Context, ep *cloudNASSidecarEndpoint, payload MountNASPayload, volumeLabel string) error {
	driveLetter, err := normalizeNASDriveLetter(payload.DriveLetter)
	if err != nil {
		return err
	}

	reqBody := sidecarMountRequest{
		MountID:     payload.MountID,
		DriveLetter: driveLetter,
		Bucket:      payload.Bucket,
		Prefix:      payload.Prefix,
		Endpoint:    payload.Endpoint,
		Region:      payload.Region,
		AccessKey:   payload.AccessKey,
		SecretKey:   payload.SecretKey,
		CacheMode:   payload.CacheMode,
		ReadOnly:    payload.ReadOnly,
		VolumeLabel: volumeLabel,
	}

	status, body, err := sidecarRequest(ctx, ep, http.MethodPost, "/mount", reqBody, sidecarMountTimeout)
	if err != nil {
		return err
	}
	if status == http.StatusOK {
		return nil
	}
	if mapped := mapSidecarHTTPError(status, body); mapped != nil {
		return mapped
	}
	return fmt.Errorf("sidecar mount failed (HTTP %d): %s", status, strings.TrimSpace(sidecarErrorText(body)))
}

func sidecarDoUnmount(ctx context.Context, ep *cloudNASSidecarEndpoint, driveLetter string) error {
	driveLetter, err := normalizeNASDriveLetter(driveLetter)
	if err != nil {
		return err
	}

	reqBody := sidecarUnmountRequest{DriveLetter: driveLetter}
	status, body, err := sidecarRequest(ctx, ep, http.MethodPost, "/unmount", reqBody, sidecarDefaultTimeout)
	if err != nil {
		return err
	}
	if status == http.StatusOK {
		return nil
	}
	if mapped := mapSidecarHTTPError(status, body); mapped != nil {
		return mapped
	}
	return fmt.Errorf("sidecar unmount failed (HTTP %d): %s", status, strings.TrimSpace(sidecarErrorText(body)))
}

func sidecarRequest(ctx context.Context, ep *cloudNASSidecarEndpoint, method, route string, payload any, timeout time.Duration) (int, string, error) {
	var body []byte
	if payload != nil {
		var err error
		body, err = json.Marshal(payload)
		if err != nil {
			return 0, "", err
		}
	}

	req, err := http.NewRequestWithContext(ctx, method, ep.BaseURL+route, bytes.NewReader(body))
	if err != nil {
		return 0, "", err
	}
	req.Header.Set(sidecarTokenHeader, ep.Token)
	if payload != nil {
		req.Header.Set("Content-Type", "application/json")
	}

	client := &http.Client{Timeout: timeout}
	resp, err := client.Do(req)
	if err != nil {
		if isSidecarConnectionError(err) {
			return 0, "", wrapCloudNASError(ErrCloudNASSidecarNotRunning, err)
		}
		return 0, "", err
	}
	defer resp.Body.Close()

	respBody, err := io.ReadAll(io.LimitReader(resp.Body, 64*1024))
	if err != nil {
		return resp.StatusCode, "", fmt.Errorf("read sidecar response: %w", err)
	}
	return resp.StatusCode, string(respBody), nil
}

func mapSidecarHTTPError(status int, body string) error {
	text := strings.ToLower(body)
	if status == http.StatusServiceUnavailable || strings.Contains(text, "winfsp") {
		return wrapCloudNASError(ErrWinFspMissing, fmt.Errorf("%s", strings.TrimSpace(sidecarErrorText(body))))
	}
	return nil
}

func sidecarErrorText(body string) string {
	var parsed sidecarErrorResponse
	if err := json.Unmarshal([]byte(body), &parsed); err == nil && strings.TrimSpace(parsed.Error) != "" {
		return parsed.Error
	}
	return body
}

func isSidecarConnectionError(err error) bool {
	if err == nil {
		return false
	}
	msg := strings.ToLower(err.Error())
	return strings.Contains(msg, "connection refused") ||
		strings.Contains(msg, "connect: connection") ||
		strings.Contains(msg, "no such host") ||
		strings.Contains(msg, "connection reset")
}

// osReadFile and osIsNotExist are thin wrappers so tests can stay in package agent
// without importing os in every helper signature.
var osReadFile = func(path string) ([]byte, error) {
	return readFileOS(path)
}

var osIsNotExist = func(err error) bool {
	return isNotExistOS(err)
}

func (r *Runner) sidecarHealth(ctx context.Context) error {
	ep, err := loadCloudNASSidecarEndpoint(cloudNASProgramDataDir())
	if err != nil {
		return err
	}
	if ep.PID > 0 && !isSidecarProcessRunning(ep.PID) {
		return wrapCloudNASError(ErrCloudNASSidecarNotRunning, fmt.Errorf("sidecar pid %d is not running", ep.PID))
	}
	return sidecarDoHealth(ctx, ep)
}

func (r *Runner) sidecarMount(ctx context.Context, payload MountNASPayload, volumeLabel string) error {
	ep, err := loadCloudNASSidecarEndpoint(cloudNASProgramDataDir())
	if err != nil {
		return err
	}
	return sidecarDoMount(ctx, ep, payload, volumeLabel)
}

func (r *Runner) sidecarUnmount(ctx context.Context, driveLetter string) error {
	ep, err := loadCloudNASSidecarEndpoint(cloudNASProgramDataDir())
	if err != nil {
		return err
	}
	return sidecarDoUnmount(ctx, ep, driveLetter)
}

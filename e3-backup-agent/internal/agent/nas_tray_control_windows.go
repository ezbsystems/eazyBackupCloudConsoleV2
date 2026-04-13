//go:build windows

package agent

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"time"
)

const cloudNASControlTokenHeader = "X-E3-CloudNAS-Token"

type cloudNASTrayDiscovery struct {
	Version      int    `json:"version"`
	SessionID    uint32 `json:"session_id"`
	Username     string `json:"username,omitempty"`
	ListenAddr   string `json:"listen_addr"`
	ControlToken string `json:"control_token"`
	PID          int    `json:"pid"`
	UpdatedAt    string `json:"updated_at"`
}

type cloudNASMountRequest struct {
	DriveLetter string `json:"drive_letter"`
	TargetURL   string `json:"target_url"`
	BucketName  string `json:"bucket_name"`
	WebDAVPort  int    `json:"webdav_port"`
}

type cloudNASRegisterRequest struct {
	MountID     int64  `json:"mount_id"`
	DriveLetter string `json:"drive_letter"`
	TargetURL   string `json:"target_url"`
	BucketName  string `json:"bucket_name"`
	WebDAVPort  int    `json:"webdav_port"`
	Status      string `json:"status"`
}

type cloudNASUnmountRequest struct {
	DriveLetter string `json:"drive_letter"`
}

type cloudNASUnregisterRequest struct {
	DriveLetter string `json:"drive_letter"`
}

type cloudNASControlResponse struct {
	Status    string `json:"status"`
	Message   string `json:"message"`
	SessionID uint32 `json:"session_id"`
	Username  string `json:"username"`
}

func cloudNASSessionDir() string {
	pd := os.Getenv("ProgramData")
	if pd == "" {
		pd = `C:\ProgramData`
	}
	return filepath.Join(pd, "E3Backup", "tray-sessions")
}

func cloudNASSessionFile(sessionID uint32) string {
	return filepath.Join(cloudNASSessionDir(), fmt.Sprintf("session-%d.json", sessionID))
}

func loadCloudNASTrayDiscovery(sessionID uint32) (*cloudNASTrayDiscovery, error) {
	body, err := os.ReadFile(cloudNASSessionFile(sessionID))
	if err != nil {
		if os.IsNotExist(err) {
			return nil, fmt.Errorf("tray helper is not running in the active Windows session; sign in to Windows and wait for the E3 Backup tray to start")
		}
		return nil, err
	}
	var discovery cloudNASTrayDiscovery
	if err := json.Unmarshal(body, &discovery); err != nil {
		return nil, fmt.Errorf("invalid tray discovery file: %w", err)
	}
	if strings.TrimSpace(discovery.ListenAddr) == "" || strings.TrimSpace(discovery.ControlToken) == "" {
		return nil, fmt.Errorf("tray discovery metadata is incomplete")
	}
	return &discovery, nil
}

func callCloudNASTrayControl(ctx context.Context, discovery *cloudNASTrayDiscovery, method, route string, payload any) (*cloudNASControlResponse, error) {
	var body []byte
	if payload != nil {
		var err error
		body, err = json.Marshal(payload)
		if err != nil {
			return nil, err
		}
	}

	req, err := http.NewRequestWithContext(ctx, method, "http://"+discovery.ListenAddr+route, bytes.NewReader(body))
	if err != nil {
		return nil, err
	}
	req.Header.Set(cloudNASControlTokenHeader, discovery.ControlToken)
	if payload != nil {
		req.Header.Set("Content-Type", "application/json")
	}

	httpClient := &http.Client{Timeout: 180 * time.Second}
	resp, err := httpClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	var out cloudNASControlResponse
	if err := json.NewDecoder(resp.Body).Decode(&out); err != nil {
		return nil, fmt.Errorf("tray control decode failed: %w", err)
	}
	if resp.StatusCode != http.StatusOK {
		if strings.TrimSpace(out.Message) == "" {
			out.Message = fmt.Sprintf("tray control HTTP %d", resp.StatusCode)
		}
		return &out, fmt.Errorf(out.Message)
	}
	if !strings.EqualFold(strings.TrimSpace(out.Status), "success") {
		if strings.TrimSpace(out.Message) == "" {
			out.Message = "tray control request failed"
		}
		return &out, fmt.Errorf(out.Message)
	}
	return &out, nil
}

func mapNASDriveViaTray(ctx context.Context, driveLetter, targetURL, bucketName string, webdavPort int) error {
	sessionID, err := resolveWTSSessionForNAS()
	if err != nil {
		return fmt.Errorf("no logged-in Windows desktop session found; sign in to Windows and ensure the E3 Backup tray is running: %w", err)
	}
	log.Printf("agent: Cloud NAS selected interactive session %d", sessionID)

	discovery, err := loadCloudNASTrayDiscovery(sessionID)
	if err != nil {
		return err
	}
	log.Printf("agent: Cloud NAS tray discovery session=%d user=%s addr=%s", discovery.SessionID, discovery.Username, discovery.ListenAddr)

	pingResp, err := callCloudNASTrayControl(ctx, discovery, http.MethodGet, "/control/ping", nil)
	if err != nil {
		return fmt.Errorf("tray helper in session %d is unavailable: %w", sessionID, err)
	}
	log.Printf("agent: Cloud NAS tray ping succeeded for session %d message=%s", sessionID, strings.TrimSpace(pingResp.Message))

	req := cloudNASMountRequest{
		DriveLetter: driveLetter,
		TargetURL:   targetURL,
		BucketName:  bucketName,
		WebDAVPort:  webdavPort,
	}
	mountResp, err := callCloudNASTrayControl(ctx, discovery, http.MethodPost, "/control/cloudnas/mount", req)
	if err != nil {
		return fmt.Errorf("tray mapping failed: %w", err)
	}
	log.Printf("agent: Cloud NAS tray mapping verified drive=%s session=%d detail=%s", driveLetter, sessionID, strings.TrimSpace(mountResp.Message))
	return nil
}

func unmapNASDriveViaTray(ctx context.Context, driveLetter string) error {
	sessionID, err := resolveWTSSessionForNAS()
	if err != nil {
		return err
	}
	discovery, err := loadCloudNASTrayDiscovery(sessionID)
	if err != nil {
		return err
	}
	unmountResp, err := callCloudNASTrayControl(ctx, discovery, http.MethodPost, "/control/cloudnas/unmount", cloudNASUnmountRequest{DriveLetter: driveLetter})
	if err != nil {
		return err
	}
	log.Printf("agent: Cloud NAS tray unmount completed drive=%s session=%d detail=%s", driveLetter, sessionID, strings.TrimSpace(unmountResp.Message))
	return nil
}

func registerPreparedNASDriveViaTray(ctx context.Context, mountID int64, driveLetter, targetURL, bucketName string, webdavPort int, status string) error {
	sessionID, err := resolveWTSSessionForNAS()
	if err != nil {
		return fmt.Errorf("no logged-in Windows desktop session found; sign in to Windows and ensure the E3 Backup tray is running: %w", err)
	}
	discovery, err := loadCloudNASTrayDiscovery(sessionID)
	if err != nil {
		return err
	}
	req := cloudNASRegisterRequest{
		MountID:     mountID,
		DriveLetter: driveLetter,
		TargetURL:   targetURL,
		BucketName:  bucketName,
		WebDAVPort:  webdavPort,
		Status:      status,
	}
	resp, err := callCloudNASTrayControl(ctx, discovery, http.MethodPost, "/control/cloudnas/register", req)
	if err != nil {
		return err
	}
	log.Printf("agent: Cloud NAS tray register drive=%s session=%d detail=%s", driveLetter, sessionID, strings.TrimSpace(resp.Message))
	return nil
}

func unregisterPreparedNASDriveViaTray(ctx context.Context, driveLetter string) error {
	sessionID, err := resolveWTSSessionForNAS()
	if err != nil {
		return err
	}
	discovery, err := loadCloudNASTrayDiscovery(sessionID)
	if err != nil {
		return err
	}
	resp, err := callCloudNASTrayControl(ctx, discovery, http.MethodPost, "/control/cloudnas/unregister", cloudNASUnregisterRequest{
		DriveLetter: driveLetter,
	})
	if err != nil {
		return err
	}
	log.Printf("agent: Cloud NAS tray unregister drive=%s session=%d detail=%s", driveLetter, sessionID, strings.TrimSpace(resp.Message))
	return nil
}

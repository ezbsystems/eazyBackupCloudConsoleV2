package api_test

import (
	"bytes"
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"sync"
	"testing"

	"github.com/ezbsystems/e3-cloudnas/internal/api"
)

type fakeMounter struct {
	mu     sync.Mutex
	mounts map[string]api.MountRequest
}

func newFakeMounter() *fakeMounter {
	return &fakeMounter{mounts: make(map[string]api.MountRequest)}
}

func (f *fakeMounter) Mount(_ context.Context, req api.MountRequest) error {
	f.mu.Lock()
	defer f.mu.Unlock()
	f.mounts[req.DriveLetter] = req
	return nil
}

func (f *fakeMounter) Unmount(_ context.Context, driveLetter string) error {
	f.mu.Lock()
	defer f.mu.Unlock()
	delete(f.mounts, driveLetter)
	return nil
}

func (f *fakeMounter) List() []api.MountStatus {
	f.mu.Lock()
	defer f.mu.Unlock()
	out := make([]api.MountStatus, 0, len(f.mounts))
	for letter, req := range f.mounts {
		out = append(out, api.MountStatus{
			MountID:     req.MountID,
			DriveLetter: letter,
			State:       "mounted",
		})
	}
	return out
}

func TestHealthRequiresToken(t *testing.T) {
	srv := api.NewServer(newFakeMounter(), "secret-token", "0.1.0")
	ts := httptest.NewServer(srv.Handler())
	t.Cleanup(ts.Close)

	resp, err := http.Get(ts.URL + "/health")
	if err != nil {
		t.Fatalf("GET /health: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusUnauthorized {
		t.Fatalf("status = %d, want %d", resp.StatusCode, http.StatusUnauthorized)
	}
}

func TestMountUnmountWithFakeMounter(t *testing.T) {
	mounter := newFakeMounter()
	srv := api.NewServer(mounter, "secret-token", "0.1.0")
	ts := httptest.NewServer(srv.Handler())
	t.Cleanup(ts.Close)

	client := &http.Client{}
	authHeader := http.Header{}
	authHeader.Set(api.TokenHeader, "secret-token")

	mountBody, err := json.Marshal(api.MountRequest{
		MountID:     42,
		DriveLetter: "Y",
		Bucket:      "test-bucket",
		Prefix:      "data/",
		Endpoint:    "https://s3.example.com",
		Region:      "us-east-1",
		AccessKey:   "ak",
		SecretKey:   "sk",
		CacheMode:   "writes",
		ReadOnly:    false,
		VolumeLabel: "Cloud NAS (test-bucket)",
	})
	if err != nil {
		t.Fatalf("marshal mount request: %v", err)
	}

	mountReq, err := http.NewRequest(http.MethodPost, ts.URL+"/mount", bytes.NewReader(mountBody))
	if err != nil {
		t.Fatalf("new mount request: %v", err)
	}
	mountReq.Header = authHeader.Clone()
	mountReq.Header.Set("Content-Type", "application/json")

	mountResp, err := client.Do(mountReq)
	if err != nil {
		t.Fatalf("POST /mount: %v", err)
	}
	mountResp.Body.Close()
	if mountResp.StatusCode != http.StatusOK {
		t.Fatalf("POST /mount status = %d, want %d", mountResp.StatusCode, http.StatusOK)
	}

	statusReq, err := http.NewRequest(http.MethodGet, ts.URL+"/status", nil)
	if err != nil {
		t.Fatalf("new status request: %v", err)
	}
	statusReq.Header = authHeader.Clone()

	statusResp, err := client.Do(statusReq)
	if err != nil {
		t.Fatalf("GET /status: %v", err)
	}
	defer statusResp.Body.Close()
	if statusResp.StatusCode != http.StatusOK {
		t.Fatalf("GET /status status = %d, want %d", statusResp.StatusCode, http.StatusOK)
	}

	var status api.StatusResponse
	if err := json.NewDecoder(statusResp.Body).Decode(&status); err != nil {
		t.Fatalf("decode status: %v", err)
	}
	if len(status.Mounts) != 1 {
		t.Fatalf("mount count = %d, want 1", len(status.Mounts))
	}
	if status.Mounts[0].DriveLetter != "Y" {
		t.Fatalf("drive letter = %q, want Y", status.Mounts[0].DriveLetter)
	}

	unmountBody, err := json.Marshal(api.UnmountRequest{DriveLetter: "Y"})
	if err != nil {
		t.Fatalf("marshal unmount request: %v", err)
	}
	unmountReq, err := http.NewRequest(http.MethodPost, ts.URL+"/unmount", bytes.NewReader(unmountBody))
	if err != nil {
		t.Fatalf("new unmount request: %v", err)
	}
	unmountReq.Header = authHeader.Clone()
	unmountReq.Header.Set("Content-Type", "application/json")

	unmountResp, err := client.Do(unmountReq)
	if err != nil {
		t.Fatalf("POST /unmount: %v", err)
	}
	unmountResp.Body.Close()
	if unmountResp.StatusCode != http.StatusOK {
		t.Fatalf("POST /unmount status = %d, want %d", unmountResp.StatusCode, http.StatusOK)
	}

	statusReq2, err := http.NewRequest(http.MethodGet, ts.URL+"/status", nil)
	if err != nil {
		t.Fatalf("new status request: %v", err)
	}
	statusReq2.Header = authHeader.Clone()

	statusResp2, err := client.Do(statusReq2)
	if err != nil {
		t.Fatalf("GET /status after unmount: %v", err)
	}
	defer statusResp2.Body.Close()

	var statusAfter api.StatusResponse
	if err := json.NewDecoder(statusResp2.Body).Decode(&statusAfter); err != nil {
		t.Fatalf("decode status after unmount: %v", err)
	}
	if len(statusAfter.Mounts) != 0 {
		t.Fatalf("mount count after unmount = %d, want 0", len(statusAfter.Mounts))
	}
}

func TestMountRejectsMissingDrive(t *testing.T) {
	srv := api.NewServer(newFakeMounter(), "secret-token", "0.1.0")
	ts := httptest.NewServer(srv.Handler())
	t.Cleanup(ts.Close)

	body, err := json.Marshal(api.MountRequest{Bucket: "test-bucket"})
	if err != nil {
		t.Fatalf("marshal mount request: %v", err)
	}

	req, err := http.NewRequest(http.MethodPost, ts.URL+"/mount", bytes.NewReader(body))
	if err != nil {
		t.Fatalf("new mount request: %v", err)
	}
	req.Header.Set(api.TokenHeader, "secret-token")
	req.Header.Set("Content-Type", "application/json")

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		t.Fatalf("POST /mount: %v", err)
	}
	resp.Body.Close()

	if resp.StatusCode != http.StatusBadRequest {
		t.Fatalf("status = %d, want %d", resp.StatusCode, http.StatusBadRequest)
	}
}

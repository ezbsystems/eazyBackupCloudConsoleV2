package agent

import (
	"context"
	"encoding/json"
	"errors"
	"io"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestSidecarMountPostsJSON(t *testing.T) {
	var gotToken string
	var gotBody map[string]any

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/mount" {
			http.NotFound(w, r)
			return
		}
		if r.Method != http.MethodPost {
			t.Errorf("method = %s, want POST", r.Method)
		}
		gotToken = r.Header.Get(sidecarTokenHeader)
		if err := json.NewDecoder(r.Body).Decode(&gotBody); err != nil {
			t.Errorf("decode mount body: %v", err)
		}
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte(`{"ok":true}`))
	}))
	t.Cleanup(srv.Close)

	host := strings.TrimPrefix(srv.URL, "http://")
	dir := t.TempDir()
	writeSidecarDiscoveryFiles(t, dir, host, "test-token-secret", 12345)

	r := &Runner{}
	payload := MountNASPayload{
		MountID:     99,
		Bucket:      "my-bucket",
		Prefix:      "data/",
		DriveLetter: "Y",
		ReadOnly:    true,
		CacheMode:   "writes",
		Endpoint:    "https://s3.example.com",
		AccessKey:   "ak-test",
		SecretKey:   "sk-test",
		Region:      "us-east-1",
	}

	t.Setenv("ProgramData", dir)
	if err := r.sidecarMount(context.Background(), payload, "Cloud NAS (my-bucket)"); err != nil {
		t.Fatalf("sidecarMount: %v", err)
	}

	if gotToken != "test-token-secret" {
		t.Fatalf("token header = %q, want test-token-secret", gotToken)
	}
	if gotBody == nil {
		t.Fatal("mount body was not received")
	}
	assertJSONField(t, gotBody, "mount_id", float64(99))
	assertJSONField(t, gotBody, "drive_letter", "Y")
	assertJSONField(t, gotBody, "bucket", "my-bucket")
	assertJSONField(t, gotBody, "prefix", "data/")
	assertJSONField(t, gotBody, "volume_label", "Cloud NAS (my-bucket)")
	assertJSONField(t, gotBody, "read_only", true)
	assertJSONField(t, gotBody, "cache_mode", "writes")
}

func TestSidecarNotRunningErrors(t *testing.T) {
	t.Run("connection refused", func(t *testing.T) {
		dir := t.TempDir()
		writeSidecarDiscoveryFiles(t, dir, "127.0.0.1:1", "token", os.Getpid())
		t.Setenv("ProgramData", dir)

		r := &Runner{}
		err := r.sidecarMount(context.Background(), MountNASPayload{DriveLetter: "Y", Bucket: "b"}, "label")
		if err == nil {
			t.Fatal("expected connection refused error")
		}
		if !errors.Is(err, ErrCloudNASSidecarNotRunning) {
			t.Fatalf("error = %v, want ErrCloudNASSidecarNotRunning", err)
		}
	})

	t.Run("dead discovery pid", func(t *testing.T) {
		srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			io.WriteString(w, `{"ok":true,"version":"0.1.0","winfsp":true}`)
		}))
		t.Cleanup(srv.Close)

		host := strings.TrimPrefix(srv.URL, "http://")
		dir := t.TempDir()
		writeSidecarDiscoveryFiles(t, dir, host, "token", 99999999)

		r := &Runner{}
		t.Setenv("ProgramData", dir)
		err := r.sidecarHealth(context.Background())
		if err == nil {
			t.Fatal("expected dead pid error")
		}
		if !errors.Is(err, ErrCloudNASSidecarNotRunning) {
			t.Fatalf("error = %v, want ErrCloudNASSidecarNotRunning", err)
		}
	})
}

func TestSidecarUnmountPostsJSON(t *testing.T) {
	var gotToken string
	var gotBody map[string]any

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/unmount" {
			http.NotFound(w, r)
			return
		}
		if r.Method != http.MethodPost {
			t.Errorf("method = %s, want POST", r.Method)
		}
		gotToken = r.Header.Get(sidecarTokenHeader)
		if err := json.NewDecoder(r.Body).Decode(&gotBody); err != nil {
			t.Errorf("decode unmount body: %v", err)
		}
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte(`{"ok":true}`))
	}))
	t.Cleanup(srv.Close)

	host := strings.TrimPrefix(srv.URL, "http://")
	dir := t.TempDir()
	writeSidecarDiscoveryFiles(t, dir, host, "unmount-token", os.Getpid())

	r := &Runner{}
	t.Setenv("ProgramData", dir)
	if err := r.sidecarUnmount(context.Background(), "z"); err != nil {
		t.Fatalf("sidecarUnmount: %v", err)
	}

	if gotToken != "unmount-token" {
		t.Fatalf("token header = %q, want unmount-token", gotToken)
	}
	if gotBody == nil {
		t.Fatal("unmount body was not received")
	}
	assertJSONField(t, gotBody, "drive_letter", "Z")
}

func TestSidecarMissingDiscovery(t *testing.T) {
	dir := t.TempDir()
	t.Setenv("ProgramData", dir)

	r := &Runner{}
	err := r.sidecarHealth(context.Background())
	if err == nil {
		t.Fatal("expected error for missing discovery")
	}
	if !errors.Is(err, ErrCloudNASSidecarMissing) {
		t.Fatalf("error = %v, want ErrCloudNASSidecarMissing", err)
	}
	if !strings.Contains(err.Error(), ErrCloudNASSidecarMissing.Error()) {
		t.Fatalf("error %q should contain code %q", err.Error(), ErrCloudNASSidecarMissing.Error())
	}
}

func TestSidecarHealthWinFspMissing(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/health" {
			http.NotFound(w, r)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte(`{"ok":true,"version":"0.1.0","winfsp":false}`))
	}))
	t.Cleanup(srv.Close)

	host := strings.TrimPrefix(srv.URL, "http://")
	dir := t.TempDir()
	writeSidecarDiscoveryFiles(t, dir, host, "token", os.Getpid())

	r := &Runner{}
	t.Setenv("ProgramData", dir)
	err := r.sidecarHealth(context.Background())
	if err == nil {
		t.Fatal("expected winfsp error")
	}
	if !errors.Is(err, ErrWinFspMissing) {
		t.Fatalf("error = %v, want ErrWinFspMissing", err)
	}
}

func TestSidecarMountMapsWinFspError(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusBadRequest)
		_, _ = w.Write([]byte(`{"error":"WinFsp mount unavailable: driver missing"}`))
	}))
	t.Cleanup(srv.Close)

	host := strings.TrimPrefix(srv.URL, "http://")
	dir := t.TempDir()
	writeSidecarDiscoveryFiles(t, dir, host, "token", os.Getpid())

	r := &Runner{}
	t.Setenv("ProgramData", dir)
	err := r.sidecarMount(context.Background(), MountNASPayload{DriveLetter: "Y", Bucket: "b"}, "label")
	if err == nil {
		t.Fatal("expected winfsp error")
	}
	if !errors.Is(err, ErrWinFspMissing) {
		t.Fatalf("error = %v, want ErrWinFspMissing", err)
	}
}

func writeSidecarDiscoveryFiles(t *testing.T, programData, listenAddr, token string, pid int) {
	t.Helper()
	e3Dir := filepath.Join(programData, "E3Backup")
	if err := os.MkdirAll(e3Dir, 0o755); err != nil {
		t.Fatalf("mkdir E3Backup: %v", err)
	}

	discovery := map[string]any{
		"listen_addr": listenAddr,
		"pid":         pid,
		"version":     "0.1.0-test",
	}
	body, err := json.Marshal(discovery)
	if err != nil {
		t.Fatalf("marshal discovery: %v", err)
	}
	if err := os.WriteFile(filepath.Join(e3Dir, "cloudnas.discovery"), body, 0o644); err != nil {
		t.Fatalf("write discovery: %v", err)
	}
	if err := os.WriteFile(filepath.Join(e3Dir, "cloudnas.token"), []byte(token), 0o600); err != nil {
		t.Fatalf("write token: %v", err)
	}
}

func assertJSONField(t *testing.T, body map[string]any, key string, want any) {
	t.Helper()
	got, ok := body[key]
	if !ok {
		t.Fatalf("body missing key %q: %v", key, body)
	}
	if got != want {
		t.Fatalf("body[%q] = %#v, want %#v", key, got, want)
	}
}

func TestSidecarDiscoveryFromPortField(t *testing.T) {
	dir := t.TempDir()
	e3Dir := filepath.Join(dir, "E3Backup")
	if err := os.MkdirAll(e3Dir, 0o755); err != nil {
		t.Fatalf("mkdir: %v", err)
	}

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		io.WriteString(w, `{"ok":true,"version":"0.1.0","winfsp":true}`)
	}))
	t.Cleanup(srv.Close)

	port := strings.TrimPrefix(srv.URL, "http://")
	port = port[strings.LastIndex(port, ":")+1:]

	discovery := map[string]any{"port": mustAtoi(t, port), "pid": os.Getpid(), "version": "0.1.0"}
	body, _ := json.Marshal(discovery)
	if err := os.WriteFile(filepath.Join(e3Dir, "cloudnas.discovery"), body, 0o644); err != nil {
		t.Fatalf("write discovery: %v", err)
	}
	if err := os.WriteFile(filepath.Join(e3Dir, "cloudnas.token"), []byte("tok"), 0o600); err != nil {
		t.Fatalf("write token: %v", err)
	}

	ep, err := loadCloudNASSidecarEndpoint(dir)
	if err != nil {
		t.Fatalf("loadCloudNASSidecarEndpoint: %v", err)
	}
	if ep.BaseURL != srv.URL {
		t.Fatalf("BaseURL = %q, want %q", ep.BaseURL, srv.URL)
	}
}

func mustAtoi(t *testing.T, s string) int {
	t.Helper()
	var n int
	for _, c := range s {
		n = n*10 + int(c-'0')
	}
	return n
}

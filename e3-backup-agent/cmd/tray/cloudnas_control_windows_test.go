//go:build windows

package main

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestLoadCloudNASSidecarEndpointAndHealth(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if got := r.Header.Get(cloudNASSidecarTokenHeader); got != "test-token" {
			t.Errorf("token header = %q", got)
		}
		_, _ = w.Write([]byte(`{"ok":true,"winfsp":true}`))
	}))
	defer srv.Close()

	programData := t.TempDir()
	writeTraySidecarDiscovery(t, programData, strings.TrimPrefix(srv.URL, "http://"), "test-token")

	ep, err := loadCloudNASSidecarEndpoint(programData)
	if err != nil {
		t.Fatal(err)
	}
	if err := checkCloudNASSidecarHealth(ep); err != nil {
		t.Fatal(err)
	}
}

func TestUnmountCloudNASSidecarDrive(t *testing.T) {
	var driveLetter string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/unmount" {
			http.NotFound(w, r)
			return
		}
		var body cloudNASSidecarUnmountRequest
		if err := json.NewDecoder(r.Body).Decode(&body); err != nil {
			t.Fatal(err)
		}
		driveLetter = body.DriveLetter
		_, _ = w.Write([]byte(`{"ok":true}`))
	}))
	defer srv.Close()

	programData := t.TempDir()
	writeTraySidecarDiscovery(t, programData, strings.TrimPrefix(srv.URL, "http://"), "test-token")
	t.Setenv("ProgramData", programData)

	if err := unmountCloudNASSidecarDrive("z:"); err != nil {
		t.Fatal(err)
	}
	if driveLetter != "Z" {
		t.Fatalf("drive letter = %q, want Z", driveLetter)
	}
}

func writeTraySidecarDiscovery(t *testing.T, programData, listenAddr, token string) {
	t.Helper()
	dir := filepath.Join(programData, "E3Backup")
	if err := os.MkdirAll(dir, 0o755); err != nil {
		t.Fatal(err)
	}
	body, _ := json.Marshal(cloudNASSidecarDiscovery{ListenAddr: listenAddr, PID: os.Getpid()})
	if err := os.WriteFile(filepath.Join(dir, "cloudnas.discovery"), body, 0o600); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(dir, "cloudnas.token"), []byte(token), 0o600); err != nil {
		t.Fatal(err)
	}
}

package kopia

import (
	"context"
	"encoding/json"
	"net"
	"path/filepath"
	"testing"
	"time"

	kopiafs "github.com/kopia/kopia/fs"
)

func TestBrowseServePing(t *testing.T) {
	socket := filepath.Join(t.TempDir(), "browse.sock")
	srv := NewBrowseServeServer(socket)
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	errCh := make(chan error, 1)
	go func() {
		errCh <- srv.Run(ctx)
	}()

	waitForSocket(t, socket)

	conn, err := net.Dial("unix", socket)
	if err != nil {
		t.Fatalf("dial: %v", err)
	}

	req := []byte(`{"op":"ping"}` + "\n")
	if _, err := conn.Write(req); err != nil {
		_ = conn.Close()
		t.Fatalf("write: %v", err)
	}

	var resp browseServeResponse
	dec := json.NewDecoder(conn)
	if err := dec.Decode(&resp); err != nil {
		_ = conn.Close()
		t.Fatalf("decode: %v", err)
	}
	_ = conn.Close()
	if !resp.OK || !resp.Pong {
		t.Fatalf("unexpected ping response: %+v", resp)
	}

	srv.Shutdown()
	cancel()
	if err := <-errCh; err != nil && err != context.Canceled {
		t.Fatalf("run: %v", err)
	}
}

func TestBrowseWithCandidates(t *testing.T) {
	ctx := context.Background()
	tenant := "tenant1"
	guid := "4728969e-5eff-4981-b0c6-46eadac79cfe"
	root := newMemDir("", map[string]kopiafs.Entry{
		tenant: newMemDir(tenant, map[string]kopiafs.Entry{
			"users": newMemDir("users", map[string]kopiafs.Entry{
				guid: newMemDir(guid, map[string]kopiafs.Entry{
					"mail": newMemDir("mail", map[string]kopiafs.Entry{
						"inbox": newMemDir("inbox", map[string]kopiafs.Entry{
							"msg1.json": newMemFile("msg1.json", []byte(`{"subject":"Hello"}`)),
						}),
					}),
				}),
			}),
		}),
	})

	fullPath := tenant + "/users/" + guid + "/mail/inbox"
	dir, resolved, err := resolveBrowseSourceDir(ctx, root, []string{fullPath})
	if err != nil {
		t.Fatalf("resolveBrowseSourceDir: %v", err)
	}
	if resolved != fullPath {
		t.Fatalf("resolved path: got %q want %q", resolved, fullPath)
	}
	children, err := kopiafs.GetAllEntries(ctx, dir)
	if err != nil {
		t.Fatalf("readdir: %v", err)
	}
	if len(children) != 1 || children[0].Name() != "msg1.json" {
		t.Fatalf("children: %+v", children)
	}
}

func TestShouldAutoDescend(t *testing.T) {
	cases := []struct {
		name string
		path string
		want bool
	}{
		{"4728969e-5eff-4981-b0c6-46eadac79cfe", "", true},
		{"content", "tenant/sites/site1/drives/drive1", true},
		{"content", "tenant/drives/drive1", true},
		{"Marketing", "tenant/sites/site1/drives/drive1/content", false},
		{"user:abc", "tenant/users", true},
	}
	for _, tc := range cases {
		if got := shouldAutoDescend(tc.name, tc.path); got != tc.want {
			t.Fatalf("shouldAutoDescend(%q, %q) = %v want %v", tc.name, tc.path, got, tc.want)
		}
	}
}

func TestBrowseWithAutoDescendMem(t *testing.T) {
	ctx := context.Background()
	tenant := "tenant1"
	site := "site1"
	drive := "drive1"
	root := newMemDir("", map[string]kopiafs.Entry{
		tenant: newMemDir(tenant, map[string]kopiafs.Entry{
			"sites": newMemDir("sites", map[string]kopiafs.Entry{
				site: newMemDir(site, map[string]kopiafs.Entry{
					"drives": newMemDir("drives", map[string]kopiafs.Entry{
						drive: newMemDir(drive, map[string]kopiafs.Entry{
							"content": newMemDir("content", map[string]kopiafs.Entry{
								"Reports": newMemDir("Reports", map[string]kopiafs.Entry{
									"Q1.pdf": newMemFile("Q1.pdf", []byte("pdf")),
								}),
							}),
						}),
					}),
				}),
			}),
		}),
	})

	// Simulate auto-descend by walking in-memory without pool/S3.
	path := tenant + "/sites/" + site + "/drives/" + drive
	cur, err := walkPath(ctx, root, path)
	if err != nil {
		t.Fatalf("walkPath: %v", err)
	}
	dir, ok := cur.(kopiafs.Directory)
	if !ok {
		t.Fatal("path not directory")
	}
	entries, err := listBrowseDirectoryChildrenAt(ctx, root, dir, path)
	if err != nil {
		t.Fatalf("list: %v", err)
	}
	if len(entries) != 1 || entries[0].entry.Name != "content" {
		t.Fatalf("wrapper entries: %+v", entries)
	}
	if !shouldAutoDescend(entries[0].entry.Name, path) {
		t.Fatal("expected auto-descend on content")
	}
}

func waitForSocket(t *testing.T, socket string) {
	t.Helper()
	deadline := time.Now().Add(3 * time.Second)
	for time.Now().Before(deadline) {
		conn, err := net.Dial("unix", socket)
		if err == nil {
			_ = conn.Close()
			return
		}
		time.Sleep(20 * time.Millisecond)
	}
	t.Fatalf("socket not ready: %s", socket)
}

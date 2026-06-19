//go:build live

package graphsync

import (
	"context"
	"os"
	"strings"
	"testing"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
	kopiafs "github.com/kopia/kopia/fs"
)

func TestSyncOneDriveDeltaAndHealLive(t *testing.T) {
	token := strings.TrimSpace(os.Getenv("GRAPH_TOKEN"))
	if token == "" {
		b, err := os.ReadFile("/tmp/worker_graph_token.txt")
		if err != nil {
			t.Skip("GRAPH_TOKEN not set")
		}
		token = strings.TrimSpace(string(b))
	}
	deltaLink := strings.TrimSpace(os.Getenv("ONEDRIVE_DELTA"))
	if deltaLink == "" {
		t.Skip("ONEDRIVE_DELTA not set")
	}

	tenant := "cfb5450a-eb80-4c61-aecc-9dca87649cf6"
	user := "1533e37a-2e8f-4f24-8155-11777c70997d"
	drive := "b!MpMKMk4AikK-x5M_QRdU2J0oEti9gbZOqyOS9Y7x9XCTYCWJG1XmTqhM61-Zakfd"

	overlay := graphfs.NewOverlayBuilder()
	overlay.PutJSON(tenant+"/users/"+user+"/onedrive/content/SeederData/seed-file-0001.txt", []byte("x"), time.Now())

	client := graph.NewClient(token, "GlobalPublicCloud", graph.ClientOptions{})
	res, err := SyncOneDrive(context.Background(), client, OneDriveSyncOptions{
		AzureTenantID: tenant,
		UserID:        user,
		DriveID:       drive,
		DeltaLink:     deltaLink,
		Overlay:       overlay,
	})
	if err != nil {
		t.Fatalf("SyncOneDrive: %v", err)
	}
	t.Logf("stats=%v changes=%v entries=%d", res.Stats, overlay.HasChanges(), overlay.EntryCount())

	contentBase := tenant + "/users/" + user + "/onedrive/content"
	for _, name := range []string{
		"cometd_26.4.2_amd64.deb",
		"JellyfinMediaPlayer-1.12.0-windows-x64.exe",
		"MediaCreationTool_22H2.exe",
	} {
		if !overlay.HasPath(contentBase + "/" + name) {
			t.Fatalf("missing overlay path %q", name)
		}
	}

	root := overlay.Build("snapshot")
	contentDir, err := walkToDir(root, tenant, "users", user, "onedrive", "content")
	if err != nil {
		t.Fatalf("walk tree: %v", err)
	}
	children, err := contentDir.Readdir(context.Background())
	if err != nil {
		t.Fatalf("readdir: %v", err)
	}
	var names []string
	for _, c := range children {
		names = append(names, c.Name())
	}
	t.Logf("built tree content children: %v", names)
	for _, want := range []string{"SeederData", "cometd_26.4.2_amd64.deb"} {
		found := false
		for _, n := range names {
			if n == want {
				found = true
				break
			}
		}
		if !found {
			t.Fatalf("built tree missing %q among %v", want, names)
		}
	}
}

func walkToDir(root kopiafs.Entry, parts ...string) (kopiafs.Directory, error) {
	cur := root
	for _, part := range parts {
		dir, ok := cur.(kopiafs.Directory)
		if !ok {
			return nil, os.ErrInvalid
		}
		next, err := dir.Child(context.Background(), part)
		if err != nil {
			return nil, err
		}
		cur = next
	}
	d, ok := cur.(kopiafs.Directory)
	if !ok {
		return nil, os.ErrInvalid
	}
	return d, nil
}

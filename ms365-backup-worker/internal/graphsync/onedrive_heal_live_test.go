//go:build live

package graphsync

import (
	"context"
	"os"
	"testing"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

func TestHealOneDriveRootFilesLive(t *testing.T) {
	token := os.Getenv("GRAPH_TOKEN")
	if token == "" {
		b, err := os.ReadFile("/tmp/graph_token.txt")
		if err != nil || len(b) == 0 {
			t.Skip("GRAPH_TOKEN not set")
		}
		token = string(b)
	}

	tenant := "cfb5450a-eb80-4c61-aecc-9dca87649cf6"
	user := "1533e37a-2e8f-4f24-8155-11777c70997d"
	drive := "b!MpMKMk4AikK-x5M_QRdU2J0oEti9gbZOqyOS9Y7x9XCTYCWJG1XmTqhM61-Zakfd"

	overlay := graphfs.NewOverlayBuilder()
	overlay.PutJSON(tenant+"/users/"+user+"/onedrive/content/SeederData/seed.txt", []byte("x"), time.Now())

	client := graph.NewClient(token, "GlobalPublicCloud", graph.ClientOptions{})
	opts := OneDriveSyncOptions{
		AzureTenantID: tenant,
		UserID:        user,
		DriveID:       drive,
		Overlay:       overlay,
	}

	added, err := healOneDriveRootFiles(context.Background(), client, opts, drive)
	if err != nil {
		t.Fatalf("heal: %v", err)
	}
	if added < 3 {
		t.Fatalf("added %d root files, want at least 3", added)
	}
	for _, name := range []string{
		"cometd_26.4.2_amd64.deb",
		"JellyfinMediaPlayer-1.12.0-windows-x64.exe",
		"MediaCreationTool_22H2.exe",
	} {
		p := tenant + "/users/" + user + "/onedrive/content/" + name
		if !overlay.HasPath(p) {
			t.Fatalf("missing healed path %q", p)
		}
	}
}

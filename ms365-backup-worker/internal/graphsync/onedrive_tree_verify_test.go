package graphsync

import (
	"context"
	"testing"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

func TestVerifyOneDriveOverlayTreeSkipsWhenNoRootContentFiles(t *testing.T) {
	tenant := "tenant-a"
	user := "user-1"
	overlay := graphfs.NewOverlayBuilder()
	// Graph sync only — no root-level files under onedrive/content.
	overlay.PutJSON(tenant+"/users/"+user+"/mail/inbox/msg.json", []byte("{}"), time.Now())

	tree := overlay.Build("snapshot")
	if err := VerifyOneDriveOverlayTree(context.Background(), overlay, tree, tenant, user); err != nil {
		t.Fatalf("expected nil when no onedrive root content files, got %v", err)
	}
}

func TestVerifyOneDriveOverlayTreeRequiresContentDirWhenRootFilesPresent(t *testing.T) {
	tenant := "tenant-a"
	user := "user-1"
	overlay := graphfs.NewOverlayBuilder()
	overlay.PutJSON(tenant+"/users/"+user+"/onedrive/content/doc.txt", []byte("x"), time.Now())

	empty := graphfs.NewOverlayBuilder().Build("snapshot")
	err := VerifyOneDriveOverlayTree(context.Background(), overlay, empty, tenant, user)
	if err == nil {
		t.Fatal("expected error when root overlay file missing from tree")
	}
}

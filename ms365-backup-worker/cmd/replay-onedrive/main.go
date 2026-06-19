package main

import (
	"context"
	"encoding/json"
	"fmt"
	"os"
	"strings"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
	"github.com/eazybackup/ms365-backup-worker/internal/graphsync"
	"github.com/eazybackup/ms365-backup-worker/internal/kopia"
	kopiafs "github.com/kopia/kopia/fs"
)

type claim struct {
	AzureTenantID      string `json:"azure_tenant_id"`
	GraphID            string `json:"graph_id"`
	DriveID            string `json:"drive_id"`
	GraphToken         string `json:"graph_token"`
	PreviousManifestID string `json:"previous_manifest_id"`
	KopiaSourcePath    string `json:"kopia_source_path"`
	DestEndpoint       string `json:"dest_endpoint"`
	DestBucket         string `json:"dest_bucket"`
	DestPrefix         string `json:"dest_prefix"`
	DestAccessKey      string `json:"dest_access_key"`
	DestSecretKey      string `json:"dest_secret_key"`
	RepoPassword       string `json:"repo_password"`
	OneDriveDelta      string `json:"onedrive_delta"`
}

func main() {
	b, err := os.ReadFile("/tmp/claim.json")
	if err != nil {
		fatal(err)
	}
	var c claim
	if err := json.Unmarshal(b, &c); err != nil {
		fatal(err)
	}

	ctx := context.Background()
	overlay := graphfs.NewOverlayBuilder()

	if c.PreviousManifestID != "" {
		storage := kopia.StorageOptions{
			Endpoint:     c.DestEndpoint,
			Bucket:       c.DestBucket,
			Prefix:       c.DestPrefix,
			AccessKey:    c.DestAccessKey,
			SecretKey:    c.DestSecretKey,
			RepoPassword: c.RepoPassword,
		}
		pool := kopia.NewPool(kopia.RepoCacheSettings{RepoConfigDir: "/tmp/ms365-replay"})
		priorRoot, err := pool.PriorSnapshotRoot(ctx, storage, c.PreviousManifestID)
		if err != nil {
			fatal(fmt.Errorf("prior: %w", err))
		}
		if err := overlay.MergePrior(ctx, priorRoot, ""); err != nil {
			fatal(fmt.Errorf("merge: %w", err))
		}
		fmt.Printf("merged prior %s entries=%d\n", c.PreviousManifestID, overlay.EntryCount())
	}

	client := graph.NewClient(c.GraphToken, "GlobalPublicCloud", graph.ClientOptions{})
	res, err := graphsync.SyncOneDrive(ctx, client, graphsync.OneDriveSyncOptions{
		AzureTenantID: c.AzureTenantID,
		UserID:        c.GraphID,
		DriveID:       c.DriveID,
		DeltaLink:     c.OneDriveDelta,
		Overlay:       overlay,
	})
	if err != nil {
		fatal(err)
	}
	fmt.Printf("onedrive stats=%v changes=%v entries=%d\n", res.Stats, overlay.HasChanges(), overlay.EntryCount())

	contentBase := c.AzureTenantID + "/users/" + c.GraphID + "/onedrive/content"
	for _, name := range []string{
		"cometd_26.4.2_amd64.deb",
		"JellyfinMediaPlayer-1.12.0-windows-x64.exe",
		"MediaCreationTool_22H2.exe",
	} {
		fmt.Printf("HasPath(%s)=%v\n", name, overlay.HasPath(contentBase+"/"+name))
	}

	root := overlay.Build("snapshot")
	contentDir, err := walkToDir(root, strings.Split(contentBase, "/")...)
	if err != nil {
		fatal(err)
	}
	children, err := contentDir.Readdir(ctx)
	if err != nil {
		fatal(err)
	}
	fmt.Print("content children:")
	for _, ch := range children {
		fmt.Printf(" %s", ch.Name())
	}
	fmt.Println()

	if os.Getenv("KOPIA_SNAPSHOT") != "1" {
		return
	}

	storage := kopia.StorageOptions{
		Endpoint:     c.DestEndpoint,
		Bucket:       c.DestBucket,
		Prefix:       c.DestPrefix,
		AccessKey:    c.DestAccessKey,
		SecretKey:    c.DestSecretKey,
		RepoPassword: c.RepoPassword,
	}
	pool := kopia.NewPool(kopia.RepoCacheSettings{RepoConfigDir: "/tmp/ms365-replay"})
	snapRes, err := pool.Snapshot(ctx, kopia.SnapshotRequest{
		Storage:    storage,
		SourcePath: c.KopiaSourcePath,
		Entry:      root,
		Parallel:   8,
		Compressor: "zstd-default",
	})
	if err != nil {
		fatal(err)
	}
	fmt.Printf("snapshot manifest=%s hashed=%d uploaded=%d files=%d\n",
		snapRes.ManifestID, snapRes.BytesHashed, snapRes.BytesUploaded, snapRes.FilesDone)

	browse, err := pool.Browse(ctx, kopia.BrowseRequest{
		Storage:    storage,
		ManifestID: snapRes.ManifestID,
		Path:       contentBase,
	})
	if err != nil {
		fatal(err)
	}
	fmt.Print("browse content:")
	for _, e := range browse.Entries {
		fmt.Printf(" %s(%s)", e.Name, e.Type)
	}
	fmt.Println()
}

func walkToDir(root kopiafs.Entry, parts ...string) (kopiafs.Directory, error) {
	cur := root
	for _, part := range parts {
		dir, ok := cur.(kopiafs.Directory)
		if !ok {
			return nil, fmt.Errorf("not dir at %s", part)
		}
		next, err := dir.Child(context.Background(), part)
		if err != nil {
			return nil, err
		}
		cur = next
	}
	d, ok := cur.(kopiafs.Directory)
	if !ok {
		return nil, fmt.Errorf("target not directory")
	}
	return d, nil
}

func fatal(err error) {
	fmt.Fprintf(os.Stderr, "error: %v\n", err)
	os.Exit(1)
}

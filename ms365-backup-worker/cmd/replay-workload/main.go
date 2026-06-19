package main

import (
	"context"
	"encoding/json"
	"fmt"
	"os"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
	"github.com/eazybackup/ms365-backup-worker/internal/graphsync"
	"github.com/eazybackup/ms365-backup-worker/internal/kopia"
)

func main() {
	b, err := os.ReadFile("/tmp/claim-full.json")
	if err != nil {
		fatal(err)
	}
	var job api.RunJob
	if err := json.Unmarshal(b, &job); err != nil {
		fatal(err)
	}

	ctx := context.Background()
	overlay := graphfs.NewOverlayBuilder()

	if job.PreviousManifest != "" {
		storage := kopia.StorageOptions{
			Endpoint:     job.DestEndpoint,
			Bucket:       job.DestBucket,
			Prefix:       job.DestPrefix,
			AccessKey:    job.DestAccessKey,
			SecretKey:    job.DestSecretKey,
			RepoPassword: job.RepoPassword,
		}
		pool := kopia.NewPool(kopia.RepoCacheSettings{RepoConfigDir: "/tmp/ms365-replay"})
		priorRoot, err := pool.PriorSnapshotRoot(ctx, storage, job.PreviousManifest)
		if err != nil {
			fatal(err)
		}
		if err := overlay.MergePrior(ctx, priorRoot, ""); err != nil {
			fatal(err)
		}
		fmt.Printf("merged prior entries=%d\n", overlay.EntryCount())
	}

	gc := graph.NewClient(job.GraphToken, job.GraphRegion, graph.ClientOptions{})
	wl := &graphsync.WorkloadRunner{
		Client:   gc,
		Job:      &job,
		Parallel: 8,
		Overlay:  overlay,
	}
	res, err := wl.Run(ctx)
	if err != nil {
		fatal(err)
	}
	fmt.Printf("workloads stats=%v fileCount=%d changes=%v\n", res.Stats, res.FileCount, overlay.HasChanges())

	contentBase := job.AzureTenantID + "/users/" + job.GraphID + "/onedrive/content"
	for _, name := range []string{"cometd_26.4.2_amd64.deb", "JellyfinMediaPlayer-1.12.0-windows-x64.exe", "MediaCreationTool_22H2.exe"} {
		fmt.Printf("HasPath(%s)=%v\n", name, overlay.HasPath(contentBase+"/"+name))
	}

	if os.Getenv("KOPIA_SNAPSHOT") != "1" {
		return
	}

	root := overlay.Build("snapshot")
	storage := kopia.StorageOptions{
		Endpoint:     job.DestEndpoint,
		Bucket:       job.DestBucket,
		Prefix:       job.DestPrefix,
		AccessKey:    job.DestAccessKey,
		SecretKey:    job.DestSecretKey,
		RepoPassword: job.RepoPassword,
	}
	pool := kopia.NewPool(kopia.RepoCacheSettings{RepoConfigDir: "/tmp/ms365-replay"})
	snapRes, err := pool.Snapshot(ctx, kopia.SnapshotRequest{
		Storage:    storage,
		SourcePath: job.KopiaSourcePath,
		Host:       "ms365-worker-01",
		Username:   "ms365",
		Entry:      root,
		Parallel:   8,
		Compressor: "zstd-default",
	})
	if err != nil {
		fatal(err)
	}
	fmt.Printf("snapshot manifest=%s hashed=%d uploaded=%d\n", snapRes.ManifestID, snapRes.BytesHashed, snapRes.BytesUploaded)
	browse, err := pool.Browse(ctx, kopia.BrowseRequest{
		Storage:    storage,
		ManifestID: snapRes.ManifestID,
		Path:       contentBase,
	})
	if err != nil {
		fatal(err)
	}
	fmt.Print("browse:")
	for _, e := range browse.Entries {
		fmt.Printf(" %s(%s)", e.Name, e.Type)
	}
	fmt.Println()
}

func fatal(err error) {
	fmt.Fprintf(os.Stderr, "error: %v\n", err)
	os.Exit(1)
}

package main

import (
	"context"
	"encoding/json"
	"flag"
	"log"
	"os"
	"os/signal"
	"syscall"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/config"
	"github.com/eazybackup/ms365-backup-worker/internal/jobs"
	"github.com/eazybackup/ms365-backup-worker/internal/kopia"
	"github.com/eazybackup/ms365-backup-worker/internal/updater"
)

func main() {
	updater.FinalizePendingInstall()

	if len(os.Args) > 1 && os.Args[1] == "browse" {
		if err := runBrowseCLI(os.Args[2:]); err != nil {
			log.Fatalf("browse: %v", err)
		}
		return
	}

	var configPath string
	flag.StringVar(&configPath, "config", "/etc/ms365-backup-worker/config.yaml", "Path to config.yaml")
	flag.Parse()

	cfg, err := config.Load(configPath)
	if err != nil {
		log.Fatalf("config: %v", err)
	}

	client := api.NewClient(cfg.API.BaseURL, cfg.Worker.Token, cfg.Worker.NodeID)
	scheduler := jobs.NewScheduler(cfg, client, configPath)

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	sigCh := make(chan os.Signal, 1)
	signal.Notify(sigCh, syscall.SIGINT, syscall.SIGTERM)
	go func() {
		<-sigCh
		log.Println("shutting down...")
		cancel()
	}()

	log.Printf("ms365-backup-worker starting host=%s max_concurrent=%d stall_seconds=%d graph_stall_check=%ds",
		cfg.Worker.Hostname, cfg.Worker.MaxConcurrentRuns, cfg.Kopia.StallSeconds, cfg.Kopia.StallCheckIntervalSeconds)
	if err := scheduler.Run(ctx); err != nil && err != context.Canceled {
		log.Fatalf("scheduler: %v", err)
	}
}

type browseCLIRequest struct {
	ManifestID    string `json:"manifest_id"`
	Path          string `json:"path"`
	Limit         int    `json:"limit"`
	Offset        int    `json:"offset"`
	DestEndpoint  string `json:"dest_endpoint"`
	DestRegion   string `json:"dest_region"`
	DestBucket   string `json:"dest_bucket"`
	DestPrefix   string `json:"dest_prefix"`
	DestAccessKey string `json:"dest_access_key"`
	DestSecretKey string `json:"dest_secret_key"`
	RepoPassword string `json:"repo_password"`
	RepoConfig   string `json:"repo_config"`
}

func runBrowseCLI(args []string) error {
	fs := flag.NewFlagSet("browse", flag.ExitOnError)
	_ = fs.Parse(args)

	var req browseCLIRequest
	if err := json.NewDecoder(os.Stdin).Decode(&req); err != nil {
		return err
	}
	storage := kopia.StorageOptions{
		Endpoint:     req.DestEndpoint,
		Region:       req.DestRegion,
		Bucket:       req.DestBucket,
		Prefix:       req.DestPrefix,
		AccessKey:    req.DestAccessKey,
		SecretKey:    req.DestSecretKey,
		RepoPassword: req.RepoPassword,
	}
	result, err := kopia.Browse(context.Background(), kopia.BrowseRequest{
		Storage:    storage,
		ManifestID: req.ManifestID,
		Path:       req.Path,
		Limit:      req.Limit,
		Offset:     req.Offset,
	})
	if err != nil {
		return err
	}
	enc := json.NewEncoder(os.Stdout)
	enc.SetIndent("", "  ")
	return enc.Encode(result)
}

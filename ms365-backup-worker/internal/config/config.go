package config

import (
	"fmt"
	"os"
	"strconv"
	"strings"
	"time"

	"gopkg.in/yaml.v3"
)

type Config struct {
	Worker WorkerConfig `yaml:"worker"`
	API    APIConfig    `yaml:"api"`
	Kopia  KopiaConfig  `yaml:"kopia"`
	Graph  GraphConfig  `yaml:"graph"`
}

func (c *Config) GraphTokenRefreshInterval() time.Duration {
	sec := c.Worker.TokenRefreshSeconds
	if sec <= 0 {
		sec = 2700
	}
	return time.Duration(sec) * time.Second
}

type WorkerConfig struct {
	NodeID                     string `yaml:"node_id"`
	Hostname                   string `yaml:"hostname"`
	Token                      string `yaml:"token"`
	PollIntervalSeconds        int    `yaml:"poll_interval_seconds"`
	MaxConcurrentRuns          int    `yaml:"max_concurrent_runs"`
	GraphParallelRequests         int `yaml:"graph_parallel_requests"`
	GraphFolderParallel           int `yaml:"graph_folder_parallel"`
	GraphSharePointDriveParallel  int `yaml:"graph_sharepoint_drive_parallel"`
	HeartbeatIntervalSeconds   int    `yaml:"heartbeat_interval_seconds"`
	ProgressHeartbeatSeconds   int    `yaml:"progress_heartbeat_seconds"`
	// MaxRunSeconds is a safety ceiling on a single run's working context. It is
	// deliberately decoupled from the server lease: the control plane keeps a live
	// run's lease fresh via heartbeat/progress, so the worker must NOT self-cancel
	// at the initial lease window (that killed long whale-scale snapshots mid-write).
	// This only bounds genuinely stuck runs from holding a slot forever. 0 = unbounded.
	MaxRunSeconds              int    `yaml:"max_run_seconds"`
	TokenRefreshSeconds        int    `yaml:"graph_token_refresh_seconds"`
	InstallPath                string `yaml:"install_path"`
	RunDir                     string `yaml:"run_dir"`
	ProxmoxVmid                int    `yaml:"proxmox_vmid"`
	DiskWatermarkMiB           int    `yaml:"disk_watermark_mib"`
	RamBudgetMiB               int    `yaml:"ram_budget_mib"`
	DiskBudgetMiB              int    `yaml:"disk_budget_mib"`
	MaxCPUCores                float64 `yaml:"max_cpu_cores"`
	JobRamBudgetMiB            int    `yaml:"job_ram_budget_mib"`
	JobDiskBudgetMiB           int    `yaml:"job_disk_budget_mib"`
	HeavyJobRamBudgetMiB       int    `yaml:"heavy_job_ram_budget_mib"`
	HeavyJobDiskBudgetMiB      int    `yaml:"heavy_job_disk_budget_mib"`
}

type APIConfig struct {
	BaseURL string `yaml:"base_url"`
}

type KopiaConfig struct {
	RepoConfigDir              string `yaml:"repo_config_dir"`
	ParallelUploads            int    `yaml:"parallel_uploads"`
	Compressor                 string `yaml:"compressor"`
	MaxPackSizeMiB             int    `yaml:"max_pack_size_mib"`
	ContentCacheSizeMiB        int    `yaml:"content_cache_size_mib"`
	CheckpointIntervalMinutes  int    `yaml:"checkpoint_interval_minutes"`
}

type GraphConfig struct {
	MaxRetries           int   `yaml:"max_retries"`
	RetryBaseDelayMs     int   `yaml:"retry_base_delay_ms"`
	AdaptiveConcurrency  *bool `yaml:"adaptive_concurrency"`
	UseBatchFallback     bool  `yaml:"use_batch_fallback"`
	GlobalMaxConcurrency int   `yaml:"global_max_concurrency"`
}

func (g GraphConfig) AdaptiveEnabled() bool {
	if g.AdaptiveConcurrency == nil {
		return true
	}
	return *g.AdaptiveConcurrency
}

func Load(path string) (*Config, error) {
	data, err := os.ReadFile(path)
	if err != nil {
		return nil, fmt.Errorf("read config: %w", err)
	}
	var cfg Config
	if err := yaml.Unmarshal(data, &cfg); err != nil {
		return nil, fmt.Errorf("parse yaml: %w", err)
	}
	cfg.applyDefaults()
	if err := cfg.validate(); err != nil {
		return nil, err
	}
	return &cfg, nil
}

func (c *Config) applyDefaults() {
	if c.Worker.PollIntervalSeconds <= 0 {
		c.Worker.PollIntervalSeconds = 5
	}
	if c.Worker.MaxConcurrentRuns <= 0 {
		c.Worker.MaxConcurrentRuns = 6
	}
	if c.Worker.GraphParallelRequests <= 0 {
		c.Worker.GraphParallelRequests = 8
	}
	if c.Worker.GraphFolderParallel <= 0 {
		c.Worker.GraphFolderParallel = 4
	}
	if c.Worker.GraphSharePointDriveParallel <= 0 {
		c.Worker.GraphSharePointDriveParallel = 4
	}
	if c.Worker.GraphSharePointDriveParallel > 16 {
		c.Worker.GraphSharePointDriveParallel = 16
	}
	if c.Worker.HeartbeatIntervalSeconds <= 0 {
		c.Worker.HeartbeatIntervalSeconds = 30
	}
	if c.Worker.ProgressHeartbeatSeconds <= 0 {
		c.Worker.ProgressHeartbeatSeconds = 60
	}
	if c.Worker.MaxRunSeconds == 0 {
		c.Worker.MaxRunSeconds = 43200 // 12h safety net for whale-scale single-resource runs
	}
	if c.Worker.RunDir == "" {
		c.Worker.RunDir = "/var/lib/ms365-backup-worker/runs"
	}
	if c.Worker.InstallPath == "" {
		c.Worker.InstallPath = "/var/lib/ms365-backup-worker/bin/ms365-backup-worker"
	}
	if c.Worker.DiskWatermarkMiB <= 0 {
		c.Worker.DiskWatermarkMiB = 2048
	}
	if c.Worker.RamBudgetMiB <= 0 {
		c.Worker.RamBudgetMiB = 92160 // ~90 GB headroom on 100 GB nodes
	}
	if c.Worker.DiskBudgetMiB <= 0 {
		c.Worker.DiskBudgetMiB = 409600 // ~400 GB on 500 GB nodes
	}
	if c.Worker.MaxCPUCores <= 0 {
		c.Worker.MaxCPUCores = 20
	}
	if c.Worker.JobRamBudgetMiB <= 0 {
		c.Worker.JobRamBudgetMiB = 512
	}
	if c.Worker.JobDiskBudgetMiB <= 0 {
		c.Worker.JobDiskBudgetMiB = 4096
	}
	if c.Worker.HeavyJobRamBudgetMiB <= 0 {
		c.Worker.HeavyJobRamBudgetMiB = 2048
	}
	if c.Worker.HeavyJobDiskBudgetMiB <= 0 {
		c.Worker.HeavyJobDiskBudgetMiB = 8192
	}
	if c.Kopia.RepoConfigDir == "" {
		c.Kopia.RepoConfigDir = "/var/lib/ms365-backup-worker/kopia"
	}
	if c.Kopia.ParallelUploads <= 0 {
		c.Kopia.ParallelUploads = 4
	}
	if c.Kopia.Compressor == "" {
		c.Kopia.Compressor = "zstd-default"
	}
	if c.Kopia.MaxPackSizeMiB <= 0 {
		c.Kopia.MaxPackSizeMiB = 32
	}
	if c.Kopia.ContentCacheSizeMiB <= 0 {
		c.Kopia.ContentCacheSizeMiB = 512
	}
	if c.Kopia.CheckpointIntervalMinutes <= 0 {
		c.Kopia.CheckpointIntervalMinutes = 15
	}
	if c.Graph.MaxRetries <= 0 {
		c.Graph.MaxRetries = 5
	}
	if c.Graph.RetryBaseDelayMs <= 0 {
		c.Graph.RetryBaseDelayMs = 2000
	}
	if c.Graph.GlobalMaxConcurrency <= 0 {
		c.Graph.GlobalMaxConcurrency = 24
	}
	if c.Worker.Hostname == "" {
		if h, err := os.Hostname(); err == nil && h != "" {
			c.Worker.Hostname = h
		}
	}
	if c.Worker.Token == "" {
		c.Worker.Token = os.Getenv("MS365_WORKER_TOKEN")
	}
	if c.API.BaseURL == "" {
		c.API.BaseURL = os.Getenv("MS365_WORKER_API_BASE")
	}
	if c.Worker.ProxmoxVmid <= 0 {
		if v := strings.TrimSpace(os.Getenv("PROXMOX_VMID")); v != "" {
			if n, err := strconv.Atoi(v); err == nil && n > 0 {
				c.Worker.ProxmoxVmid = n
			}
		}
	}
}

func (c *Config) validate() error {
	if c.API.BaseURL == "" {
		return fmt.Errorf("api.base_url is required")
	}
	if c.Worker.Token == "" {
		return fmt.Errorf("worker.token is required")
	}
	return nil
}

func (c *Config) PollInterval() time.Duration {
	return time.Duration(c.Worker.PollIntervalSeconds) * time.Second
}

func (c *Config) HeartbeatInterval() time.Duration {
	return time.Duration(c.Worker.HeartbeatIntervalSeconds) * time.Second
}

func (c *Config) ProgressHeartbeat() time.Duration {
	return time.Duration(c.Worker.ProgressHeartbeatSeconds) * time.Second
}

// MaxRunDuration returns the working-context safety ceiling for a single run.
// A value <= 0 means unbounded (rely solely on server-side lease/orphan handling).
func (c *Config) MaxRunDuration() time.Duration {
	if c.Worker.MaxRunSeconds <= 0 {
		return 0
	}
	return time.Duration(c.Worker.MaxRunSeconds) * time.Second
}

func (c *KopiaConfig) CheckpointInterval() time.Duration {
	return time.Duration(c.CheckpointIntervalMinutes) * time.Minute
}

func (c *WorkerConfig) DiskWatermarkBytes() int64 {
	return int64(c.DiskWatermarkMiB) << 20
}

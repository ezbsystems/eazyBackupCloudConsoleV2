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
	NodeID                       string `yaml:"node_id"`
	Hostname                     string `yaml:"hostname"`
	Token                        string `yaml:"token"`
	PollIntervalSeconds          int    `yaml:"poll_interval_seconds"`
	MaxConcurrentRuns            int    `yaml:"max_concurrent_runs"`
	GraphParallelRequests        int    `yaml:"graph_parallel_requests"`
	GraphFolderParallel          int    `yaml:"graph_folder_parallel"`
	GraphSharePointDriveParallel int    `yaml:"graph_sharepoint_drive_parallel"`
	HeartbeatIntervalSeconds     int    `yaml:"heartbeat_interval_seconds"`
	ProgressHeartbeatSeconds     int    `yaml:"progress_heartbeat_seconds"`
	// ProgressMinIntervalSeconds coalesces high-frequency progress callbacks (Kopia
	// emits a progress event per hashed/uploaded chunk). Without this throttle the
	// worker POSTs thousands of progress updates per run, each fanning out to several
	// committed DB transactions on the control plane and pinning mysqld via the
	// fsync commit convoy. The periodic ProgressHeartbeat still guarantees lease
	// renewal, so coalescing intermediate updates only trades UI granularity for DB health.
	ProgressMinIntervalSeconds int `yaml:"progress_min_interval_seconds"`
	// ProgressStallSeconds marks heartbeat payloads with no_progress after items/bytes
	// are flat for this long so the control plane stops renewing the lease.
	ProgressStallSeconds int `yaml:"progress_stall_seconds"`
	// MaxRunSeconds is a safety ceiling on a single run's working context. It is
	// deliberately decoupled from the server lease: the control plane keeps a live
	// run's lease fresh via heartbeat/progress, so the worker must NOT self-cancel
	// at the initial lease window (that killed long whale-scale snapshots mid-write).
	// This only bounds genuinely stuck runs from holding a slot forever. 0 = unbounded.
	MaxRunSeconds         int     `yaml:"max_run_seconds"`
	TokenRefreshSeconds   int     `yaml:"graph_token_refresh_seconds"`
	InstallPath           string  `yaml:"install_path"`
	RunDir                string  `yaml:"run_dir"`
	ProxmoxVmid           int     `yaml:"proxmox_vmid"`
	DiskWatermarkMiB      int     `yaml:"disk_watermark_mib"`
	// DiskFlushWatermarkMiB triggers mid-batch flush (GC run dirs + evict idle caches) when
	// real free space drops below this threshold. Defaults to 2× disk_watermark_mib.
	DiskFlushWatermarkMiB int `yaml:"disk_flush_watermark_mib"`
	// UpdateReserveMiB is headroom reserved for binary update staging so cache-heavy
	// backups cannot consume the space needed to deploy recovery code.
	UpdateReserveMiB int `yaml:"update_reserve_mib"`
	// DiskHysteresisMiB is extra free space required before resuming admissions after pressure.
	DiskHysteresisMiB int `yaml:"disk_hysteresis_mib"`
	// RunDirGCTTLSeconds is the grace period before an orphaned run directory (not in s.running)
	// may be deleted by gcOrphanedRuns. Active runs are always protected.
	RunDirGCTTLSeconds    int     `yaml:"run_dir_gc_ttl_seconds"`
	RamBudgetMiB          int     `yaml:"ram_budget_mib"`
	DiskBudgetMiB         int     `yaml:"disk_budget_mib"`
	MaxCPUCores           float64 `yaml:"max_cpu_cores"`
	JobRamBudgetMiB       int     `yaml:"job_ram_budget_mib"`
	JobDiskBudgetMiB      int     `yaml:"job_disk_budget_mib"`
	HeavyJobRamBudgetMiB  int     `yaml:"heavy_job_ram_budget_mib"`
	HeavyJobDiskBudgetMiB int     `yaml:"heavy_job_disk_budget_mib"`
	// HeavyJobCPUCores is the CPU budget charged per drive/site/onedrive job (I/O-bound; default 1).
	HeavyJobCPUCores float64 `yaml:"heavy_job_cpu_cores"`
	// ArchiveParallelExtracts is the number of concurrent Kopia reads while building
	// a restore archive zip (zip entries are still written in order).
	ArchiveParallelExtracts int `yaml:"archive_parallel_extracts"`
}

type APIConfig struct {
	BaseURL string `yaml:"base_url"`
}

type KopiaConfig struct {
	RepoConfigDir             string `yaml:"repo_config_dir"`
	ParallelUploads           int    `yaml:"parallel_uploads"`
	Compressor                string `yaml:"compressor"`
	MaxPackSizeMiB            int    `yaml:"max_pack_size_mib"`
	ContentCacheSizeMiB       int `yaml:"content_cache_size_mib"`
	MetadataCacheSizeMiB      int `yaml:"metadata_cache_size_mib"`
	MinIndexSweepAgeSeconds   int `yaml:"min_index_sweep_age_seconds"`
	IndexMaintenanceThreshold int `yaml:"index_maintenance_threshold"`
	CheckpointIntervalMinutes int `yaml:"checkpoint_interval_minutes"`
	StallSeconds              int    `yaml:"stall_seconds"`
	StallCheckIntervalSeconds int    `yaml:"stall_check_interval_seconds"`
	StallGraceSeconds         int    `yaml:"stall_grace_seconds"`
}

type GraphConfig struct {
	MaxRetries           int   `yaml:"max_retries"`
	RetryBaseDelayMs     int   `yaml:"retry_base_delay_ms"`
	AdaptiveConcurrency  *bool `yaml:"adaptive_concurrency"`
	UseBatchFallback     bool  `yaml:"use_batch_fallback"`
	GlobalMaxConcurrency int   `yaml:"global_max_concurrency"`
	// ThrottleStallCeilingSeconds cancels graph_sync when items/bytes are flat for
	// this long even if Graph 429 activity continues (perpetual throttle wedge).
	// 0 = disabled (default); rely on MaxRunSeconds as the ultimate ceiling.
	ThrottleStallCeilingSeconds int `yaml:"throttle_stall_ceiling_seconds"`
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
	if c.Worker.ProgressMinIntervalSeconds <= 0 {
		c.Worker.ProgressMinIntervalSeconds = 5
	}
	if c.Worker.ProgressStallSeconds <= 0 {
		c.Worker.ProgressStallSeconds = 600
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
	if c.Worker.DiskFlushWatermarkMiB <= 0 {
		c.Worker.DiskFlushWatermarkMiB = c.Worker.DiskWatermarkMiB * 2
	}
	if c.Worker.UpdateReserveMiB <= 0 {
		c.Worker.UpdateReserveMiB = 256
	}
	if c.Worker.DiskHysteresisMiB <= 0 {
		c.Worker.DiskHysteresisMiB = 512
	}
	if c.Worker.RunDirGCTTLSeconds <= 0 {
		c.Worker.RunDirGCTTLSeconds = 3600
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
	if c.Worker.HeavyJobCPUCores <= 0 {
		c.Worker.HeavyJobCPUCores = 1
	}
	if c.Worker.ArchiveParallelExtracts <= 0 {
		c.Worker.ArchiveParallelExtracts = 32
	}
	if c.Worker.ArchiveParallelExtracts > 64 {
		c.Worker.ArchiveParallelExtracts = 64
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
	if c.Kopia.MetadataCacheSizeMiB <= 0 {
		c.Kopia.MetadataCacheSizeMiB = c.Kopia.ContentCacheSizeMiB / 4
		if c.Kopia.MetadataCacheSizeMiB < 64 {
			c.Kopia.MetadataCacheSizeMiB = 64
		}
	}
	if c.Kopia.MinIndexSweepAgeSeconds <= 0 {
		c.Kopia.MinIndexSweepAgeSeconds = 3600
	}
	if c.Kopia.IndexMaintenanceThreshold <= 0 {
		c.Kopia.IndexMaintenanceThreshold = 5000
	}
	if c.Kopia.CheckpointIntervalMinutes <= 0 {
		c.Kopia.CheckpointIntervalMinutes = 15
	}
	if c.Kopia.StallCheckIntervalSeconds <= 0 {
		c.Kopia.StallCheckIntervalSeconds = 60
	}
	if c.Kopia.StallGraceSeconds <= 0 {
		c.Kopia.StallGraceSeconds = 300
	}
	if c.Kopia.StallSeconds <= 0 {
		c.Kopia.StallSeconds = 2700
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
	// ThrottleStallCeilingSeconds defaults to 0 (disabled) so throttle-parked runs
	// are not cancelled; MaxRunSeconds remains the ultimate safety ceiling.
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
	if c.Worker.ProxmoxVmid > 0 {
		h := strings.TrimSpace(c.Worker.Hostname)
		if h == "" || strings.EqualFold(h, "ms365-template") {
			c.Worker.Hostname = fmt.Sprintf("ms365-worker-%d", c.Worker.ProxmoxVmid)
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

// ProgressMinInterval is the minimum spacing between intermediate progress POSTs
// emitted from high-frequency callbacks (Kopia per-chunk hash/upload events).
func (c *Config) ProgressMinInterval() time.Duration {
	if c.Worker.ProgressMinIntervalSeconds <= 0 {
		return 5 * time.Second
	}
	return time.Duration(c.Worker.ProgressMinIntervalSeconds) * time.Second
}

func (c *Config) ProgressStallDuration() time.Duration {
	if c.Worker.ProgressStallSeconds <= 0 {
		return 600 * time.Second
	}
	return time.Duration(c.Worker.ProgressStallSeconds) * time.Second
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

func (c *WorkerConfig) DiskFlushWatermarkBytes() int64 {
	return int64(c.DiskFlushWatermarkMiB) << 20
}

func (c *Config) RunDirGCTTL() time.Duration {
	return time.Duration(c.Worker.RunDirGCTTLSeconds) * time.Second
}

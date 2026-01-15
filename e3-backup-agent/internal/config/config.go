package config

import (
	"fmt"
	"os"
	"time"

	"gopkg.in/yaml.v3"
)

type Config struct {
	Database    DatabaseConfig    `yaml:"database"`
	Worker      WorkerConfig      `yaml:"worker"`
	Rclone      RcloneConfig      `yaml:"rclone"`
	Destination DestinationConfig `yaml:"destination"`
	Logging     LoggingConfig     `yaml:"logging"`
}

type DatabaseConfig struct {
	Host               string `yaml:"host"`
	Port               int    `yaml:"port"`
	Database           string `yaml:"database"`
	Username           string `yaml:"username"`
	Password           string `yaml:"password"`
	MaxConnections     int    `yaml:"max_connections"`
	MaxIdleConnections int    `yaml:"max_idle_connections"`
}

type WorkerConfig struct {
	Hostname            string `yaml:"hostname"`
	PollIntervalSeconds int    `yaml:"poll_interval_seconds"`
	MaxConcurrentJobs   int    `yaml:"max_concurrent_jobs"`
	MaxBandwidthKbps    int    `yaml:"max_bandwidth_kbps"`
}

type RcloneConfig struct {
	BinaryPath   string `yaml:"binary_path"`
	ConfigDir    string `yaml:"config_dir"`
	LogDir       string `yaml:"log_dir"`
	RunDir       string `yaml:"run_dir"`
	StatsInterval string `yaml:"stats_interval"`
	LogLevel     string `yaml:"log_level"`
}

type DestinationConfig struct {
	Endpoint string `yaml:"endpoint"`
	Region   string `yaml:"region"`
}

type LoggingConfig struct {
	Level       string `yaml:"level"`
	File        string `yaml:"file"`
	MaxSizeMB   int    `yaml:"max_size_mb"`
	MaxBackups  int    `yaml:"max_backups"`
	MaxAgeDays  int    `yaml:"max_age_days"`
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
		return nil, fmt.Errorf("validate config: %w", err)
	}
	return &cfg, nil
}

func (c *Config) validate() error {
	if c.Database.Host == "" {
		return fmt.Errorf("database.host is required")
	}
	if c.Database.Port <= 0 || c.Database.Port > 65535 {
		return fmt.Errorf("database.port must be between 1 and 65535")
	}
	if c.Database.Database == "" {
		return fmt.Errorf("database.database is required")
	}
	if c.Database.Username == "" {
		return fmt.Errorf("database.username is required")
	}
	if c.Database.Password == "" {
		return fmt.Errorf("database.password is required")
	}
	if c.Worker.Hostname == "" {
		return fmt.Errorf("worker.hostname is required")
	}
	if c.Destination.Endpoint == "" {
		return fmt.Errorf("destination.endpoint is required")
	}
	return nil
}

func (c *Config) applyDefaults() {
	if c.Worker.PollIntervalSeconds <= 0 {
		c.Worker.PollIntervalSeconds = 10
	}
	if c.Worker.MaxConcurrentJobs <= 0 {
		c.Worker.MaxConcurrentJobs = 2
	}
	if c.Rclone.BinaryPath == "" {
		c.Rclone.BinaryPath = "/usr/bin/rclone"
	}
	if c.Rclone.StatsInterval == "" {
		c.Rclone.StatsInterval = "5s"
	}
	if c.Rclone.LogDir == "" {
		c.Rclone.LogDir = "/var/log/e3-cloudbackup"
	}
	if c.Rclone.RunDir == "" {
		c.Rclone.RunDir = "/var/lib/e3-cloudbackup/runs"
	}
}

func (c *Config) PollInterval() time.Duration {
	return time.Duration(c.Worker.PollIntervalSeconds) * time.Second
}

func (c *Config) MySQLDSN() string {
	// username:password@tcp(host:port)/database?parseTime=true&charset=utf8mb4&loc=UTC
	return fmt.Sprintf(
		"%s:%s@tcp(%s:%d)/%s?parseTime=true&charset=utf8mb4&loc=UTC",
		c.Database.Username,
		c.Database.Password,
		c.Database.Host,
		c.Database.Port,
		c.Database.Database,
	)
}

func GetEncryptionKey() string {
	return os.Getenv("CLOUD_BACKUP_ENCRYPTION_KEY")
}



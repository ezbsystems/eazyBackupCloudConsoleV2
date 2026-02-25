package agent

import (
	"errors"
	"fmt"
	"os"
	"runtime"

	"gopkg.in/yaml.v3"
)

var ErrMissingEnrollment = errors.New("missing enrollment credentials")

// AgentConfig holds the minimal identity and endpoint configuration the agent needs.
// This keeps secrets out of flags and environment variables; the installer will drop
// a small agent.conf that the binary loads on startup.
type AgentConfig struct {
	APIBaseURL string `yaml:"api_base_url" json:"api_base_url"`

	// Optional override. If empty, the agent sets a safe default User-Agent.
	UserAgent string `yaml:"user_agent,omitempty" json:"user_agent,omitempty"`

	// Stable device identity (generated once if missing).
	DeviceID   string `yaml:"device_id,omitempty" json:"device_id,omitempty"`
	InstallID  string `yaml:"install_id,omitempty" json:"install_id,omitempty"`
	DeviceName string `yaml:"device_name,omitempty" json:"device_name,omitempty"`

	// Post-enrollment (persistent credentials)
	ClientID   string `yaml:"client_id,omitempty" json:"client_id,omitempty"`
	AgentUUID  string `yaml:"agent_uuid,omitempty" json:"agent_uuid,omitempty"`
	AgentID    string `yaml:"agent_id,omitempty" json:"agent_id,omitempty"` // legacy key; rejected for enrollment
	AgentToken string `yaml:"agent_token,omitempty" json:"agent_token,omitempty"`

	// Pre-enrollment inputs (used on first run, then cleared)
	EnrollmentToken  string `yaml:"enrollment_token,omitempty" json:"enrollment_token,omitempty"`
	EnrollEmail      string `yaml:"enroll_email,omitempty" json:"enroll_email,omitempty"`
	EnrollPassword   string `yaml:"enroll_password,omitempty" json:"enroll_password,omitempty"`
	EnrollRememberMe *bool  `yaml:"enroll_remember_me,omitempty" json:"enroll_remember_me,omitempty"`

	LogLevel         string `yaml:"log_level" json:"log_level,omitempty"`
	PollIntervalSecs int    `yaml:"poll_interval_secs" json:"poll_interval_secs,omitempty"`
	RunDir           string `yaml:"run_dir" json:"run_dir,omitempty"`
	RcloneBinary     string `yaml:"rclone_binary" json:"rclone_binary,omitempty"`
	DestEndpoint     string `yaml:"dest_endpoint" json:"dest_endpoint,omitempty"`
	DestRegion       string `yaml:"dest_region" json:"dest_region,omitempty"`
}

// LoadConfig reads and validates the agent configuration file.
func LoadConfig(path string) (*AgentConfig, error) {
	data, err := os.ReadFile(path)
	if err != nil {
		return nil, fmt.Errorf("read config: %w", err)
	}

	var cfg AgentConfig
	if err := yaml.Unmarshal(data, &cfg); err != nil {
		return nil, fmt.Errorf("parse config: %w", err)
	}

	if err := cfg.Validate(); err != nil {
		return nil, err
	}

	return &cfg, nil
}

// Validate ensures required fields are present.
func (c *AgentConfig) Validate() error {
	if c.APIBaseURL == "" {
		return fmt.Errorf("api_base_url is required")
	}

	c.applyDefaults()

	// Enrolled config OR pre-enrollment config must be present.
	enrolled := c.AgentUUID != "" && c.AgentToken != ""
	legacyEnrolled := c.AgentID != "" && c.AgentToken != ""
	if !enrolled && legacyEnrolled {
		return fmt.Errorf("%w: legacy agent_id is no longer accepted; set agent_uuid with agent_token", ErrMissingEnrollment)
	}
	preEnrollToken := c.EnrollmentToken != ""
	preEnrollLogin := c.EnrollEmail != "" && c.EnrollPassword != ""
	if !enrolled && !preEnrollToken && !preEnrollLogin {
		return fmt.Errorf("%w: agent_uuid/agent_token are required unless enrollment_token or enroll_email+enroll_password are provided", ErrMissingEnrollment)
	}

	return nil
}

func (c *AgentConfig) applyDefaults() {
	if c.EnrollRememberMe == nil {
		// Default is to remember credentials (installer/tray may override).
		// Note: the agent service clears enrollment fields after successful enrollment regardless.
		def := true
		c.EnrollRememberMe = &def
	}
	if c.PollIntervalSecs == 0 {
		c.PollIntervalSecs = 5
	}
	if c.RcloneBinary == "" {
		c.RcloneBinary = "rclone"
	}
	if c.DestEndpoint == "" {
		c.DestEndpoint = "https://s3.ca-central-1.eazybackup.com"
	}
	if c.UserAgent == "" {
		// A browser-like UA (but still honest) can help avoid over-aggressive WAF bot heuristics.
		// Keep this stable and short; endpoints may log it.
		c.UserAgent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 e3-backup-agent/1.0"
	}
	if c.RunDir == "" {
		// Default per-OS run dir
		if runtime.GOOS == "windows" {
			c.RunDir = `C:\ProgramData\E3Backup\runs`
		} else {
			c.RunDir = "/var/lib/e3-backup-agent/runs"
		}
	}
}

// LoadConfigAllowUnenrolled reads the config and applies defaults, but
// allows missing enrollment credentials so the service can wait for enrollment.
func LoadConfigAllowUnenrolled(path string) (*AgentConfig, error) {
	data, err := os.ReadFile(path)
	if err != nil {
		return nil, fmt.Errorf("read config: %w", err)
	}

	var cfg AgentConfig
	if err := yaml.Unmarshal(data, &cfg); err != nil {
		return nil, fmt.Errorf("parse config: %w", err)
	}

	if err := cfg.Validate(); err != nil {
		if errors.Is(err, ErrMissingEnrollment) {
			return &cfg, err
		}
		return nil, err
	}

	return &cfg, nil
}

// Save writes the config back to disk with restrictive permissions.
func (c *AgentConfig) Save(path string) error {
	data, err := yaml.Marshal(c)
	if err != nil {
		return fmt.Errorf("marshal config: %w", err)
	}
	// Best-effort restrictive permissions. Note: chmod semantics differ on Windows.
	return os.WriteFile(path, data, 0o600)
}


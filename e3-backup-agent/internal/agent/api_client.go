package agent

import (
	"bytes"
	"compress/gzip"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strings"
	"time"
)

// Client wraps HTTP calls to the WHMCS agent API.
type Client struct {
	httpClient *http.Client
	baseURL    string
	agentID    string
	token      string
	deviceID   string
	installID  string
	deviceName string
	userAgent  string
}

func NewClient(cfg *AgentConfig) *Client {
	return &Client{
		httpClient: &http.Client{Timeout: 15 * time.Second},
		baseURL:    strings.TrimRight(cfg.APIBaseURL, "/"),
		agentID:    cfg.AgentID,
		token:      cfg.AgentToken,
		deviceID:   cfg.DeviceID,
		installID:  cfg.InstallID,
		deviceName: cfg.DeviceName,
		userAgent:  strings.TrimSpace(cfg.UserAgent),
	}
}

func (c *Client) applyUserAgent(req *http.Request) {
	if c.userAgent != "" {
		req.Header.Set("User-Agent", c.userAgent)
	}
}

func (c *Client) authHeaders(req *http.Request) {
	req.Header.Set("X-Agent-ID", c.agentID)
	req.Header.Set("X-Agent-Token", c.token)
	req.Header.Set("Content-Type", "application/json")
	c.applyUserAgent(req)
}

// EnrollResponse represents the enrollment payload returned by the server.
type EnrollResponse struct {
	Status     string `json:"status"`
	AgentID    string `json:"agent_id"`
	ClientID   string `json:"client_id"`
	AgentToken string `json:"agent_token"`
	APIBaseURL string `json:"api_base_url"`
	TenantID   *int   `json:"tenant_id,omitempty"`
	Message    string `json:"message"`
}

// EnrollWithToken enrolls the agent using an enrollment token (MSP/RMM flow).
func (c *Client) EnrollWithToken(token, hostname string) (*EnrollResponse, error) {
	form := url.Values{}
	form.Set("token", token)
	form.Set("hostname", hostname)
	if c.deviceID != "" {
		form.Set("device_id", c.deviceID)
	}
	if c.installID != "" {
		form.Set("install_id", c.installID)
	}
	if c.deviceName != "" {
		form.Set("device_name", c.deviceName)
	}

	endpoint := c.baseURL + "/agent_enroll.php"
	req, err := http.NewRequest(http.MethodPost, endpoint, strings.NewReader(form.Encode()))
	if err != nil {
		return nil, err
	}
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	c.applyUserAgent(req)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	var out EnrollResponse
	if err := json.NewDecoder(resp.Body).Decode(&out); err != nil {
		return nil, err
	}
	if out.Status != "success" {
		if out.Message != "" {
			return nil, fmt.Errorf("enroll failed: %s", out.Message)
		}
		return nil, fmt.Errorf("enroll failed: status %s", out.Status)
	}
	return &out, nil
}

// EnrollWithCredentials enrolls the agent using email/password (simple user flow).
func (c *Client) EnrollWithCredentials(email, password, hostname string) (*EnrollResponse, error) {
	form := url.Values{}
	form.Set("email", email)
	form.Set("password", password)
	form.Set("hostname", hostname)
	if c.deviceID != "" {
		form.Set("device_id", c.deviceID)
	}
	if c.installID != "" {
		form.Set("install_id", c.installID)
	}
	if c.deviceName != "" {
		form.Set("device_name", c.deviceName)
	}

	endpoint := c.baseURL + "/agent_login.php"
	req, err := http.NewRequest(http.MethodPost, endpoint, strings.NewReader(form.Encode()))
	if err != nil {
		return nil, err
	}
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	c.applyUserAgent(req)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	var out EnrollResponse
	if err := json.NewDecoder(resp.Body).Decode(&out); err != nil {
		return nil, err
	}
	if out.Status != "success" {
		if out.Message != "" {
			return nil, fmt.Errorf("login enroll failed: %s", out.Message)
		}
		return nil, fmt.Errorf("login enroll failed: status %s", out.Status)
	}
	return &out, nil
}

type Job struct {
	ID                  int64  `json:"id"`
	Name                string `json:"name"`
	SourceType          string `json:"source_type"`
	SourceDisplayName   string `json:"source_display_name"`
	SourceConfigEnc     string `json:"source_config_enc"`
	SourcePath          string `json:"source_path"`
	IncludeGlob         string `json:"include_glob"`
	ExcludeGlob         string `json:"exclude_glob"`
	BandwidthLimitKbps  string `json:"local_bandwidth_limit_kbps"`
	DestBucketID        int64  `json:"dest_bucket_id"`
	DestPrefix          string `json:"dest_prefix"`
	BackupMode          string `json:"backup_mode"`
	EncryptionEnabled   bool   `json:"encryption_enabled"`
	ValidationMode      string `json:"validation_mode"`
	ScheduleType        string `json:"schedule_type"`
	ScheduleTime        string `json:"schedule_time"`
	ScheduleWeekday     int    `json:"schedule_weekday"`
	RetentionMode       string `json:"retention_mode"`
	RetentionValue      int    `json:"retention_value"`
	NotifyOverrideEmail string `json:"notify_override_email"`
	Status              string `json:"status"`
}

type FetchJobsResponse struct {
	Status string `json:"status"`
	Jobs   []Job  `json:"jobs"`
}

type NextRunResponse struct {
	RunID                   int64          `json:"run_id"`
	JobID                   int64          `json:"job_id"`
	Engine                  string         `json:"engine"`
	SourcePath              string         `json:"source_path"`
	SourcePaths             []string       `json:"source_paths,omitempty"`
	LocalIncludeGlob        string         `json:"local_include_glob"`
	LocalExcludeGlob        string         `json:"local_exclude_glob"`
	LocalBandwidthLimitKbps int            `json:"local_bandwidth_limit_kbps"`
	DestType                string         `json:"dest_type"`
	DestBucketName          string         `json:"dest_bucket_name"`
	DestPrefix              string         `json:"dest_prefix"`
	DestEndpoint            string         `json:"dest_endpoint"`
	DestRegion              string         `json:"dest_region"`
	DestLocalPath           string         `json:"dest_local_path"`
	DestAutoCreateBucket    bool           `json:"dest_auto_create_bucket"`
	DestAccessKey           string         `json:"dest_access_key"`
	DestSecretKey           string         `json:"dest_secret_key"`
	ScheduleJSON            map[string]any `json:"schedule_json"`
	RetentionJSON           map[string]any `json:"retention_json"`
	PolicyJSON              map[string]any `json:"policy_json"`
	CompressionEnabled      bool           `json:"compression_enabled"`
	DiskSourceVolume        string         `json:"disk_source_volume"`
	DiskImageFormat         string         `json:"disk_image_format"`
	DiskTempDir             string         `json:"disk_temp_dir"`
	// Network share credentials for UNC paths
	NetworkCredentials *NetworkCredentials `json:"network_credentials,omitempty"`
	// Hyper-V specific fields
	HyperVConfig *HyperVConfig  `json:"hyperv_config,omitempty"`
	HyperVVMs    []HyperVVMRun  `json:"hyperv_vms,omitempty"`
}

// HyperVConfig contains job-level Hyper-V settings.
type HyperVConfig struct {
	VMs                []string `json:"vms"`
	ExcludeVMs         []string `json:"exclude_vms"`
	BackupAllVMs       bool     `json:"backup_all_vms"`
	EnableRCT          bool     `json:"enable_rct"`
	ConsistencyLevel   string   `json:"consistency_level"` // "application" or "crash"
	QuiesceTimeoutSecs int      `json:"quiesce_timeout_seconds"`
}

// HyperVVMRun contains per-VM context for a backup run.
type HyperVVMRun struct {
	VMID             int64             `json:"vm_id"`
	VMName           string            `json:"vm_name"`
	VMGUID           string            `json:"vm_guid"`
	LastCheckpointID string            `json:"last_checkpoint_id"`
	LastRCTIDs       map[string]string `json:"last_rct_ids"` // disk path -> RCT ID
}

func (c *Client) NextRun() (*NextRunResponse, error) {
	endpoint := c.baseURL + "/agent_next_run.php"
	req, err := http.NewRequest(http.MethodGet, endpoint, nil)
	if err != nil {
		return nil, err
	}
	c.authHeaders(req)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("next run status %d", resp.StatusCode)
	}

	var out struct {
		Status  string           `json:"status"`
		Message string           `json:"message,omitempty"`
		Run     *NextRunResponse `json:"run,omitempty"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&out); err != nil {
		return nil, err
	}
	switch out.Status {
	case "no_run":
		return nil, nil
	case "success":
		return out.Run, nil
	default:
		if out.Message != "" {
			return nil, fmt.Errorf("next run failed: %s", out.Message)
		}
		return nil, fmt.Errorf("next run failed: status %s", out.Status)
	}
}

func (c *Client) FetchJobs() ([]Job, error) {
	endpoint := c.baseURL + "/agent_fetch_jobs.php"
	req, err := http.NewRequest(http.MethodGet, endpoint, nil)
	if err != nil {
		return nil, err
	}
	c.authHeaders(req)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("fetch jobs status %d", resp.StatusCode)
	}

	var out FetchJobsResponse
	if err := json.NewDecoder(resp.Body).Decode(&out); err != nil {
		return nil, err
	}
	if out.Status != "success" {
		return nil, fmt.Errorf("fetch jobs failed: %s", out.Status)
	}
	return out.Jobs, nil
}

// ReportVolumes sends the agent's available volumes to the server for UI consumption.
func (c *Client) ReportVolumes(vols []VolumeInfo) error {
	endpoint := c.baseURL + "/agent_report_volumes.php"
	body := map[string]any{"volumes": vols}
	buf, _ := json.Marshal(body)

	req, err := http.NewRequest(http.MethodPost, endpoint, bytes.NewReader(buf))
	if err != nil {
		return err
	}
	c.authHeaders(req)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("report volumes status %d", resp.StatusCode)
	}
	return nil
}

type StartRunResponse struct {
	Status         string `json:"status"`
	Message        string `json:"message,omitempty"`
	RunID          int64  `json:"run_id,omitempty"`
	SourcePath     string `json:"source_path,omitempty"`
	DestBucketName string `json:"dest_bucket_name,omitempty"`
	DestPrefix     string `json:"dest_prefix,omitempty"`
	DestEndpoint   string `json:"dest_endpoint,omitempty"`
	DestRegion     string `json:"dest_region,omitempty"`
	DestAccessKey  string `json:"dest_access_key,omitempty"`
	DestSecretKey  string `json:"dest_secret_key,omitempty"`
}

func (c *Client) StartRun(jobID int64) (*StartRunResponse, error) {
	endpoint := c.baseURL + "/agent_start_run.php"
	body := map[string]any{"job_id": jobID}
	buf, _ := json.Marshal(body)

	req, err := http.NewRequest(http.MethodPost, endpoint, bytes.NewReader(buf))
	if err != nil {
		return nil, err
	}
	c.authHeaders(req)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	var out StartRunResponse
	if err := json.NewDecoder(resp.Body).Decode(&out); err != nil {
		return nil, err
	}
	if out.Status != "success" {
		return &out, fmt.Errorf("start run failed: %s", out.Message)
	}
	return &out, nil
}

// Int64Ptr returns a pointer to the given int64 value. Useful for optional fields.
func Int64Ptr(v int64) *int64 { return &v }

type RunUpdate struct {
	RunID                int64   `json:"run_id"`
	Status               string  `json:"status,omitempty"`
	StartedAt            string  `json:"started_at,omitempty"`
	FinishedAt           string  `json:"finished_at,omitempty"`
	ProgressPct          float64 `json:"progress_pct,omitempty"`
	BytesTransferred     *int64  `json:"bytes_transferred,omitempty"` // Actual bytes uploaded to storage
	BytesProcessed       *int64  `json:"bytes_processed,omitempty"`   // Bytes read/hashed from source (for dedup progress)
	BytesTotal           *int64  `json:"bytes_total,omitempty"`
	ObjectsTransferred   int64   `json:"objects_transferred,omitempty"`
	ObjectsTotal         int64   `json:"objects_total,omitempty"`
	SpeedBytesPerSec     int64   `json:"speed_bytes_per_sec,omitempty"`
	EtaSeconds           int64   `json:"eta_seconds,omitempty"`
	CurrentItem          string  `json:"current_item,omitempty"`
	LogExcerpt           string  `json:"log_excerpt,omitempty"`
	ErrorSummary         string  `json:"error_summary,omitempty"`
	ValidationStatus     string  `json:"validation_status,omitempty"`
	ValidationLogExcerpt string  `json:"validation_log_excerpt,omitempty"`
	// Manifest/restore helpers
	ManifestID string         `json:"manifest_id,omitempty"`
	StatsJSON  map[string]any `json:"stats_json,omitempty"`
	// Hyper-V specific fields
	DiskManifestsJSON map[string]string `json:"disk_manifests_json,omitempty"`
	HyperVResults     []HyperVVMResult  `json:"hyperv_results,omitempty"`
}

// HyperVVMResult contains the result of backing up a single VM.
type HyperVVMResult struct {
	VMID             int64             `json:"vm_id"`
	VMName           string            `json:"vm_name"`
	BackupType       string            `json:"backup_type"` // "Full" or "Incremental"
	CheckpointID     string            `json:"checkpoint_id"`
	RCTIDs           map[string]string `json:"rct_ids"`
	DiskManifests    map[string]string `json:"disk_manifests"`
	TotalBytes       int64             `json:"total_bytes"`
	ChangedBytes     int64             `json:"changed_bytes"`
	ConsistencyLevel string            `json:"consistency_level"`
	DurationSeconds  int               `json:"duration_seconds"`
	Error            string            `json:"error,omitempty"`
	Warnings         []string          `json:"warnings,omitempty"`
	WarningCode      string            `json:"warning_code,omitempty"` // e.g., "CHECKPOINTS_DISABLED"
}

func (c *Client) UpdateRun(u RunUpdate) error {
	endpoint := c.baseURL + "/agent_update_run.php"
	buf, _ := json.Marshal(u)
	req, err := http.NewRequest(http.MethodPost, endpoint, bytes.NewReader(buf))
	if err != nil {
		return err
	}
	c.authHeaders(req)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		// Try to surface response body for diagnostics
		body, _ := io.ReadAll(resp.Body)
		return fmt.Errorf("update run status %d body=%s", resp.StatusCode, strings.TrimSpace(string(body)))
	}
	return nil
}

type RunEvent struct {
	Type       string         `json:"type,omitempty"`
	Level      string         `json:"level,omitempty"`
	Code       string         `json:"code,omitempty"`
	MessageID  string         `json:"message_id,omitempty"`
	ParamsJSON map[string]any `json:"params_json,omitempty"`
}

func (c *Client) PushEvents(runID int64, events []RunEvent) error {
	if len(events) == 0 {
		return nil
	}
	endpoint := c.baseURL + "/agent_push_events.php"
	body := map[string]any{"run_id": runID, "events": events}
	buf, _ := json.Marshal(body)

	req, err := http.NewRequest(http.MethodPost, endpoint, bytes.NewReader(buf))
	if err != nil {
		return err
	}
	c.authHeaders(req)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		// Surface response body to distinguish app-level 403 (JSON) from proxy/WAF 403 (HTML).
		b, _ := io.ReadAll(resp.Body)
		msg := strings.TrimSpace(string(b))
		if len(msg) > 600 {
			msg = msg[:600] + "…"
		}
		if msg != "" {
			return fmt.Errorf("push events status %d body=%s", resp.StatusCode, msg)
		}
		return fmt.Errorf("push events status %d", resp.StatusCode)
	}
	return nil
}

// CompleteCommand marks a run command as completed/failed.
func (c *Client) CompleteCommand(commandID int64, status, resultMessage string) error {
	endpoint := c.baseURL + "/agent_complete_command.php"
	body := map[string]any{
		"command_id":     commandID,
		"status":         status, // completed|failed
		"result_message": resultMessage,
	}
	buf, _ := json.Marshal(body)
	req, err := http.NewRequest(http.MethodPost, endpoint, bytes.NewReader(buf))
	if err != nil {
		return err
	}
	c.authHeaders(req)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("complete command status %d", resp.StatusCode)
	}
	return nil
}

// ReportBrowseResult sends a filesystem browse result for a command.
func (c *Client) ReportBrowseResult(commandID int64, result BrowseDirectoryResponse) error {
	endpoint := c.baseURL + "/agent_report_browse.php"
	// Some web stacks/WAF rules will block requests containing Windows paths like "C:\Users\..."
	// (often returning HTTP 403 before PHP runs). To make this robust, send the browse result
	// as gzip+base64 and let the server decode it.
	raw, _ := json.Marshal(result)
	var gz bytes.Buffer
	zw := gzip.NewWriter(&gz)
	_, _ = zw.Write(raw)
	_ = zw.Close()

	body := map[string]any{
		"command_id":      commandID,
		"result_encoding": "gzip+base64",
		"result_b64":      base64.StdEncoding.EncodeToString(gz.Bytes()),
	}
	buf, _ := json.Marshal(body)
	req, err := http.NewRequest(http.MethodPost, endpoint, bytes.NewReader(buf))
	if err != nil {
		return err
	}
	c.authHeaders(req)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("report browse status %d", resp.StatusCode)
	}
	return nil
}

// UpdateNASMountStatus updates the mount status in the dashboard.
// status: mounted, unmounted, mounting, unmounting, error
func (c *Client) UpdateNASMountStatus(mountID int64, status, errorMsg string) error {
	endpoint := c.baseURL + "/cloudnas_update_status.php"
	body := map[string]any{
		"mount_id": mountID,
		"status":   status,
	}
	if errorMsg != "" {
		body["error"] = errorMsg
	}
	buf, _ := json.Marshal(body)
	req, err := http.NewRequest(http.MethodPost, endpoint, bytes.NewReader(buf))
	if err != nil {
		return err
	}
	c.authHeaders(req)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("update NAS mount status %d", resp.StatusCode)
	}
	return nil
}

// PendingCommand represents a command waiting to be executed (restore, maintenance).
type PendingCommand struct {
	CommandID  int64          `json:"command_id"`
	Type       string         `json:"type"`
	RunID      int64          `json:"run_id"`
	JobID      int64          `json:"job_id"`
	Payload    map[string]any `json:"payload"`
	JobContext *JobContext    `json:"job_context"`
}

// JobContext provides the job configuration needed to execute commands like restore.
type JobContext struct {
	JobID                   int64  `json:"job_id"`
	RunID                   int64  `json:"run_id"`
	Engine                  string `json:"engine"`
	SourcePath              string `json:"source_path"`
	DestType                string `json:"dest_type"`
	DestBucketName          string `json:"dest_bucket_name"`
	DestPrefix              string `json:"dest_prefix"`
	DestLocalPath           string `json:"dest_local_path"`
	DestEndpoint            string `json:"dest_endpoint"`
	DestRegion              string `json:"dest_region"`
	DestAccessKey           string `json:"dest_access_key"`
	DestSecretKey           string `json:"dest_secret_key"`
	LocalBandwidthLimitKbps int    `json:"local_bandwidth_limit_kbps"`
	ManifestID              string `json:"manifest_id"`
	DiskSourceVolume        string `json:"disk_source_volume"`
	DiskImageFormat         string `json:"disk_image_format"`
	DiskTempDir             string `json:"disk_temp_dir"`
}

// PollPendingCommands fetches pending commands (restore, maintenance) for this agent.
// This is called independently of active runs so the agent can handle restores.
func (c *Client) PollPendingCommands() ([]PendingCommand, error) {
	endpoint := c.baseURL + "/agent_poll_pending_commands.php"
	req, err := http.NewRequest(http.MethodGet, endpoint, nil)
	if err != nil {
		return nil, err
	}
	c.authHeaders(req)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("poll pending commands status %d", resp.StatusCode)
	}

	var out struct {
		Status   string           `json:"status"`
		Message  string           `json:"message,omitempty"`
		Commands []PendingCommand `json:"commands"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&out); err != nil {
		return nil, err
	}
	if out.Status != "success" {
		return nil, fmt.Errorf("poll pending commands failed: %s", out.Message)
	}
	return out.Commands, nil
}

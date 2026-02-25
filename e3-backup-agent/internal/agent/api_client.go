package agent

import (
	"bytes"
	"compress/gzip"
	"crypto/sha256"
	"encoding/base64"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"mime/multipart"
	"net/http"
	"net/url"
	"os"
	"runtime"
	"strings"
	"time"
)

// Client wraps HTTP calls to the WHMCS agent API.
type Client struct {
	httpClient           *http.Client
	baseURL              string
	agentUUID            string
	token                string
	deviceID             string
	installID            string
	deviceName           string
	userAgent            string
	recoverySessionToken string
}

func NewClient(cfg *AgentConfig) *Client {
	return &Client{
		httpClient: &http.Client{Timeout: 15 * time.Second},
		baseURL:    strings.TrimRight(cfg.APIBaseURL, "/"),
		agentUUID:  cfg.AgentUUID,
		token:      cfg.AgentToken,
		deviceID:   cfg.DeviceID,
		installID:  cfg.InstallID,
		deviceName: cfg.DeviceName,
		userAgent:  strings.TrimSpace(cfg.UserAgent),
	}
}

func NewRecoveryClient(apiBaseURL, sessionToken string) *Client {
	return &Client{
		httpClient:           &http.Client{Timeout: 30 * time.Second},
		baseURL:              strings.TrimRight(apiBaseURL, "/"),
		userAgent:            "e3-backup-agent-recovery/1.0",
		recoverySessionToken: sessionToken,
	}
}

func (c *Client) applyUserAgent(req *http.Request) {
	if c.userAgent != "" {
		req.Header.Set("User-Agent", c.userAgent)
	}
}

func (c *Client) authHeaders(req *http.Request) {
	req.Header.Set("X-Agent-UUID", c.agentUUID)
	req.Header.Set("X-Agent-Token", c.token)
	req.Header.Set("Content-Type", "application/json")
	c.applyUserAgent(req)
}

type jsonString string

func (s *jsonString) UnmarshalJSON(b []byte) error {
	if len(b) == 0 {
		*s = ""
		return nil
	}
	if b[0] == '"' {
		var v string
		if err := json.Unmarshal(b, &v); err != nil {
			return err
		}
		*s = jsonString(v)
		return nil
	}
	var num json.Number
	if err := json.Unmarshal(b, &num); err != nil {
		return err
	}
	*s = jsonString(num.String())
	return nil
}

// EnrollResponse represents the enrollment payload returned by the server.
type EnrollResponse struct {
	Status     string     `json:"status"`
	AgentUUID  jsonString `json:"agent_uuid"`
	ClientID   jsonString `json:"client_id"`
	AgentToken string     `json:"agent_token"`
	APIBaseURL string     `json:"api_base_url"`
	TenantID   *int       `json:"tenant_id,omitempty"`
	Message    string     `json:"message"`
}

func (c *Client) addAgentMetadata(form url.Values) {
	version := ""
	ua := strings.TrimSpace(c.userAgent)
	if idx := strings.Index(ua, "/"); idx > -1 && idx < len(ua)-1 {
		version = strings.TrimSpace(ua[idx+1:])
	}
	if version != "" {
		form.Set("agent_version", version)
	}
	form.Set("agent_os", runtime.GOOS)
	form.Set("agent_arch", runtime.GOARCH)
	if b := strings.TrimSpace(os.Getenv("E3_AGENT_BUILD")); b != "" {
		form.Set("agent_build", b)
	}
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
	c.addAgentMetadata(form)

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
	if strings.TrimSpace(string(out.AgentUUID)) == "" || strings.TrimSpace(out.AgentToken) == "" {
		return nil, fmt.Errorf("enroll failed: missing agent credentials")
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
	c.addAgentMetadata(form)

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
	if strings.TrimSpace(string(out.AgentUUID)) == "" || strings.TrimSpace(out.AgentToken) == "" {
		return nil, fmt.Errorf("login enroll failed: missing agent credentials")
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
	RepositoryID            string         `json:"repository_id,omitempty"`
	RepoConfigKey           string         `json:"repo_config_key,omitempty"` // e.g. "repo_7" for repo-scoped ops
	RepositoryPassword      string         `json:"repository_password,omitempty"`
	RepoPasswordMode        string         `json:"repo_password_mode,omitempty"`
	PayloadVersion          string         `json:"payload_version,omitempty"`
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
	HyperVConfig *HyperVConfig `json:"hyperv_config,omitempty"`
	HyperVVMs    []HyperVVMRun `json:"hyperv_vms,omitempty"`
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
	if c.recoverySessionToken != "" {
		endpoint := c.baseURL + "/cloudbackup_recovery_update_run.php"
		payload, _ := json.Marshal(u)
		body := map[string]any{}
		_ = json.Unmarshal(payload, &body)
		body["session_token"] = c.recoverySessionToken
		buf, _ := json.Marshal(body)
		req, err := http.NewRequest(http.MethodPost, endpoint, bytes.NewReader(buf))
		if err != nil {
			return err
		}
		req.Header.Set("Content-Type", "application/json")
		c.applyUserAgent(req)
		resp, err := c.httpClient.Do(req)
		if err != nil {
			return err
		}
		defer resp.Body.Close()
		if resp.StatusCode != http.StatusOK {
			bodyBytes, _ := io.ReadAll(resp.Body)
			return fmt.Errorf("recovery update run status %d body=%s", resp.StatusCode, strings.TrimSpace(string(bodyBytes)))
		}
		return nil
	}

	endpoint := c.baseURL + "/agent_update_run.php"
	buf, _ := json.Marshal(u)
	var lastErr error
	for attempt := 0; attempt < 3; attempt++ {
		req, err := http.NewRequest(http.MethodPost, endpoint, bytes.NewReader(buf))
		if err != nil {
			return err
		}
		c.authHeaders(req)

		resp, err := c.httpClient.Do(req)
		if err != nil {
			lastErr = err
			if !isTransientNetErr(err) || attempt == 2 {
				return err
			}
			time.Sleep(time.Duration(attempt+1) * 250 * time.Millisecond)
			continue
		}
		body, _ := io.ReadAll(resp.Body)
		resp.Body.Close()

		if resp.StatusCode == http.StatusOK {
			return nil
		}
		lastErr = fmt.Errorf("update run status %d body=%s", resp.StatusCode, strings.TrimSpace(string(body)))
		if !shouldRetryStatus(resp.StatusCode) || attempt == 2 {
			return lastErr
		}
		time.Sleep(time.Duration(attempt+1) * 250 * time.Millisecond)
	}
	return lastErr
}

type RunEvent struct {
	Type       string         `json:"type,omitempty"`
	Level      string         `json:"level,omitempty"`
	Code       string         `json:"code,omitempty"`
	MessageID  string         `json:"message_id,omitempty"`
	ParamsJSON map[string]any `json:"params_json,omitempty"`
}

type RunLogEntry struct {
	Level       string         `json:"level,omitempty"`
	Code        string         `json:"code,omitempty"`
	Message     string         `json:"message,omitempty"`
	DetailsJSON map[string]any `json:"details_json,omitempty"`
}

func (c *Client) PushEvents(runID int64, events []RunEvent) error {
	if len(events) == 0 {
		return nil
	}
	compactEvents := compactEventsForTransport(events)
	if c.recoverySessionToken != "" {
		endpoint := c.baseURL + "/cloudbackup_recovery_push_events.php"
		lastErr := error(nil)
		for attempt := 0; attempt < 3; attempt++ {
			body := map[string]any{"run_id": runID, "events": compactEvents, "session_token": c.recoverySessionToken}
			buf, _ := json.Marshal(body)
			req, err := http.NewRequest(http.MethodPost, endpoint, bytes.NewReader(buf))
			if err != nil {
				return err
			}
			req.Header.Set("Content-Type", "application/json")
			c.applyUserAgent(req)
			resp, err := c.httpClient.Do(req)
			if err != nil {
				lastErr = err
				if !isTransientNetErr(err) || attempt == 2 {
					return err
				}
				time.Sleep(time.Duration(attempt+1) * 300 * time.Millisecond)
				continue
			}
			b, _ := io.ReadAll(io.LimitReader(resp.Body, 900))
			resp.Body.Close()
			if resp.StatusCode == http.StatusOK {
				return nil
			}
			msg := strings.TrimSpace(string(b))
			lastErr = fmt.Errorf("recovery push events status %d body=%s", resp.StatusCode, msg)
			if !shouldRetryStatus(resp.StatusCode) || attempt == 2 {
				return lastErr
			}
			time.Sleep(time.Duration(attempt+1) * 300 * time.Millisecond)
		}
		return lastErr
	}
	endpoint := c.baseURL + "/agent_push_events.php"
	payload, useCompactPayload, payloadErr := buildPushEventsPayload(runID, events, compactEvents, false)
	if payloadErr != nil {
		return payloadErr
	}
	lastErr := error(nil)
	for attempt := 0; attempt < 4; attempt++ {
		buf, _ := json.Marshal(payload)
		req, err := http.NewRequest(http.MethodPost, endpoint, bytes.NewReader(buf))
		if err != nil {
			return err
		}
		c.authHeaders(req)

		resp, err := c.httpClient.Do(req)
		if err != nil {
			lastErr = err
			if !isTransientNetErr(err) || attempt == 3 {
				return err
			}
			time.Sleep(time.Duration(attempt+1) * 300 * time.Millisecond)
			continue
		}
		b, _ := io.ReadAll(io.LimitReader(resp.Body, 900))
		resp.Body.Close()

		if resp.StatusCode == http.StatusOK {
			return nil
		}

		msg := strings.TrimSpace(string(b))
		if len(msg) > 600 {
			msg = msg[:600] + "…"
		}
		if msg != "" {
			lastErr = fmt.Errorf("push events status %d body=%s", resp.StatusCode, msg)
		} else {
			lastErr = fmt.Errorf("push events status %d", resp.StatusCode)
		}

		// If a 403 is caused by content policy/WAF, retry once with compact payload.
		if resp.StatusCode == http.StatusForbidden && !useCompactPayload {
			payload, useCompactPayload, payloadErr = buildPushEventsPayload(runID, events, compactEvents, true)
			if payloadErr != nil {
				return payloadErr
			}
			time.Sleep(200 * time.Millisecond)
			continue
		}

		if !shouldRetryStatus(resp.StatusCode) || attempt == 3 {
			return lastErr
		}
		time.Sleep(time.Duration(attempt+1) * 300 * time.Millisecond)
	}
	return lastErr
}

func buildPushEventsPayload(runID int64, events []RunEvent, compactEvents []RunEvent, forceCompact bool) (map[string]any, bool, error) {
	body := map[string]any{"run_id": runID, "events": events}
	buf, _ := json.Marshal(body)
	if !forceCompact && len(buf) < 48*1024 {
		return body, false, nil
	}
	var gz bytes.Buffer
	zw := gzip.NewWriter(&gz)
	rawCompact, _ := json.Marshal(compactEvents)
	if _, err := zw.Write(rawCompact); err != nil {
		_ = zw.Close()
		return nil, true, err
	}
	if err := zw.Close(); err != nil {
		return nil, true, err
	}
	compactBody := map[string]any{
		"run_id":          runID,
		"events_encoding": "gzip+base64",
		"events_b64":      base64.StdEncoding.EncodeToString(gz.Bytes()),
	}
	return compactBody, true, nil
}

func (c *Client) PushRecoveryLogs(runID int64, logs []RunLogEntry) error {
	if c.recoverySessionToken == "" || len(logs) == 0 {
		return nil
	}
	endpoint := c.baseURL + "/cloudbackup_recovery_push_events.php"
	body := map[string]any{"run_id": runID, "logs": logs, "session_token": c.recoverySessionToken}
	buf, _ := json.Marshal(body)
	req, err := http.NewRequest(http.MethodPost, endpoint, bytes.NewReader(buf))
	if err != nil {
		return err
	}
	req.Header.Set("Content-Type", "application/json")
	c.applyUserAgent(req)
	resp, err := c.httpClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		b, _ := io.ReadAll(resp.Body)
		msg := strings.TrimSpace(string(b))
		return fmt.Errorf("recovery push logs status %d body=%s", resp.StatusCode, msg)
	}
	return nil
}

func (c *Client) PushRecoveryDebugLog(runID int64, level, code, message string, details map[string]any) error {
	if c.recoverySessionToken == "" {
		return nil
	}
	endpoint := c.baseURL + "/cloudbackup_recovery_debug_log.php"
	body := map[string]any{
		"run_id":        runID,
		"session_token": c.recoverySessionToken,
		"level":         level,
		"code":          code,
		"message":       message,
		"details":       details,
	}
	buf, _ := json.Marshal(body)
	req, err := http.NewRequest(http.MethodPost, endpoint, bytes.NewReader(buf))
	if err != nil {
		return err
	}
	req.Header.Set("Content-Type", "application/json")
	c.applyUserAgent(req)
	resp, err := c.httpClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		b, _ := io.ReadAll(resp.Body)
		msg := strings.TrimSpace(string(b))
		return fmt.Errorf("recovery debug log status %d body=%s", resp.StatusCode, msg)
	}
	return nil
}

// PollRecoveryCancel checks whether a recovery restore should be cancelled.
func (c *Client) PollRecoveryCancel(runID int64) (bool, error) {
	if c.recoverySessionToken == "" {
		return false, nil
	}
	endpoint := c.baseURL + "/cloudbackup_recovery_poll_cancel.php"
	body := map[string]any{"run_id": runID, "session_token": c.recoverySessionToken}
	buf, _ := json.Marshal(body)
	req, err := http.NewRequest(http.MethodPost, endpoint, bytes.NewReader(buf))
	if err != nil {
		return false, err
	}
	req.Header.Set("Content-Type", "application/json")
	c.applyUserAgent(req)
	resp, err := c.httpClient.Do(req)
	if err != nil {
		return false, err
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		b, _ := io.ReadAll(resp.Body)
		msg := strings.TrimSpace(string(b))
		return false, fmt.Errorf("recovery cancel poll status %d body=%s", resp.StatusCode, msg)
	}
	var out struct {
		Status          string `json:"status"`
		CancelRequested bool   `json:"cancel_requested"`
		Message         string `json:"message"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&out); err != nil {
		return false, err
	}
	if out.Status != "success" {
		if out.Message == "" {
			out.Message = "cancel poll failed"
		}
		return false, errors.New(out.Message)
	}
	return out.CancelRequested, nil
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
func (c *Client) ReportBrowseResult(commandID int64, result any) error {
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
	JobID                   int64          `json:"job_id"`
	RunID                   int64          `json:"run_id"`
	Engine                  string         `json:"engine"`
	SourcePath              string         `json:"source_path"`
	DestType                string         `json:"dest_type"`
	DestBucketName          string         `json:"dest_bucket_name"`
	DestPrefix              string         `json:"dest_prefix"`
	DestLocalPath           string         `json:"dest_local_path"`
	DestEndpoint            string         `json:"dest_endpoint"`
	DestRegion              string         `json:"dest_region"`
	DestAccessKey           string         `json:"dest_access_key"`
	DestSecretKey           string         `json:"dest_secret_key"`
	RepositoryID            string         `json:"repository_id,omitempty"`
	RepositoryPassword      string         `json:"repository_password,omitempty"`
	RepoPasswordMode        string         `json:"repo_password_mode,omitempty"`
	PayloadVersion          string         `json:"payload_version,omitempty"`
	LocalBandwidthLimitKbps int            `json:"local_bandwidth_limit_kbps"`
	ManifestID              string         `json:"manifest_id"`
	DiskSourceVolume        string         `json:"disk_source_volume"`
	DiskImageFormat         string         `json:"disk_image_format"`
	DiskTempDir             string         `json:"disk_temp_dir"`
	PolicyJSON              map[string]any `json:"policy_json"`
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

// PollRepoOperations fetches one eligible queued repo operation (retention, maintenance) for this agent.
// Returns nil when no operation is available. Uses same auth as other agent endpoints.
func (c *Client) PollRepoOperations() (*RepoOperation, error) {
	endpoint := c.baseURL + "/agent_poll_repo_operations.php"
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
		return nil, fmt.Errorf("poll repo operations status %d", resp.StatusCode)
	}

	var out struct {
		Status    string         `json:"status"`
		Message   string         `json:"message,omitempty"`
		Operation *RepoOperation `json:"operation,omitempty"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&out); err != nil {
		return nil, err
	}
	if out.Status != "success" {
		return nil, fmt.Errorf("poll repo operations failed: %s", out.Message)
	}
	return out.Operation, nil
}

// CompleteRepoOperation reports completion of a repo operation.
// status must be "success" or "failed". resultJSON is optional.
func (c *Client) CompleteRepoOperation(operationID int64, operationToken, status string, resultJSON map[string]any) error {
	endpoint := c.baseURL + "/agent_complete_repo_operation.php"
	form := url.Values{}
	form.Set("operation_id", fmt.Sprintf("%d", operationID))
	form.Set("operation_token", operationToken)
	form.Set("status", status)
	if resultJSON != nil {
		buf, _ := json.Marshal(resultJSON)
		form.Set("result_json", string(buf))
	}

	req, err := http.NewRequest(http.MethodPost, endpoint, strings.NewReader(form.Encode()))
	if err != nil {
		return err
	}
	c.authHeaders(req)
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		bodyBytes, _ := io.ReadAll(resp.Body)
		return fmt.Errorf("complete repo operation status %d body=%s", resp.StatusCode, strings.TrimSpace(string(bodyBytes)))
	}
	return nil
}

type DriverBundleUploadResponse struct {
	Status       string `json:"status"`
	Message      string `json:"message,omitempty"`
	BundleID     int64  `json:"bundle_id,omitempty"`
	Profile      string `json:"profile,omitempty"`
	ArtifactURL  string `json:"artifact_url,omitempty"`
	ArtifactPath string `json:"artifact_path,omitempty"`
	DestBucketID int64  `json:"dest_bucket_id,omitempty"`
	DestPrefix   string `json:"dest_prefix,omitempty"`
	S3UserID     int64  `json:"s3_user_id,omitempty"`
	SHA256       string `json:"sha256,omitempty"`
	SizeBytes    int64  `json:"size_bytes,omitempty"`
}

func (c *Client) UploadDriverBundle(runID int64, profile, artifactName string, bundle []byte, backupFinishedAt string) (*DriverBundleUploadResponse, error) {
	if len(bundle) == 0 {
		return nil, fmt.Errorf("bundle payload is empty")
	}
	endpoint := c.baseURL + "/agent_upload_driver_bundle.php"
	var payload bytes.Buffer
	mp := multipart.NewWriter(&payload)
	_ = mp.WriteField("run_id", fmt.Sprintf("%d", runID))
	_ = mp.WriteField("profile", profile)
	_ = mp.WriteField("artifact_name", artifactName)
	_ = mp.WriteField("sha256", fmt.Sprintf("%x", sha256Bytes(bundle)))
	_ = mp.WriteField("size_bytes", fmt.Sprintf("%d", len(bundle)))
	if strings.TrimSpace(backupFinishedAt) != "" {
		_ = mp.WriteField("backup_finished_at", backupFinishedAt)
	}
	fw, err := mp.CreateFormFile("bundle_file", artifactName)
	if err != nil {
		return nil, err
	}
	if _, err := fw.Write(bundle); err != nil {
		return nil, err
	}
	if err := mp.Close(); err != nil {
		return nil, err
	}

	req, err := http.NewRequest(http.MethodPost, endpoint, &payload)
	if err != nil {
		return nil, err
	}
	req.Header.Set("X-Agent-UUID", c.agentUUID)
	req.Header.Set("X-Agent-Token", c.token)
	req.Header.Set("Content-Type", mp.FormDataContentType())
	c.applyUserAgent(req)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()
	bodyBytes, _ := io.ReadAll(io.LimitReader(resp.Body, 4096))
	bodyText := strings.TrimSpace(string(bodyBytes))
	if resp.StatusCode != http.StatusOK {
		if bodyText == "" {
			bodyText = "empty response body"
		}
		return nil, fmt.Errorf("bundle upload status %d body=%s", resp.StatusCode, bodyText)
	}

	var out DriverBundleUploadResponse
	if err := json.Unmarshal(bodyBytes, &out); err != nil {
		if len(bodyText) > 180 {
			bodyText = bodyText[:180] + "..."
		}
		return nil, fmt.Errorf("bundle upload returned non-json response: %s", bodyText)
	}
	if out.Status != "success" {
		if out.Message == "" {
			out.Message = "bundle upload failed"
		}
		return &out, errors.New(out.Message)
	}
	return &out, nil
}

type DriverBundleExistsResponse struct {
	Status    string `json:"status"`
	Message   string `json:"message,omitempty"`
	RunID     int64  `json:"run_id,omitempty"`
	Profile   string `json:"profile,omitempty"`
	Exists    bool   `json:"exists"`
	ObjectKey string `json:"object_key,omitempty"`
}

func (c *Client) DriverBundleExists(runID int64, profile string) (*DriverBundleExistsResponse, error) {
	endpoint := c.baseURL + "/agent_driver_bundle_exists.php"
	body := map[string]any{
		"run_id":  runID,
		"profile": profile,
	}
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
	bodyBytes, _ := io.ReadAll(io.LimitReader(resp.Body, 4096))
	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("bundle exists check status %d body=%s", resp.StatusCode, strings.TrimSpace(string(bodyBytes)))
	}
	var out DriverBundleExistsResponse
	if err := json.Unmarshal(bodyBytes, &out); err != nil {
		return nil, err
	}
	if out.Status == "unsupported" {
		// Treat unsupported destination as "exists" from the agent perspective to skip capture.
		out.Exists = true
		return &out, nil
	}
	if out.Status != "success" {
		if out.Message == "" {
			out.Message = "bundle existence check failed"
		}
		return &out, errors.New(out.Message)
	}
	return &out, nil
}

func sha256Bytes(data []byte) []byte {
	sum := sha256.Sum256(data)
	return sum[:]
}

type MediaBuildManifest struct {
	Mode                string `json:"mode"`
	SourceAgentUUID     string `json:"source_agent_uuid"`
	SourceAgentHostname string `json:"source_agent_hostname"`
	BaseISOURL          string `json:"base_iso_url"`
	BaseISOSHA256       string `json:"base_iso_sha256"`
	SourceBundleURL     string `json:"source_bundle_url"`
	SourceBundleSHA256  string `json:"source_bundle_sha256"`
	SourceBundleProfile string `json:"source_bundle_profile"`
	BroadExtrasURL      string `json:"broad_extras_url"`
	BroadExtrasSHA256   string `json:"broad_extras_sha256"`
	HasSourceBundle     bool   `json:"has_source_bundle"`
	Warning             string `json:"warning"`
}

type MediaManifestResponse struct {
	Status   string             `json:"status"`
	Message  string             `json:"message,omitempty"`
	Manifest MediaBuildManifest `json:"manifest"`
}

func (c *Client) GetMediaManifest(mode string, sourceAgentUUID string) (*MediaBuildManifest, error) {
	endpoint := c.baseURL + "/agent_get_media_manifest.php"
	body := map[string]any{
		"mode": mode,
	}
	if strings.TrimSpace(sourceAgentUUID) != "" {
		body["source_agent_uuid"] = strings.TrimSpace(sourceAgentUUID)
	}
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

	var out MediaManifestResponse
	if err := json.NewDecoder(resp.Body).Decode(&out); err != nil {
		return nil, err
	}
	if out.Status != "success" {
		if out.Message == "" {
			out.Message = "manifest request failed"
		}
		return nil, errors.New(out.Message)
	}
	return &out.Manifest, nil
}

func ExchangeMediaBuildToken(apiBaseURL, token string) (*MediaBuildManifest, error) {
	endpoint := strings.TrimRight(apiBaseURL, "/") + "/cloudbackup_media_build_token_exchange.php"
	body := map[string]any{"token": token}
	buf, _ := json.Marshal(body)
	req, err := http.NewRequest(http.MethodPost, endpoint, bytes.NewReader(buf))
	if err != nil {
		return nil, err
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("User-Agent", "e3-recovery-media-creator/1.0")

	client := &http.Client{Timeout: 30 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	var out MediaManifestResponse
	if err := json.NewDecoder(resp.Body).Decode(&out); err != nil {
		return nil, err
	}
	if out.Status != "success" {
		if out.Message == "" {
			out.Message = "token exchange failed"
		}
		return nil, errors.New(out.Message)
	}
	return &out.Manifest, nil
}

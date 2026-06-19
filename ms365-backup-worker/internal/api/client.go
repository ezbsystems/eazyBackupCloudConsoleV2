package api

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strings"
	"sync"
	"time"
)

type Client struct {
	baseURL    string
	token      string
	nodeID     string
	httpClient *http.Client
	runLogMu   *sync.Mutex
	runLogState map[string]*runLogState
}

func NewClient(baseURL, token, nodeID string) *Client {
	return &Client{
		baseURL: strings.TrimRight(baseURL, "/"),
		token:   token,
		nodeID:  nodeID,
		httpClient: &http.Client{
			Timeout: 120 * time.Second,
		},
	}
}

type RegisterResponse struct {
	NodeID string `json:"node_id"`
	Status string `json:"status"`
}

type ShardInfo struct {
	Index              int    `json:"index"`
	Total              int    `json:"total"`
	Kind               string `json:"kind"`
	Segment            string `json:"segment"`
	ParentPhysicalKey  string `json:"parent_physical_key"`
}

type PaginationLimit struct {
	MaxPages int    `json:"max_pages"`
	OnCap    string `json:"on_cap"`
}

type RunJob struct {
	RunID              string                       `json:"run_id"`
	JobType            string                       `json:"job_type"`
	TenantRecordID     int                          `json:"tenant_record_id"`
	WhmcsClientID      int                          `json:"whmcs_client_id"`
	AzureTenantID      string                       `json:"azure_tenant_id"`
	ResourceID         string                       `json:"resource_id"`
	ResourceType       string                       `json:"resource_type"`
	PhysicalKey        string                       `json:"physical_key"`
	ParentPhysicalKey  string                       `json:"parent_physical_key"`
	KopiaSourcePath    string                       `json:"kopia_source_path"`
	Shard              *ShardInfo                   `json:"shard"`
	GraphID            string                       `json:"graph_id"`
	DriveID            string                       `json:"drive_id"`
	SiteID             string                       `json:"site_id"`
	ListID             string                       `json:"list_id"`
	ExcludedListIDs    []string                     `json:"excluded_list_ids"`
	Scope            ScopeFlags                   `json:"scope"`
	LogicalSources   json.RawMessage   `json:"logical_sources"`
	GraphToken       string            `json:"graph_token"`
	GraphRegion      string            `json:"graph_region"`
	DestEndpoint     string            `json:"dest_endpoint"`
	DestRegion       string            `json:"dest_region"`
	DestBucket       string            `json:"dest_bucket"`
	DestPrefix       string            `json:"dest_prefix"`
	DestAccessKey    string            `json:"dest_access_key"`
	DestSecretKey    string            `json:"dest_secret_key"`
	RepoPassword     string            `json:"repo_password"`
	KopiaRepoID      string            `json:"kopia_repo_id"`
	PreviousManifest string            `json:"previous_manifest_id"`
	SourceManifestID string            `json:"source_manifest_id"`
	IncrementalEnabled bool            `json:"incremental_enabled"`
	DeltaStates      map[string]map[string]string `json:"delta_states"`
	EngineMode       string            `json:"engine_mode"`
	Workloads        map[string]bool   `json:"workloads"`
	GraphPagination  map[string]PaginationLimit `json:"graph_pagination"`
	LeaseExpiresAt   int64             `json:"lease_expires_at"`
	RestoreSelection RestoreSelection  `json:"restore_selection"`
}

type RestoreSelection struct {
	Items          []RestoreItem `json:"items"`
	Targets        []RestoreTarget `json:"targets"`
	ConflictPolicy string        `json:"conflict_policy"`
}

type RestoreItem struct {
	ChildRunID string `json:"child_run_id"`
	ManifestID string `json:"manifest_id"`
	Path       string `json:"path"`
	PathPrefix string `json:"path_prefix"`
	Type       string `json:"type"`
}

type RestoreTarget struct {
	ResourceID   string `json:"resource_id"`
	GraphID      string `json:"graph_id"`
	ResourceType string `json:"resource_type"`
}

type ProgressUpdate struct {
	RunID          string  `json:"run_id"`
	Phase          string  `json:"phase"`
	Percent        float64 `json:"percent"`
	ItemsDone      int     `json:"items_done"`
	ItemsTotal     int     `json:"items_total"`
	ItemsSkipped   int     `json:"items_skipped,omitempty"`
	BytesHashed    int64   `json:"bytes_hashed"`
	BytesUploaded  int64   `json:"bytes_uploaded"`
	ManifestID     string  `json:"manifest_id,omitempty"`
	Message        string  `json:"message,omitempty"`
}

type CompleteUpdate struct {
	RunID      string `json:"run_id"`
	ManifestID string `json:"manifest_id"`
	StatsJSON  string `json:"stats_json"`
}

type FailUpdate struct {
	RunID   string `json:"run_id"`
	Message string `json:"message"`
}

type UpdateOffer struct {
	Version     string `json:"version"`
	Sha256      string `json:"sha256"`
	DownloadURL string `json:"download_url"`
	ReleaseID   int    `json:"release_id"`
}

type HeartbeatResponse struct {
	Update *UpdateOffer `json:"update"`
}

type RepoOperation struct {
	OperationID     int            `json:"operation_id"`
	OpType          string         `json:"op_type"`
	OperationToken  string         `json:"operation_token"`
	RepositoryID    string         `json:"repository_id"`
	TenantRecordID  int            `json:"tenant_record_id"`
	E3JobID         string         `json:"e3_job_id"`
	DestEndpoint    string         `json:"dest_endpoint"`
	DestRegion      string         `json:"dest_region"`
	DestBucket      string         `json:"dest_bucket"`
	DestPrefix      string         `json:"dest_prefix"`
	DestAccessKey   string         `json:"dest_access_key"`
	DestSecretKey   string         `json:"dest_secret_key"`
	RepoPassword    string         `json:"repo_password"`
	KopiaRepoID     string         `json:"kopia_repo_id"`
	EffectivePolicy map[string]any `json:"effective_policy"`
}

func (c *Client) Register(ctx context.Context, hostname string, maxConcurrent int, version string, proxmoxVmid int) (*RegisterResponse, error) {
	var out RegisterResponse
	payload := map[string]any{
		"hostname":            hostname,
		"max_concurrent_runs": maxConcurrent,
		"version":             version,
	}
	if proxmoxVmid > 0 {
		payload["proxmox_vmid"] = proxmoxVmid
	}
	err := c.post(ctx, "ms365_worker_register.php", payload, &out)
	if err != nil {
		return nil, err
	}
	if out.NodeID != "" {
		c.nodeID = out.NodeID
	}
	return &out, nil
}

func (c *Client) Heartbeat(ctx context.Context, currentLoad int, version string, deployError string, proxmoxVmid int) (*HeartbeatResponse, error) {
	var out HeartbeatResponse
	payload := map[string]any{
		"node_id":      c.nodeID,
		"current_load": currentLoad,
		"version":      version,
		"deploy_error": deployError,
	}
	if proxmoxVmid > 0 {
		payload["proxmox_vmid"] = proxmoxVmid
	}
	err := c.post(ctx, "ms365_worker_heartbeat.php", payload, &out)
	if err != nil {
		return nil, err
	}
	return &out, nil
}

func (c *Client) Token() string {
	return c.token
}

func (c *Client) Claim(ctx context.Context) (*RunJob, error) {
	var out struct {
		Run *RunJob `json:"run"`
	}
	err := c.post(ctx, "ms365_worker_claim.php", map[string]any{
		"node_id": c.nodeID,
	}, &out)
	if err != nil {
		return nil, err
	}
	if out.Run == nil {
		return nil, nil
	}
	return out.Run, nil
}

func (c *Client) ClaimRepoOperation(ctx context.Context) (*RepoOperation, error) {
	var out *RepoOperation
	err := c.post(ctx, "ms365_worker_maintenance_claim.php", map[string]any{
		"node_id": c.nodeID,
	}, &out)
	if err != nil {
		return nil, err
	}
	if out == nil || out.OperationID <= 0 || strings.TrimSpace(out.OpType) == "" {
		return nil, nil
	}
	return out, nil
}

func (c *Client) CompleteRepoOperation(ctx context.Context, operationID int, status string, result map[string]any) error {
	if result == nil {
		result = map[string]any{}
	}
	return c.postWithRetry(ctx, "ms365_worker_maintenance_complete.php", map[string]any{
		"operation_id": operationID,
		"status":       status,
		"result":       result,
	}, &struct{}{}, 3)
}

type GraphTokenResponse struct {
	GraphToken string `json:"graph_token"`
	ExpiresIn  int    `json:"expires_in"`
}

func (c *Client) RefreshGraphToken(ctx context.Context, runID string) (string, error) {
	var out GraphTokenResponse
	err := c.post(ctx, "ms365_worker_graph_token.php", map[string]any{
		"run_id": strings.TrimSpace(runID),
	}, &out)
	if err != nil {
		return "", err
	}
	if strings.TrimSpace(out.GraphToken) == "" {
		return "", fmt.Errorf("empty graph_token in refresh response")
	}
	return out.GraphToken, nil
}

func (c *Client) Progress(ctx context.Context, upd ProgressUpdate) error {
	upd.RunID = strings.TrimSpace(upd.RunID)
	return c.post(ctx, "ms365_worker_progress.php", upd, &struct{}{})
}

func (c *Client) Complete(ctx context.Context, upd CompleteUpdate) error {
	return c.postWithRetry(ctx, "ms365_worker_complete.php", upd, &struct{}{}, 3)
}

func (c *Client) Fail(ctx context.Context, upd FailUpdate) error {
	return c.postWithRetry(ctx, "ms365_worker_fail.php", upd, &struct{}{}, 3)
}

func (c *Client) Release(ctx context.Context, runID string) error {
	return c.post(ctx, "ms365_worker_release.php", map[string]any{
		"node_id": c.nodeID,
		"run_id":  runID,
	}, &struct{}{})
}

func (c *Client) NodeID() string {
	return c.nodeID
}

func (c *Client) SetNodeID(id string) {
	c.nodeID = id
}

func (c *Client) post(ctx context.Context, endpoint string, body any, out any) error {
	return c.postWithRetry(ctx, endpoint, body, out, 1)
}

func (c *Client) postWithRetry(ctx context.Context, endpoint string, body any, out any, attempts int) error {
	if attempts < 1 {
		attempts = 1
	}
	var lastErr error
	for attempt := 0; attempt < attempts; attempt++ {
		if attempt > 0 {
			select {
			case <-ctx.Done():
				return ctx.Err()
			case <-time.After(time.Duration(attempt) * 500 * time.Millisecond):
			}
		}
		lastErr = c.postOnce(ctx, endpoint, body, out)
		if lastErr == nil {
			return nil
		}
	}
	return lastErr
}

func (c *Client) postOnce(ctx context.Context, endpoint string, body any, out any) error {
	b, err := json.Marshal(body)
	if err != nil {
		return err
	}
	u := c.baseURL + "/" + strings.TrimLeft(endpoint, "/")
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, u, bytes.NewReader(b))
	if err != nil {
		return err
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-MS365-Worker-Token", c.token)
	if c.nodeID != "" {
		req.Header.Set("X-MS365-Worker-Node", c.nodeID)
	}
	resp, err := c.httpClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	raw, err := io.ReadAll(resp.Body)
	if err != nil {
		return err
	}
	if resp.StatusCode >= 400 {
		return fmt.Errorf("api %s http %d: %s", endpoint, resp.StatusCode, string(raw))
	}
	if out == nil {
		return nil
	}
	if err := decodeEnvelopeResponse(raw, out); err != nil {
		return err
	}
	return nil
}

func decodeEnvelopeResponse(raw []byte, out any) error {
	var envelope struct {
		Status  string          `json:"status"`
		Message string          `json:"message"`
		Data    json.RawMessage `json:"data"`
	}
	if err := json.Unmarshal(raw, &envelope); err != nil {
		if err := json.Unmarshal(raw, out); err != nil {
			return fmt.Errorf("decode response: %w", err)
		}
		return nil
	}
	if envelope.Status == "error" {
		return fmt.Errorf("api error: %s", envelope.Message)
	}
	if len(envelope.Data) == 0 || string(envelope.Data) == "null" {
		return nil
	}
	if err := json.Unmarshal(envelope.Data, out); err != nil {
		return fmt.Errorf("decode data: %w", err)
	}
	return nil
}

func BuildAPIURL(base, path string, q map[string]string) string {
	u, _ := url.Parse(strings.TrimRight(base, "/") + "/" + strings.TrimLeft(path, "/"))
	if len(q) > 0 {
		vals := u.Query()
		for k, v := range q {
			vals.Set(k, v)
		}
		u.RawQuery = vals.Encode()
	}
	return u.String()
}

// StartProgressHeartbeat sends periodic progress updates (lease renewal on PHP side) during long operations.
func (c *Client) StartProgressHeartbeat(ctx context.Context, runID string, interval time.Duration, getUpdate func() ProgressUpdate) func() {
	if interval <= 0 {
		interval = 60 * time.Second
	}
	stop := make(chan struct{})
	go func() {
		ticker := time.NewTicker(interval)
		defer ticker.Stop()
		for {
			select {
			case <-ctx.Done():
				return
			case <-stop:
				return
			case <-ticker.C:
				upd := getUpdate()
				upd.RunID = runID
				if upd.Phase == "" {
					upd.Phase = "heartbeat"
				}
				_ = c.Progress(ctx, upd)
			}
		}
	}()
	return func() { close(stop) }
}

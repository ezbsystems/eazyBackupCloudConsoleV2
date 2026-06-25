package graph

import (
	"bytes"
	"compress/gzip"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"math/rand"
	"net/http"
	"net/url"
	"strconv"
	"strings"
	"sync"
	"sync/atomic"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/version"
)

const defaultGraphHost = "https://graph.microsoft.com"

// adaptiveSuccessStreak is the number of consecutive successful Graph responses
// before additive-increase grows the adaptive concurrency limit by one (AIMD).
const adaptiveSuccessStreak = 10

// adaptiveDecreaseRatio is the multiplicative shrink factor applied on Graph 429.
const adaptiveDecreaseRatio = 0.5

// retryAfter429Cap is the maximum Retry-After honored for Graph 429 responses.
const retryAfter429Cap = 600 * time.Second

// retryAfterOtherCap is the maximum Retry-After honored for 503/504 and transport retries.
const retryAfterOtherCap = 120 * time.Second

// MailMessageSelect matches PHP MailBackupService::MESSAGE_SELECT.
const MailMessageSelect = "id,subject,receivedDateTime,sentDateTime,from,toRecipients,ccRecipients,bccRecipients,body,bodyPreview,parentFolderId,conversationId,internetMessageId,hasAttachments,importance,isRead,isDraft,flag,categories"

type Client struct {
	tokenMu    sync.RWMutex
	token      string
	refresh    TokenRefreshFunc
	graphBase  string
	httpClient *http.Client
	maxRetries int
	retryDelay time.Duration
	sem             chan struct{}
	throttle429     int64
	adaptiveEnabled bool
	maxConcurrency  int
	azureTenantID   string
	lastThrottleAt  atomic.Int64
	requestsTotal   int64
}

// TokenRefreshFunc fetches a new Graph bearer token (e.g. from WHMCS mid-run refresh API).
type TokenRefreshFunc func(ctx context.Context) (string, error)

type ClientOptions struct {
	MaxRetries       int
	RetryBaseDelayMs int
	MaxConcurrency   int
	AdaptiveLimit    bool
}

func NewClient(token, region string, opts ClientOptions) *Client {
	base := defaultGraphHost
	switch strings.TrimSpace(region) {
	case "USGovCloud":
		base = "https://graph.microsoft.us"
	case "ChinaCloud":
		base = "https://microsoftgraph.chinacloudapi.cn"
	case "GermanyCloud":
		base = "https://graph.microsoft.de"
	}
	maxRetries := opts.MaxRetries
	if maxRetries <= 0 {
		maxRetries = 5
	}
	retryDelayMs := opts.RetryBaseDelayMs
	if retryDelayMs <= 0 {
		retryDelayMs = 2000
	}
	concurrency := opts.MaxConcurrency
	if concurrency <= 0 {
		concurrency = 16
	}

	transport := &http.Transport{
		MaxIdleConns:        concurrency * 2,
		MaxIdleConnsPerHost: concurrency,
		MaxConnsPerHost:     concurrency,
		IdleConnTimeout:     90 * time.Second,
		ForceAttemptHTTP2:   true,
	}

	c := &Client{
		token:          token,
		graphBase:      strings.TrimRight(base, "/") + "/v1.0",
		httpClient:     &http.Client{Timeout: 120 * time.Second, Transport: transport},
		maxRetries:     maxRetries,
		retryDelay:     time.Duration(retryDelayMs) * time.Millisecond,
		sem:            make(chan struct{}, concurrency),
		maxConcurrency: concurrency,
	}
	if opts.AdaptiveLimit {
		c.adaptiveEnabled = true
	}
	return c
}

func userAgent() string {
	return "eazyBackup-MS365-Backup/" + version.Version
}

func (c *Client) ThrottleHits() int64 {
	return atomic.LoadInt64(&c.throttle429)
}

// RequestsTotal returns the number of completed Graph HTTP round-trips.
func (c *Client) RequestsTotal() int64 {
	return atomic.LoadInt64(&c.requestsTotal)
}

// ThrottleWaiting reports whether the tenant controller is sleeping on Graph 429 backoff.
func (c *Client) ThrottleWaiting() bool {
	return getTenantController(c.azureTenantID).throttleWaiting()
}

// LastThrottleAt returns the time of the most recent Graph 429 (zero when none).
func (c *Client) LastThrottleAt() time.Time {
	ns := c.lastThrottleAt.Load()
	if ns == 0 {
		return time.Time{}
	}
	return time.Unix(0, ns)
}

// AdaptiveConcurrency returns the current tenant adaptive in-flight limit (0 when disabled).
func (c *Client) AdaptiveConcurrency() int {
	if !c.adaptiveEnabled {
		return 0
	}
	return getTenantController(c.azureTenantID).limitValue()
}

func (c *Client) SetToken(token string) {
	c.tokenMu.Lock()
	c.token = strings.TrimSpace(token)
	c.tokenMu.Unlock()
}

func (c *Client) SetTokenRefresh(fn TokenRefreshFunc) {
	c.refresh = fn
}

func (c *Client) getToken() string {
	c.tokenMu.RLock()
	defer c.tokenMu.RUnlock()
	return c.token
}

// IsUnauthorized reports whether err is a Graph HTTP 401 response.
func IsUnauthorized(err error) bool {
	if err == nil {
		return false
	}
	msg := strings.ToLower(err.Error())
	return strings.Contains(msg, "graph 401")
}

// IsMailboxNotEnabled reports whether err indicates the user's mailbox is inactive,
// soft-deleted, on-premise, or otherwise unavailable via the Graph REST API.
func IsMailboxNotEnabled(err error) bool {
	if err == nil {
		return false
	}
	msg := strings.ToLower(err.Error())
	return strings.Contains(msg, "mailboxnotenabledforrestapi") ||
		strings.Contains(msg, "mailbox is either inactive, soft-deleted, or is hosted on-premise")
}

// IsMailboxUnavailable reports whether err indicates the user's mailbox cannot be
// reached via the Graph REST API, covering the two distinct shapes Graph uses for
// the same no-mailbox / unlicensed user:
//
//   - mail and contacts endpoints return 404 MailboxNotEnabledForRESTAPI
//     (handled by IsMailboxNotEnabled), while
//   - the To Do (tasks) and Outlook endpoints instead return 401 Unauthorized
//     with an empty-message "UnknownError" body for the very same users.
//
// A genuine bad-token 401 is surfaced by doRequest as "graph 401 after token
// refresh" (the token is refreshed and retried once), and a missing-scope 401
// uses a distinct error code (e.g. Authorization_RequestDenied), so neither is
// matched here. This keeps the no-mailbox skip from masking real auth failures.
// IsSharePointAccessDenied reports whether err is a Graph HTTP 403 indicating the
// backup app lacks access to a specific SharePoint site (accessDenied,
// Authorization_RequestDenied, or site-scoped generalException).
//
// Genuine app-wide auth failures (graph 401 after token refresh) are excluded.
func IsSharePointAccessDenied(err error) bool {
	if err == nil {
		return false
	}
	msg := strings.ToLower(err.Error())
	if strings.Contains(msg, "graph 401 after token refresh") {
		return false
	}
	if !strings.Contains(msg, "graph 403") {
		return false
	}
	return strings.Contains(msg, "accessdenied") ||
		strings.Contains(msg, "authorization_requestdenied") ||
		strings.Contains(msg, "generalexception") ||
		strings.Contains(msg, "access denied")
}

func IsMailboxUnavailable(err error) bool {
	if err == nil {
		return false
	}
	if IsMailboxNotEnabled(err) {
		return true
	}
	msg := strings.ToLower(err.Error())
	if !strings.Contains(msg, "graph 401") {
		return false
	}
	if strings.Contains(msg, "after token refresh") {
		return false
	}
	return strings.Contains(msg, `"code":"unknownerror"`)
}

func (c *Client) SetAzureTenantID(tenantID string) {
	c.azureTenantID = strings.TrimSpace(tenantID)
	ceiling := c.maxConcurrency
	if ceiling <= 0 {
		ceiling = defaultTenantCeiling
	}
	getTenantController(c.azureTenantID).ensureAdaptiveLimit(ceiling)
}

// SetTenantCeilingFromClient applies a refreshed fleet budget ceiling for this client's tenant.
func (c *Client) SetTenantCeilingFromClient(budget int) {
	if budget <= 0 {
		return
	}
	if budget < c.maxConcurrency {
		c.maxConcurrency = budget
	}
	SetTenantCeiling(c.azureTenantID, budget)
}

func (c *Client) tenantController() *tenantController {
	return getTenantController(c.azureTenantID)
}

func (c *Client) acquireTransport(ctx context.Context) error {
	return acquireGlobal(ctx)
}

func (c *Client) releaseTransport() {
	releaseGlobal()
}

func (c *Client) acquireWorkload(ctx context.Context) error {
	if c.adaptiveEnabled {
		return c.tenantController().acquire(ctx)
	}
	select {
	case c.sem <- struct{}{}:
		return nil
	case <-ctx.Done():
		return ctx.Err()
	}
}

func (c *Client) releaseWorkload() {
	if c.adaptiveEnabled {
		c.tenantController().release()
		return
	}
	<-c.sem
}

func (c *Client) acquire(ctx context.Context) error {
	if err := c.acquireWorkload(ctx); err != nil {
		return err
	}
	if err := c.acquireTransport(ctx); err != nil {
		c.releaseWorkload()
		return err
	}
	return nil
}

func (c *Client) release() {
	c.releaseTransport()
	c.releaseWorkload()
}

func (c *Client) sleepRetry(ctx context.Context, delay time.Duration) error {
	c.releaseTransport()
	timer := time.NewTimer(delay)
	defer timer.Stop()
	select {
	case <-ctx.Done():
		return ctx.Err()
	case <-timer.C:
		return nil
	}
}

func (c *Client) record429(cooldown time.Duration) {
	atomic.AddInt64(&c.throttle429, 1)
	c.lastThrottleAt.Store(time.Now().UnixNano())
	if c.adaptiveEnabled {
		c.tenantController().record429(cooldown)
	}
}

func (c *Client) recordSuccess() {
	if c.adaptiveEnabled {
		c.tenantController().recordSuccess()
	}
}

func parseRetryAfter(header string, attempt int, baseDelay time.Duration) time.Duration {
	return parseRetryAfterWithCap(header, attempt, baseDelay, retryAfterOtherCap)
}

func parseRetryAfter429(header string, attempt int, baseDelay time.Duration) time.Duration {
	return parseRetryAfterWithCap(header, attempt, baseDelay, retryAfter429Cap)
}

func parseRetryAfterWithCap(header string, attempt int, baseDelay, cap time.Duration) time.Duration {
	header = strings.TrimSpace(header)
	if header != "" {
		if n, err := strconv.Atoi(header); err == nil && n > 0 {
			d := time.Duration(n) * time.Second
			return minDuration(d, cap)
		}
		if ts, err := http.ParseTime(header); err == nil {
			d := time.Until(ts)
			if d > 0 {
				return minDuration(d, cap)
			}
		}
	}
	// Exponential backoff with jitter.
	delay := baseDelay * time.Duration(attempt+2)
	jitter := time.Duration(rand.Int63n(int64(baseDelay)))
	return minDuration(delay+jitter, cap)
}

func minDuration(a, b time.Duration) time.Duration {
	if a < b {
		return a
	}
	return b
}

func min(a, b int) int {
	if a < b {
		return a
	}
	return b
}

func isBoundedRetryableStatus(code int) bool {
	return code == http.StatusServiceUnavailable || code == http.StatusGatewayTimeout
}

func (c *Client) setRequestHeaders(req *http.Request) {
	if req.Header.Get("Authorization") == "" {
		req.Header.Set("Authorization", "Bearer "+c.getToken())
	}
	if req.Header.Get("Accept") == "" {
		req.Header.Set("Accept", "application/json")
	}
	if req.Header.Get("Accept-Encoding") == "" {
		req.Header.Set("Accept-Encoding", "gzip")
	}
	if req.Header.Get("User-Agent") == "" {
		req.Header.Set("User-Agent", userAgent())
	}
}

func isThrottleStatus(code int) bool {
	return code == http.StatusTooManyRequests || isBoundedRetryableStatus(code)
}

func (c *Client) sleep429(ctx context.Context, delay time.Duration) error {
	tc := c.tenantController()
	tc.beginThrottleWait()
	defer tc.endThrottleWait()
	return c.sleepRetry(ctx, delay)
}

func (c *Client) sleep429WithWorkload(ctx context.Context, delay time.Duration, workloadHeld *bool) error {
	_ = workloadHeld // slot stays held during Retry-After for self-clocking backpressure.
	return c.sleep429(ctx, delay)
}

type httpResponse struct {
	status int
	body   []byte
	header http.Header
}

func (c *Client) doRequest(ctx context.Context, req *http.Request) (*httpResponse, error) {
	if err := c.acquireWorkload(ctx); err != nil {
		return nil, err
	}
	workloadHeld := true
	defer func() {
		if workloadHeld {
			c.releaseWorkload()
		}
	}()

	var lastErr error
	retried401 := false
	throttle429Attempt := 0
	for attempt := 0; attempt <= c.maxRetries; attempt++ {
		if ctx.Err() != nil {
			return nil, ctx.Err()
		}
		if err := c.acquireTransport(ctx); err != nil {
			return nil, err
		}

		reqClone := req.Clone(ctx)
		c.setRequestHeaders(reqClone)

		resp, err := c.httpClient.Do(reqClone)
		if err != nil {
			lastErr = err
			if sleepErr := c.sleepRetry(ctx, parseRetryAfter("", attempt, c.retryDelay)); sleepErr != nil {
				return nil, sleepErr
			}
			continue
		}
		body, readErr := readResponseBody(resp)
		statusCode := resp.StatusCode
		retryAfter := resp.Header.Get("Retry-After")
		resp.Body.Close()
		atomic.AddInt64(&c.requestsTotal, 1)
		if readErr != nil {
			lastErr = readErr
			if sleepErr := c.sleepRetry(ctx, parseRetryAfter("", attempt, c.retryDelay)); sleepErr != nil {
				return nil, sleepErr
			}
			continue
		}

		for statusCode == http.StatusTooManyRequests {
			delay := parseRetryAfter429(retryAfter, throttle429Attempt, c.retryDelay)
			c.record429(delay)
			lastErr = fmt.Errorf("graph %d", statusCode)
			throttle429Attempt++
			if sleepErr := c.sleep429WithWorkload(ctx, delay, &workloadHeld); sleepErr != nil {
				return nil, sleepErr
			}
			if ctx.Err() != nil {
				return nil, ctx.Err()
			}
			if err := c.acquireTransport(ctx); err != nil {
				return nil, err
			}
			reqClone = req.Clone(ctx)
			c.setRequestHeaders(reqClone)
			resp, err = c.httpClient.Do(reqClone)
			if err != nil {
				c.releaseTransport()
				lastErr = err
				break
			}
			body, readErr = readResponseBody(resp)
			statusCode = resp.StatusCode
			retryAfter = resp.Header.Get("Retry-After")
			resp.Body.Close()
			atomic.AddInt64(&c.requestsTotal, 1)
			if readErr != nil {
				c.releaseTransport()
				lastErr = readErr
				break
			}
		}
		if err != nil || readErr != nil {
			if sleepErr := c.sleepRetry(ctx, parseRetryAfter("", attempt, c.retryDelay)); sleepErr != nil {
				return nil, sleepErr
			}
			continue
		}

		if isBoundedRetryableStatus(statusCode) && attempt < c.maxRetries {
			lastErr = fmt.Errorf("graph %d", statusCode)
			if sleepErr := c.sleepRetry(ctx, parseRetryAfter(retryAfter, attempt, c.retryDelay)); sleepErr != nil {
				return nil, sleepErr
			}
			continue
		}
		if statusCode == http.StatusUnauthorized && c.refresh != nil && !retried401 {
			c.releaseTransport()
			retried401 = true
			newToken, refreshErr := c.refresh(ctx)
			if refreshErr != nil || strings.TrimSpace(newToken) == "" {
				if refreshErr != nil {
					return nil, fmt.Errorf("graph 401 after token refresh: %w", refreshErr)
				}
				return nil, fmt.Errorf("graph 401 after token refresh: empty token")
			}
			c.SetToken(newToken)
			continue
		}
		if statusCode >= 400 {
			c.releaseTransport()
			return nil, fmt.Errorf("graph %s: %s", resp.Status, string(body))
		}
		c.recordSuccess()
		c.releaseTransport()
		return &httpResponse{status: statusCode, body: body, header: resp.Header}, nil
	}
	return nil, lastErr
}

// readResponseBody reads resp.Body, transparently decompressing gzip payloads.
//
// We explicitly request "Accept-Encoding: gzip" in doRequest, which disables
// the Go transport's automatic gzip decompression, so we must handle it here.
// Without this, gzipped Graph responses are fed verbatim to json.Unmarshal and
// fail with: invalid character '\x1f' looking for beginning of value.
func readResponseBody(resp *http.Response) ([]byte, error) {
	reader := resp.Body
	if strings.Contains(strings.ToLower(resp.Header.Get("Content-Encoding")), "gzip") {
		gz, err := gzip.NewReader(resp.Body)
		if err != nil {
			if err == io.EOF {
				return nil, nil
			}
			return nil, fmt.Errorf("gzip: %w", err)
		}
		defer gz.Close()
		reader = gz
	}

	return io.ReadAll(reader)
}

func (c *Client) GetJSON(ctx context.Context, path string, query map[string]string) (map[string]any, error) {
	return c.GetJSONWithHeaders(ctx, path, query, nil)
}

// GetJSONWithHeaders performs a GET with optional extra headers (e.g. Prefer: IdType="ImmutableId").
func (c *Client) GetJSONWithHeaders(ctx context.Context, path string, query map[string]string, headers map[string]string) (map[string]any, error) {
	u := c.graphBase + "/" + strings.TrimPrefix(path, "/")
	if len(query) > 0 {
		parsed, err := url.Parse(u)
		if err != nil {
			return nil, err
		}
		q := parsed.Query()
		for k, v := range query {
			q.Set(k, v)
		}
		parsed.RawQuery = q.Encode()
		u = parsed.String()
	}
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, u, nil)
	if err != nil {
		return nil, err
	}
	for k, v := range headers {
		req.Header.Set(k, v)
	}
	resp, err := c.doRequest(ctx, req)
	if err != nil {
		return nil, err
	}
	var out map[string]any
	if err := json.Unmarshal(resp.body, &out); err != nil {
		return nil, err
	}
	return out, nil
}

// PaginateOptions configures monitored pagination.
type PaginateOptions struct {
	Monitor      *PaginationMonitor
	Outcome      *PaginationOutcome
	Headers      map[string]string
	TrackDupIDs  bool // when true, dedupe by item id and detect duplicate-only pages
}

func (c *Client) Paginate(ctx context.Context, path string, query map[string]string) ([]map[string]any, error) {
	return c.PaginateOpts(ctx, path, query, nil)
}

// PaginateOpts follows @odata.nextLink with optional safety monitoring and logging.
func (c *Client) PaginateOpts(ctx context.Context, path string, query map[string]string, opts *PaginateOptions) ([]map[string]any, error) {
	var monitor *PaginationMonitor
	var outcome *PaginationOutcome
	headers := map[string]string{}
	trackDup := false
	if opts != nil {
		monitor = opts.Monitor
		outcome = opts.Outcome
		headers = opts.Headers
		trackDup = opts.TrackDupIDs
	}
	if monitor == nil && trackDup {
		monitor = NewPaginationMonitor("", DuplicatePageStrict, nil)
	}

	session := newPaginationSession(monitor, outcome, trackDup)
	var all []map[string]any
	next := ""
	first := true
	for {
		if session.stopped() {
			break
		}
		q := map[string]string{}
		for k, v := range query {
			q[k] = v
		}
		p := path
		useHeaders := headers
		if next != "" {
			if err := session.checkNextLink(next, first); err != nil {
				return all, err
			}
			p = next
			q = nil
			useHeaders = nil
			first = false
		}

		var data map[string]any
		var err error
		if strings.HasPrefix(p, "http") {
			data, err = c.getURLWithHeaders(ctx, p, useHeaders)
		} else {
			data, err = c.GetJSONWithHeaders(ctx, p, q, useHeaders)
		}
		if err != nil {
			return all, err
		}
		first = false

		var pageItems []map[string]any
		if values, ok := data["value"].([]any); ok {
			for _, v := range values {
				if m, ok := v.(map[string]any); ok {
					pageItems = append(pageItems, m)
				}
			}
		}
		nextLink, _ := data["@odata.nextLink"].(string)
		yielded, err := session.processPage(pageItems, nextLink)
		if err != nil {
			return all, err
		}
		all = append(all, yielded...)
		if session.stopped() || nextLink == "" {
			session.finish(nextLink == "" && !session.stopped())
			break
		}
		next = nextLink
	}
	return all, nil
}

// FindEventsByICalUID looks up calendar events by iCalUId. Graph requires the
// ConsistencyLevel header for $filter queries on the events collection.
func (c *Client) FindEventsByICalUID(ctx context.Context, listPath, iCalUID string) ([]map[string]any, error) {
	escaped := strings.ReplaceAll(strings.TrimSpace(iCalUID), "'", "''")
	if escaped == "" {
		return nil, nil
	}
	u := c.graphBase + "/" + strings.TrimPrefix(listPath, "/")
	parsed, err := url.Parse(u)
	if err != nil {
		return nil, err
	}
	q := parsed.Query()
	q.Set("$filter", fmt.Sprintf("iCalUId eq '%s'", escaped))
	q.Set("$top", "1")
	q.Set("$select", "id")
	q.Set("$count", "true")
	parsed.RawQuery = q.Encode()

	req, err := http.NewRequestWithContext(ctx, http.MethodGet, parsed.String(), nil)
	if err != nil {
		return nil, err
	}
	req.Header.Set("ConsistencyLevel", "eventual")
	resp, err := c.doRequest(ctx, req)
	if err != nil {
		return nil, err
	}
	var data map[string]any
	if len(resp.body) > 0 {
		if err := json.Unmarshal(resp.body, &data); err != nil {
			return nil, err
		}
	}
	var out []map[string]any
	if values, ok := data["value"].([]any); ok {
		for _, v := range values {
			if m, ok := v.(map[string]any); ok {
				out = append(out, m)
			}
		}
	}
	return out, nil
}

type DeltaPage struct {
	Items     []map[string]any
	NextLink  string
	DeltaLink string
}

// DeltaPaginateOptions configures monitored delta pagination.
type DeltaPaginateOptions struct {
	Monitor           *PaginationMonitor
	Outcome           *PaginationOutcome
	Headers           map[string]string
	TrackDupIDs       bool // when true (default), dedupe by item id and detect duplicate-only pages
	DuplicatePageMode DuplicatePageMode
	// OmitDeltaQueryParams skips $top, $select, and similar query params on the initial delta
	// request. Required for Graph resources like contactFolder/contacts/delta that reject
	// query parameters with change tracking.
	OmitDeltaQueryParams bool
}

// PaginateDelta streams delta pages. When selectFields is non-empty it is sent as $select on the initial request.
// onPage is called after each page with the cumulative item count (optional).
func (c *Client) PaginateDelta(ctx context.Context, initialPath, deltaLink, selectFields string, top int, onPage func(itemsSoFar int)) ([]map[string]any, string, error) {
	return c.PaginateDeltaOpts(ctx, initialPath, deltaLink, selectFields, top, onPage, nil)
}

// PaginateDeltaOpts is PaginateDelta with optional monitoring and 410 delta-reset detection.
func (c *Client) PaginateDeltaOpts(ctx context.Context, initialPath, deltaLink, selectFields string, top int, onPage func(itemsSoFar int), opts *DeltaPaginateOptions) ([]map[string]any, string, error) {
	if top <= 0 {
		top = 100
	}
	var monitor *PaginationMonitor
	var outcome *PaginationOutcome
	headers := map[string]string{}
	trackDup := true
	if opts != nil {
		monitor = opts.Monitor
		outcome = opts.Outcome
		headers = opts.Headers
		trackDup = opts.TrackDupIDs || monitor != nil
	}
	if monitor == nil {
		monitor = ForBackupPagination("", nil)
	}
	if opts != nil && opts.DuplicatePageMode != DuplicatePageStrict {
		monitor.DuplicatePageMode = opts.DuplicatePageMode
	}

	path := initialPath
	resume := strings.TrimSpace(deltaLink) != ""
	if resume {
		path = deltaLink
	}
	monitor.logf("info", "Graph delta sync started path=%s resume=%v", initialPath, resume)

	session := newPaginationSession(monitor, outcome, trackDup)
	var items []map[string]any
	var newDelta string
	first := true
	capHit := false

	for path != "" && !session.stopped() && !capHit {
		if !first {
			if err := session.checkNextLink(path, false); err != nil {
				return items, "", err
			}
		}

		var data map[string]any
		var err error
		if strings.HasPrefix(path, "http") {
			data, err = c.getURLWithHeaders(ctx, path, headers)
			headers = nil
		} else {
			var query map[string]string
			omitQuery := opts != nil && opts.OmitDeltaQueryParams
			if !omitQuery {
				query = map[string]string{"$top": strconv.Itoa(top)}
				if selectFields != "" {
					query["$select"] = selectFields
				}
			}
			data, err = c.GetJSONWithHeaders(ctx, path, query, headers)
			headers = nil
		}
		first = false
		if err != nil {
			if IsDeltaResetError(err) || isDeltaResetStatus(err) {
				return items, "", &DeltaResetError{Message: err.Error(), StatusCode: 410}
			}
			return items, "", err
		}

		var pageItems []map[string]any
		if values, ok := data["value"].([]any); ok {
			for _, v := range values {
				if m, ok := v.(map[string]any); ok {
					pageItems = append(pageItems, m)
				}
			}
		}

		nextLink, _ := data["@odata.nextLink"].(string)
		deltaOut, _ := data["@odata.deltaLink"].(string)

		yielded, err := session.processPage(pageItems, nextLink)
		if err != nil {
			return items, "", err
		}
		items = append(items, yielded...)
		if onPage != nil {
			onPage(len(items))
		}
		if outcome != nil && outcome.CapReached {
			capHit = true
			break
		}
		if session.stopped() {
			break
		}

		if nextLink != "" {
			path = nextLink
			continue
		}
		if deltaOut != "" {
			newDelta = deltaOut
		}
		break
	}

	naturalComplete := newDelta != "" && !capHit && !session.stopped()
	session.finish(naturalComplete)

	monitor.logf("info", "Graph delta sync completed pages=%d total_items=%d has_delta_link=%v", session.page, len(items), newDelta != "")
	if newDelta == "" && resume && !capHit {
		monitor.logf("warning", "Graph delta sync ended without @odata.deltaLink; token not advanced")
	}
	return items, newDelta, nil
}

func isDeltaResetStatus(err error) bool {
	if err == nil {
		return false
	}
	msg := strings.ToLower(err.Error())
	return strings.Contains(msg, "graph 410") ||
		strings.Contains(msg, "syncstatenotfound") ||
		strings.Contains(msg, "resyncrequired") ||
		strings.Contains(msg, "fullsyncrequired")
}

func (c *Client) getURL(ctx context.Context, rawURL string) (map[string]any, error) {
	return c.getURLWithHeaders(ctx, rawURL, nil)
}

func (c *Client) getURLWithHeaders(ctx context.Context, rawURL string, headers map[string]string) (map[string]any, error) {
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, rawURL, nil)
	if err != nil {
		return nil, err
	}
	for k, v := range headers {
		req.Header.Set(k, v)
	}
	resp, err := c.doRequest(ctx, req)
	if err != nil {
		if isDeltaResetStatus(err) {
			return nil, &DeltaResetError{Message: err.Error(), StatusCode: 410}
		}
		return nil, err
	}
	if resp.status >= 400 {
		if resp.status == 410 || isDeltaResetBody(resp.body) {
			return nil, &DeltaResetError{Message: fmt.Sprintf("graph %d: %s", resp.status, string(resp.body)), StatusCode: resp.status}
		}
	}
	var out map[string]any
	if err := json.Unmarshal(resp.body, &out); err != nil {
		return nil, err
	}
	return out, nil
}

func isDeltaResetBody(body []byte) bool {
	msg := strings.ToLower(string(body))
	return strings.Contains(msg, "syncstatenotfound") ||
		strings.Contains(msg, "resyncrequired") ||
		strings.Contains(msg, "fullsyncrequired")
}

func (c *Client) GetMessageJSON(ctx context.Context, userID, messageID string) ([]byte, error) {
	path := fmt.Sprintf("/users/%s/messages/%s", url.PathEscape(userID), url.PathEscape(messageID))
	data, err := c.GetJSON(ctx, path, map[string]string{
		"$select": MailMessageSelect,
	})
	if err != nil {
		return nil, err
	}
	return json.Marshal(data)
}

func (c *Client) Get(ctx context.Context, path string, query map[string]string) (map[string]any, error) {
	return c.GetJSON(ctx, path, query)
}

// GetStream performs a streaming GET without buffering the response body.
// Returns the response body, Content-Length when available, and any error.
func (c *Client) GetStream(ctx context.Context, path string) (io.ReadCloser, int64, error) {
	return c.getStream(ctx, path, 0)
}

// GetStreamRange resumes a download using HTTP Range.
func (c *Client) GetStreamRange(ctx context.Context, path string, offset int64) (io.ReadCloser, int64, error) {
	return c.getStream(ctx, path, offset)
}

func (c *Client) getStream(ctx context.Context, path string, offset int64) (io.ReadCloser, int64, error) {
	u := c.graphBase + "/" + strings.TrimPrefix(path, "/")
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, u, nil)
	if err != nil {
		return nil, 0, err
	}
	if offset > 0 {
		req.Header.Set("Range", fmt.Sprintf("bytes=%d-", offset))
	}

	if err := c.acquireWorkload(ctx); err != nil {
		return nil, 0, err
	}
	workloadHeld := true

	var lastErr error
	throttle429Attempt := 0
	for attempt := 0; attempt <= c.maxRetries; attempt++ {
		if ctx.Err() != nil {
			if workloadHeld {
				c.releaseWorkload()
			}
			return nil, 0, ctx.Err()
		}
		if err := c.acquireTransport(ctx); err != nil {
			if workloadHeld {
				c.releaseWorkload()
			}
			return nil, 0, err
		}

		reqClone := req.Clone(ctx)
		c.setRequestHeaders(reqClone)
		if offset > 0 && reqClone.Header.Get("Range") == "" {
			reqClone.Header.Set("Range", fmt.Sprintf("bytes=%d-", offset))
		}

		resp, err := c.httpClient.Do(reqClone)
		if err != nil {
			lastErr = err
			if sleepErr := c.sleepRetry(ctx, parseRetryAfter("", attempt, c.retryDelay)); sleepErr != nil {
				if workloadHeld {
					c.releaseWorkload()
				}
				return nil, 0, sleepErr
			}
			continue
		}

		statusCode := resp.StatusCode
		retryAfter := resp.Header.Get("Retry-After")

		for statusCode == http.StatusTooManyRequests {
			io.Copy(io.Discard, resp.Body)
			resp.Body.Close()
			delay := parseRetryAfter429(retryAfter, throttle429Attempt, c.retryDelay)
			c.record429(delay)
			throttle429Attempt++
			lastErr = fmt.Errorf("graph %d", statusCode)
			c.releaseTransport()
			if sleepErr := c.sleep429WithWorkload(ctx, delay, &workloadHeld); sleepErr != nil {
				return nil, 0, sleepErr
			}
			if ctx.Err() != nil {
				if workloadHeld {
					c.releaseWorkload()
				}
				return nil, 0, ctx.Err()
			}
			if err := c.acquireTransport(ctx); err != nil {
				if workloadHeld {
					c.releaseWorkload()
				}
				return nil, 0, err
			}
			reqClone = req.Clone(ctx)
			c.setRequestHeaders(reqClone)
			if offset > 0 && reqClone.Header.Get("Range") == "" {
				reqClone.Header.Set("Range", fmt.Sprintf("bytes=%d-", offset))
			}
			resp, err = c.httpClient.Do(reqClone)
			if err != nil {
				c.releaseTransport()
				lastErr = err
				break
			}
			statusCode = resp.StatusCode
			retryAfter = resp.Header.Get("Retry-After")
		}
		if err != nil {
			if sleepErr := c.sleepRetry(ctx, parseRetryAfter("", attempt, c.retryDelay)); sleepErr != nil {
				if workloadHeld {
					c.releaseWorkload()
				}
				return nil, 0, sleepErr
			}
			continue
		}

		if isBoundedRetryableStatus(statusCode) && attempt < c.maxRetries {
			io.Copy(io.Discard, resp.Body)
			resp.Body.Close()
			c.releaseTransport()
			lastErr = fmt.Errorf("graph %d", statusCode)
			if sleepErr := c.sleepRetry(ctx, parseRetryAfter(retryAfter, attempt, c.retryDelay)); sleepErr != nil {
				if workloadHeld {
					c.releaseWorkload()
				}
				return nil, 0, sleepErr
			}
			continue
		}

		if statusCode == http.StatusRequestedRangeNotSatisfiable {
			io.Copy(io.Discard, resp.Body)
			resp.Body.Close()
			c.releaseTransport()
			if workloadHeld {
				c.releaseWorkload()
			}
			return nil, 0, fmt.Errorf("graph range not satisfiable at offset %d", offset)
		}

		if statusCode >= 400 {
			body, _ := io.ReadAll(resp.Body)
			resp.Body.Close()
			c.releaseTransport()
			if workloadHeld {
				c.releaseWorkload()
			}
			return nil, 0, fmt.Errorf("graph %s: %s", resp.Status, string(body))
		}

		size := parseContentLength(resp.Header.Get("Content-Length"))
		if offset > 0 && statusCode == http.StatusPartialContent {
			if cr := resp.Header.Get("Content-Range"); cr != "" {
				if total := parseContentRangeTotal(cr); total > 0 {
					size = total
				}
			}
		} else if size == 0 && resp.ContentLength > 0 {
			size = resp.ContentLength
		}

		releaseHeld := func() {
			c.releaseTransport()
			if workloadHeld {
				c.releaseWorkload()
				workloadHeld = false
			}
		}
		return &streamBody{
			ReadCloser: resp.Body,
			release:    releaseHeld,
			onClose:    c.recordSuccess,
		}, size, nil
	}
	if workloadHeld {
		c.releaseWorkload()
	}
	return nil, 0, lastErr
}

type streamBody struct {
	io.ReadCloser
	release func()
	onClose func()
	once    sync.Once
}

func (s *streamBody) Close() error {
	err := s.ReadCloser.Close()
	s.once.Do(func() {
		if s.onClose != nil {
			s.onClose()
		}
		if s.release != nil {
			s.release()
		}
	})
	return err
}

func parseContentLength(v string) int64 {
	v = strings.TrimSpace(v)
	if v == "" {
		return 0
	}
	n, err := strconv.ParseInt(v, 10, 64)
	if err != nil || n < 0 {
		return 0
	}
	return n
}

func parseContentRangeTotal(v string) int64 {
	// bytes 0-1023/2048
	parts := strings.Split(v, "/")
	if len(parts) != 2 {
		return 0
	}
	return parseContentLength(parts[1])
}

func (c *Client) PostJSON(ctx context.Context, path string, body map[string]any) (map[string]any, error) {
	return c.doJSON(ctx, http.MethodPost, path, body)
}

func (c *Client) Delete(ctx context.Context, path string) error {
	u := c.graphBase + "/" + strings.TrimPrefix(path, "/")
	req, err := http.NewRequestWithContext(ctx, http.MethodDelete, u, nil)
	if err != nil {
		return err
	}
	_, err = c.doRequest(ctx, req)
	return err
}

func (c *Client) PutBytes(ctx context.Context, path string, data []byte) (map[string]any, error) {
	if len(data) > uploadSessionThreshold {
		return c.putViaUploadSession(ctx, path, int64(len(data)), bytes.NewReader(data))
	}
	u := c.graphBase + "/" + strings.TrimPrefix(path, "/")
	req, err := http.NewRequestWithContext(ctx, http.MethodPut, u, bytes.NewReader(data))
	if err != nil {
		return nil, err
	}
	req.Header.Set("Content-Type", "application/octet-stream")
	resp, err := c.doRequest(ctx, req)
	if err != nil {
		return nil, err
	}
	if len(resp.body) == 0 {
		return map[string]any{}, nil
	}
	var out map[string]any
	return out, json.Unmarshal(resp.body, &out)
}

const uploadSessionThreshold = 4 << 20 // 4 MiB
const uploadSessionChunkSize = 10 << 20 // 10 MiB

func (c *Client) PutStream(ctx context.Context, path string, size int64, r io.Reader) (map[string]any, error) {
	if size > 0 && size <= uploadSessionThreshold {
		data, err := io.ReadAll(io.LimitReader(r, size))
		if err != nil {
			return nil, err
		}
		return c.PutBytes(ctx, path, data)
	}
	return c.putViaUploadSession(ctx, path, size, r)
}

func (c *Client) putViaUploadSession(ctx context.Context, path string, size int64, r io.Reader) (map[string]any, error) {
	sessionPath := strings.TrimSuffix(path, ":/content") + ":/createUploadSession"
	if !strings.Contains(sessionPath, ":/") {
		sessionPath = path + ":/createUploadSession"
	}
	body := map[string]any{
		"item": map[string]any{
			"@microsoft.graph.conflictBehavior": "replace",
		},
	}
	// Do not send item.size on createUploadSession — Graph returns 400 invalidRequest
	// for drive root uploads (size is conveyed via Content-Range on chunk PUTs).
	session, err := c.PostJSON(ctx, sessionPath, body)
	if err != nil {
		return nil, fmt.Errorf("create upload session %s: %w", sessionPath, err)
	}
	uploadURL, _ := session["uploadUrl"].(string)
	if uploadURL == "" {
		return nil, fmt.Errorf("upload session missing uploadUrl")
	}

	offset := int64(0)
	buf := make([]byte, uploadSessionChunkSize)
	for {
		if ctx.Err() != nil {
			return nil, ctx.Err()
		}
		n, readErr := io.ReadFull(r, buf)
		if readErr == io.EOF || readErr == io.ErrUnexpectedEOF {
			if n == 0 {
				break
			}
		} else if readErr != nil {
			return nil, readErr
		}
		chunk := buf[:n]
		end := offset + int64(n) - 1
		contentRange := fmt.Sprintf("bytes %d-%d/%d", offset, end, size)
		if size <= 0 {
			contentRange = fmt.Sprintf("bytes %d-%d/*", offset, end)
		}

		if err := c.acquireWorkload(ctx); err != nil {
			return nil, err
		}
		workloadHeld := true
		throttle429Attempt := 0
		chunkDone := false
		for !chunkDone {
			if ctx.Err() != nil {
				if workloadHeld {
					c.releaseWorkload()
				}
				return nil, ctx.Err()
			}
			if err := c.acquireTransport(ctx); err != nil {
				if workloadHeld {
					c.releaseWorkload()
				}
				return nil, err
			}

			req, err := http.NewRequestWithContext(ctx, http.MethodPut, uploadURL, bytes.NewReader(chunk))
			if err != nil {
				c.releaseTransport()
				if workloadHeld {
					c.releaseWorkload()
				}
				return nil, err
			}
			req.Header.Set("Content-Length", strconv.FormatInt(int64(n), 10))
			req.Header.Set("Content-Range", contentRange)

			resp, err := c.httpClient.Do(req)
			if err != nil {
				c.releaseTransport()
				if sleepErr := c.sleepRetry(ctx, parseRetryAfter("", throttle429Attempt, c.retryDelay)); sleepErr != nil {
					if workloadHeld {
						c.releaseWorkload()
					}
					return nil, sleepErr
				}
				continue
			}
			respBody, _ := io.ReadAll(resp.Body)
			statusCode := resp.StatusCode
			retryAfter := resp.Header.Get("Retry-After")
			resp.Body.Close()

			for isThrottleStatus(statusCode) {
				var delay time.Duration
				if statusCode == http.StatusTooManyRequests {
					delay = parseRetryAfter429(retryAfter, throttle429Attempt, c.retryDelay)
					c.record429(delay)
				} else {
					delay = parseRetryAfter(retryAfter, throttle429Attempt, c.retryDelay)
				}
				throttle429Attempt++
				c.releaseTransport()
				if sleepErr := c.sleep429WithWorkload(ctx, delay, &workloadHeld); sleepErr != nil {
					return nil, sleepErr
				}
				if err := c.acquireTransport(ctx); err != nil {
					if workloadHeld {
						c.releaseWorkload()
					}
					return nil, err
				}
				req, err = http.NewRequestWithContext(ctx, http.MethodPut, uploadURL, bytes.NewReader(chunk))
				if err != nil {
					c.releaseTransport()
					if workloadHeld {
						c.releaseWorkload()
					}
					return nil, err
				}
				req.Header.Set("Content-Length", strconv.FormatInt(int64(n), 10))
				req.Header.Set("Content-Range", contentRange)
				resp, err = c.httpClient.Do(req)
				if err != nil {
					c.releaseTransport()
					if sleepErr := c.sleepRetry(ctx, parseRetryAfter("", throttle429Attempt, c.retryDelay)); sleepErr != nil {
						if workloadHeld {
							c.releaseWorkload()
						}
						return nil, sleepErr
					}
					break
				}
				respBody, _ = io.ReadAll(resp.Body)
				statusCode = resp.StatusCode
				retryAfter = resp.Header.Get("Retry-After")
				resp.Body.Close()
			}
			if err != nil {
				continue
			}

			c.releaseTransport()
			if statusCode == http.StatusAccepted || statusCode == http.StatusOK || statusCode == http.StatusCreated {
				if statusCode != http.StatusAccepted {
					if workloadHeld {
						c.releaseWorkload()
					}
					var out map[string]any
					if len(respBody) > 0 {
						_ = json.Unmarshal(respBody, &out)
					}
					return out, nil
				}
				chunkDone = true
			} else {
				if workloadHeld {
					c.releaseWorkload()
				}
				return nil, fmt.Errorf("upload chunk http %d: %s", statusCode, string(respBody))
			}
		}
		if workloadHeld {
			c.releaseWorkload()
		}
		offset += int64(n)
		if readErr == io.EOF || readErr == io.ErrUnexpectedEOF {
			break
		}
	}
	return map[string]any{}, nil
}

func (c *Client) doJSON(ctx context.Context, method, path string, body map[string]any) (map[string]any, error) {
	u := c.graphBase + "/" + strings.TrimPrefix(path, "/")
	payload, err := json.Marshal(body)
	if err != nil {
		return nil, err
	}
	req, err := http.NewRequestWithContext(ctx, method, u, strings.NewReader(string(payload)))
	if err != nil {
		return nil, err
	}
	req.Header.Set("Content-Type", "application/json")
	resp, err := c.doRequest(ctx, req)
	if err != nil {
		return nil, err
	}
	if len(resp.body) == 0 {
		return map[string]any{}, nil
	}
	var out map[string]any
	if err := json.Unmarshal(resp.body, &out); err != nil {
		// Create/update responses are fire-and-forget for restore; a 2xx means success.
		return map[string]any{}, nil
	}
	return out, nil
}

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
)

const defaultGraphHost = "https://graph.microsoft.com"

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
	sem        chan struct{}
	throttleMu sync.Mutex
	throttle429 int64
	adaptiveSem chan struct{}
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
		token:      token,
		graphBase:  strings.TrimRight(base, "/") + "/v1.0",
		httpClient: &http.Client{Timeout: 120 * time.Second, Transport: transport},
		maxRetries: maxRetries,
		retryDelay: time.Duration(retryDelayMs) * time.Millisecond,
		sem:        make(chan struct{}, concurrency),
	}
	if opts.AdaptiveLimit {
		c.adaptiveSem = make(chan struct{}, concurrency)
	}
	return c
}

func (c *Client) ThrottleHits() int64 {
	return atomic.LoadInt64(&c.throttle429)
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

func (c *Client) acquire(ctx context.Context) error {
	if err := acquireGlobal(ctx); err != nil {
		return err
	}
	if c.adaptiveSem != nil {
		select {
		case c.adaptiveSem <- struct{}{}:
			return nil
		case <-ctx.Done():
			releaseGlobal()
			return ctx.Err()
		}
	}
	select {
	case c.sem <- struct{}{}:
		return nil
	case <-ctx.Done():
		releaseGlobal()
		return ctx.Err()
	}
}

func (c *Client) release() {
	if c.adaptiveSem != nil {
		<-c.adaptiveSem
		releaseGlobal()
		return
	}
	<-c.sem
	releaseGlobal()
}

func (c *Client) record429() {
	atomic.AddInt64(&c.throttle429, 1)
	if c.adaptiveSem == nil {
		return
	}
	c.throttleMu.Lock()
	defer c.throttleMu.Unlock()
	cap := cap(c.adaptiveSem)
	if cap <= 1 {
		return
	}
	// Shrink adaptive limit by one on sustained throttling.
	newCap := cap - 1
	newSem := make(chan struct{}, newCap)
	for len(c.adaptiveSem) > 0 {
		select {
		case <-c.adaptiveSem:
		default:
		}
	}
	c.adaptiveSem = newSem
}

func (c *Client) growAdaptiveLimit(max int) {
	if c.adaptiveSem == nil || max <= 0 {
		return
	}
	c.throttleMu.Lock()
	defer c.throttleMu.Unlock()
	cap := cap(c.adaptiveSem)
	if cap >= max {
		return
	}
	newSem := make(chan struct{}, cap+1)
	for len(c.adaptiveSem) > 0 {
		select {
		case v := <-c.adaptiveSem:
			select {
			case newSem <- v:
			default:
			}
		default:
		}
	}
	c.adaptiveSem = newSem
}

func parseRetryAfter(header string, attempt int, baseDelay time.Duration) time.Duration {
	header = strings.TrimSpace(header)
	if header != "" {
		if n, err := strconv.Atoi(header); err == nil && n > 0 {
			return time.Duration(min(n, 120)) * time.Second
		}
		if ts, err := http.ParseTime(header); err == nil {
			d := time.Until(ts)
			if d > 0 {
				return minDuration(d, 120*time.Second)
			}
		}
	}
	// Exponential backoff with jitter.
	delay := baseDelay * time.Duration(attempt+2)
	jitter := time.Duration(rand.Int63n(int64(baseDelay)))
	return minDuration(delay+jitter, 120*time.Second)
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

func isRetryableStatus(code int) bool {
	return code == http.StatusTooManyRequests || code == http.StatusServiceUnavailable || code == http.StatusGatewayTimeout
}

type httpResponse struct {
	status int
	body   []byte
	header http.Header
}

func (c *Client) doRequest(ctx context.Context, req *http.Request) (*httpResponse, error) {
	if err := c.acquire(ctx); err != nil {
		return nil, err
	}
	defer c.release()

	var lastErr error
	retried401 := false
	for attempt := 0; attempt <= c.maxRetries; attempt++ {
		if ctx.Err() != nil {
			return nil, ctx.Err()
		}
		reqClone := req.Clone(ctx)
		if reqClone.Header.Get("Authorization") == "" {
			reqClone.Header.Set("Authorization", "Bearer "+c.getToken())
		}
		if reqClone.Header.Get("Accept") == "" {
			reqClone.Header.Set("Accept", "application/json")
		}
		if reqClone.Header.Get("Accept-Encoding") == "" {
			reqClone.Header.Set("Accept-Encoding", "gzip")
		}

		resp, err := c.httpClient.Do(reqClone)
		if err != nil {
			lastErr = err
			time.Sleep(parseRetryAfter("", attempt, c.retryDelay))
			continue
		}
		body, readErr := readResponseBody(resp)
		resp.Body.Close()
		if readErr != nil {
			lastErr = readErr
			time.Sleep(parseRetryAfter("", attempt, c.retryDelay))
			continue
		}

		if isRetryableStatus(resp.StatusCode) && attempt < c.maxRetries {
			if resp.StatusCode == http.StatusTooManyRequests {
				c.record429()
			}
			lastErr = fmt.Errorf("graph %d", resp.StatusCode)
			time.Sleep(parseRetryAfter(resp.Header.Get("Retry-After"), attempt, c.retryDelay))
			continue
		}
		if resp.StatusCode == http.StatusUnauthorized && c.refresh != nil && !retried401 {
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
		if resp.StatusCode >= 400 {
			return nil, fmt.Errorf("graph %s: %s", resp.Status, string(body))
		}
		return &httpResponse{status: resp.StatusCode, body: body, header: resp.Header}, nil
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
			query := map[string]string{"$top": strconv.Itoa(top)}
			if selectFields != "" {
				query["$select"] = selectFields
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

	if err := c.acquire(ctx); err != nil {
		return nil, 0, err
	}

	var lastErr error
	for attempt := 0; attempt <= c.maxRetries; attempt++ {
		if ctx.Err() != nil {
			c.release()
			return nil, 0, ctx.Err()
		}
		reqClone := req.Clone(ctx)
		if reqClone.Header.Get("Authorization") == "" {
			reqClone.Header.Set("Authorization", "Bearer "+c.getToken())
		}
		if offset > 0 && reqClone.Header.Get("Range") == "" {
			reqClone.Header.Set("Range", fmt.Sprintf("bytes=%d-", offset))
		}

		resp, err := c.httpClient.Do(reqClone)
		if err != nil {
			lastErr = err
			time.Sleep(parseRetryAfter("", attempt, c.retryDelay))
			continue
		}

		if isRetryableStatus(resp.StatusCode) && attempt < c.maxRetries {
			if resp.StatusCode == http.StatusTooManyRequests {
				c.record429()
			}
			io.Copy(io.Discard, resp.Body)
			resp.Body.Close()
			lastErr = fmt.Errorf("graph %d", resp.StatusCode)
			time.Sleep(parseRetryAfter(resp.Header.Get("Retry-After"), attempt, c.retryDelay))
			continue
		}

		if resp.StatusCode == http.StatusRequestedRangeNotSatisfiable {
			io.Copy(io.Discard, resp.Body)
			resp.Body.Close()
			c.release()
			return nil, 0, fmt.Errorf("graph range not satisfiable at offset %d", offset)
		}

		if resp.StatusCode >= 400 {
			body, _ := io.ReadAll(resp.Body)
			resp.Body.Close()
			c.release()
			return nil, 0, fmt.Errorf("graph %s: %s", resp.Status, string(body))
		}

		size := parseContentLength(resp.Header.Get("Content-Length"))
		if offset > 0 && resp.StatusCode == http.StatusPartialContent {
			if cr := resp.Header.Get("Content-Range"); cr != "" {
				if total := parseContentRangeTotal(cr); total > 0 {
					size = total
				}
			}
		} else if size == 0 && resp.ContentLength > 0 {
			size = resp.ContentLength
		}

		return &streamBody{ReadCloser: resp.Body, release: c.release}, size, nil
	}
	c.release()
	return nil, 0, lastErr
}

type streamBody struct {
	io.ReadCloser
	release func()
	once    sync.Once
}

func (s *streamBody) Close() error {
	err := s.ReadCloser.Close()
	s.once.Do(s.release)
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

		req, err := http.NewRequestWithContext(ctx, http.MethodPut, uploadURL, bytes.NewReader(chunk))
		if err != nil {
			return nil, err
		}
		req.Header.Set("Content-Length", strconv.FormatInt(int64(n), 10))
		req.Header.Set("Content-Range", contentRange)

		if err := c.acquire(ctx); err != nil {
			return nil, err
		}
		resp, err := c.httpClient.Do(req)
		c.release()
		if err != nil {
			return nil, err
		}
		respBody, _ := io.ReadAll(resp.Body)
		resp.Body.Close()

		if resp.StatusCode == http.StatusAccepted || resp.StatusCode == http.StatusOK || resp.StatusCode == http.StatusCreated {
			if resp.StatusCode != http.StatusAccepted {
				var out map[string]any
				if len(respBody) > 0 {
					_ = json.Unmarshal(respBody, &out)
				}
				return out, nil
			}
			offset += int64(n)
			if readErr == io.EOF || readErr == io.ErrUnexpectedEOF {
				break
			}
			continue
		}
		return nil, fmt.Errorf("upload chunk http %d: %s", resp.StatusCode, string(respBody))
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

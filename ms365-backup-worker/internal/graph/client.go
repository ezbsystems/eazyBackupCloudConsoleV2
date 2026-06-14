package graph

import (
	"bytes"
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
	token      string
	graphBase  string
	httpClient *http.Client
	maxRetries int
	retryDelay time.Duration
	sem        chan struct{}
	throttleMu sync.Mutex
	throttle429 int64
	adaptiveSem chan struct{}
}

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

func (c *Client) acquire(ctx context.Context) error {
	if c.adaptiveSem != nil {
		select {
		case c.adaptiveSem <- struct{}{}:
			return nil
		case <-ctx.Done():
			return ctx.Err()
		}
	}
	select {
	case c.sem <- struct{}{}:
		return nil
	case <-ctx.Done():
		return ctx.Err()
	}
}

func (c *Client) release() {
	if c.adaptiveSem != nil {
		<-c.adaptiveSem
		return
	}
	<-c.sem
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
	for attempt := 0; attempt <= c.maxRetries; attempt++ {
		if ctx.Err() != nil {
			return nil, ctx.Err()
		}
		reqClone := req.Clone(ctx)
		if reqClone.Header.Get("Authorization") == "" {
			reqClone.Header.Set("Authorization", "Bearer "+c.token)
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
		body, _ := io.ReadAll(resp.Body)
		resp.Body.Close()

		if isRetryableStatus(resp.StatusCode) && attempt < c.maxRetries {
			if resp.StatusCode == http.StatusTooManyRequests {
				c.record429()
			}
			lastErr = fmt.Errorf("graph %d", resp.StatusCode)
			time.Sleep(parseRetryAfter(resp.Header.Get("Retry-After"), attempt, c.retryDelay))
			continue
		}
		if resp.StatusCode >= 400 {
			return nil, fmt.Errorf("graph %s: %s", resp.Status, string(body))
		}
		return &httpResponse{status: resp.StatusCode, body: body, header: resp.Header}, nil
	}
	return nil, lastErr
}

func (c *Client) GetJSON(ctx context.Context, path string, query map[string]string) (map[string]any, error) {
	u := c.graphBase + "/" + strings.TrimPrefix(path, "/")
	if len(query) > 0 {
		parsed, _ := url.Parse(u)
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

func (c *Client) Paginate(ctx context.Context, path string, query map[string]string) ([]map[string]any, error) {
	var all []map[string]any
	next := ""
	for page := 0; page < 500; page++ {
		q := map[string]string{}
		for k, v := range query {
			q[k] = v
		}
		p := path
		if next != "" {
			p = next
			q = nil
		}
		var data map[string]any
		var err error
		if strings.HasPrefix(p, "http") {
			data, err = c.getURL(ctx, p)
		} else {
			data, err = c.GetJSON(ctx, p, q)
		}
		if err != nil {
			return all, err
		}
		if values, ok := data["value"].([]any); ok {
			for _, v := range values {
				if m, ok := v.(map[string]any); ok {
					all = append(all, m)
				}
			}
		}
		nextLink, _ := data["@odata.nextLink"].(string)
		if nextLink == "" {
			break
		}
		next = nextLink
	}
	return all, nil
}

type DeltaPage struct {
	Items     []map[string]any
	NextLink  string
	DeltaLink string
}

// PaginateDelta streams delta pages. When selectFields is non-empty it is sent as $select on the initial request.
func (c *Client) PaginateDelta(ctx context.Context, initialPath, deltaLink, selectFields string, top int) ([]map[string]any, string, error) {
	if top <= 0 {
		top = 100
	}
	path := initialPath
	if strings.TrimSpace(deltaLink) != "" {
		path = deltaLink
	}
	var items []map[string]any
	var newDelta string
	for page := 0; page < 500; page++ {
		var data map[string]any
		var err error
		if strings.HasPrefix(path, "http") {
			data, err = c.getURL(ctx, path)
		} else {
			query := map[string]string{"$top": strconv.Itoa(top)}
			if selectFields != "" {
				query["$select"] = selectFields
			}
			data, err = c.GetJSON(ctx, path, query)
		}
		if err != nil {
			return items, "", err
		}
		if values, ok := data["value"].([]any); ok {
			for _, v := range values {
				if m, ok := v.(map[string]any); ok {
					items = append(items, m)
				}
			}
		}
		if dl, ok := data["@odata.deltaLink"].(string); ok && dl != "" {
			return items, dl, nil
		}
		next, _ := data["@odata.nextLink"].(string)
		if next == "" {
			break
		}
		path = next
	}
	return items, newDelta, nil
}

func (c *Client) getURL(ctx context.Context, rawURL string) (map[string]any, error) {
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, rawURL, nil)
	if err != nil {
		return nil, err
	}
	resp, err := c.doRequest(ctx, req)
	if err != nil {
		return nil, err
	}
	var out map[string]any
	return out, json.Unmarshal(resp.body, &out)
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
			reqClone.Header.Set("Authorization", "Bearer "+c.token)
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
	if size > 0 {
		body["item"].(map[string]any)["size"] = size
	}
	session, err := c.PostJSON(ctx, sessionPath, body)
	if err != nil {
		return nil, fmt.Errorf("create upload session: %w", err)
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
	return out, json.Unmarshal(resp.body, &out)
}

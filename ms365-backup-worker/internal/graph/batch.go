package graph

import (
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"net/url"
	"strings"
	"time"
)

const maxBatchRequests = 20

type BatchRequest struct {
	ID     string
	Method string
	URL    string
}

type BatchResult struct {
	ID         string
	Status     int
	Body       []byte
	RetryAfter string
}

func batchSubRetryAfter(headers map[string]any) string {
	if headers == nil {
		return ""
	}
	raw, ok := headers["Retry-After"]
	if !ok {
		return ""
	}
	switch v := raw.(type) {
	case string:
		return strings.TrimSpace(v)
	case []any:
		if len(v) > 0 {
			if s, ok := v[0].(string); ok {
				return strings.TrimSpace(s)
			}
		}
	}
	return ""
}

// BatchGet executes up to 20 GET sub-requests via Graph $batch.
func (c *Client) BatchGet(ctx context.Context, requests []BatchRequest) ([]BatchResult, error) {
	if len(requests) == 0 {
		return nil, nil
	}
	if len(requests) > maxBatchRequests {
		return nil, fmt.Errorf("batch: max %d requests", maxBatchRequests)
	}

	payload := map[string]any{
		"requests": make([]map[string]any, 0, len(requests)),
	}
	for _, r := range requests {
		u := r.URL
		if !strings.HasPrefix(u, "/") {
			u = "/" + u
		}
		payload["requests"] = append(payload["requests"].([]map[string]any), map[string]any{
			"id":     r.ID,
			"method": http.MethodGet,
			"url":    u,
		})
	}

	out, err := c.doJSON(ctx, http.MethodPost, "/$batch", payload)
	if err != nil {
		return nil, err
	}
	responses, ok := out["responses"].([]any)
	if !ok {
		return nil, fmt.Errorf("batch: missing responses")
	}
	results := make([]BatchResult, 0, len(responses))
	for _, raw := range responses {
		m, ok := raw.(map[string]any)
		if !ok {
			continue
		}
		id, _ := m["id"].(string)
		status := 0
		switch v := m["status"].(type) {
		case float64:
			status = int(v)
		case int:
			status = v
		}
		var body []byte
		if b, ok := m["body"]; ok && b != nil {
			body, _ = json.Marshal(b)
		}
		retryAfter := ""
		if headers, ok := m["headers"].(map[string]any); ok {
			retryAfter = batchSubRetryAfter(headers)
		}
		results = append(results, BatchResult{ID: id, Status: status, Body: body, RetryAfter: retryAfter})
	}
	return results, nil
}

func (c *Client) batchThrottleDelay(status int, retryAfter string, attempt int) (time.Duration, bool) {
	if status != http.StatusTooManyRequests && status != http.StatusServiceUnavailable {
		return 0, false
	}
	if status == http.StatusTooManyRequests {
		return parseRetryAfter429(retryAfter, attempt, c.retryDelay), true
	}
	return parseRetryAfter(retryAfter, attempt, c.retryDelay), true
}

// BatchGetMessages fetches multiple messages in one $batch call.
func (c *Client) BatchGetMessages(ctx context.Context, userID string, messageIDs []string) (map[string][]byte, error) {
	out := map[string][]byte{}
	for i := 0; i < len(messageIDs); i += maxBatchRequests {
		end := i + maxBatchRequests
		if end > len(messageIDs) {
			end = len(messageIDs)
		}
		chunk := messageIDs[i:end]
		pending := append([]string(nil), chunk...)
		throttleAttempt := 0
		for len(pending) > 0 && throttleAttempt <= c.maxRetries {
			reqs := make([]BatchRequest, len(pending))
			for j, msgID := range pending {
				path := fmt.Sprintf("/users/%s/messages/%s?$select=%s",
					url.PathEscape(userID), url.PathEscape(msgID), url.QueryEscape(MailMessageSelect))
				reqs[j] = BatchRequest{ID: msgID, Method: http.MethodGet, URL: path}
			}
			results, err := c.BatchGet(ctx, reqs)
			if err != nil {
				return out, err
			}
			var throttled []string
			maxDelay := time.Duration(0)
			saw429 := false
			for _, r := range results {
				if delay, ok := c.batchThrottleDelay(r.Status, r.RetryAfter, throttleAttempt); ok {
					throttled = append(throttled, r.ID)
					if r.Status == http.StatusTooManyRequests {
						saw429 = true
					}
					if delay > maxDelay {
						maxDelay = delay
					}
					continue
				}
				if r.Status >= 400 || len(r.Body) == 0 {
					continue
				}
				out[r.ID] = r.Body
			}
			if len(throttled) == 0 {
				break
			}
			if maxDelay <= 0 {
				maxDelay = c.retryDelay
			}
			if saw429 {
				c.record429(maxDelay)
			}
			if sleepErr := c.sleep429(ctx, maxDelay); sleepErr != nil {
				return out, sleepErr
			}
			throttleAttempt++
			pending = throttled
		}
	}
	return out, nil
}

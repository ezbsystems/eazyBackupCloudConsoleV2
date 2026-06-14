package graph

import (
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"net/url"
	"strings"
)

const maxBatchRequests = 20

type BatchRequest struct {
	ID     string
	Method string
	URL    string
}

type BatchResult struct {
	ID     string
	Status int
	Body   []byte
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
		results = append(results, BatchResult{ID: id, Status: status, Body: body})
	}
	return results, nil
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
		reqs := make([]BatchRequest, len(chunk))
		for j, msgID := range chunk {
			path := fmt.Sprintf("/users/%s/messages/%s?$select=%s",
				url.PathEscape(userID), url.PathEscape(msgID), url.QueryEscape(MailMessageSelect))
			reqs[j] = BatchRequest{ID: msgID, Method: http.MethodGet, URL: path}
		}
		results, err := c.BatchGet(ctx, reqs)
		if err != nil {
			return out, err
		}
		for _, r := range results {
			if r.Status >= 400 || len(r.Body) == 0 {
				continue
			}
			out[r.ID] = r.Body
		}
	}
	return out, nil
}

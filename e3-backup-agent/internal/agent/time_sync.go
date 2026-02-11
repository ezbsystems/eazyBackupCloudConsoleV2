package agent

import (
	"crypto/tls"
	"fmt"
	"net/http"
	"net/url"
	"strings"
	"time"
)

type TimeSyncAttempt struct {
	Endpoint    string `json:"endpoint"`
	Method      string `json:"method"`
	Status      int    `json:"status,omitempty"`
	DateHeader  string `json:"date_header,omitempty"`
	ParsedUTC   string `json:"parsed_utc,omitempty"`
	DurationMs  int64  `json:"duration_ms,omitempty"`
	InsecureTLS bool   `json:"insecure_tls,omitempty"`
	Error       string `json:"error,omitempty"`
}

// syncClockFromAPIBase reads the Date header from the configured API host and
// sets local system clock to that UTC value.
func syncClockFromAPIBase(apiBase string) (time.Time, error) {
	serverTime, _, err := probeServerDate(apiBase)
	if err != nil {
		return time.Time{}, err
	}
	if err := setSystemTimeUTC(serverTime.UTC()); err != nil {
		return time.Time{}, err
	}
	return serverTime.UTC(), nil
}

func probeServerDate(raw string) (time.Time, []TimeSyncAttempt, error) {
	endpoints, err := timeSyncEndpoints(raw)
	if err != nil {
		return time.Time{}, nil, err
	}
	clients := []struct {
		client      *http.Client
		insecureTLS bool
	}{
		{client: newTimeSyncHTTPClient(10 * time.Second, false), insecureTLS: false},
		{client: newTimeSyncHTTPClient(10 * time.Second, true), insecureTLS: true},
	}

	var attempts []TimeSyncAttempt
	var lastErr error
	for _, c := range clients {
		for _, endpoint := range endpoints {
			if strings.TrimSpace(endpoint) == "" {
				continue
			}
			serverTime, newAttempts, err := fetchServerDateWithAttempts(c.client, endpoint, c.insecureTLS)
			if len(newAttempts) > 0 {
				attempts = append(attempts, newAttempts...)
			}
			if err != nil {
				lastErr = err
				continue
			}
			return serverTime.UTC(), attempts, nil
		}
	}
	if lastErr != nil {
		return time.Time{}, attempts, lastErr
	}
	return time.Time{}, attempts, fmt.Errorf("unable to fetch server time from %s", raw)
}

func timeSyncEndpoints(raw string) ([]string, error) {
	raw = strings.TrimSpace(raw)
	if raw == "" {
		return nil, fmt.Errorf("api base url is empty")
	}
	var endpoints []string
	u, err := url.Parse(raw)
	if err == nil && u.Scheme != "" && u.Host != "" {
		origin := u.Scheme + "://" + u.Host
		endpoints = append(endpoints, origin)
		if raw != origin {
			endpoints = append(endpoints, raw)
		}
	} else {
		endpoints = append(endpoints, raw)
	}

	seen := map[string]bool{}
	var out []string
	for _, endpoint := range endpoints {
		endpoint = strings.TrimSpace(endpoint)
		if endpoint == "" {
			continue
		}
		if strings.HasPrefix(endpoint, "http://") || strings.HasPrefix(endpoint, "https://") {
			if !seen[endpoint] {
				seen[endpoint] = true
				out = append(out, endpoint)
			}
			continue
		}
		for _, scheme := range []string{"https://", "http://"} {
			candidate := scheme + endpoint
			if !seen[candidate] {
				seen[candidate] = true
				out = append(out, candidate)
			}
		}
	}
	if len(out) == 0 {
		return nil, fmt.Errorf("invalid api base url: %q", raw)
	}
	return out, nil
}

func fetchServerDateWithAttempts(client *http.Client, endpoint string, insecureTLS bool) (time.Time, []TimeSyncAttempt, error) {
	var attempts []TimeSyncAttempt
	var lastErr error
	for _, method := range []string{http.MethodHead, http.MethodGet} {
		start := time.Now()
		req, err := http.NewRequest(method, endpoint, nil)
		if err != nil {
			return time.Time{}, nil, err
		}
		resp, err := client.Do(req)
		if err != nil {
			attempts = append(attempts, TimeSyncAttempt{
				Endpoint:    endpoint,
				Method:      method,
				DurationMs:  time.Since(start).Milliseconds(),
				InsecureTLS: insecureTLS,
				Error:       err.Error(),
			})
			lastErr = err
			continue
		}
		status := resp.StatusCode
		dateHeader := strings.TrimSpace(resp.Header.Get("Date"))
		resp.Body.Close()

		if dateHeader == "" {
			attempts = append(attempts, TimeSyncAttempt{
				Endpoint:    endpoint,
				Method:      method,
				Status:      status,
				DateHeader:  "",
				DurationMs:  time.Since(start).Milliseconds(),
				InsecureTLS: insecureTLS,
				Error:       "missing Date header",
			})
			lastErr = fmt.Errorf("missing Date header from %s", endpoint)
			continue
		}
		ts, err := http.ParseTime(dateHeader)
		if err != nil {
			attempts = append(attempts, TimeSyncAttempt{
				Endpoint:    endpoint,
				Method:      method,
				Status:      status,
				DateHeader:  dateHeader,
				DurationMs:  time.Since(start).Milliseconds(),
				InsecureTLS: insecureTLS,
				Error:       fmt.Sprintf("invalid Date header: %v", err),
			})
			lastErr = fmt.Errorf("invalid Date header %q from %s: %w", dateHeader, endpoint, err)
			continue
		}
		attempts = append(attempts, TimeSyncAttempt{
			Endpoint:    endpoint,
			Method:      method,
			Status:      status,
			DateHeader:  dateHeader,
			ParsedUTC:   ts.UTC().Format(time.RFC3339),
			DurationMs:  time.Since(start).Milliseconds(),
			InsecureTLS: insecureTLS,
		})
		return ts.UTC(), attempts, nil
	}
	if lastErr != nil {
		return time.Time{}, attempts, lastErr
	}
	return time.Time{}, attempts, fmt.Errorf("failed to fetch Date header from %s", endpoint)
}

func newTimeSyncHTTPClient(timeout time.Duration, insecureTLS bool) *http.Client {
	transport := http.DefaultTransport.(*http.Transport).Clone()
	if insecureTLS {
		transport.TLSClientConfig = &tls.Config{InsecureSkipVerify: true}
	}
	return &http.Client{
		Timeout:   timeout,
		Transport: transport,
	}
}

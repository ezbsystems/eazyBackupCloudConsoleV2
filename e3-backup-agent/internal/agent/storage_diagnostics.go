package agent

import (
	"context"
	"crypto/tls"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net"
	"net/http"
	"net/url"
	"strconv"
	"strings"
	"time"
)

type storageFailureClass struct {
	ReasonCode string
	MessageID  string
	Summary    string
	Hint       string
	Endpoint   string
	Host       string
	Port       int
	Technical  string
}

type storagePreflightResult struct {
	Reachable bool
	Class     storageFailureClass
	HTTPCode  int
}

func classifyStorageInitError(endpoint string, err error) storageFailureClass {
	host, port := endpointHostPort(endpoint)
	out := storageFailureClass{
		ReasonCode: "endpoint_unreachable",
		MessageID:  "STORAGE_ENDPOINT_UNREACHABLE",
		Summary:    "Cannot reach cloud storage endpoint.",
		Hint:       "Check network path, firewall rules, and endpoint configuration.",
		Endpoint:   endpoint,
		Host:       host,
		Port:       port,
	}
	if err != nil {
		out.Technical = sanitizeErrorMessage(err)
	}
	if err == nil {
		return out
	}

	var dnsErr *net.DNSError
	if errors.As(err, &dnsErr) {
		out.ReasonCode = "dns_lookup_failed"
		out.MessageID = "STORAGE_DNS_FAILED"
		out.Summary = fmt.Sprintf("Cannot resolve storage hostname %q.", nonEmpty(host, "unknown host"))
		out.Hint = "Verify DNS settings and that the endpoint hostname is correct."
		return out
	}

	var opErr *net.OpError
	if errors.As(err, &opErr) {
		if opErr.Timeout() {
			out.ReasonCode = "tcp_timeout"
			out.MessageID = "STORAGE_TCP_TIMEOUT"
			out.Summary = fmt.Sprintf("Connection to storage endpoint %s timed out.", hostPortLabel(host, port))
			out.Hint = "Check network connectivity, firewall policy, and endpoint availability."
			return out
		}
	}

	msg := strings.ToLower(err.Error())
	switch {
	case strings.Contains(msg, "no such host"),
		strings.Contains(msg, "temporary failure in name resolution"),
		strings.Contains(msg, "name or service not known"),
		strings.Contains(msg, "server misbehaving"):
		out.ReasonCode = "dns_lookup_failed"
		out.MessageID = "STORAGE_DNS_FAILED"
		out.Summary = fmt.Sprintf("Cannot resolve storage hostname %q.", nonEmpty(host, "unknown host"))
		out.Hint = "Verify DNS settings and that the endpoint hostname is correct."
	case strings.Contains(msg, "connection refused"),
		strings.Contains(msg, "actively refused it"),
		strings.Contains(msg, "connectex"),
		strings.Contains(msg, "refused"):
		out.ReasonCode = "tcp_refused"
		out.MessageID = "STORAGE_TCP_REFUSED"
		out.Summary = fmt.Sprintf("Storage endpoint %s refused the connection.", hostPortLabel(host, port))
		out.Hint = "Ensure the storage service is running and accepting connections on the expected port."
	case strings.Contains(msg, "i/o timeout"),
		strings.Contains(msg, "timed out"),
		strings.Contains(msg, "timeout"):
		out.ReasonCode = "tcp_timeout"
		out.MessageID = "STORAGE_TCP_TIMEOUT"
		out.Summary = fmt.Sprintf("Connection to storage endpoint %s timed out.", hostPortLabel(host, port))
		out.Hint = "Check network connectivity, firewall policy, and endpoint availability."
	case strings.Contains(msg, "x509"),
		strings.Contains(msg, "certificate"),
		strings.Contains(msg, "tls handshake"):
		out.ReasonCode = "tls_failed"
		out.MessageID = "STORAGE_TLS_FAILED"
		out.Summary = fmt.Sprintf("TLS handshake with storage endpoint %s failed.", hostPortLabel(host, port))
		out.Hint = "Verify certificate validity and TLS termination settings for the endpoint."
	case strings.Contains(msg, "status 403 body"),
		strings.Contains(msg, "access blocked"):
		out.ReasonCode = "http_blocked"
		out.MessageID = "STORAGE_HTTP_BLOCKED"
		out.Summary = "Storage/API request was blocked by upstream policy (HTTP 403)."
		out.Hint = "Check reverse-proxy, WAF, and network security policy for agent API and storage endpoints."
	}
	return out
}

func storageFailureParams(c storageFailureClass) map[string]any {
	params := map[string]any{
		"reason_code": c.ReasonCode,
		"summary":     c.Summary,
		"hint":        c.Hint,
	}
	if c.Endpoint != "" {
		params["endpoint"] = c.Endpoint
	}
	if c.Host != "" {
		params["host"] = c.Host
	}
	if c.Port > 0 {
		params["port"] = c.Port
	}
	if c.Technical != "" {
		params["error"] = c.Technical
	}
	return params
}

func storageFailureSummary(c storageFailureClass) string {
	if c.Summary == "" {
		return "Cloud storage endpoint is unreachable."
	}
	if strings.TrimSpace(c.Hint) == "" {
		return c.Summary
	}
	return c.Summary + " " + c.Hint
}

func runStoragePreflight(ctx context.Context, endpoint string) storagePreflightResult {
	host, port, scheme := endpointParts(endpoint)
	if strings.TrimSpace(host) == "" {
		c := storageFailureClass{
			ReasonCode: "endpoint_invalid",
			MessageID:  "STORAGE_ENDPOINT_UNREACHABLE",
			Summary:    "Storage endpoint is invalid or empty.",
			Hint:       "Review storage endpoint settings and provide a valid hostname.",
			Endpoint:   endpoint,
			Host:       host,
			Port:       port,
		}
		return storagePreflightResult{Reachable: false, Class: c}
	}

	c := storageFailureClass{
		ReasonCode: "preflight_ok",
		MessageID:  "STORAGE_PREFLIGHT_OK",
		Summary:    fmt.Sprintf("Storage endpoint %s is reachable.", hostPortLabel(host, port)),
		Hint:       "Storage connectivity preflight succeeded.",
		Endpoint:   endpoint,
		Host:       host,
		Port:       port,
	}

	dnsCtx, dnsCancel := context.WithTimeout(ctx, 4*time.Second)
	_, dnsErr := net.DefaultResolver.LookupIPAddr(dnsCtx, host)
	dnsCancel()
	if dnsErr != nil {
		fail := classifyStorageInitError(endpoint, dnsErr)
		fail.Technical = sanitizeErrorMessage(dnsErr)
		return storagePreflightResult{Reachable: false, Class: fail}
	}

	target := net.JoinHostPort(host, strconv.Itoa(port))
	dialer := &net.Dialer{Timeout: 4 * time.Second}
	tcpCtx, tcpCancel := context.WithTimeout(ctx, 5*time.Second)
	conn, tcpErr := dialer.DialContext(tcpCtx, "tcp", target)
	tcpCancel()
	if tcpErr != nil {
		fail := classifyStorageInitError(endpoint, tcpErr)
		fail.Technical = sanitizeErrorMessage(tcpErr)
		return storagePreflightResult{Reachable: false, Class: fail}
	}
	_ = conn.Close()

	if strings.EqualFold(scheme, "https") {
		tlsCtx, tlsCancel := context.WithTimeout(ctx, 6*time.Second)
		tlsDialer := tls.Dialer{
			NetDialer: dialer,
			Config: &tls.Config{
				InsecureSkipVerify: true, //nolint:gosec // align with agent storage client behavior
				ServerName:         host,
			},
		}
		tlsConn, tlsErr := tlsDialer.DialContext(tlsCtx, "tcp", target)
		tlsCancel()
		if tlsErr != nil {
			fail := classifyStorageInitError(endpoint, tlsErr)
			fail.Technical = sanitizeErrorMessage(tlsErr)
			return storagePreflightResult{Reachable: false, Class: fail}
		}
		_ = tlsConn.Close()
	}

	httpCode, probeErr := probeHTTPPolicyBlock(ctx, endpoint)
	if probeErr != nil {
		lower := strings.ToLower(probeErr.Error())
		if strings.Contains(lower, "http 403 access blocked") {
			fail := classifyStorageInitError(endpoint, probeErr)
			fail.Technical = sanitizeErrorMessage(probeErr)
			return storagePreflightResult{Reachable: false, Class: fail, HTTPCode: http.StatusForbidden}
		}
		// HTTP probe is best-effort. Reachability is already verified by DNS/TCP/TLS.
		c.Technical = sanitizeErrorMessage(probeErr)
		return storagePreflightResult{Reachable: true, Class: c}
	}
	if httpCode > 0 {
		return storagePreflightResult{Reachable: true, Class: c, HTTPCode: httpCode}
	}
	return storagePreflightResult{Reachable: true, Class: c}
}

func probeHTTPPolicyBlock(ctx context.Context, endpoint string) (int, error) {
	u, err := parseEndpointURL(endpoint)
	if err != nil {
		return 0, err
	}
	origin := u.Scheme + "://" + u.Host
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, origin, nil)
	if err != nil {
		return 0, err
	}
	transport := http.DefaultTransport.(*http.Transport).Clone()
	transport.TLSClientConfig = &tls.Config{InsecureSkipVerify: true} //nolint:gosec // diagnostics probe only
	client := &http.Client{
		Timeout:   6 * time.Second,
		Transport: transport,
	}
	resp, err := client.Do(req)
	if err != nil {
		return 0, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusForbidden {
		return resp.StatusCode, nil
	}

	body, _ := io.ReadAll(io.LimitReader(resp.Body, 1200))
	lower := strings.ToLower(string(body))
	if strings.Contains(lower, "access blocked") || strings.Contains(lower, "blocked") {
		return http.StatusForbidden, fmt.Errorf("http 403 access blocked from endpoint origin")
	}
	return http.StatusForbidden, nil
}

func endpointParts(endpoint string) (host string, port int, scheme string) {
	parsed, err := parseEndpointURL(endpoint)
	if err != nil {
		return "", 0, ""
	}
	host = parsed.Hostname()
	scheme = strings.ToLower(parsed.Scheme)
	if host == "" {
		host = strings.TrimSpace(parsed.Host)
	}
	port = 0
	if p := strings.TrimSpace(parsed.Port()); p != "" {
		if pi, convErr := strconv.Atoi(p); convErr == nil {
			port = pi
		}
	}
	if port == 0 {
		if scheme == "http" {
			port = 80
		} else {
			port = 443
		}
	}
	return host, port, scheme
}

func endpointHostPort(endpoint string) (host string, port int) {
	host, port, _ = endpointParts(endpoint)
	return host, port
}

func parseEndpointURL(endpoint string) (*url.URL, error) {
	raw := strings.TrimSpace(endpoint)
	if raw == "" {
		return nil, fmt.Errorf("endpoint is empty")
	}
	u, err := url.Parse(raw)
	if err == nil && u.Host != "" {
		if u.Scheme == "" {
			u.Scheme = "https"
		}
		return u, nil
	}
	u2, err2 := url.Parse("https://" + raw)
	if err2 != nil || u2.Host == "" {
		return nil, fmt.Errorf("invalid endpoint %q", raw)
	}
	return u2, nil
}

func nonEmpty(v, fallback string) string {
	if strings.TrimSpace(v) == "" {
		return fallback
	}
	return v
}

func hostPortLabel(host string, port int) string {
	if strings.TrimSpace(host) == "" {
		return "unknown endpoint"
	}
	if port <= 0 {
		return host
	}
	return net.JoinHostPort(host, strconv.Itoa(port))
}

func truncateForTransport(s string, max int) string {
	if max <= 0 || len(s) <= max {
		return s
	}
	if max <= 1 {
		return s[:max]
	}
	return s[:max-1] + "…"
}

func compactEventsForTransport(events []RunEvent) []RunEvent {
	out := make([]RunEvent, 0, len(events))
	for _, ev := range events {
		clone := ev
		if clone.Code != "" {
			clone.Code = truncateForTransport(clone.Code, 80)
		}
		if clone.MessageID != "" {
			clone.MessageID = truncateForTransport(clone.MessageID, 120)
		}
		if len(clone.ParamsJSON) > 0 {
			params := map[string]any{}
			for k, v := range clone.ParamsJSON {
				switch t := v.(type) {
				case string:
					params[k] = truncateForTransport(t, 320)
				case int, int32, int64, float32, float64, bool:
					params[k] = t
				default:
					b, err := json.Marshal(t)
					if err == nil {
						params[k] = truncateForTransport(string(b), 320)
					} else {
						params[k] = truncateForTransport(fmt.Sprintf("%v", t), 320)
					}
				}
			}
			clone.ParamsJSON = params
		}
		out = append(out, clone)
	}
	return out
}

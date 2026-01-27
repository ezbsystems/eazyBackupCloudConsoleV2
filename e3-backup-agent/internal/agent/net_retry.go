package agent

import (
	"context"
	"errors"
	"io"
	"net"
	"strings"
)

func isTransientNetErr(err error) bool {
	if err == nil {
		return false
	}
	if errors.Is(err, io.EOF) || errors.Is(err, context.DeadlineExceeded) {
		return true
	}
	var netErr net.Error
	if errors.As(err, &netErr) {
		if netErr.Timeout() || netErr.Temporary() {
			return true
		}
	}
	msg := strings.ToLower(err.Error())
	return strings.Contains(msg, "connection reset") ||
		strings.Contains(msg, "broken pipe") ||
		strings.Contains(msg, "tls handshake timeout") ||
		strings.Contains(msg, "eof")
}

func shouldRetryStatus(status int) bool {
	if status == 429 {
		return true
	}
	return status >= 500 && status <= 599
}

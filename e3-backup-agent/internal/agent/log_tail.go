package agent

import (
	"bytes"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"time"
)

const (
	defaultLogTailBytes = 131072
	maxLogTailBytes     = 262144
)

var (
	reSensitiveKV = regexp.MustCompile(`(?i)\b(access[_-]?key|secret|token|password|authorization)\b\s*[:=]\s*([^\s,;]+)`)
)

func executeLogTail(path string, maxBytes int) (string, bool, error) {
	allowed := map[string]struct{}{
		strings.ToLower(filepath.Clean(`C:\ProgramData\E3Backup\logs\agent.log`)): {},
		strings.ToLower(filepath.Clean(`C:\ProgramData\E3Backup\logs\tray.log`)):  {},
	}
	cleanPath := strings.ToLower(filepath.Clean(strings.TrimSpace(path)))
	if _, ok := allowed[cleanPath]; !ok {
		return "", false, fmt.Errorf("log path is not allowed")
	}

	if maxBytes <= 0 {
		maxBytes = defaultLogTailBytes
	}
	if maxBytes > maxLogTailBytes {
		maxBytes = maxLogTailBytes
	}

	f, err := os.Open(path)
	if err != nil {
		return "", false, err
	}
	defer f.Close()

	stat, err := f.Stat()
	if err != nil {
		return "", false, err
	}

	size := stat.Size()
	start := int64(0)
	truncated := false
	if size > int64(maxBytes) {
		start = size - int64(maxBytes)
		truncated = true
	}
	if _, err := f.Seek(start, 0); err != nil {
		return "", false, err
	}
	b, err := io.ReadAll(io.LimitReader(f, int64(maxBytes)+1))
	if err != nil {
		return "", false, err
	}
	if int64(len(b)) > int64(maxBytes) {
		b = b[:maxBytes]
	}
	if start > 0 {
		if idx := bytes.IndexByte(b, '\n'); idx >= 0 && idx+1 < len(b) {
			b = b[idx+1:]
		}
	}

	content := string(bytes.ToValidUTF8(b, []byte("?")))
	content = reSensitiveKV.ReplaceAllString(content, `$1=[redacted]`)
	return content, truncated, nil
}

func (r *Runner) executeFetchLogTailCommand(cmd PendingCommand) {
	logPath := `C:\ProgramData\E3Backup\logs\agent.log`
	maxBytes := defaultLogTailBytes

	if cmd.Payload != nil {
		if v, ok := cmd.Payload["log_path"].(string); ok && strings.TrimSpace(v) != "" {
			logPath = v
		}
		if v, ok := cmd.Payload["max_bytes"].(float64); ok && v > 0 {
			maxBytes = int(v)
		}
	}

	content, truncated, err := executeLogTail(logPath, maxBytes)
	if err != nil {
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "fetch_log_tail failed: "+err.Error())
		return
	}

	result := map[string]any{
		"path":         logPath,
		"truncated":    truncated,
		"retrieved_at": time.Now().UTC().Format(time.RFC3339),
		"content":      content,
	}
	if err := r.client.ReportBrowseResult(cmd.CommandID, result); err != nil {
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "report fetch_log_tail failed: "+err.Error())
		return
	}
}


// Package applog provides a small leveled logger with size-based rotation,
// designed to keep the customer-PC footprint tiny while preserving useful
// state-transition information when the agent cannot reach the server.
//
// Usage:
//
//	applog.Init(applog.Options{
//	    Path:    `C:\ProgramData\E3Backup\logs\agent.log`,
//	    MaxSize: 5 * 1024 * 1024,
//	    Keep:    3,
//	    Level:   applog.LevelWarn,
//	})
//	defer applog.Close()
//	applog.Infof("lifecycle", "service started version=%s", v)
//
// The package also redirects the standard library's `log` package to the same
// rotating sink so legacy log.Printf call sites keep working under rotation.
package applog

import (
	"fmt"
	"io"
	stdlog "log"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"
)

// Level is a syslog-style severity. Lower values are more verbose.
type Level int32

const (
	LevelDebug Level = iota
	LevelInfo
	LevelWarn
	LevelError
)

// Options configure the logger at Init time.
type Options struct {
	Path    string // log file path (e.g. C:\ProgramData\E3Backup\logs\agent.log)
	MaxSize int64  // bytes per file before rotation; default 5 MiB
	Keep    int    // total files to keep (including active); default 3
	Level   Level  // minimum level to write; default LevelInfo
	Mode    os.FileMode
}

var (
	mu      sync.Mutex
	w       *rotatingWriter
	level   Level = LevelInfo
	stderrW io.Writer = os.Stderr // fallback when no file
)

// Init configures the global logger. Calling Init replaces any prior state.
func Init(opts Options) error {
	mu.Lock()
	defer mu.Unlock()
	if opts.MaxSize <= 0 {
		opts.MaxSize = 5 * 1024 * 1024
	}
	if opts.Keep <= 0 {
		opts.Keep = 3
	}
	if opts.Mode == 0 {
		opts.Mode = 0o600
	}
	level = opts.Level

	if opts.Path == "" {
		w = nil
		stdlog.SetOutput(stderrW)
		stdlog.SetFlags(stdlog.LstdFlags | stdlog.Lmicroseconds)
		return nil
	}
	if err := os.MkdirAll(filepath.Dir(opts.Path), 0o755); err != nil {
		// Continue with stderr fallback; don't crash the agent.
		w = nil
		stdlog.SetOutput(stderrW)
		stdlog.SetFlags(stdlog.LstdFlags | stdlog.Lmicroseconds)
		return err
	}
	rw, err := newRotatingWriter(opts.Path, opts.MaxSize, opts.Keep, opts.Mode)
	if err != nil {
		w = nil
		stdlog.SetOutput(stderrW)
		stdlog.SetFlags(stdlog.LstdFlags | stdlog.Lmicroseconds)
		return err
	}
	w = rw
	stdlog.SetOutput(rw)
	stdlog.SetFlags(stdlog.LstdFlags | stdlog.Lmicroseconds)
	return nil
}

// Close flushes and releases the underlying file handle.
func Close() {
	mu.Lock()
	defer mu.Unlock()
	if w != nil {
		_ = w.Close()
		w = nil
	}
}

// SetLevel updates the active minimum level at runtime.
func SetLevel(l Level) {
	mu.Lock()
	defer mu.Unlock()
	level = l
}

// IsDebug reports whether debug-level messages will be emitted.
func IsDebug() bool {
	mu.Lock()
	defer mu.Unlock()
	return level <= LevelDebug
}

// ParseLevel maps a string ("debug","info","warn","error") to a Level.
// Unknown values default to LevelWarn (production-safe).
func ParseLevel(s string) Level {
	switch strings.ToLower(strings.TrimSpace(s)) {
	case "debug", "trace":
		return LevelDebug
	case "info":
		return LevelInfo
	case "warn", "warning":
		return LevelWarn
	case "error", "err", "fatal":
		return LevelError
	default:
		return LevelWarn
	}
}

// Debugf records a verbose, developer-oriented line.
// Off in production by default; useful when "verbose admin diagnostics" is on.
func Debugf(category, format string, args ...interface{}) { logf(LevelDebug, category, format, args...) }

// Infof records a state transition or routine lifecycle event.
func Infof(category, format string, args ...interface{}) { logf(LevelInfo, category, format, args...) }

// Warnf records an unexpected but recoverable condition.
func Warnf(category, format string, args ...interface{}) { logf(LevelWarn, category, format, args...) }

// Errorf records a failure that requires attention.
func Errorf(category, format string, args ...interface{}) { logf(LevelError, category, format, args...) }

func logf(l Level, category, format string, args ...interface{}) {
	mu.Lock()
	if l < level {
		mu.Unlock()
		return
	}
	out := w
	mu.Unlock()
	msg := fmt.Sprintf(format, args...)
	line := fmt.Sprintf("%s %s [%s] %s\n",
		time.Now().UTC().Format("2006-01-02T15:04:05.000Z"),
		levelLabel(l),
		safeCategory(category),
		msg,
	)
	if out != nil {
		_, _ = out.Write([]byte(line))
	} else {
		_, _ = stderrW.Write([]byte(line))
	}
}

func levelLabel(l Level) string {
	switch l {
	case LevelDebug:
		return "DEBUG"
	case LevelInfo:
		return "INFO"
	case LevelWarn:
		return "WARN"
	case LevelError:
		return "ERROR"
	}
	return "INFO"
}

func safeCategory(c string) string {
	c = strings.TrimSpace(c)
	if c == "" {
		return "general"
	}
	return c
}

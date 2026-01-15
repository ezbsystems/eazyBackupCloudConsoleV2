package logs

import (
	"context"
	"encoding/json"
	"fmt"
	"regexp"
	"strconv"
	"strings"
	"time"

	"github.com/hpcloud/tail"
)

type ProgressUpdate struct {
	ProgressPct        float64
	BytesTransferred   int64
	BytesTotal         int64
	ObjectsTransferred int64
	ObjectsTotal       int64
	SpeedBytesPerSec   int64
	EtaSeconds         int64
	CurrentItem        string
}

// TailRcloneJSON tails an rclone JSON log file and emits normalized progress updates.
// It attempts to interpret common rclone JSON "stats" lines. Unknown lines are ignored.
func TailRcloneJSON(ctx context.Context, path string) (<-chan ProgressUpdate, <-chan error) {
	out := make(chan ProgressUpdate, 32)
	errCh := make(chan error, 1)

	go func() {
		defer close(out)
		defer close(errCh)
		t, err := tail.TailFile(path, tail.Config{
			Follow:    true,
			ReOpen:    true,
			MustExist: false,
			Logger:    tail.DiscardingLogger,
		})
		if err != nil {
			errCh <- fmt.Errorf("tail log: %w", err)
			return
		}
		defer t.Cleanup()

		for {
			select {
			case <-ctx.Done():
				return
			case line, ok := <-t.Lines:
				if !ok || line == nil {
					time.Sleep(200 * time.Millisecond)
					continue
				}
				// Try JSON first
				if u, ok := parseJSONStats(line.Text); ok {
					out <- u
					continue
				}
				// Fallback: parse rclone plain text "Transferred:" line
				if u, ok := parsePlainTextStats(line.Text); ok {
					out <- u
					continue
				}
			}
		}
	}()
	return out, errCh
}

func parseJSONStats(text string) (ProgressUpdate, bool) {
	var m map[string]any
	if err := json.Unmarshal([]byte(text), &m); err != nil {
		return ProgressUpdate{}, false
	}
	u := ProgressUpdate{}
	// rclone often uses "stats" for periodic updates
	if statsV, ok := m["stats"]; ok {
		if stats, ok := statsV.(map[string]any); ok {
			u.BytesTransferred = asInt64(stats["bytes"])
			u.BytesTotal = asInt64(stats["bytesTotal"])
			u.ObjectsTransferred = asInt64(stats["files"])
			u.ObjectsTotal = asInt64(stats["filesTotal"])
			u.SpeedBytesPerSec = asInt64(stats["speed"])
			u.EtaSeconds = asInt64(stats["eta"])
			if pct, ok := stats["percent"]; ok {
				u.ProgressPct = asFloat64(pct)
			} else {
				u.ProgressPct = percent(u.BytesTransferred, u.BytesTotal)
			}
			if last, ok := stats["lastTransferred"]; ok {
				u.CurrentItem = asString(last)
			}
			return u, true
		}
	}
	// Fallback: try top-level fields
	u.BytesTransferred = asInt64(m["bytes"])
	u.BytesTotal = asInt64(m["bytesTotal"])
	u.ObjectsTransferred = asInt64(m["files"])
	u.ObjectsTotal = asInt64(m["filesTotal"])
	u.SpeedBytesPerSec = asInt64(m["speed"])
	u.EtaSeconds = asInt64(m["eta"])
	if u.BytesTransferred > 0 || u.ObjectsTransferred > 0 {
		u.ProgressPct = percent(u.BytesTransferred, u.BytesTotal)
		return u, true
	}
	return ProgressUpdate{}, false
}

var plainStatsRe = regexp.MustCompile(`Transferred:\s+([\d\.]+)\s+([KMGTP]?i?B)\s*/\s*([\d\.]+)\s+([KMGTP]?i?B),\s*([0-9]+|-)%.+?([\d\.]+)\s+([KMGTP]?i?B)/s,\s*ETA\s*(?:([0-9]+)s|-)`)

func parsePlainTextStats(text string) (ProgressUpdate, bool) {
	// Example: Transferred: 1.890 GiB / 1.990 GiB, 95%, 42.596 MiB/s, ETA 2s
	loc := plainStatsRe.FindStringSubmatch(text)
	if len(loc) == 0 {
		return ProgressUpdate{}, false
	}
	bytesDone := humanToBytes(loc[1], loc[2])
	bytesTotal := humanToBytes(loc[3], loc[4])
	// percent may be "-" at early stage; compute from bytes in that case
	var pct float64
	if loc[5] == "-" {
		pct = percent(bytesDone, bytesTotal)
	} else {
		pct = asFloat64(loc[5])
	}
	speed := humanToBytes(loc[6], loc[7])
	// ETA capture group may be empty if "ETA -"
	var eta int64
	if len(loc) >= 9 && loc[8] != "" {
		eta = asInt64(loc[8])
	} else {
		eta = 0
	}
	return ProgressUpdate{
		ProgressPct:      pct,
		BytesTransferred: bytesDone,
		BytesTotal:       bytesTotal,
		SpeedBytesPerSec: speed,
		EtaSeconds:       eta,
	}, true
}

func humanToBytes(val string, unit string) int64 {
	f, _ := strconv.ParseFloat(val, 64)
	u := strings.ToUpper(strings.TrimSpace(unit))
	mult := float64(1)
	switch u {
	case "B":
		mult = 1
	case "KIB":
		mult = 1024
	case "MIB":
		mult = 1024 * 1024
	case "GIB":
		mult = 1024 * 1024 * 1024
	case "TIB":
		mult = 1024 * 1024 * 1024 * 1024
	default:
		// fallback: assume bytes
		mult = 1
	}
	return int64(f * mult)
}

func asInt64(v any) int64 {
	switch x := v.(type) {
	case nil:
		return 0
	case float64:
		return int64(x)
	case float32:
		return int64(x)
	case int64:
		return x
	case int:
		return int64(x)
	case string:
		if x == "" {
			return 0
		}
		if strings.Contains(x, ".") {
			f, _ := strconv.ParseFloat(x, 64)
			return int64(f)
		}
		i, _ := strconv.ParseInt(x, 10, 64)
		return i
	default:
		return 0
	}
}

func asFloat64(v any) float64 {
	switch x := v.(type) {
	case nil:
		return 0
	case float64:
		return x
	case float32:
		return float64(x)
	case int64:
		return float64(x)
	case int:
		return float64(x)
	case string:
		f, _ := strconv.ParseFloat(x, 64)
		return f
	default:
		return 0
	}
}

func asString(v any) string {
	switch x := v.(type) {
	case nil:
		return ""
	case string:
		return x
	default:
		b, _ := json.Marshal(x)
		return string(b)
	}
}

func percent(n, d int64) float64 {
	if d <= 0 {
		return 0
	}
	return (float64(n) / float64(d)) * 100.0
}

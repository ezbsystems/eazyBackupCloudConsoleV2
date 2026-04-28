package agent

import (
	"bytes"
	"compress/gzip"
	"sync"
	"time"
)

// VerboseRun tracks an opt-in admin verbose-logging window for a single run.
type VerboseRun struct {
	RunID    string
	Source   string
	Expires  time.Time
	mu       sync.Mutex
	buf      bytes.Buffer
	lineCnt  int
	firstTS  time.Time
	chunkSeq int
}

// VerboseRegistry holds all currently-active verbose runs.
type VerboseRegistry struct {
	mu   sync.Mutex
	runs map[string]*VerboseRun
}

func NewVerboseRegistry() *VerboseRegistry {
	return &VerboseRegistry{runs: map[string]*VerboseRun{}}
}

func (vr *VerboseRegistry) Enable(runID, source string, ttl time.Duration) {
	vr.mu.Lock()
	defer vr.mu.Unlock()
	exp := time.Now().Add(ttl)
	if v, ok := vr.runs[runID]; ok {
		if exp.After(v.Expires) {
			v.Expires = exp
		}
		return
	}
	if source == "" {
		source = "run"
	}
	vr.runs[runID] = &VerboseRun{RunID: runID, Source: source, Expires: exp}
}

func (vr *VerboseRegistry) IsActive(runID string) bool {
	vr.mu.Lock()
	defer vr.mu.Unlock()
	v, ok := vr.runs[runID]
	if !ok {
		return false
	}
	return time.Now().Before(v.Expires)
}

func (vr *VerboseRegistry) Append(runID, line string) {
	vr.mu.Lock()
	v, ok := vr.runs[runID]
	vr.mu.Unlock()
	if !ok || time.Now().After(v.Expires) {
		return
	}
	v.mu.Lock()
	defer v.mu.Unlock()
	if v.firstTS.IsZero() {
		v.firstTS = time.Now()
	}
	v.buf.WriteString(line)
	if line == "" || line[len(line)-1] != '\n' {
		v.buf.WriteByte('\n')
	}
	v.lineCnt++
}

// FlushChunk returns gzipped buffered content. ok=false when nothing buffered.
func (vr *VerboseRegistry) FlushChunk(runID string) (chunkSeq int, source string, gzipped []byte, lineCount int, firstTS, lastTS time.Time, ok bool) {
	vr.mu.Lock()
	v, present := vr.runs[runID]
	vr.mu.Unlock()
	if !present {
		return 0, "", nil, 0, time.Time{}, time.Time{}, false
	}
	v.mu.Lock()
	defer v.mu.Unlock()
	if v.lineCnt == 0 || v.buf.Len() == 0 {
		return 0, "", nil, 0, time.Time{}, time.Time{}, false
	}
	var gz bytes.Buffer
	w := gzip.NewWriter(&gz)
	if _, err := w.Write(v.buf.Bytes()); err != nil {
		return 0, "", nil, 0, time.Time{}, time.Time{}, false
	}
	_ = w.Close()
	v.chunkSeq++
	chunkSeq = v.chunkSeq
	source = v.Source
	gzipped = gz.Bytes()
	lineCount = v.lineCnt
	firstTS = v.firstTS
	lastTS = time.Now()
	v.buf.Reset()
	v.lineCnt = 0
	v.firstTS = time.Time{}
	ok = true
	return
}

func (vr *VerboseRegistry) Close(runID string) {
	vr.mu.Lock()
	delete(vr.runs, runID)
	vr.mu.Unlock()
}

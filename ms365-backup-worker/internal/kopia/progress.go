package kopia

import (
	"fmt"
	"sync/atomic"
	"time"

	"github.com/kopia/kopia/snapshot/upload"
)

type ProgressCounter struct {
	BytesHashed   atomic.Int64
	BytesUploaded atomic.Int64
	FilesTotal    atomic.Int64
	FilesDone     atomic.Int64
	lastHashAt    atomic.Int64
	lastUploadAt  atomic.Int64
	startedAt     atomic.Int64
	uploadStarted atomic.Bool
	currentFile   atomic.Value
	callback      func(ProgressCounter)
}

func NewProgressCounter(callback func(ProgressCounter)) *ProgressCounter {
	now := time.Now().UnixNano()
	p := &ProgressCounter{callback: callback}
	p.lastHashAt.Store(now)
	p.lastUploadAt.Store(now)
	p.startedAt.Store(now)
	return p
}

func (p *ProgressCounter) notify() {
	if p.callback != nil {
		p.callback(*p)
	}
}

func (p *ProgressCounter) touchHashProgress() {
	p.lastHashAt.Store(time.Now().UnixNano())
}

func (p *ProgressCounter) touchUploadProgress() {
	p.lastUploadAt.Store(time.Now().UnixNano())
}

func (p *ProgressCounter) SecondsSinceLastHash() int64 {
	return secondsSinceAtomic(&p.lastHashAt)
}

func (p *ProgressCounter) SecondsSinceLastUpload() int64 {
	return secondsSinceAtomic(&p.lastUploadAt)
}

func (p *ProgressCounter) IsUploadStarted() bool {
	return p.uploadStarted.Load()
}

func (p *ProgressCounter) DebugSnapshot() map[string]any {
	now := time.Now()
	var currentItem any
	if v := p.currentFile.Load(); v != nil {
		currentItem = v
	}
	started := time.Unix(0, p.startedAt.Load())
	return map[string]any{
		"bytes_hashed":              p.BytesHashed.Load(),
		"bytes_uploaded":            p.BytesUploaded.Load(),
		"files_done":                p.FilesDone.Load(),
		"files_total":               p.FilesTotal.Load(),
		"elapsed_seconds":           int64(now.Sub(started).Seconds()),
		"seconds_since_last_hash":   p.SecondsSinceLastHash(),
		"seconds_since_last_upload": p.SecondsSinceLastUpload(),
		"current_item":              currentItem,
	}
}

// SafeDebugSnapshot returns stall diagnostics without customer filenames.
func (p *ProgressCounter) SafeDebugSnapshot() map[string]any {
	snap := p.DebugSnapshot()
	delete(snap, "current_item")
	if v := p.currentFile.Load(); v != nil {
		if name, ok := v.(string); ok && name != "" {
			snap["current_item_hash"] = hashProgressPath(name)
		}
	}
	return snap
}

func hashProgressPath(name string) string {
	if name == "" {
		return ""
	}
	var h uint64
	for i := 0; i < len(name); i++ {
		h = h*131 + uint64(name[i])
	}
	return fmt.Sprintf("%016x", h)
}

func secondsSinceAtomic(ts *atomic.Int64) int64 {
	v := ts.Load()
	if v == 0 {
		return -1
	}
	elapsed := time.Since(time.Unix(0, v))
	if elapsed < 0 {
		return 0
	}
	return int64(elapsed.Seconds())
}

func (p *ProgressCounter) UploadStarted() {
	now := time.Now().UnixNano()
	p.startedAt.Store(now)
	p.lastHashAt.Store(now)
	p.lastUploadAt.Store(now)
	p.uploadStarted.Store(true)
}
func (p *ProgressCounter) UploadFinished()                       { p.notify() }
func (p *ProgressCounter) Enabled() bool                         { return p.callback != nil }
func (p *ProgressCounter) HashingFile(fname string)              { p.currentFile.Store(fname) }
func (p *ProgressCounter) ExcludedFile(fname string, size int64) {}
func (p *ProgressCounter) ExcludedDir(dirname string)            {}
func (p *ProgressCounter) StartedDirectory(dirname string)       {}
func (p *ProgressCounter) FinishedDirectory(dirname string)      {}
func (p *ProgressCounter) EstimatedDataSize(fileCount, totalBytes int64) {
	p.FilesTotal.Store(fileCount)
}

func (p *ProgressCounter) EstimationParameters() upload.EstimationParameters {
	return upload.EstimationParameters{Type: upload.EstimationTypeAdaptive}
}

func (p *ProgressCounter) FinishedFile(fname string, err error) {
	if err != nil {
		return
	}
	p.FilesDone.Add(1)
	p.touchHashProgress()
	p.notify()
}

func (p *ProgressCounter) CachedFile(fname string, numBytes int64) {
	p.BytesHashed.Add(numBytes)
	p.FilesDone.Add(1)
	p.touchHashProgress()
	p.notify()
}

func (p *ProgressCounter) FinishedHashingFile(fname string, numBytes int64) {
	p.BytesHashed.Add(numBytes)
	p.FilesDone.Add(1)
	p.touchHashProgress()
	p.notify()
}

func (p *ProgressCounter) HashedBytes(numBytes int64) {
	p.BytesHashed.Add(numBytes)
	p.touchHashProgress()
	p.notify()
}

func (p *ProgressCounter) Error(path string, err error, isIgnored bool) {}

func (p *ProgressCounter) UploadedBytes(numBytes int64) {
	p.BytesUploaded.Add(numBytes)
	p.touchUploadProgress()
	p.notify()
}

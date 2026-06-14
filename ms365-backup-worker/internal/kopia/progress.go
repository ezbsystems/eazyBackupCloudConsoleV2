package kopia

import (
	"sync/atomic"
)

type ProgressCounter struct {
	BytesHashed   atomic.Int64
	BytesUploaded atomic.Int64
	FilesTotal    atomic.Int64
	FilesDone     atomic.Int64
	currentFile   atomic.Value
	callback      func(ProgressCounter)
}

func NewProgressCounter(callback func(ProgressCounter)) *ProgressCounter {
	return &ProgressCounter{callback: callback}
}

func (p *ProgressCounter) notify() {
	if p.callback != nil {
		p.callback(*p)
	}
}

func (p *ProgressCounter) UploadStarted()                          {}
func (p *ProgressCounter) UploadFinished()                         { p.notify() }
func (p *ProgressCounter) HashingFile(fname string)                { p.currentFile.Store(fname) }
func (p *ProgressCounter) ExcludedFile(fname string, size int64)   {}
func (p *ProgressCounter) ExcludedDir(dirname string)              {}
func (p *ProgressCounter) StartedDirectory(dirname string)         {}
func (p *ProgressCounter) FinishedDirectory(dirname string)        {}
func (p *ProgressCounter) EstimatedDataSize(fileCount int, totalBytes int64) {
	p.FilesTotal.Store(int64(fileCount))
}

func (p *ProgressCounter) CachedFile(fname string, numBytes int64) {
	p.BytesHashed.Add(numBytes)
	p.FilesDone.Add(1)
	p.notify()
}

func (p *ProgressCounter) FinishedHashingFile(fname string, numBytes int64) {
	p.BytesHashed.Add(numBytes)
	p.FilesDone.Add(1)
	p.notify()
}

func (p *ProgressCounter) HashedBytes(numBytes int64) {
	p.BytesHashed.Add(numBytes)
	p.notify()
}

func (p *ProgressCounter) Error(path string, err error, isIgnored bool) {}

func (p *ProgressCounter) UploadedBytes(numBytes int64) {
	p.BytesUploaded.Add(numBytes)
	p.notify()
}

//go:build !windows
// +build !windows

package agent

import (
	"fmt"
	"io"
	"time"

	"github.com/your-org/e3-backup-agent/internal/agent/hyperv"
)

// sparseVHDXReader is a stub for non-Windows platforms.
type sparseVHDXReader struct{}

func newSparseVHDXReader(vhdxPath string, totalSize int64, changedBlocks []hyperv.ChangedBlockRange) (*sparseVHDXReader, error) {
	return nil, fmt.Errorf("hyper-v is only supported on Windows")
}

func (r *sparseVHDXReader) Read(p []byte) (int, error) {
	return 0, fmt.Errorf("hyper-v is only supported on Windows")
}

func (r *sparseVHDXReader) Seek(offset int64, whence int) (int64, error) {
	return 0, fmt.Errorf("hyper-v is only supported on Windows")
}

func (r *sparseVHDXReader) Close() error {
	return nil
}

func (r *sparseVHDXReader) Size() int64 {
	return 0
}

// fullVHDXReader is a stub for non-Windows platforms.
type fullVHDXReader struct{}

func newFullVHDXReader(vhdxPath string) (*fullVHDXReader, error) {
	return nil, fmt.Errorf("hyper-v is only supported on Windows")
}

func (r *fullVHDXReader) Read(p []byte) (int, error) {
	return 0, fmt.Errorf("hyper-v is only supported on Windows")
}

func (r *fullVHDXReader) Seek(offset int64, whence int) (int64, error) {
	return 0, fmt.Errorf("hyper-v is only supported on Windows")
}

func (r *fullVHDXReader) Close() error {
	return nil
}

func (r *fullVHDXReader) Size() int64 {
	return 0
}

// VHDXStreamEntry is a stub for non-Windows platforms.
type VHDXStreamEntry struct{}

func NewVHDXStreamEntry(name string, reader io.ReadSeekCloser, size int64, diskPath string) *VHDXStreamEntry {
	return &VHDXStreamEntry{}
}

func (e *VHDXStreamEntry) Name() string     { return "" }
func (e *VHDXStreamEntry) Size() int64      { return 0 }
func (e *VHDXStreamEntry) ModTime() time.Time { return time.Time{} }
func (e *VHDXStreamEntry) IsDir() bool      { return false }
func (e *VHDXStreamEntry) Reader() io.ReadSeekCloser { return nil }
func (e *VHDXStreamEntry) DiskPath() string { return "" }
func (e *VHDXStreamEntry) Close() error     { return nil }


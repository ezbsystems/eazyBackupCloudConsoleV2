package graphfs

import (
	"context"
	"errors"
	"fmt"
	"io"
	"net"
	"os"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	kopiafs "github.com/kopia/kopia/fs"
)

// GraphFile streams drive item content from Microsoft Graph on Open().
type GraphFile struct {
	client      *graph.Client
	name        string
	contentPath string
	size        int64
	modTime     time.Time
}

var _ kopiafs.File = (*GraphFile)(nil)

func NewGraphFile(client *graph.Client, name, contentPath string, size int64, modTime time.Time) *GraphFile {
	if modTime.IsZero() {
		modTime = time.Now().UTC()
	}
	return &GraphFile{
		client:      client,
		name:        name,
		contentPath: contentPath,
		size:        size,
		modTime:     modTime,
	}
}

func NewGraphFileFromDriveItem(client *graph.Client, driveID string, item map[string]any) (*GraphFile, error) {
	itemID, _ := item["id"].(string)
	if itemID == "" {
		return nil, fmt.Errorf("drive item missing id")
	}
	name, _ := item["name"].(string)
	if name == "" {
		name = itemID
	}
	size := int64(0)
	if v, ok := item["size"].(float64); ok {
		size = int64(v)
	}
	modTime := parseGraphTime(item["lastModifiedDateTime"])
	contentPath := fmt.Sprintf("/drives/%s/items/%s/content", driveID, itemID)
	return NewGraphFile(client, SanitizeName(name), contentPath, size, modTime), nil
}

func (f *GraphFile) Name() string               { return f.name }
func (f *GraphFile) Size() int64                { return f.size }
func (f *GraphFile) Mode() os.FileMode          { return 0644 }
func (f *GraphFile) ModTime() time.Time         { return f.modTime }
func (f *GraphFile) IsDir() bool                { return false }
func (f *GraphFile) Sys() any                   { return nil }
func (f *GraphFile) Owner() kopiafs.OwnerInfo   { return kopiafs.OwnerInfo{} }
func (f *GraphFile) Device() kopiafs.DeviceInfo { return kopiafs.DeviceInfo{} }
func (f *GraphFile) LocalFilesystemPath() string { return "" }

func (f *GraphFile) Close() {}

func (f *GraphFile) Open(ctx context.Context) (kopiafs.Reader, error) {
	rc, size, err := f.client.GetStream(ctx, f.contentPath)
	if err != nil {
		return nil, err
	}
	if size > 0 && f.size == 0 {
		f.size = size
	}
	return &streamReader{
		ctx:        ctx,
		ReadCloser: rc,
		size:       f.size,
		offset:     0,
		entry:      f,
		client:     f.client,
		path:       f.contentPath,
		maxRetries: f.client.ContentReadRetries(),
	}, nil
}

type streamReader struct {
	ctx        context.Context
	io.ReadCloser
	size       int64
	offset     int64
	entry      kopiafs.Entry
	client     *graph.Client
	path       string
	maxRetries int
	attempts   int
}

func (r *streamReader) Close() error {
	return r.ReadCloser.Close()
}

func (r *streamReader) Seek(offset int64, whence int) (int64, error) {
	var abs int64
	switch whence {
	case io.SeekStart:
		abs = offset
	case io.SeekCurrent:
		abs = r.offset + offset
	case io.SeekEnd:
		abs = r.size + offset
	default:
		return 0, fmt.Errorf("invalid whence")
	}
	if abs < 0 {
		return 0, fmt.Errorf("negative seek")
	}
	if abs == r.offset {
		return abs, nil
	}
	_ = r.ReadCloser.Close()
	rc, _, err := r.client.GetStreamRange(r.ctx, r.path, abs)
	if err != nil {
		return 0, err
	}
	r.ReadCloser = rc
	r.offset = abs
	r.attempts = 0
	return abs, nil
}

func (r *streamReader) Read(p []byte) (int, error) {
	for {
		n, err := r.ReadCloser.Read(p)
		if n > 0 {
			r.offset += int64(n)
		}
		if err == nil || !isRetriableContentReadError(err) {
			return n, err
		}
		if r.attempts >= r.maxRetries {
			return n, fmt.Errorf("graph content read failed after %d attempts at offset %d for %s: %w", r.attempts, r.offset, r.path, err)
		}
		r.attempts++
		_ = r.ReadCloser.Close()
		rc, _, reopenErr := r.client.GetStreamRange(r.ctx, r.path, r.offset)
		if reopenErr != nil {
			return n, fmt.Errorf("graph content read retry %d/%d at offset %d for %s: %w", r.attempts, r.maxRetries, r.offset, r.path, reopenErr)
		}
		r.ReadCloser = rc
		if n > 0 {
			return n, nil
		}
	}
}

func (r *streamReader) Entry() (kopiafs.Entry, error) {
	return r.entry, nil
}

func isRetriableContentReadError(err error) bool {
	if err == nil || errors.Is(err, io.EOF) {
		return false
	}
	if graph.IsContentReadIdleTimeout(err) {
		return true
	}
	var netErr net.Error
	return errors.As(err, &netErr) && netErr.Timeout()
}

func parseGraphTime(v any) time.Time {
	s, _ := v.(string)
	if s == "" {
		return time.Time{}
	}
	t, err := time.Parse(time.RFC3339, s)
	if err != nil {
		return time.Time{}
	}
	return t.UTC()
}

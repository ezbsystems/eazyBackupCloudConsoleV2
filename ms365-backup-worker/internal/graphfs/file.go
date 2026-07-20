package graphfs

import (
	"bytes"
	"context"
	"io"
	"os"
	"time"

	kopiafs "github.com/kopia/kopia/fs"
)

type staticFile struct {
	name    string
	content []byte
	modTime time.Time
}

var _ kopiafs.File = (*staticFile)(nil)

func newStaticFile(name string, content []byte, modTime time.Time) *staticFile {
	if modTime.IsZero() {
		modTime = time.Now().UTC()
	}
	return &staticFile{name: name, content: content, modTime: modTime}
}

func (f *staticFile) Name() string               { return f.name }
func (f *staticFile) Size() int64                { return int64(len(f.content)) }
func (f *staticFile) Mode() os.FileMode          { return 0644 }
func (f *staticFile) ModTime() time.Time         { return f.modTime }
func (f *staticFile) IsDir() bool                { return false }
func (f *staticFile) Sys() any                   { return nil }
func (f *staticFile) Owner() kopiafs.OwnerInfo   { return kopiafs.OwnerInfo{} }
func (f *staticFile) Device() kopiafs.DeviceInfo { return kopiafs.DeviceInfo{} }
func (f *staticFile) LocalFilesystemPath() string { return "" }

func (f *staticFile) Close() {}

func (f *staticFile) Open(ctx context.Context) (kopiafs.Reader, error) {
	return &bytesReader{Reader: bytes.NewReader(f.content), size: int64(len(f.content)), entry: f}, nil
}

type bytesReader struct {
	io.Reader
	size  int64
	entry kopiafs.Entry
}

func (r *bytesReader) Close() error { return nil }

func (r *bytesReader) Seek(offset int64, whence int) (int64, error) {
	if s, ok := r.Reader.(io.Seeker); ok {
		return s.Seek(offset, whence)
	}
	return 0, io.ErrUnexpectedEOF
}

func (r *bytesReader) Entry() (kopiafs.Entry, error) {
	return r.entry, nil
}

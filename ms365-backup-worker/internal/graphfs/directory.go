package graphfs

import (
	"context"
	"fmt"
	"os"
	"strings"
	"time"

	kopiafs "github.com/kopia/kopia/fs"
)

type memoryDir struct {
	name     string
	children map[string]kopiafs.Entry
	modTime  time.Time
}

var _ kopiafs.Directory = (*memoryDir)(nil)

func NewDirectory(name string) *memoryDir {
	return &memoryDir{
		name:     name,
		children: make(map[string]kopiafs.Entry),
		modTime:  time.Now().UTC(),
	}
}

func (d *memoryDir) AddEntry(entry kopiafs.Entry) {
	if entry == nil {
		return
	}
	d.children[entry.Name()] = entry
}

func (d *memoryDir) Name() string               { return d.name }
func (d *memoryDir) Size() int64                { return 0 }
func (d *memoryDir) Mode() os.FileMode          { return os.ModeDir | 0755 }
func (d *memoryDir) ModTime() time.Time         { return d.modTime }
func (d *memoryDir) IsDir() bool                { return true }
func (d *memoryDir) Sys() any                   { return nil }
func (d *memoryDir) Owner() kopiafs.OwnerInfo   { return kopiafs.OwnerInfo{} }
func (d *memoryDir) Device() kopiafs.DeviceInfo { return kopiafs.DeviceInfo{} }
func (d *memoryDir) LocalFilesystemPath() string { return "" }

func (d *memoryDir) Close() {}

func (d *memoryDir) Child(ctx context.Context, name string) (kopiafs.Entry, error) {
	if e, ok := d.children[name]; ok {
		return e, nil
	}
	return nil, fmt.Errorf("child not found: %s", name)
}

func (d *memoryDir) SupportsMultipleIterations() bool { return true }

func (d *memoryDir) Iterate(ctx context.Context) (kopiafs.DirectoryIterator, error) {
	return newSliceIterator(sortedEntries(d.children)), nil
}

func SanitizeName(s string) string {
	s = strings.TrimSpace(s)
	if s == "" {
		return "unknown"
	}
	replacer := strings.NewReplacer("/", "_", "\\", "_", ":", "_", "*", "_", "?", "_", "\"", "_", "<", "_", ">", "_", "|", "_")
	return replacer.Replace(s)
}

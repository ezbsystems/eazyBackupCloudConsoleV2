package graphfs

import (
	"context"
	"fmt"
	"os"
	"sort"
	"sync"
	"time"

	kopiafs "github.com/kopia/kopia/fs"
)

// LazyDir enumerates children on Readdir; Child resolves from the in-memory map.
type LazyDir struct {
	name     string
	modTime  time.Time
	mu       sync.RWMutex
	children map[string]kopiafs.Entry
	loader   func(ctx context.Context) ([]kopiafs.Entry, error)
	loaded   bool
}

var _ kopiafs.Directory = (*LazyDir)(nil)

func NewLazyDir(name string, loader func(ctx context.Context) ([]kopiafs.Entry, error)) *LazyDir {
	return &LazyDir{
		name:     name,
		modTime:  time.Now().UTC(),
		children: make(map[string]kopiafs.Entry),
		loader:   loader,
	}
}

func (d *LazyDir) ensureLoaded(ctx context.Context) error {
	d.mu.Lock()
	defer d.mu.Unlock()
	if d.loaded || d.loader == nil {
		return nil
	}
	entries, err := d.loader(ctx)
	if err != nil {
		return err
	}
	for _, e := range entries {
		if e != nil {
			d.children[e.Name()] = e
		}
	}
	d.loaded = true
	return nil
}

func (d *LazyDir) AddEntry(entry kopiafs.Entry) {
	if entry == nil {
		return
	}
	d.mu.Lock()
	defer d.mu.Unlock()
	d.children[entry.Name()] = entry
}

func (d *LazyDir) Name() string               { return d.name }
func (d *LazyDir) Size() int64                { return 0 }
func (d *LazyDir) Mode() os.FileMode          { return os.ModeDir | 0755 }
func (d *LazyDir) ModTime() time.Time         { return d.modTime }
func (d *LazyDir) IsDir() bool                { return true }
func (d *LazyDir) Sys() any                   { return nil }
func (d *LazyDir) Owner() kopiafs.OwnerInfo   { return kopiafs.OwnerInfo{} }
func (d *LazyDir) Device() kopiafs.DeviceInfo { return kopiafs.DeviceInfo{} }
func (d *LazyDir) LocalFilesystemPath() string { return "" }

func (d *LazyDir) Child(ctx context.Context, name string) (kopiafs.Entry, error) {
	if err := d.ensureLoaded(ctx); err != nil {
		return nil, err
	}
	d.mu.RLock()
	defer d.mu.RUnlock()
	if e, ok := d.children[name]; ok {
		return e, nil
	}
	return nil, fmt.Errorf("child not found: %s", name)
}

func (d *LazyDir) Readdir(ctx context.Context) (kopiafs.Entries, error) {
	if err := d.ensureLoaded(ctx); err != nil {
		return nil, err
	}
	d.mu.RLock()
	defer d.mu.RUnlock()
	names := make([]string, 0, len(d.children))
	for n := range d.children {
		names = append(names, n)
	}
	sort.Strings(names)
	out := make(kopiafs.Entries, 0, len(names))
	for _, n := range names {
		out = append(out, d.children[n])
	}
	return out, nil
}

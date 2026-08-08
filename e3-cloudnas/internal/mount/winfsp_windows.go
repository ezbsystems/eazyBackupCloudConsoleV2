//go:build windows && cgo

package mount

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"sync"
	"sync/atomic"
	"time"

	"github.com/ezbsystems/e3-cloudnas/internal/api"
	"github.com/rclone/rclone/backend/s3"
	"github.com/rclone/rclone/fs"
	"github.com/rclone/rclone/fs/fserrors"
	"github.com/rclone/rclone/vfs"
	"github.com/winfsp/cgofuse/fuse"
	"golang.org/x/sys/windows/registry"
)

const (
	winFspDLLx86 = `C:\Program Files (x86)\WinFsp\bin\winfsp-x64.dll`
	winFspDLLx64 = `C:\Program Files\WinFsp\bin\winfsp-x64.dll`
)

type winFspMount struct {
	request api.MountRequest
	state   string
	errMsg  string
	unmount func() error
}

type winFspMounter struct {
	mu      sync.RWMutex
	mounts  map[string]*winFspMount
	version string
}

func NewWinFspMounter(version string) (Mounter, error) {
	return &winFspMounter{
		mounts:  make(map[string]*winFspMount),
		version: version,
	}, nil
}

func WinFspAvailable() bool {
	for _, path := range []string{winFspDLLx86, winFspDLLx64} {
		if info, err := os.Stat(path); err == nil && !info.IsDir() {
			return true
		}
	}

	// WinFsp 2.x uses SxS layouts; bin\ may be a junction under SxS\*\bin\.
	for _, pattern := range []string{
		`C:\Program Files (x86)\WinFsp\SxS\*\bin\winfsp-x64.dll`,
		`C:\Program Files\WinFsp\SxS\*\bin\winfsp-x64.dll`,
	} {
		if matches, err := filepath.Glob(pattern); err == nil {
			for _, match := range matches {
				if info, err := os.Stat(match); err == nil && !info.IsDir() {
					return true
				}
			}
		}
	}

	for _, path := range []string{
		`SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\WinFsp`,
		`SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall\WinFsp`,
	} {
		key, err := registry.OpenKey(registry.LOCAL_MACHINE, path, registry.QUERY_VALUE)
		if err == nil {
			_ = key.Close()
			return true
		}
	}
	return false
}

func (m *winFspMounter) Mount(ctx context.Context, req api.MountRequest) error {
	if !WinFspAvailable() {
		return fmt.Errorf("WinFsp is not installed or its runtime is unavailable")
	}
	letter, err := normalizeDriveLetter(req.DriveLetter)
	if err != nil {
		return err
	}
	if strings.TrimSpace(req.Bucket) == "" || strings.TrimSpace(req.Endpoint) == "" ||
		strings.TrimSpace(req.AccessKey) == "" || req.SecretKey == "" {
		return fmt.Errorf("bucket, endpoint, access_key, and secret_key are required")
	}

	m.mu.RLock()
	_, exists := m.mounts[letter]
	m.mu.RUnlock()
	if exists {
		return fmt.Errorf("drive %s: is already managed", letter)
	}
	if _, err := os.Stat(letter + `:\`); err == nil {
		return fmt.Errorf("drive %s: is already in use", letter)
	} else if !os.IsNotExist(err) {
		return fmt.Errorf("check drive %s: availability: %w", letter, err)
	}

	remote, err := s3.NewFs(ctx, "cloudnas", remoteRoot(req), s3Config(req))
	if err != nil {
		return fmt.Errorf("create Ceph S3 filesystem: %w", err)
	}
	vfsOpt := vfsOptions(req)
	cloudNASDebugLog("H2", "Mount", letter, map[string]any{
		"cacheMode":    req.CacheMode,
		"vfsCacheMode": vfsOpt.CacheMode.String(),
		"readOnly":     req.ReadOnly,
		"bucket":       req.Bucket,
	})
	vfsInstance := vfs.New(remote, &vfsOpt)
	fsys := newRcloneFileSystem(vfsInstance)
	host := fuse.NewFileSystemHost(fsys)
	host.SetCapReaddirPlus(true)
	host.SetCapCaseInsensitive(remote.Features().CaseInsensitive)
	unmount := func() error {
		vfsInstance.Shutdown()
		if !host.Unmount() {
			return fmt.Errorf("WinFsp unmount failed for drive %s:", letter)
		}
		return nil
	}

	mountpoint := letter + ":"
	options := []string{"-o", "uid=-1", "-o", "gid=-1", "--FileSystemName=e3-cloudnas"}
	if label := strings.TrimSpace(req.VolumeLabel); label != "" {
		options = append(options, "-o", "volname="+label)
	}

	result := make(chan error, 1)
	go func() {
		defer func() {
			if recovered := recover(); recovered != nil {
				result <- fmt.Errorf("WinFsp mount panic: %v", recovered)
			}
		}()
		if !host.Mount(mountpoint, options) {
			result <- fmt.Errorf("WinFsp mount failed")
			return
		}
		result <- nil
	}()

	readyCtx, cancel := context.WithTimeout(ctx, 10*time.Second)
	defer cancel()
	ticker := time.NewTicker(25 * time.Millisecond)
	defer ticker.Stop()
	for {
		select {
		case mountErr := <-result:
			vfsInstance.Shutdown()
			if mountErr == nil {
				mountErr = fmt.Errorf("WinFsp mount stopped before drive became ready")
			}
			return mountErr
		case <-readyCtx.Done():
			_ = unmount()
			return fmt.Errorf("wait for drive %s: %w", mountpoint, readyCtx.Err())
		case <-ticker.C:
			if _, statErr := os.Stat(mountpoint + `\`); statErr == nil {
				active := &winFspMount{
					request: req,
					state:   "mounted",
					unmount: unmount,
				}
				m.mu.Lock()
				m.mounts[letter] = active
				m.mu.Unlock()
				go m.watch(letter, active, result)
				return nil
			}
		}
	}
}

func (m *winFspMounter) watch(letter string, active *winFspMount, result <-chan error) {
	err := <-result
	m.mu.Lock()
	defer m.mu.Unlock()
	if m.mounts[letter] != active {
		return
	}
	active.state = "error"
	if err == nil {
		active.errMsg = "mount stopped unexpectedly"
	} else {
		active.errMsg = err.Error()
	}
}

func (m *winFspMounter) Unmount(_ context.Context, driveLetter string) error {
	letter, err := normalizeDriveLetter(driveLetter)
	if err != nil {
		return err
	}

	m.mu.Lock()
	active, exists := m.mounts[letter]
	if exists {
		delete(m.mounts, letter)
	}
	m.mu.Unlock()
	if !exists {
		return nil
	}

	return active.unmount()
}

func (m *winFspMounter) List() []api.MountStatus {
	m.mu.RLock()
	defer m.mu.RUnlock()

	out := make([]api.MountStatus, 0, len(m.mounts))
	for letter, active := range m.mounts {
		out = append(out, api.MountStatus{
			MountID:     active.request.MountID,
			DriveLetter: letter,
			State:       active.state,
			Error:       active.errMsg,
		})
	}
	sort.Slice(out, func(i, j int) bool { return out[i].DriveLetter < out[j].DriveLetter })
	return out
}

const unsetHandle = ^uint64(0)

type rcloneFileSystem struct {
	fuse.FileSystemBase
	vfs       *vfs.VFS
	mu        sync.Mutex
	handles   []vfs.Handle
	destroyed atomic.Bool
}

func newRcloneFileSystem(instance *vfs.VFS) *rcloneFileSystem {
	return &rcloneFileSystem{vfs: instance}
}

func (f *rcloneFileSystem) Destroy() {
	f.destroyed.Store(true)
}

func (f *rcloneFileSystem) openHandle(handle vfs.Handle) uint64 {
	f.mu.Lock()
	defer f.mu.Unlock()
	for index, old := range f.handles {
		if old == nil {
			f.handles[index] = handle
			return uint64(index)
		}
	}
	f.handles = append(f.handles, handle)
	return uint64(len(f.handles) - 1)
}

func (f *rcloneFileSystem) getHandle(handleID uint64) (vfs.Handle, int) {
	f.mu.Lock()
	defer f.mu.Unlock()
	if handleID >= uint64(len(f.handles)) || f.handles[handleID] == nil {
		return nil, -fuse.EBADF
	}
	return f.handles[handleID], 0
}

func (f *rcloneFileSystem) closeHandle(handleID uint64) (vfs.Handle, int) {
	f.mu.Lock()
	defer f.mu.Unlock()
	if handleID >= uint64(len(f.handles)) || f.handles[handleID] == nil {
		return nil, -fuse.EBADF
	}
	handle := f.handles[handleID]
	f.handles[handleID] = nil
	return handle, 0
}

func (f *rcloneFileSystem) node(path string, handleID uint64) (vfs.Node, int) {
	if handleID != unsetHandle {
		handle, errc := f.getHandle(handleID)
		if errc != 0 {
			return nil, errc
		}
		return handle.Node(), 0
	}
	node, err := f.vfs.Stat(path)
	return node, translateVFSError(err)
}

func fillStat(node vfs.Node, stat *fuse.Stat_t) {
	size := uint64(node.Size())
	mode := node.Mode().Perm()
	if node.IsDir() {
		mode |= fuse.S_IFDIR
	} else {
		mode |= fuse.S_IFREG
	}
	stat.Ino = node.Inode()
	stat.Mode = uint32(mode)
	stat.Nlink = 1
	stat.Size = int64(size)
	stat.Blksize = 4096
	stat.Blocks = int64((size + 511) / 512)
	timestamp := fuse.NewTimespec(node.ModTime())
	stat.Atim, stat.Mtim, stat.Ctim, stat.Birthtim = timestamp, timestamp, timestamp, timestamp
}

func (f *rcloneFileSystem) Getattr(path string, stat *fuse.Stat_t, handleID uint64) int {
	node, errc := f.node(path, handleID)
	if errc == 0 {
		fillStat(node, stat)
	}
	return errc
}

func (f *rcloneFileSystem) Statfs(_ string, stat *fuse.Statfs_t) int {
	const blockSize = 4096
	total, _, free := f.vfs.Statfs()
	stat.Blocks = uint64(total) / blockSize
	stat.Bfree = uint64(free) / blockSize
	stat.Bavail = stat.Bfree
	stat.Files, stat.Ffree = 1e9, 1e9
	stat.Bsize, stat.Frsize, stat.Namemax = blockSize, blockSize, 255
	return 0
}

func (f *rcloneFileSystem) Open(path string, flags int) (int, uint64) {
	handle, err := f.vfs.OpenFile(path, fuseFlags(flags), 0o777)
	if err != nil {
		errc := translateVFSError(err)
		cloudNASDebugLog("H3", "Open", path, map[string]any{"flags": flags, "errc": errc, "err": err.Error()})
		return errc, unsetHandle
	}
	return 0, f.openHandle(handle)
}

func (f *rcloneFileSystem) Create(path string, flags int, mode uint32) (int, uint64) {
	errc, fh := f.Open(path, flags|fuse.O_CREAT)
	if errc != 0 {
		cloudNASDebugLog("H3", "Create", path, map[string]any{"flags": flags, "mode": mode, "errc": errc})
	}
	return errc, fh
}

func (f *rcloneFileSystem) Read(_ string, buffer []byte, offset int64, handleID uint64) int {
	handle, errc := f.getHandle(handleID)
	if errc != 0 {
		return errc
	}
	n, err := handle.ReadAt(buffer, offset)
	if err != nil && !errors.Is(err, io.EOF) {
		errc = translateVFSError(err)
		cloudNASDebugLog("H4", "Read", handle.Node().Path(), map[string]any{"offset": offset, "errc": errc, "err": err.Error()})
		return errc
	}
	return n
}

func (f *rcloneFileSystem) Write(_ string, buffer []byte, offset int64, handleID uint64) int {
	handle, errc := f.getHandle(handleID)
	if errc != 0 {
		cloudNASDebugLog("H3", "Write", "", map[string]any{"handleID": handleID, "errc": errc})
		return errc
	}
	n, err := handle.WriteAt(buffer, offset)
	if err != nil {
		errc = translateVFSError(err)
		cloudNASDebugLog("H3", "Write", handle.Node().Path(), map[string]any{
			"offset": offset, "len": len(buffer), "n": n, "errc": errc, "err": err.Error(),
		})
		return errc
	}
	return n
}

func (f *rcloneFileSystem) Flush(_ string, handleID uint64) int {
	handle, errc := f.getHandle(handleID)
	if errc != 0 {
		return errc
	}
	if err := handle.Flush(); err != nil {
		errc = translateVFSError(err)
		cloudNASDebugLog("H3", "Flush", handle.Node().Path(), map[string]any{"errc": errc, "err": err.Error()})
		return errc
	}
	return 0
}

func (f *rcloneFileSystem) Release(_ string, handleID uint64) int {
	handle, errc := f.closeHandle(handleID)
	if errc != 0 {
		return errc
	}
	return translateVFSError(handle.Release())
}

func (f *rcloneFileSystem) Truncate(path string, size int64, handleID uint64) int {
	if handleID != unsetHandle {
		handle, errc := f.getHandle(handleID)
		if errc != 0 {
			return errc
		}
		if err := handle.Truncate(size); err != nil {
			errc = translateVFSError(err)
			cloudNASDebugLog("H3", "Truncate", path, map[string]any{"size": size, "errc": errc, "err": err.Error()})
			return errc
		}
		return 0
	}
	node, err := f.vfs.Stat(path)
	if err != nil {
		return translateVFSError(err)
	}
	if err := node.Truncate(size); err != nil {
		errc := translateVFSError(err)
		cloudNASDebugLog("H3", "Truncate", path, map[string]any{"size": size, "errc": errc, "err": err.Error()})
		return errc
	}
	return 0
}

// Chmod is a no-op (S3 has no POSIX modes). Returning -ENOSYS makes many
// Windows apps report a generic IO error after Explorer-style creates succeed.
func (f *rcloneFileSystem) Chmod(path string, mode uint32) int {
	cloudNASDebugLog("H1", "Chmod", path, map[string]any{"mode": mode, "errc": 0})
	return 0
}

// Chown is a no-op for the same reason as Chmod.
func (f *rcloneFileSystem) Chown(path string, uid uint32, gid uint32) int {
	return 0
}

// Access is a no-op; permission checks are enforced by S3 credentials.
func (f *rcloneFileSystem) Access(path string, mask uint32) int {
	return 0
}

var invalidDateCutoff = time.Date(1601, 1, 2, 0, 0, 0, 0, time.UTC)

// Utimens maps Windows SetFileTime onto VFS SetModTime when possible.
func (f *rcloneFileSystem) Utimens(path string, tmsp []fuse.Timespec) int {
	node, errc := f.node(path, unsetHandle)
	if errc != 0 {
		cloudNASDebugLog("H1", "Utimens", path, map[string]any{"errc": errc})
		return errc
	}
	if len(tmsp) < 2 {
		return 0
	}
	t := tmsp[1].Time()
	if t.Before(invalidDateCutoff) {
		return 0
	}
	if err := node.SetModTime(t); err != nil {
		errc = translateVFSError(err)
		cloudNASDebugLog("H1", "Utimens", path, map[string]any{"errc": errc, "err": err.Error()})
		return errc
	}
	return 0
}

// Fsync is a no-op for rclone VFS (uploads happen on Flush/Release).
func (f *rcloneFileSystem) Fsync(path string, _ bool, handleID uint64) int {
	cloudNASDebugLog("H1", "Fsync", path, map[string]any{"handleID": handleID, "errc": 0})
	return 0
}

// Fsyncdir is a no-op for rclone VFS.
func (f *rcloneFileSystem) Fsyncdir(path string, _ bool, _ uint64) int {
	return 0
}

func (f *rcloneFileSystem) Mkdir(path string, _ uint32) int {
	parent, leaf, err := f.vfs.StatParent(path)
	if err != nil {
		return translateVFSError(err)
	}
	_, err = parent.Mkdir(leaf)
	return translateVFSError(err)
}

func (f *rcloneFileSystem) Unlink(path string) int {
	return f.remove(path)
}

func (f *rcloneFileSystem) Rmdir(path string) int {
	return f.remove(path)
}

func (f *rcloneFileSystem) remove(path string) int {
	parent, leaf, err := f.vfs.StatParent(path)
	if err != nil {
		return translateVFSError(err)
	}
	return translateVFSError(parent.RemoveName(leaf))
}

func (f *rcloneFileSystem) Rename(oldPath, newPath string) int {
	return translateVFSError(f.vfs.Rename(oldPath, newPath))
}

func (f *rcloneFileSystem) Opendir(path string) (int, uint64) {
	return f.Open(path, fuse.O_RDONLY)
}

func (f *rcloneFileSystem) Readdir(path string, fill func(string, *fuse.Stat_t, int64) bool, offset int64, _ uint64) int {
	if offset != 0 {
		return -fuse.ESPIPE
	}
	node, err := f.vfs.Stat(path)
	if err != nil {
		return translateVFSError(err)
	}
	dir, ok := node.(*vfs.Dir)
	if !ok {
		return -fuse.ENOTDIR
	}
	nodes, err := dir.ReadDirAll()
	if err != nil {
		return translateVFSError(err)
	}
	fill(".", nil, 0)
	fill("..", nil, 0)
	for _, child := range nodes {
		var stat fuse.Stat_t
		fillStat(child, &stat)
		if !fill(child.Name(), &stat, 0) {
			break
		}
	}
	return 0
}

func (f *rcloneFileSystem) Releasedir(_ string, handleID uint64) int {
	handle, errc := f.closeHandle(handleID)
	if errc != 0 {
		return errc
	}
	return translateVFSError(handle.Release())
}

func fuseFlags(flags int) int {
	out := os.O_RDONLY
	switch flags & fuse.O_ACCMODE {
	case fuse.O_WRONLY:
		out = os.O_WRONLY
	case fuse.O_RDWR:
		out = os.O_RDWR
	}
	if flags&fuse.O_APPEND != 0 {
		out |= os.O_APPEND
	}
	if flags&fuse.O_CREAT != 0 {
		out |= os.O_CREATE
	}
	if flags&fuse.O_EXCL != 0 {
		out |= os.O_EXCL
	}
	if flags&fuse.O_TRUNC != 0 {
		out |= os.O_TRUNC
	}
	return out
}

func translateVFSError(err error) int {
	if err == nil {
		return 0
	}
	_, cause := fserrors.Cause(err)
	switch cause {
	case vfs.ENOENT, fs.ErrorDirNotFound, fs.ErrorObjectNotFound:
		return -fuse.ENOENT
	case vfs.EEXIST, fs.ErrorDirExists:
		return -fuse.EEXIST
	case vfs.EPERM, fs.ErrorPermissionDenied:
		return -fuse.EPERM
	case vfs.ECLOSED, vfs.EBADF:
		return -fuse.EBADF
	case vfs.ENOTEMPTY:
		return -fuse.ENOTEMPTY
	case vfs.ESPIPE:
		return -fuse.ESPIPE
	case vfs.EROFS:
		return -fuse.EROFS
	case vfs.ENOSYS, fs.ErrorNotImplemented:
		return -fuse.ENOSYS
	case vfs.EINVAL:
		return -fuse.EINVAL
	default:
		return -fuse.EIO
	}
}

// cloudNASDebugLog writes compact NDJSON diagnostics for mount IO failures.
// Kept tiny and best-effort; never blocks the FUSE path on log errors.
func cloudNASDebugLog(hypothesisID, op, path string, data map[string]any) {
	payload := map[string]any{
		"sessionId":    "acfd10",
		"hypothesisId": hypothesisID,
		"location":     "winfsp_windows.go:" + op,
		"message":      op,
		"data":         data,
		"path":         path,
		"timestamp":    time.Now().UnixMilli(),
		"runId":        "cloudnas-io",
	}
	if path != "" {
		if data == nil {
			data = map[string]any{}
		}
		data["path"] = path
		payload["data"] = data
	}
	line, err := json.Marshal(payload)
	if err != nil {
		return
	}
	log.Printf("cloudnas-debug %s", string(line))
	// Also append beside the normal sidecar log when ProgramData is available.
	programData := os.Getenv("ProgramData")
	if programData == "" {
		return
	}
	logPath := filepath.Join(programData, "E3Backup", "logs", "cloudnas-debug.ndjson")
	f, err := os.OpenFile(logPath, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0o644)
	if err != nil {
		return
	}
	defer f.Close()
	_, _ = f.Write(append(line, '\n'))
}

var _ Mounter = (*winFspMounter)(nil)
var _ fuse.FileSystemInterface = (*rcloneFileSystem)(nil)

package kopia

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log"
	"net"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"

	"bufio"
)

const (
	DefaultBrowseServeSocket        = "/run/ms365-browse/browse.sock"
	defaultBrowseServeMaxConcurrent = 8
	defaultBrowseServeIdleEviction  = 20 * time.Minute
	defaultBrowseServeWarmTimeout   = 30 * time.Minute
)

// BrowseServeCacheSettings returns small on-disk cache limits for the WHMCS browse sidecar.
func BrowseServeCacheSettings() RepoCacheSettings {
	return RepoCacheSettings{
		RepoConfigDir:         browseRepoConfigDir(),
		ContentCacheSizeMiB:   128,
		ContentCacheLimitMiB:  512,
		MetadataCacheSizeMiB:  64,
		MetadataCacheLimitMiB: 256,
	}
}

type browseServeRequest struct {
	Op string `json:"op"`
	browseServeBrowsePayload
}

type browseServeBrowsePayload struct {
	ManifestID         string         `json:"manifest_id"`
	Path               string         `json:"path"`
	Limit              int            `json:"limit"`
	Offset             int            `json:"offset"`
	Sources            []BrowseSource `json:"sources,omitempty"`
	CandidatePaths     []string       `json:"candidate_paths,omitempty"`
	ManifestCandidates []string       `json:"manifest_candidates,omitempty"`
	AutoDescend        bool           `json:"auto_descend,omitempty"`
	DestEndpoint       string         `json:"dest_endpoint"`
	DestRegion         string         `json:"dest_region"`
	DestBucket         string         `json:"dest_bucket"`
	DestPrefix         string         `json:"dest_prefix"`
	DestAccessKey      string         `json:"dest_access_key"`
	DestSecretKey      string         `json:"dest_secret_key"`
	RepoPassword       string         `json:"repo_password"`
}

type browseServeResponse struct {
	OK      bool          `json:"ok,omitempty"`
	Error   string        `json:"error,omitempty"`
	Pong    bool          `json:"pong,omitempty"`
	Latency int64         `json:"latency_ms,omitempty"`
	Result  *BrowseResult `json:"result,omitempty"`
}

// BrowseServeServer is a long-lived Unix-socket browse daemon with a warm Kopia pool.
type BrowseServeServer struct {
	SocketPath     string
	MaxConcurrent  int
	IdleEviction   time.Duration
	pool           *Pool
	sem            chan struct{}
	lastUsed       map[string]time.Time
	warming        map[string]struct{}
	lastUsedMu     sync.Mutex
	warmingMu      sync.Mutex
	listener       net.Listener
	shutdown       chan struct{}
	shutdownOnce   sync.Once
}

func NewBrowseServeServer(socketPath string) *BrowseServeServer {
	if socketPath == "" {
		socketPath = DefaultBrowseServeSocket
	}
	return &BrowseServeServer{
		SocketPath:    socketPath,
		MaxConcurrent: defaultBrowseServeMaxConcurrent,
		IdleEviction:  defaultBrowseServeIdleEviction,
		pool:          NewPool(BrowseServeCacheSettings()),
		sem:           make(chan struct{}, defaultBrowseServeMaxConcurrent),
		lastUsed:      make(map[string]time.Time),
		warming:       make(map[string]struct{}),
		shutdown:      make(chan struct{}),
	}
}

func (s *BrowseServeServer) Run(ctx context.Context) error {
	if err := s.prepareSocket(); err != nil {
		return err
	}

	ln, err := net.Listen("unix", s.SocketPath)
	if err != nil {
		return fmt.Errorf("listen %s: %w", s.SocketPath, err)
	}
	s.listener = ln
	log.Printf("browse-serve listening socket=%s max_concurrent=%d idle_eviction=%s cache_dir=%s",
		s.SocketPath, s.MaxConcurrent, s.IdleEviction, BrowseServeCacheSettings().RepoConfigDir)

	go s.evictionLoop(ctx)

	for {
		conn, err := ln.Accept()
		if err != nil {
			select {
			case <-ctx.Done():
				return ctx.Err()
			case <-s.shutdown:
				return nil
			default:
			}
			if errors.Is(err, net.ErrClosed) {
				return nil
			}
			if strings.Contains(strings.ToLower(err.Error()), "closed") {
				return nil
			}
			log.Printf("browse-serve accept: %v", err)
			continue
		}
		go s.handleConn(ctx, conn)
	}
}

func (s *BrowseServeServer) Shutdown() {
	s.shutdownOnce.Do(func() {
		close(s.shutdown)
		if s.listener != nil {
			_ = s.listener.Close()
		}
	})
}

func (s *BrowseServeServer) Pool() *Pool {
	return s.pool
}

func (s *BrowseServeServer) prepareSocket() error {
	dir := filepath.Dir(s.SocketPath)
	if err := os.MkdirAll(dir, 0o755); err != nil {
		return fmt.Errorf("mkdir socket dir: %w", err)
	}
	_ = os.Remove(s.SocketPath)
	return nil
}

func (s *BrowseServeServer) handleConn(ctx context.Context, conn net.Conn) {
	defer conn.Close()
	reader := bufio.NewReader(conn)
	writer := bufio.NewWriter(conn)
	defer writer.Flush()

	for {
		line, err := reader.ReadBytes('\n')
		if err != nil {
			if err != io.EOF && !errors.Is(err, net.ErrClosed) {
				log.Printf("browse-serve read: %v", err)
			}
			return
		}
		line = trimLine(line)
		if len(line) == 0 {
			continue
		}

		resp := s.dispatch(ctx, line)
		out, err := json.Marshal(resp)
		if err != nil {
			out = []byte(`{"error":"encode response"}` + "\n")
		} else {
			out = append(out, '\n')
		}
		if _, err := writer.Write(out); err != nil {
			return
		}
		if err := writer.Flush(); err != nil {
			return
		}
	}
}

func trimLine(b []byte) []byte {
	for len(b) > 0 && (b[len(b)-1] == '\n' || b[len(b)-1] == '\r') {
		b = b[:len(b)-1]
	}
	return b
}

func (s *BrowseServeServer) dispatch(ctx context.Context, raw []byte) browseServeResponse {
	var req browseServeRequest
	if err := json.Unmarshal(raw, &req); err != nil {
		return browseServeResponse{Error: "invalid request json"}
	}
	if req.Op == "ping" {
		start := time.Now()
		return browseServeResponse{OK: true, Pong: true, Latency: time.Since(start).Milliseconds()}
	}
	if req.Op == "warm" {
		return s.handleWarm(req.browseServeBrowsePayload)
	}

	select {
	case s.sem <- struct{}{}:
		defer func() { <-s.sem }()
	case <-ctx.Done():
		return browseServeResponse{Error: ctx.Err().Error()}
	}

	start := time.Now()
	storage := storageFromBrowsePayload(req.browseServeBrowsePayload)
	repoWarm := s.pool.HasActiveEntry(storage)
	indexBlobs := s.pool.IndexBlobCount(storage)
	result, timing, err := s.handleBrowse(ctx, req.browseServeBrowsePayload)
	if err != nil {
		log.Printf("browse-serve browse error candidates_tried=%d repo_warm=%v index_blobs=%d err=%v",
			timing.CandidatesTried, repoWarm, indexBlobs, err)
		return browseServeResponse{Error: err.Error()}
	}
	log.Printf("browse-serve browse path=%q manifest=%s candidates_tried=%d repo_warm=%v index_blobs=%d repo_acquire_ms=%d list_ms=%d entries=%d duration_ms=%d",
		req.Path, req.ManifestID, timing.CandidatesTried, repoWarm, indexBlobs, timing.RepoAcquireMS, timing.ListMS, len(result.Entries), time.Since(start).Milliseconds())
	return browseServeResponse{OK: true, Result: result}
}

func storageFromBrowsePayload(req browseServeBrowsePayload) StorageOptions {
	return StorageOptions{
		Endpoint:     req.DestEndpoint,
		Region:       req.DestRegion,
		Bucket:       req.DestBucket,
		Prefix:       req.DestPrefix,
		AccessKey:    req.DestAccessKey,
		SecretKey:    req.DestSecretKey,
		RepoPassword: req.RepoPassword,
	}
}

func (s *BrowseServeServer) handleWarm(req browseServeBrowsePayload) browseServeResponse {
	storage := storageFromBrowsePayload(req)
	key := storage.RepoIdentity()
	s.touchRepo(storage)

	if s.pool.HasActiveEntry(storage) {
		return browseServeResponse{OK: true}
	}

	s.warmingMu.Lock()
	if _, ok := s.warming[key]; ok {
		s.warmingMu.Unlock()
		return browseServeResponse{OK: true}
	}
	s.warming[key] = struct{}{}
	s.warmingMu.Unlock()

	go func() {
		defer func() {
			s.warmingMu.Lock()
			delete(s.warming, key)
			s.warmingMu.Unlock()
		}()

		warmCtx, cancel := context.WithTimeout(context.Background(), defaultBrowseServeWarmTimeout)
		defer cancel()
		start := time.Now()
		_, release, err := s.pool.Acquire(warmCtx, storage, 64)
		if err != nil {
			log.Printf("browse-serve warm error key=%s err=%v", key, err)
			return
		}
		release()
		log.Printf("browse-serve warm complete key=%s index_blobs=%d duration_ms=%d",
			key, s.pool.IndexBlobCount(storage), time.Since(start).Milliseconds())
	}()

	return browseServeResponse{OK: true}
}

func (s *BrowseServeServer) handleBrowse(ctx context.Context, req browseServeBrowsePayload) (*BrowseResult, browseTiming, error) {
	storage := storageFromBrowsePayload(req)
	s.touchRepo(storage)

	browseReq := BrowseRequest{
		Storage:            storage,
		ManifestID:         req.ManifestID,
		Path:               req.Path,
		Limit:              req.Limit,
		Offset:             req.Offset,
		Sources:            req.Sources,
		CandidatePaths:     req.CandidatePaths,
		ManifestCandidates: req.ManifestCandidates,
		AutoDescend:        req.AutoDescend,
	}

	return browseWithAutoDescendSession(ctx, s.pool, browseReq)
}

func (s *BrowseServeServer) touchRepo(storage StorageOptions) {
	key := storage.RepoIdentity()
	s.lastUsedMu.Lock()
	s.lastUsed[key] = time.Now()
	s.lastUsedMu.Unlock()
}

func (s *BrowseServeServer) evictionLoop(ctx context.Context) {
	ticker := time.NewTicker(time.Minute)
	defer ticker.Stop()
	for {
		select {
		case <-ctx.Done():
			return
		case <-s.shutdown:
			return
		case <-ticker.C:
			s.evictIdleRepos(ctx)
			s.pool.PurgeStaleCaches(defaultStaleCacheMaxAge, defaultStaleCacheMaxTotalMiB)
		}
	}
}

func (s *BrowseServeServer) evictIdleRepos(ctx context.Context) {
	cutoff := time.Now().Add(-s.IdleEviction)
	type idleRepo struct {
		key     string
		storage StorageOptions
	}
	var idle []idleRepo

	s.lastUsedMu.Lock()
	for key, used := range s.lastUsed {
		if used.After(cutoff) {
			continue
		}
		bucket, prefix := splitRepoIdentityKey(key)
		idle = append(idle, idleRepo{key: key, storage: StorageOptions{Bucket: bucket, Prefix: prefix}})
		delete(s.lastUsed, key)
	}
	s.lastUsedMu.Unlock()

	for _, item := range idle {
		s.pool.CloseIdleRepo(ctx, item.storage)
		log.Printf("browse-serve closed idle repo key=%s", item.key)
	}
}

func splitRepoIdentityKey(key string) (bucket, prefix string) {
	if idx := strings.Index(key, ":"); idx >= 0 {
		return key[:idx], key[idx+1:]
	}
	return key, ""
}

// HandleBrowseRequest processes one browse payload (used by tests and the socket server).
func HandleBrowseRequest(ctx context.Context, pool *Pool, req browseServeBrowsePayload) (*BrowseResult, browseTiming, error) {
	srv := &BrowseServeServer{pool: pool}
	return srv.handleBrowse(ctx, req)
}

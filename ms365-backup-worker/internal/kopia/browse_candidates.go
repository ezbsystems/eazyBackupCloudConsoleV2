package kopia

import (
	"context"
	"fmt"
	"strings"

	"github.com/kopia/kopia/repo"
)

// browseTiming captures serve-side performance fields for structured logs.
type browseTiming struct {
	RepoAcquireMS  int64 `json:"repo_acquire_ms"`
	ListMS         int64 `json:"list_ms"`
	CandidatesTried int  `json:"candidates_tried"`
}

func browseManifestCandidates(req BrowseRequest) []string {
	seen := make(map[string]struct{})
	var out []string
	add := func(id string) {
		id = strings.TrimSpace(id)
		if id == "" {
			return
		}
		if _, ok := seen[id]; ok {
			return
		}
		seen[id] = struct{}{}
		out = append(out, id)
	}
	add(req.ManifestID)
	for _, id := range req.ManifestCandidates {
		add(id)
	}
	return out
}

func browsePathCandidates(req BrowseRequest) []string {
	seen := make(map[string]struct{})
	var out []string
	add := func(p string) {
		p = normalizeBrowsePath(p)
		if _, ok := seen[p]; ok {
			return
		}
		seen[p] = struct{}{}
		out = append(out, p)
	}
	add(req.Path)
	for _, p := range req.CandidatePaths {
		add(p)
	}
	return out
}

// browseWithCandidates tries manifest and path aliases until a non-empty listing is found.
func browseWithCandidates(ctx context.Context, pool *Pool, req BrowseRequest) (*BrowseResult, browseTiming, error) {
	return browseWithCandidatesAcquirer(ctx, func(ctx context.Context) (repo.Repository, func(), error) {
		return pool.Acquire(ctx, req.Storage, 64)
	}, req)
}

func browseWithCandidatesAcquirer(ctx context.Context, acquire repoAcquirer, req BrowseRequest) (*BrowseResult, browseTiming, error) {
	manifests := browseManifestCandidates(req)
	if len(manifests) == 0 {
		return nil, browseTiming{}, fmt.Errorf("manifest_id required")
	}
	paths := browsePathCandidates(req)
	if len(paths) == 0 {
		paths = []string{""}
	}

	var timing browseTiming
	var lastResult *BrowseResult
	var lastErr error
	requestedPath := normalizeBrowsePath(req.Path)

	for _, manifestID := range manifests {
		for _, path := range paths {
			timing.CandidatesTried++
			tryReq := req
			tryReq.ManifestID = manifestID
			tryReq.Path = path
			tryReq.CandidatePaths = nil
			tryReq.ManifestCandidates = nil
			tryReq.Sources = nil

			result, err := browseWithRepo(ctx, tryReq, acquire)
			if err != nil {
				lastErr = err
				continue
			}
			result.ResolvedPath = path
			lastResult = result
			if len(result.Entries) > 0 {
				return result, timing, nil
			}
		}
	}

	if lastResult != nil {
		lastResult.ResolvedPath = normalizeBrowsePath(lastResult.ResolvedPath)
		return lastResult, timing, nil
	}
	if lastErr != nil {
		return nil, timing, lastErr
	}
	return &BrowseResult{Entries: []BrowseEntry{}, ResolvedPath: requestedPath}, timing, nil
}

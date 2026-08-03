package kopia

import (
	"context"
	"regexp"
	"strings"
	"time"

	"github.com/kopia/kopia/repo"
)

var autoDescendTypedPrefixRe = regexp.MustCompile(`(?i)^[a-z]+:[0-9a-f-]+$`)
var autoDescendSiteDrivesRe = regexp.MustCompile(`/sites/[^/]+/drives/`)

// shouldAutoDescend mirrors RestoreTreeBrowseService::shouldAutoDescend in PHP.
func shouldAutoDescend(name, path string) bool {
	name = strings.TrimSpace(name)
	if name == "" {
		return false
	}
	if isGuidLikeBrowseSegment(name) {
		return true
	}
	if strings.HasPrefix(name, "user:") || strings.HasPrefix(name, "site:") || strings.HasPrefix(name, "team:") {
		return true
	}
	if path == "" && autoDescendTypedPrefixRe.MatchString(name) {
		return true
	}
	if name == "content" && (strings.Contains(path, "/drives/") || autoDescendSiteDrivesRe.MatchString(path)) {
		return true
	}
	return false
}

func isGuidLikeBrowseSegment(value string) bool {
	if value == "" {
		return false
	}
	if guidLikeRe32.MatchString(value) || guidLikeRe36.MatchString(value) {
		return true
	}
	return false
}

var (
	guidLikeRe36 = regexp.MustCompile(`(?i)^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$`)
	guidLikeRe32 = regexp.MustCompile(`(?i)^[0-9a-f]{32}$`)
)

// browseWithAutoDescend lists a directory and transparently descends single-child wrapper folders.
func browseWithAutoDescend(ctx context.Context, pool *Pool, req BrowseRequest) (*BrowseResult, browseTiming, error) {
	return browseWithAutoDescendSession(ctx, pool, req)
}

func browseWithAutoDescendSession(ctx context.Context, pool *Pool, req BrowseRequest) (*BrowseResult, browseTiming, error) {
	if len(req.Sources) > 0 {
		acquireStart := time.Now()
		result, err := pool.Browse(ctx, req)
		return result, browseTiming{
			RepoAcquireMS:   time.Since(acquireStart).Milliseconds(),
			ListMS:          time.Since(acquireStart).Milliseconds(),
			CandidatesTried: 1,
		}, err
	}

	acquireStart := time.Now()
	rep, release, err := pool.Acquire(ctx, req.Storage, 64)
	timing := browseTiming{RepoAcquireMS: time.Since(acquireStart).Milliseconds()}
	if err != nil {
		return nil, timing, err
	}
	defer release()

	acquirer := func(ctx context.Context) (repo.Repository, func(), error) {
		return rep, func() {}, nil
	}

	listStart := time.Now()
	result, timing, err := browseWithAutoDescendAcquirer(ctx, acquirer, req, timing)
	timing.ListMS = time.Since(listStart).Milliseconds()
	return result, timing, err
}

func browseWithAutoDescendAcquirer(ctx context.Context, acquire repoAcquirer, req BrowseRequest, timing browseTiming) (*BrowseResult, browseTiming, error) {
	if len(req.Sources) > 0 {
		result, err := browseWithRepo(ctx, req, acquire)
		timing.CandidatesTried = 1
		return result, timing, err
	}

	currentPath := normalizeBrowsePath(req.Path)
	tryReq := req
	tryReq.Path = currentPath

	result, timing, err := browseWithCandidatesAcquirer(ctx, acquire, tryReq)
	if err != nil {
		return nil, timing, err
	}
	if !req.AutoDescend {
		return result, timing, nil
	}

	guard := 0
	for guard < 10 && len(result.Entries) == 1 && result.Entries[0].HasChildren {
		only := result.Entries[0]
		if !shouldAutoDescend(only.Name, currentPath) {
			break
		}
		if currentPath == "" {
			currentPath = only.Name
		} else {
			currentPath = joinBrowsePath(currentPath, only.Name)
		}
		tryReq.Path = currentPath
		tryReq.CandidatePaths = nil
		tryReq.ManifestCandidates = nil

		next, nextTiming, err := browseWithCandidatesAcquirer(ctx, acquire, tryReq)
		timing.CandidatesTried += nextTiming.CandidatesTried
		if err != nil {
			return nil, timing, err
		}
		next.ResolvedPath = currentPath
		result = next
		guard++
	}

	return result, timing, nil
}

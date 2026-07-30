package kopia

import (
	"context"
	"regexp"
	"strings"
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
	if len(req.Sources) > 0 {
		result, err := pool.Browse(ctx, req)
		return result, browseTiming{CandidatesTried: 1}, err
	}

	currentPath := normalizeBrowsePath(req.Path)
	tryReq := req
	tryReq.Path = currentPath

	result, timing, err := browseWithCandidates(ctx, pool, tryReq)
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

		next, nextTiming, err := browseWithCandidates(ctx, pool, tryReq)
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

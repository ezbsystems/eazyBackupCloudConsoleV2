package kopia

import (
	"context"
	"errors"
	"fmt"
	"log"
	"os"
	"path/filepath"
	"strings"

	"github.com/kopia/kopia/repo"
	"github.com/kopia/kopia/repo/content"
	"github.com/kopia/kopia/repo/format"
)

type openRepoOptions struct {
	storage        StorageOptions
	cache          RepoCacheSettings
	maxPackSizeMiB int
}

func openRepository(ctx context.Context, opts openRepoOptions) (repo.Repository, error) {
	repoConfig := opts.storage.PersistentRepoConfigPath(opts.cache.RepoConfigDir)
	password := opts.storage.Password()
	caching := opts.cache.cachingOptions(opts.storage)

	if err := os.MkdirAll(filepath.Dir(repoConfig), 0o755); err != nil {
		return nil, err
	}
	if err := os.MkdirAll(caching.CacheDirectory, 0o755); err != nil {
		return nil, err
	}

	maxPack := opts.maxPackSizeMiB
	if maxPack <= 0 {
		maxPack = 64
	}

	initAndConnect := func() error {
		st, err := opts.storage.Storage(ctx)
		if err != nil {
			return fmt.Errorf("storage: %w", err)
		}
		initOpts := &repo.NewRepositoryOptions{
			BlockFormat: format.ContentFormat{
				MutableParameters: format.MutableParameters{
					MaxPackSize: maxPack << 20,
				},
			},
		}
		if err := repo.Initialize(ctx, st, initOpts, password); err != nil && !errors.Is(err, repo.ErrAlreadyInitialized) {
			return fmt.Errorf("initialize: %w", err)
		}
		if err := repo.Connect(ctx, repoConfig, st, password, connectOptions(caching)); err != nil && !errors.Is(err, repo.ErrAlreadyInitialized) {
			return fmt.Errorf("connect: %w", err)
		}
		return nil
	}

	if _, err := os.Stat(repoConfig); err != nil {
		if os.IsNotExist(err) {
			if err := initAndConnect(); err != nil {
				return nil, err
			}
		} else {
			return nil, fmt.Errorf("stat repo config: %w", err)
		}
	}

	// Kopia v0.23.1 exposes SetCachingOptions as a package-level function that
	// rewrites the on-disk repo config (contentCacheSizeLimitBytes /
	// metadataCacheSizeLimitBytes); there is no Repository method for this. It
	// must run against an existing config file and before Open, so the
	// PersistentCache constructed by Open picks up the hard limits. Without
	// this, LimitBytes stays 0 (unlimited) and Kopia's content cache grows
	// unbounded under concurrent uploads (prod: ~53GiB before hard drain).
	if err := reconcileCacheSettings(ctx, repoConfig, caching); err != nil {
		return nil, fmt.Errorf("set caching options: %w", err)
	}

	rep, err := repo.Open(ctx, repoConfig, password, nil)
	if err != nil {
		if errors.Is(err, repo.ErrRepositoryNotInitialized) || strings.Contains(strings.ToLower(err.Error()), "not initialized") {
			if err := initAndConnect(); err != nil {
				return nil, err
			}
			if err := reconcileCacheSettings(ctx, repoConfig, caching); err != nil {
				return nil, fmt.Errorf("set caching options: %w", err)
			}
			rep, err = repo.Open(ctx, repoConfig, password, nil)
		}
		if err != nil {
			return nil, fmt.Errorf("open repo: %w", err)
		}
	}
	logCacheSettingsApplied(repoConfig, caching)
	return rep, nil
}

// reconcileCacheSettings persists the desired cache soft/hard limits into the
// repo's on-disk config file. Must be called with an existing config (after
// initAndConnect has run at least once) and before repo.Open.
func reconcileCacheSettings(ctx context.Context, repoConfig string, caching *content.CachingOptions) error {
	if caching == nil {
		return nil
	}
	return repo.SetCachingOptions(ctx, repoConfig, caching)
}

// logCacheSettingsApplied reports the applied content/metadata soft+hard MiB
// limits once per repo open for ops visibility (e.g. confirming a rollout
// actually caps the on-disk cache instead of silently no-op'ing).
func logCacheSettingsApplied(repoConfig string, caching *content.CachingOptions) {
	if caching == nil {
		return
	}
	log.Printf("kopia cache limits applied config=%s: content soft=%dMiB hard=%dMiB metadata soft=%dMiB hard=%dMiB",
		filepath.Base(repoConfig),
		caching.ContentCacheSizeBytes>>20, caching.ContentCacheSizeLimitBytes>>20,
		caching.MetadataCacheSizeBytes>>20, caching.MetadataCacheSizeLimitBytes>>20)
}

func connectOptions(caching *content.CachingOptions) *repo.ConnectOptions {
	if caching == nil {
		return &repo.ConnectOptions{}
	}
	return &repo.ConnectOptions{CachingOptions: *caching}
}

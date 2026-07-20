package kopia

import (
	"context"
	"errors"
	"fmt"
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

	rep, err := repo.Open(ctx, repoConfig, password, nil)
	if err != nil {
		if errors.Is(err, repo.ErrRepositoryNotInitialized) || strings.Contains(strings.ToLower(err.Error()), "not initialized") {
			if err := initAndConnect(); err != nil {
				return nil, err
			}
			rep, err = repo.Open(ctx, repoConfig, password, nil)
		}
		if err != nil {
			return nil, fmt.Errorf("open repo: %w", err)
		}
	}
	if err := reconcileCacheSettings(ctx, rep, caching); err != nil {
		_ = rep.Close(ctx)
		return nil, err
	}
	return rep, nil
}

func reconcileCacheSettings(ctx context.Context, rep repo.Repository, caching *content.CachingOptions) error {
	if rep == nil || caching == nil {
		return nil
	}
	bm, ok := rep.(interface {
		SetCachingOptions(ctx context.Context, opts *content.CachingOptions) error
	})
	if !ok {
		return nil
	}
	return bm.SetCachingOptions(ctx, caching)
}

func connectOptions(caching *content.CachingOptions) *repo.ConnectOptions {
	if caching == nil {
		return &repo.ConnectOptions{}
	}
	return &repo.ConnectOptions{CachingOptions: *caching}
}

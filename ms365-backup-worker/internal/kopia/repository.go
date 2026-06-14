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
)

func openOrInitRepo(ctx context.Context, storage StorageOptions, repoConfig string, password string) (repo.Repository, error) {
	if err := os.MkdirAll(filepath.Dir(repoConfig), 0o755); err != nil {
		return nil, err
	}

	initAndConnect := func() error {
		st, err := storage.Storage(ctx)
		if err != nil {
			return fmt.Errorf("storage: %w", err)
		}
		initOpts := &repo.NewRepositoryOptions{
			BlockFormat: content.FormattingOptions{
				MutableParameters: content.MutableParameters{
					MaxPackSize: 64 << 20,
				},
			},
		}
		if err := repo.Initialize(ctx, st, initOpts, password); err != nil && !errors.Is(err, repo.ErrAlreadyInitialized) {
			return fmt.Errorf("initialize: %w", err)
		}
		if err := repo.Connect(ctx, repoConfig, st, password, nil); err != nil && !errors.Is(err, repo.ErrAlreadyInitialized) {
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
			return nil, err
		}
	}
	return rep, nil
}

package jobs

import (
	"context"
	"strings"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/config"
	"github.com/eazybackup/ms365-backup-worker/internal/graph"
)

func bindGraphTokenRefresh(ctx context.Context, cfg *config.Config, apiClient *api.Client, gc *graph.Client, runID string) func() {
	runID = strings.TrimSpace(runID)
	if runID == "" || apiClient == nil || gc == nil {
		return func() {}
	}

	gc.SetTokenRefresh(func(refreshCtx context.Context) (string, error) {
		return apiClient.RefreshGraphToken(refreshCtx, runID)
	})

	interval := cfg.GraphTokenRefreshInterval()
	stop := make(chan struct{})
	go func() {
		ticker := time.NewTicker(interval)
		defer ticker.Stop()
		for {
			select {
			case <-ctx.Done():
				return
			case <-stop:
				return
			case <-ticker.C:
				token, err := apiClient.RefreshGraphToken(ctx, runID)
				if err == nil && strings.TrimSpace(token) != "" {
					gc.SetToken(token)
				}
			}
		}
	}()
	return func() { close(stop) }
}

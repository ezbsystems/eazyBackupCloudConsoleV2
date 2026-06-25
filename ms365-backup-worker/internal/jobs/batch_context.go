package jobs

import (
	"context"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/graph"
)

type batchRunContext struct {
	sharedGC     *graph.Client
	progressSink func(api.ProgressUpdate)
	completeSink func(api.CompleteUpdate) error
	failSink     func(string, string)
}

type batchRunContextKey struct{}

func withBatchRunContext(ctx context.Context, brc *batchRunContext) context.Context {
	if brc == nil {
		return ctx
	}
	return context.WithValue(ctx, batchRunContextKey{}, brc)
}

func batchRunContextFrom(ctx context.Context) *batchRunContext {
	if ctx == nil {
		return nil
	}
	brc, _ := ctx.Value(batchRunContextKey{}).(*batchRunContext)
	return brc
}

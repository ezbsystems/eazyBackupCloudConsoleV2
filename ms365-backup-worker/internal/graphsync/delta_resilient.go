package graphsync

import (
	"context"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
)

// paginateDeltaResilient runs delta sync; on token reset (410) retries once with a full baseline.
func paginateDeltaResilient(
	ctx context.Context,
	client *graph.Client,
	path, priorDelta, selectFields string,
	top int,
	onPage func(int),
	opts *graph.DeltaPaginateOptions,
) ([]map[string]any, string, error) {
	items, deltaLink, err := client.PaginateDeltaOpts(ctx, path, priorDelta, selectFields, top, onPage, opts)
	if err != nil && graph.IsDeltaResetError(err) {
		if opts != nil && opts.Monitor != nil {
			opts.Monitor.Log("warning", "Graph delta token reset; re-baselining")
		}
		return client.PaginateDeltaOpts(ctx, path, "", selectFields, top, onPage, opts)
	}
	return items, deltaLink, err
}

//go:build !windows

package agent

import "context"

func (r *Runner) captureAndUploadDriverBundles(ctx context.Context, run *NextRunResponse, runID int64, finishedAt string) map[string]any {
	return nil
}

func marshalDriverBundlesForStats(data map[string]any) map[string]any {
	return nil
}

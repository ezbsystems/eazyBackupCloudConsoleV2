package graphsync

import (
	"strings"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/graph"
)

func paginationMonitorForJob(job *api.RunJob, workload, context string, log graph.PageLogFunc) *graph.PaginationMonitor {
	monitor := graph.ForBackupPagination(context, log)
	if job == nil || len(job.GraphPagination) == 0 {
		return monitor
	}
	lim, ok := job.GraphPagination[workload]
	if !ok {
		lim, ok = job.GraphPagination["default"]
	}
	if !ok {
		return monitor
	}
	if lim.MaxPages > 0 {
		monitor.MaxPages = lim.MaxPages
	}
	if strings.EqualFold(strings.TrimSpace(lim.OnCap), "warn_continue") {
		monitor.CapMode = graph.CapWarnContinue
	}
	return monitor
}

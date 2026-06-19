package graphsync

import "github.com/eazybackup/ms365-backup-worker/internal/api"

// JobIncludesOneDrive reports whether the backup job selected OneDrive.
func JobIncludesOneDrive(job *api.RunJob) bool {
	if job == nil {
		return false
	}
	if job.Workloads != nil {
		if v, ok := job.Workloads["onedrive"]; ok {
			return v
		}
	}
	if job.Scope != nil {
		return job.Scope["onedrive"] || job.Scope["files"]
	}
	return false
}

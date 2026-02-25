package agent

import "strings"

// RepoOperation represents a queued repo operation (retention, maintenance) from the server.
type RepoOperation struct {
	OpType          string            `json:"op_type"`
	RepoID          int               `json:"repo_id"`
	OperationID     int64             `json:"operation_id"`
	OperationToken  string            `json:"operation_token"`
	RepositoryID    string            `json:"repository_id"`
	BucketID        int               `json:"bucket_id"`
	BucketName      string            `json:"bucket_name"`
	RootPrefix      string            `json:"root_prefix"`
	Endpoint        string            `json:"endpoint"`
	Region          string            `json:"region"`
	DestAccessKey   string            `json:"dest_access_key,omitempty"`
	DestSecretKey   string            `json:"dest_secret_key,omitempty"`
	EffectivePolicy map[string]any    `json:"effective_policy"`
}

// isRepoRetentionType returns true if the operation type is one we can execute:
// kopia_retention_apply, kopia_maintenance_quick, kopia_maintenance_full,
// or their internal short forms (retention_apply, maintenance_quick, maintenance_full).
func (op *RepoOperation) isRepoRetentionType() bool {
	if op == nil {
		return false
	}
	t := strings.TrimSpace(strings.ToLower(op.OpType))
	switch t {
	case "kopia_retention_apply", "retention_apply",
		"kopia_maintenance_quick", "maintenance_quick",
		"kopia_maintenance_full", "maintenance_full":
		return true
	}
	return false
}

package agent

// DiskInfo is a lightweight disk description for UI selection.
type DiskInfo struct {
	Path            string                `json:"path"`
	Name            string                `json:"name,omitempty"`
	Model           string                `json:"model,omitempty"`
	Serial          string                `json:"serial,omitempty"`
	BusType         string                `json:"bus_type,omitempty"`
	PartitionStyle  string                `json:"partition_style,omitempty"`
	SizeBytes       uint64                `json:"size_bytes,omitempty"`
	Partitions      []DiskPartitionSummary `json:"partitions,omitempty"`
}

// DiskPartitionSummary is a lightweight partition entry for UI.
type DiskPartitionSummary struct {
	Name       string `json:"name,omitempty"`
	Path       string `json:"path,omitempty"`
	StartBytes int64  `json:"start_bytes,omitempty"`
	SizeBytes  int64  `json:"size_bytes,omitempty"`
	FileSystem string `json:"filesystem,omitempty"`
	Label      string `json:"label,omitempty"`
	PartType   string `json:"part_type,omitempty"`
	IsEFI      bool   `json:"is_efi,omitempty"`
	IsSystem   bool   `json:"is_system,omitempty"`
	IsRecovery bool   `json:"is_recovery,omitempty"`
	Mountpoint string `json:"mountpoint,omitempty"`
}

// ListDisks returns physical disks for disk image selection.
func ListDisks() ([]DiskInfo, error) {
	return enumerateDisks()
}

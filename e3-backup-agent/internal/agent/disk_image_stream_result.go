package agent

// CBTState tracks per-volume change-tracking state for disk image backups.
type CBTState struct {
	Path           string `json:"-"`
	JobID          int64  `json:"-"`
	Volume         string `json:"volume"`
	VolumeSerial   uint32 `json:"volume_serial"`
	JournalID      uint64 `json:"journal_id"`
	LastUSN        int64  `json:"last_usn"`
	LastManifestID string `json:"last_manifest_id,omitempty"`
	UpdatedAt      string `json:"updated_at,omitempty"`
}

// CBTStats captures change-tracking metrics for reporting/debugging.
type CBTStats struct {
	Mode           string `json:"mode"`
	Reason         string `json:"reason,omitempty"`
	ChangedFiles   int    `json:"changed_files,omitempty"`
	ChangedExtents int    `json:"changed_extents,omitempty"`
	ChangedBytes   int64  `json:"changed_bytes,omitempty"`
	MetaExtents    int    `json:"meta_extents,omitempty"`
	MetaBytes      int64  `json:"meta_bytes,omitempty"`
	ReadRanges     int    `json:"read_ranges,omitempty"`
	ReadBytes      int64  `json:"read_bytes,omitempty"`
	JournalID      uint64 `json:"journal_id,omitempty"`
	LastUSN        int64  `json:"last_usn,omitempty"`
	NextUSN        int64  `json:"next_usn,omitempty"`
}

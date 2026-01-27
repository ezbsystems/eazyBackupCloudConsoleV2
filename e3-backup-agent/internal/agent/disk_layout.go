package agent

import (
	"errors"
	"strings"
	"time"
)

// DiskExtent represents a contiguous range of used bytes.
type DiskExtent struct {
	OffsetBytes int64 `json:"offset_bytes"`
	LengthBytes int64 `json:"length_bytes"`
}

// DiskPartition captures metadata needed for bare-metal restore.
type DiskPartition struct {
	Index        int         `json:"index"`
	Name         string      `json:"name,omitempty"`
	Path         string      `json:"path,omitempty"`
	StartBytes   int64       `json:"start_bytes"`
	SizeBytes    int64       `json:"size_bytes"`
	UsedBytes    int64       `json:"used_bytes,omitempty"`
	FileSystem   string      `json:"filesystem,omitempty"`
	Label        string      `json:"label,omitempty"`
	Type         string      `json:"type,omitempty"`
	PartType     string      `json:"part_type,omitempty"`
	PartUUID     string      `json:"part_uuid,omitempty"`
	Mountpoint   string      `json:"mountpoint,omitempty"`
	IsBoot       bool        `json:"is_boot,omitempty"`
	IsSystem     bool        `json:"is_system,omitempty"`
	IsEFI        bool        `json:"is_efi,omitempty"`
	IsRecovery   bool        `json:"is_recovery,omitempty"`
	UsedExtents  []DiskExtent `json:"used_extents,omitempty"`
	ClusterBytes int64       `json:"cluster_bytes,omitempty"`
}

// DiskLayout describes a whole disk for recovery.
type DiskLayout struct {
	DiskPath        string         `json:"disk_path,omitempty"`
	DiskID          string         `json:"disk_id,omitempty"`
	DiskNumber      int            `json:"disk_number,omitempty"`
	Model           string         `json:"model,omitempty"`
	Serial          string         `json:"serial,omitempty"`
	BusType         string         `json:"bus_type,omitempty"`
	PartitionStyle  string         `json:"partition_style,omitempty"` // gpt|mbr|unknown
	BootMode        string         `json:"boot_mode,omitempty"`        // uefi|bios|unknown
	TotalBytes      int64          `json:"total_bytes,omitempty"`
	UsedBytes       int64          `json:"used_bytes,omitempty"`
	Partitions      []DiskPartition `json:"partitions,omitempty"`
	GeneratedAt     string         `json:"generated_at,omitempty"`
	BlockMapSource  string         `json:"block_map_source,omitempty"`
}

// CollectDiskLayout gathers a disk layout for the specified source volume or disk path.
func CollectDiskLayout(source string) (*DiskLayout, error) {
	layout, err := collectDiskLayout(source)
	if err != nil {
		return nil, err
	}
	if layout == nil {
		return nil, errors.New("disk layout unavailable")
	}
	layout.GeneratedAt = time.Now().UTC().Format(time.RFC3339)
	if layout.UsedBytes == 0 {
		var totalUsed int64
		for _, p := range layout.Partitions {
			if p.UsedBytes > 0 {
				totalUsed += p.UsedBytes
			}
		}
		layout.UsedBytes = totalUsed
	}
	return layout, nil
}

func normalizePartitionStyle(style string) string {
	switch strings.ToLower(strings.TrimSpace(style)) {
	case "gpt":
		return "gpt"
	case "mbr":
		return "mbr"
	case "dos":
		return "mbr"
	default:
		return "unknown"
	}
}

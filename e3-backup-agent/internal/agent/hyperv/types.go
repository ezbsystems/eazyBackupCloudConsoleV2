// Package hyperv provides Hyper-V management operations for VM backup.
package hyperv

import (
	"time"
)

// VMInfo represents a Hyper-V virtual machine.
type VMInfo struct {
	ID                string     `json:"id"`                 // Hyper-V GUID
	Name              string     `json:"name"`
	State             string     `json:"state"`              // Running, Off, Saved, Paused
	Generation        int        `json:"generation"`         // 1 or 2
	CPUCount          int        `json:"cpu_count"`
	MemoryMB          int64      `json:"memory_mb"`
	IntegrationSvcs   bool       `json:"integration_services"`
	Disks             []DiskInfo `json:"disks"`
	IsLinux           bool       `json:"is_linux"`           // Detected via integration services
	RCTEnabled        bool       `json:"rct_enabled"`
	CheckpointFileDir string     `json:"checkpoint_file_dir"`
}

// DiskInfo represents a VM's virtual hard disk.
type DiskInfo struct {
	ControllerType   string `json:"controller_type"`   // SCSI, IDE
	ControllerNumber int    `json:"controller_number"`
	ControllerLoc    int    `json:"controller_location"`
	Path             string `json:"path"`              // Full path to VHDX
	SizeBytes        int64  `json:"size_bytes"`        // Virtual size
	UsedBytes        int64  `json:"used_bytes"`        // Actual data size (file size)
	VHDFormat        string `json:"vhd_format"`        // VHDX, VHD
	RCTEnabled       bool   `json:"rct_enabled"`
	RCTID            string `json:"rct_id"`            // Current RCT tracking ID
}

// CheckpointInfo represents a VM checkpoint/snapshot.
type CheckpointInfo struct {
	ID           string    `json:"id"`
	Name         string    `json:"name"`
	VMName       string    `json:"vm_name"`
	CreationTime time.Time `json:"creation_time"`
	ParentID     string    `json:"parent_id"`
	IsReference  bool      `json:"is_reference"`       // Reference point for RCT
	SnapshotType string    `json:"snapshot_type"`      // Production, Standard
}

// ChangedBlockRange represents a range of changed bytes in a VHDX.
type ChangedBlockRange struct {
	Offset int64 `json:"offset"` // Byte offset from start of disk
	Length int64 `json:"length"` // Number of changed bytes
}

// RCTInfo contains RCT metadata for a disk.
type RCTInfo struct {
	DiskPath      string              `json:"disk_path"`
	RCTID         string              `json:"rct_id"`           // Current RCT tracking ID
	ChangedBlocks []ChangedBlockRange `json:"changed_blocks"`
	TotalChanged  int64               `json:"total_changed"`    // Sum of all changed bytes
	Valid         bool                `json:"valid"`            // Whether RCT data is valid
}

// BackupType indicates whether a backup is full or incremental.
type BackupType string

const (
	BackupTypeFull        BackupType = "Full"
	BackupTypeIncremental BackupType = "Incremental"
)

// ConsistencyLevel indicates the type of consistency for the backup.
type ConsistencyLevel string

const (
	ConsistencyApplication      ConsistencyLevel = "Application"
	ConsistencyCrash            ConsistencyLevel = "Crash"
	ConsistencyCrashNoCheckpoint ConsistencyLevel = "CrashNoCheckpoint" // Live VM backup without checkpoint
)

// VMBackupResult contains the result of backing up a single VM.
type VMBackupResult struct {
	VMID             int64             `json:"vm_id"`
	VMName           string            `json:"vm_name"`
	BackupType       BackupType        `json:"backup_type"`
	CheckpointID     string            `json:"checkpoint_id"`
	RCTIDs           map[string]string `json:"rct_ids"`           // disk path -> RCT ID
	DiskManifests    map[string]string `json:"disk_manifests"`    // disk path -> Kopia manifest
	TotalBytes       int64             `json:"total_bytes"`
	ChangedBytes     int64             `json:"changed_bytes"`
	ConsistencyLevel ConsistencyLevel  `json:"consistency_level"`
	DurationSeconds  int               `json:"duration_seconds"`
	Error            string            `json:"error,omitempty"`
}

// VMState represents the state of a Hyper-V VM.
type VMState string

const (
	VMStateRunning         VMState = "Running"
	VMStateOff             VMState = "Off"
	VMStateSaved           VMState = "Saved"
	VMStatePaused          VMState = "Paused"
	VMStateSaving          VMState = "Saving"
	VMStateStarting        VMState = "Starting"
	VMStateStopping        VMState = "Stopping"
	VMStateCheckpointing   VMState = "Checkpointing"
	VMStateResetting       VMState = "Resetting"
	VMStateUnknown         VMState = "Unknown"
)

// IsVMRunning returns true if the VM is in a running state.
func (s VMState) IsRunning() bool {
	return s == VMStateRunning
}

// CanBackup returns true if the VM is in a state that allows backup.
func (s VMState) CanBackup() bool {
	switch s {
	case VMStateRunning, VMStateOff, VMStateSaved, VMStatePaused:
		return true
	default:
		return false
	}
}


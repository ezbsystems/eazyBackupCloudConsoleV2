# Hyper-V Backup Engine – Technical Design Document

**Version:** 1.1  
**Last Updated:** December 2025  
**Status:** Planning/Design Phase

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Agent Integration & Architecture](#agent-integration--architecture)
3. [Requirements](#requirements)
4. [Architecture Overview](#architecture-overview)
5. [Core Components](#core-components)
6. [RCT (Resilient Change Tracking)](#rct-resilient-change-tracking)
7. [VSS Integration](#vss-integration)
8. [Linux VM Support](#linux-vm-support)
9. [Instant Restore & Mount](#instant-restore--mount)
10. [Database Schema](#database-schema)
11. [API Endpoints](#api-endpoints)
12. [Agent Implementation](#agent-implementation)
13. [Development Roadmap](#development-roadmap)
14. [Security Considerations](#security-considerations)

---

## Executive Summary

This document outlines the design for a Hyper-V virtual machine backup engine integrated with the EazyBackup agent. The solution leverages:

- **Resilient Change Tracking (RCT)** for efficient incremental backups
- **VSS (Volume Shadow Copy Service)** for application-consistent snapshots
- **Kopia** for deduplication, encryption, and storage management
- **Direct VHDX streaming** for instant restore capabilities

### Key Goals

1. **Efficiency**: Use CBT/RCT to back up only changed blocks, reducing backup windows and storage
2. **Consistency**: Application-consistent backups via VSS for Windows VMs, fsfreeze for Linux VMs
3. **Speed**: Instant restore capability by mounting backups directly from Kopia repository
4. **Scale**: Handle VMs from 100GB to several TB efficiently
5. **Unified Agent**: Single executable running as one Windows Service supporting ALL features simultaneously

---

## Agent Integration & Architecture

### Design Principles

The Hyper-V engine integrates with the existing e3-backup-agent following these principles:

1. **Single Agent, Single Service** – One executable running as a Windows Service (SYSTEM account)
2. **All Features Simultaneously** – Hyper-V backup, Cloud NAS, file backup, and disk image backup run concurrently
3. **Consistent Patterns** – Follow existing `runner.go` and `kopia.go` patterns using methods on `Runner` struct
4. **No Unnecessary Abstractions** – Avoid creating interfaces unless required; use direct method calls

### How Hyper-V Fits with Existing Engines

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                     EAZYBACKUP AGENT – ENGINE ARCHITECTURE                  │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  runner.go: runRun()                                                        │
│  ─────────────────────────────────────────────────────────────────────────  │
│                                                                             │
│  switch engine {                                                            │
│  case "kopia":       return r.runKopia(run)       // File/folder backup     │
│  case "disk_image":  return r.runDiskImage(run)   // Whole disk backup      │
│  case "hyperv":      return r.runHyperV(run)      // NEW: VM backup         │
│  default:            return r.runSync(run)        // Legacy rclone sync     │
│  }                                                                          │
│                                                                             │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐              │
│  │   runKopia()    │  │  runDiskImage() │  │   runHyperV()   │ ◄── NEW     │
│  │                 │  │                 │  │                 │              │
│  │ kopiaSnapshot() │  │ vss.Create()    │  │ hyperv.Backup() │              │
│  │ kopiaRestore()  │  │ streamToKopia() │  │ hyperv.Restore()│              │
│  └────────┬────────┘  └────────┬────────┘  └────────┬────────┘              │
│           │                    │                    │                       │
│           └────────────────────┴────────────────────┘                       │
│                                │                                            │
│                                ▼                                            │
│                    ┌───────────────────────┐                                │
│                    │   Kopia Repository    │                                │
│                    │   (S3 Storage)        │                                │
│                    └───────────────────────┘                                │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Windows Service Mode & Feature Compatibility

**Critical Requirement:** The agent MUST run as a single Windows Service under the SYSTEM account to support all features:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│            SINGLE AGENT – WINDOWS SERVICE – ALL FEATURES                    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  e3-backup-agent.exe (Windows Service, SYSTEM account)                      │
│  ═══════════════════════════════════════════════════                        │
│                                                                             │
│  Session 0 (Service - SYSTEM)               Session 1+ (User)               │
│  ────────────────────────────               ──────────────────              │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │                           AGENT SERVICE                             │    │
│  │                                                                     │    │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌───────────┐  │    │
│  │  │  Hyper-V    │  │ Disk Image  │  │   Kopia     │  │  Cloud    │  │    │
│  │  │  Backup     │  │   Backup    │  │  Backup     │  │   NAS     │  │    │
│  │  │             │  │             │  │             │  │           │  │    │
│  │  │ • RCT Query │  │ • VSS       │  │ • File scan │  │ • WebDAV  │  │    │
│  │  │ • VSS       │  │ • Stream    │  │ • Upload    │  │ • VFS     │  │    │
│  │  │ • Checkpoint│  │ • Upload    │  │ • Dedup     │  │ • S3      │  │    │
│  │  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘  └─────┬─────┘  │    │
│  │         │                │                │               │        │    │
│  │         │  SYSTEM has Hyper-V permissions ✓               │        │    │
│  │         │  SYSTEM has VSS access ✓                        │        │    │
│  │         │  SYSTEM has file system access ✓                │        │    │
│  │         │                                                 │        │    │
│  │         │                                                 ▼        │    │
│  │         │                            ┌──────────────────────────┐  │    │
│  │         │                            │ WebDAV Server            │  │    │
│  │         │                            │ (http://127.0.0.1:PORT/) │  │    │
│  │         │                            └────────────┬─────────────┘  │    │
│  │         │                                         │                │    │
│  └─────────┼─────────────────────────────────────────┼────────────────┘    │
│            │                                         │                      │
│            │                                         │ Scheduled Task       │
│            │                                         │ (mapDriveInUserSession)
│            │                                         ▼                      │
│            │                            ┌────────────────────────────┐      │
│            │                            │ User Session               │      │
│            │                            │                            │      │
│            │                            │  Drive Y: ──────▶ WebDAV   │      │
│            │                            │  (Visible in Explorer)     │      │
│            │                            └────────────────────────────┘      │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Why SYSTEM Account Works for All Features

| Feature | Required Permissions | SYSTEM Has? | Notes |
|---------|---------------------|-------------|-------|
| **Hyper-V Backup** | Hyper-V Administrators | ✅ Yes | SYSTEM is implicitly in this group |
| **VSS Snapshots** | Backup Operators | ✅ Yes | SYSTEM has full VSS access |
| **File Backup** | Read file system | ✅ Yes | Full system access |
| **Disk Image** | Raw disk access | ✅ Yes | Administrator privileges |
| **Cloud NAS WebDAV** | Network listener | ✅ Yes | Can bind to localhost |
| **Cloud NAS Drive Map** | User session | 🔄 Via Task | Uses scheduled task to map in user session |

### Cloud NAS Session Isolation Solution

When running as a Windows Service, mapped network drives are not visible in Explorer due to Windows session isolation. The solution uses a scheduled task to create the drive mapping in the user's interactive session.

**Implementation in `nas.go`:**

```go
// nas.go: mountNASDrive() modification
func (r *Runner) mountNASDrive(ctx context.Context, payload MountNASPayload) error {
    // ... existing WebDAV server setup ...
    
    httpTarget := fmt.Sprintf("http://127.0.0.1:%d/", port)
    
    // Phase 1: Try to map in user session (for service mode)
    if err := r.mapDriveInUserSession(ctx, driveLetter, httpTarget); err != nil {
        log.Printf("agent: user session mapping failed (may be running interactively): %v", err)
        // Fall through to direct mapping as fallback
    }
    
    // Phase 2: Direct mapping (fallback for interactive mode or if no user logged in)
    // ... existing PowerShell mapping code ...
}
```

The `mapDriveInUserSession()` function already exists in `nas.go` (lines 83-194) and:
1. Detects the logged-in user via WMI or explorer.exe process owner
2. Creates a temporary scheduled task running as that user
3. Executes `net use` in the user's interactive session
4. Cleans up the temporary task

**Requirement:** At least one user must be logged in for Cloud NAS drive letters to be visible in Explorer. The WebDAV server runs regardless.

---

## Requirements

### Supported Platforms

| Requirement | Specification |
|-------------|---------------|
| Hyper-V Host OS | Windows Server 2016, 2019, 2022, Windows 10/11 Pro/Enterprise |
| Guest VM Types | Generation 2 VMs (required for RCT) |
| Guest OS | Windows Server 2012+, Windows 10+, Linux (with integration services) |
| VM Disk Format | VHDX (VHD not supported for RCT) |

### Functional Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-01 | Full VM backup (all disks + configuration) | Must Have |
| FR-02 | Incremental backup using RCT | Must Have |
| FR-03 | Application-consistent backups (VSS) | Must Have |
| FR-04 | Linux VM backup with fsfreeze | Must Have |
| FR-05 | Instant VM restore/mount | Must Have |
| FR-06 | Granular file restore from VHDX | Should Have |
| FR-07 | Cross-host restore | Should Have |
| FR-08 | Backup verification/integrity check | Should Have |
| FR-09 | Bandwidth throttling | Must Have |
| FR-10 | Encryption at rest and in transit | Must Have |

---

## Architecture Overview

### High-Level Data Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              HYPER-V HOST                                   │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐              │
│  │   Windows VM    │  │   Windows VM    │  │    Linux VM     │              │
│  │  (SQL Server)   │  │  (File Server)  │  │   (Web App)     │              │
│  │                 │  │                 │  │                 │              │
│  │ VSS Writer ────▶│  │ VSS Writer ────▶│  │ LIS + fsfreeze  │              │
│  └────────┬────────┘  └────────┬────────┘  └────────┬────────┘              │
│           │                    │                    │                       │
│           ▼                    ▼                    ▼                       │
│  ┌──────────────────────────────────────────────────────────────────┐       │
│  │                     HYPER-V VSS WRITER                           │       │
│  │   • Coordinates guest VSS writers                                │       │
│  │   • Creates application-consistent checkpoints                   │       │
│  │   • Triggers RCT reference points                                │       │
│  └──────────────────────────────────────────────────────────────────┘       │
│                                    │                                        │
│                                    ▼                                        │
│  ┌──────────────────────────────────────────────────────────────────┐       │
│  │                     EAZYBACKUP AGENT                             │       │
│  │                                                                  │       │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │       │
│  │  │  Hyper-V    │  │    RCT      │  │   VHDX      │              │       │
│  │  │  Manager    │  │   Engine    │  │   Reader    │              │       │
│  │  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘              │       │
│  │         │                │                │                      │       │
│  │         └────────────────┼────────────────┘                      │       │
│  │                          ▼                                       │       │
│  │              ┌───────────────────────┐                           │       │
│  │              │     Kopia Engine      │                           │       │
│  │              │  • Deduplication      │                           │       │
│  │              │  • Compression        │                           │       │
│  │              │  • Encryption         │                           │       │
│  │              └───────────┬───────────┘                           │       │
│  │                          │                                       │       │
│  └──────────────────────────┼───────────────────────────────────────┘       │
│                             │                                               │
└─────────────────────────────┼───────────────────────────────────────────────┘
                              │
                              ▼
              ┌───────────────────────────────┐
              │        S3 STORAGE             │
              │   (Wasabi/AWS/MinIO/etc)      │
              │                               │
              │  ┌─────────────────────────┐  │
              │  │   Kopia Repository      │  │
              │  │   • Content-addressed   │  │
              │  │   • Deduplicated        │  │
              │  │   • Encrypted           │  │
              │  └─────────────────────────┘  │
              └───────────────────────────────┘
```

### Component Interaction Sequence

```
┌──────┐     ┌─────────┐     ┌──────────┐     ┌─────────┐     ┌───────┐
│WHMCS │     │  Agent  │     │ Hyper-V  │     │   RCT   │     │ Kopia │
└──┬───┘     └────┬────┘     └────┬─────┘     └────┬────┘     └───┬───┘
   │              │               │                │              │
   │ Start Run    │               │                │              │
   │─────────────▶│               │                │              │
   │              │               │                │              │
   │              │ Get VM Config │                │              │
   │              │──────────────▶│                │              │
   │              │               │                │              │
   │              │ Create VSS    │                │              │
   │              │ Checkpoint    │                │              │
   │              │──────────────▶│                │              │
   │              │               │                │              │
   │              │   Checkpoint  │                │              │
   │              │   Created     │                │              │
   │              │◀──────────────│                │              │
   │              │               │                │              │
   │              │ Query Changed Blocks           │              │
   │              │────────────────────────────────▶              │
   │              │               │                │              │
   │              │               │   Block Ranges │              │
   │              │◀────────────────────────────────              │
   │              │               │                │              │
   │              │ Stream Changed Blocks to Kopia │              │
   │              │────────────────────────────────────────────────▶
   │              │               │                │              │
   │              │               │                │    Manifest  │
   │              │◀────────────────────────────────────────────────
   │              │               │                │              │
   │              │ Merge/Remove  │                │              │
   │              │ Checkpoint    │                │              │
   │              │──────────────▶│                │              │
   │              │               │                │              │
   │ Run Complete │               │                │              │
   │◀─────────────│               │                │              │
   │              │               │                │              │
```

---

## Core Components

### 1. Hyper-V Manager (`internal/agent/hyperv/manager.go`)

Responsible for all Hyper-V interactions via WMI/PowerShell:

```go
package hyperv

import (
    "context"
    "encoding/json"
    "fmt"
    "os/exec"
    "strings"
    "time"
)

// VMInfo represents a Hyper-V virtual machine
type VMInfo struct {
    ID              string     `json:"id"`               // Hyper-V GUID
    Name            string     `json:"name"`
    State           string     `json:"state"`            // Running, Off, Saved, Paused
    Generation      int        `json:"generation"`       // 1 or 2
    CPUCount        int        `json:"cpu_count"`
    MemoryMB        int64      `json:"memory_mb"`
    IntegrationSvcs bool       `json:"integration_services"`
    Disks           []DiskInfo `json:"disks"`
    IsLinux         bool       `json:"is_linux"`         // Detected via integration services
    RCTEnabled      bool       `json:"rct_enabled"`
}

// DiskInfo represents a VM's virtual hard disk
type DiskInfo struct {
    ControllerType   string `json:"controller_type"`   // SCSI, IDE
    ControllerNumber int    `json:"controller_number"`
    ControllerLoc    int    `json:"controller_location"`
    Path             string `json:"path"`              // Full path to VHDX
    SizeBytes        int64  `json:"size_bytes"`
    UsedBytes        int64  `json:"used_bytes"`        // Actual data size
    VHDFormat        string `json:"vhd_format"`        // VHDX, VHD
    RCTEnabled       bool   `json:"rct_enabled"`
    RCTID            string `json:"rct_id"`            // Current RCT tracking ID
}

// CheckpointInfo represents a VM checkpoint/snapshot
type CheckpointInfo struct {
    ID           string    `json:"id"`
    Name         string    `json:"name"`
    VMName       string    `json:"vm_name"`
    CreationTime time.Time `json:"creation_time"`
    ParentID     string    `json:"parent_id"`
    IsReference  bool      `json:"is_reference"`      // Reference point for RCT
}

// Manager provides Hyper-V management operations
type Manager struct {
    psPath string // Path to PowerShell executable
}

// NewManager creates a new Hyper-V manager
func NewManager() *Manager {
    return &Manager{
        psPath: "powershell.exe",
    }
}

// ListVMs returns all VMs on the host
func (m *Manager) ListVMs(ctx context.Context) ([]VMInfo, error) {
    script := `
        Get-VM | ForEach-Object {
            $vm = $_
            $disks = Get-VMHardDiskDrive -VM $vm | ForEach-Object {
                $vhd = Get-VHD -Path $_.Path -ErrorAction SilentlyContinue
                @{
                    ControllerType = $_.ControllerType.ToString()
                    ControllerNumber = $_.ControllerNumber
                    ControllerLocation = $_.ControllerLocation
                    Path = $_.Path
                    SizeBytes = if ($vhd) { $vhd.Size } else { 0 }
                    UsedBytes = if ($vhd) { $vhd.FileSize } else { 0 }
                    VHDFormat = if ($vhd) { $vhd.VhdFormat.ToString() } else { "Unknown" }
                    RCTEnabled = $_.SupportPersistentReservations
                    RCTID = ""
                }
            }
            
            $lis = Get-VMIntegrationService -VM $vm | Where-Object { $_.Enabled }
            $isLinux = ($vm.GuestServiceInterfaceComponentMode -eq "Linux")
            
            @{
                ID = $vm.VMId.ToString()
                Name = $vm.Name
                State = $vm.State.ToString()
                Generation = $vm.Generation
                CPUCount = $vm.ProcessorCount
                MemoryMB = $vm.MemoryStartup / 1MB
                IntegrationServices = ($lis.Count -gt 0)
                Disks = @($disks)
                IsLinux = $isLinux
                RCTEnabled = $false
            }
        } | ConvertTo-Json -Depth 4
    `
    return m.runPSJson[[]VMInfo](ctx, script)
}

// GetVM returns info for a specific VM
func (m *Manager) GetVM(ctx context.Context, vmName string) (*VMInfo, error) {
    script := fmt.Sprintf(`
        $vm = Get-VM -Name '%s' -ErrorAction Stop
        # ... (similar to ListVMs but for single VM)
    `, escapePSString(vmName))
    return m.runPSJson[*VMInfo](ctx, script)
}

// EnableRCT enables Resilient Change Tracking on a VM's disks
func (m *Manager) EnableRCT(ctx context.Context, vmName string) error {
    script := fmt.Sprintf(`
        $vm = Get-VM -Name '%s'
        Get-VMHardDiskDrive -VM $vm | ForEach-Object {
            Set-VMHardDiskDrive -VMHardDiskDrive $_ -SupportPersistentReservations $true
        }
    `, escapePSString(vmName))
    _, err := m.runPS(ctx, script)
    return err
}

// CreateReferenceCheckpoint creates a reference point for RCT tracking
func (m *Manager) CreateReferenceCheckpoint(ctx context.Context, vmName string) (*CheckpointInfo, error) {
    checkpointName := fmt.Sprintf("EazyBackup_Ref_%s", time.Now().Format("20060102_150405"))
    script := fmt.Sprintf(`
        $vm = Get-VM -Name '%s'
        $cp = Checkpoint-VM -VM $vm -SnapshotName '%s' -Passthru
        @{
            ID = $cp.Id.ToString()
            Name = $cp.Name
            VMName = $cp.VMName
            CreationTime = $cp.CreationTime.ToString("o")
            ParentID = if ($cp.ParentSnapshotId) { $cp.ParentSnapshotId.ToString() } else { "" }
            IsReference = $true
        } | ConvertTo-Json
    `, escapePSString(vmName), checkpointName)
    return m.runPSJson[*CheckpointInfo](ctx, script)
}

// RemoveCheckpoint removes a checkpoint
func (m *Manager) RemoveCheckpoint(ctx context.Context, vmName, checkpointID string) error {
    script := fmt.Sprintf(`
        $vm = Get-VM -Name '%s'
        Get-VMSnapshot -VM $vm | Where-Object { $_.Id -eq '%s' } | Remove-VMSnapshot
    `, escapePSString(vmName), checkpointID)
    _, err := m.runPS(ctx, script)
    return err
}

// Helper methods
func (m *Manager) runPS(ctx context.Context, script string) (string, error) {
    cmd := exec.CommandContext(ctx, m.psPath, "-NoProfile", "-NonInteractive", "-Command", script)
    out, err := cmd.Output()
    if err != nil {
        if ee, ok := err.(*exec.ExitError); ok {
            return "", fmt.Errorf("powershell error: %s", string(ee.Stderr))
        }
        return "", err
    }
    return string(out), nil
}

func (m *Manager) runPSJson[T any](ctx context.Context, script string) (T, error) {
    var result T
    out, err := m.runPS(ctx, script)
    if err != nil {
        return result, err
    }
    if err := json.Unmarshal([]byte(out), &result); err != nil {
        return result, fmt.Errorf("json unmarshal: %w", err)
    }
    return result, nil
}

func escapePSString(s string) string {
    // Escape single quotes for PowerShell strings
    return strings.ReplaceAll(s, "'", "''")
}
```

### 2. RCT Engine (`internal/agent/hyperv/rct.go`)

Handles change tracking queries:

```go
package hyperv

import (
    "context"
    "fmt"
)

// ChangedBlockRange represents a range of changed bytes in a VHDX
type ChangedBlockRange struct {
    Offset int64 `json:"offset"` // Byte offset from start of disk
    Length int64 `json:"length"` // Number of changed bytes
}

// RCTInfo contains RCT metadata for a disk
type RCTInfo struct {
    DiskPath      string              `json:"disk_path"`
    RCTID         string              `json:"rct_id"`          // Current RCT tracking ID
    ChangedBlocks []ChangedBlockRange `json:"changed_blocks"`
    TotalChanged  int64               `json:"total_changed"`   // Sum of all changed bytes
}

// RCTEngine handles Resilient Change Tracking operations
type RCTEngine struct {
    manager *Manager
}

// NewRCTEngine creates a new RCT engine
func NewRCTEngine(mgr *Manager) *RCTEngine {
    return &RCTEngine{manager: mgr}
}

// GetChangedBlocks returns changed block ranges since the reference checkpoint
func (e *RCTEngine) GetChangedBlocks(ctx context.Context, vmName string, baseCheckpointID string) ([]RCTInfo, error) {
    script := fmt.Sprintf(`
        $vm = Get-VM -Name '%s'
        $baseCheckpoint = Get-VMSnapshot -VM $vm | Where-Object { $_.Id -eq '%s' }
        
        if (-not $baseCheckpoint) {
            throw "Base checkpoint not found"
        }
        
        $results = @()
        
        Get-VMHardDiskDrive -VM $vm | ForEach-Object {
            $disk = $_
            $vhdPath = $disk.Path
            
            # Get changed regions using RCT
            try {
                $changes = Get-VMHardDiskDriveChangedBlockInformation `
                    -VMHardDiskDrive $disk `
                    -BaseSnapshot $baseCheckpoint
                
                $changedBlocks = @()
                $totalChanged = 0
                
                foreach ($region in $changes.ChangedByteRanges) {
                    $changedBlocks += @{
                        Offset = $region.Offset
                        Length = $region.Length
                    }
                    $totalChanged += $region.Length
                }
                
                $results += @{
                    DiskPath = $vhdPath
                    RCTID = $changes.ResilientChangeTrackingId
                    ChangedBlocks = $changedBlocks
                    TotalChanged = $totalChanged
                }
            } catch {
                # If RCT query fails, return null to indicate full backup needed
                $results += @{
                    DiskPath = $vhdPath
                    RCTID = ""
                    ChangedBlocks = $null
                    TotalChanged = -1
                }
            }
        }
        
        $results | ConvertTo-Json -Depth 4
    `, escapePSString(vmName), baseCheckpointID)
    
    return e.manager.runPSJson[[]RCTInfo](ctx, script)
}

// GetCurrentRCTIDs returns current RCT tracking IDs for all disks
func (e *RCTEngine) GetCurrentRCTIDs(ctx context.Context, vmName string) (map[string]string, error) {
    script := fmt.Sprintf(`
        $vm = Get-VM -Name '%s'
        $result = @{}
        
        Get-VMHardDiskDrive -VM $vm | ForEach-Object {
            $rctInfo = Get-VHD -Path $_.Path | Select-Object -ExpandProperty ResilientChangeTrackingId
            $result[$_.Path] = $rctInfo
        }
        
        $result | ConvertTo-Json
    `, escapePSString(vmName))
    
    return e.manager.runPSJson[map[string]string](ctx, script)
}

// ValidateRCTChain validates that RCT tracking is continuous (no gaps)
func (e *RCTEngine) ValidateRCTChain(ctx context.Context, vmName string, expectedRCTID string) (bool, error) {
    currentIDs, err := e.GetCurrentRCTIDs(ctx, vmName)
    if err != nil {
        return false, err
    }
    
    for _, id := range currentIDs {
        if id != expectedRCTID {
            return false, nil // RCT chain broken, need full backup
        }
    }
    return true, nil
}
```

---

## RCT (Resilient Change Tracking)

### Overview

RCT is Microsoft's block-level change tracking for Hyper-V, introduced in Windows Server 2016. It tracks which 256KB blocks have changed since a reference point.

### Requirements

- Windows Server 2016 or later
- Generation 2 VMs only
- VHDX format (not VHD)
- Hyper-V role with integration services

### RCT Lifecycle

```
┌─────────────────────────────────────────────────────────────────┐
│                    RCT BACKUP LIFECYCLE                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  INITIAL BACKUP (Full)                                          │
│  ─────────────────────                                          │
│  1. Enable RCT on VM disks                                      │
│  2. Create reference checkpoint (CP-0)                          │
│  3. Back up entire VHDX to Kopia                                │
│  4. Store CP-0 ID + RCT ID in database                          │
│  5. Merge CP-0 (checkpoint becomes reference point only)        │
│                                                                 │
│  INCREMENTAL BACKUP                                             │
│  ─────────────────────                                          │
│  1. Create new checkpoint (CP-N)                                │
│  2. Query changed blocks since CP-(N-1)                         │
│  3. Stream only changed blocks to Kopia                         │
│  4. Update database with new RCT ID                             │
│  5. Remove old checkpoint, keep CP-N as new reference           │
│                                                                 │
│  FULL BACKUP (Forced or RCT Reset)                              │
│  ──────────────────────────────────                             │
│  - Triggered when:                                              │
│    • RCT chain broken (VM migrated, disk compacted)             │
│    • Administrator requests full backup                         │
│    • RCT ID mismatch detected                                   │
│  - Process same as initial backup                               │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### RCT Block Size Considerations

| Metric | Value |
|--------|-------|
| RCT Block Size | 256 KB (fixed) |
| Min Changed Unit | 256 KB even for 1-byte change |
| Typical Overhead | 1-5% for active workloads |
| Optimal For | VMs > 50GB with < 30% daily change |

### Handling RCT Failures

RCT tracking can be invalidated by:

1. **VM Migration** – Live migration resets RCT
2. **Disk Compaction** – Optimize-VHD breaks chain
3. **Checkpoint Deletion** – Removing reference point
4. **Storage Migration** – Moving VHDX files
5. **Hyper-V Upgrade** – Major version changes

**Recovery Strategy:**

```go
func (r *Runner) determineHyperVBackupType(ctx context.Context, run *NextRunResponse) (string, error) {
    // Check if this is the first backup
    if run.HyperVLastCheckpointID == "" {
        return "full", nil
    }
    
    // Validate RCT chain is intact
    mgr := hyperv.NewManager()
    rct := hyperv.NewRCTEngine(mgr)
    
    valid, err := rct.ValidateRCTChain(ctx, run.HyperVVMName, run.HyperVLastRCTID)
    if err != nil {
        log.Printf("agent: RCT validation error, falling back to full: %v", err)
        return "full", nil
    }
    
    if !valid {
        log.Printf("agent: RCT chain broken for VM %s, performing full backup", run.HyperVVMName)
        return "full", nil
    }
    
    return "incremental", nil
}
```

---

## VSS Integration

### Windows VSS Flow

For application-consistent backups of Windows VMs:

```
┌────────────────────────────────────────────────────────────────────┐
│                     VSS BACKUP FLOW                                │
├────────────────────────────────────────────────────────────────────┤
│                                                                    │
│  HOST LEVEL                           GUEST LEVEL                  │
│  ──────────                           ───────────                  │
│                                                                    │
│  1. Agent requests                                                 │
│     backup via WMI        ─────────▶  2. Hyper-V IC triggers       │
│                                          guest VSS                 │
│                                                                    │
│                                       3. Guest VSS Writers         │
│                                          (SQL, Exchange, AD)       │
│                                          flush and freeze          │
│                                                                    │
│  4. Host creates          ◀─────────  5. Writers confirm           │
│     checkpoint with                      consistency               │
│     application state                                              │
│                                                                    │
│  6. Agent reads VHDX                  7. Writers resume            │
│     from checkpoint                      normal I/O                │
│     (frozen state)                                                 │
│                                                                    │
│  8. Backup completes,                                              │
│     checkpoint merged                                              │
│                                                                    │
└────────────────────────────────────────────────────────────────────┘
```

### VSS Writer Support

| Application | VSS Writer | Consistency Level |
|-------------|------------|-------------------|
| SQL Server | SQLServerVSSWriter | Transaction-consistent |
| Exchange | Microsoft Exchange Writer | Database-consistent |
| Active Directory | NTDS Writer | Database-consistent |
| File System | VSS Default Writer | Crash-consistent |
| SharePoint | SharePoint VSS Writer | Farm-consistent |
| Hyper-V (nested) | Hyper-V VSS Writer | VM-consistent |

### VSS Checkpoint Code

```go
// CreateVSSCheckpoint creates an application-consistent checkpoint
func (m *Manager) CreateVSSCheckpoint(ctx context.Context, vmName string) (*CheckpointInfo, error) {
    checkpointName := fmt.Sprintf("EazyBackup_VSS_%s", time.Now().Format("20060102_150405"))
    
    script := fmt.Sprintf(`
        $vm = Get-VM -Name '%s'
        
        # Verify integration services are running for VSS
        $vss = Get-VMIntegrationService -VM $vm | Where-Object { $_.Name -eq "VSS" }
        if (-not $vss.Enabled) {
            throw "VSS integration service not enabled on VM"
        }
        
        # Create production checkpoint (application-consistent)
        # This triggers guest VSS writers
        $cp = Checkpoint-VM -VM $vm `
            -SnapshotName '%s' `
            -SnapshotType Production `
            -Passthru
        
        if ($cp.SnapshotType -ne 'Production') {
            Write-Warning "Production checkpoint failed, fell back to standard"
        }
        
        @{
            ID = $cp.Id.ToString()
            Name = $cp.Name
            VMName = $cp.VMName
            CreationTime = $cp.CreationTime.ToString("o")
            ParentID = if ($cp.ParentSnapshotId) { $cp.ParentSnapshotId.ToString() } else { "" }
            IsReference = $true
            SnapshotType = $cp.SnapshotType.ToString()
        } | ConvertTo-Json
    `, escapePSString(vmName), checkpointName)
    
    return m.runPSJson[*CheckpointInfo](ctx, script)
}
```

---

## Linux VM Support

### Linux Integration Services (LIS)

Linux VMs require Hyper-V Linux Integration Services for:

- Heartbeat monitoring
- Time synchronization
- **VSS-equivalent freezing via `fsfreeze`**
- Key-Value Pair exchange

### Supported Linux Distributions

| Distribution | LIS Support | fsfreeze |
|--------------|-------------|----------|
| Ubuntu 18.04+ | Built-in | ✓ |
| RHEL/CentOS 7+ | Built-in | ✓ |
| Debian 10+ | Built-in | ✓ |
| SLES 12+ | Built-in | ✓ |
| Fedora 30+ | Built-in | ✓ |

### Linux Freeze Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                  LINUX VM FREEZE FLOW                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1. Host triggers VSS via Hyper-V IC                            │
│                     │                                           │
│                     ▼                                           │
│  2. hv_vss_daemon (guest) receives freeze request               │
│                     │                                           │
│                     ▼                                           │
│  3. Daemon executes pre-freeze scripts:                         │
│     /etc/hvscript/pre_freeze                                    │
│     (custom app quiescing here)                                 │
│                     │                                           │
│                     ▼                                           │
│  4. Daemon calls fsfreeze --freeze on each filesystem           │
│     - Flushes dirty buffers                                     │
│     - Blocks new writes                                         │
│     - Creates consistent state                                  │
│                     │                                           │
│                     ▼                                           │
│  5. Daemon signals host: ready for snapshot                     │
│                     │                                           │
│                     ▼                                           │
│  6. Host creates checkpoint                                     │
│                     │                                           │
│                     ▼                                           │
│  7. Daemon calls fsfreeze --unfreeze                            │
│                     │                                           │
│                     ▼                                           │
│  8. Daemon executes post-thaw scripts:                          │
│     /etc/hvscript/post_thaw                                     │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## Instant Restore & Mount

### Architecture

Instant restore allows starting a VM directly from the backup without full restore:

```
┌─────────────────────────────────────────────────────────────────────┐
│                    INSTANT RESTORE ARCHITECTURE                     │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌─────────────┐     ┌─────────────────────┐     ┌──────────────┐  │
│  │   Kopia     │     │   VHDX Virtual      │     │  Hyper-V     │  │
│  │   Repo      │────▶│   Block Device      │────▶│  VM          │  │
│  │   (S3)      │     │   (NBD/iSCSI)       │     │              │  │
│  └─────────────┘     └─────────────────────┘     └──────────────┘  │
│                                                                     │
│  Data flows on-demand:                                              │
│  - VM reads block → NBD fetches from Kopia → Returns to VM          │
│  - Write redirected to differential disk (if enabled)              │
│  - Background migration copies data to local storage                │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Instant Restore Workflow

```
┌─────────────────────────────────────────────────────────────────────┐
│                   INSTANT RESTORE WORKFLOW                          │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  1. USER INITIATES INSTANT RESTORE                                  │
│     └─▶ Select VM and snapshot from UI                              │
│     └─▶ Choose target Hyper-V host                                  │
│                                                                     │
│  2. AGENT PREPARES RESTORE                                          │
│     └─▶ Start NBD/iSCSI server exposing snapshot                    │
│     └─▶ Create differential VHDX for writes                         │
│     └─▶ Generate VM configuration from backup metadata              │
│                                                                     │
│  3. CREATE VM WITH REMOTE STORAGE                                   │
│     └─▶ Register VM pointing to NBD-backed VHDX                     │
│     └─▶ VM boots in seconds (data fetched on-demand)                │
│                                                                     │
│  4. STORAGE VMOTION (Background)                                    │
│     └─▶ Migrate VM storage to local while running                   │
│     └─▶ Copy blocks from Kopia to local in background               │
│     └─▶ Merge differential disk with base                           │
│                                                                     │
│  5. FINALIZE                                                        │
│     └─▶ Disconnect from NBD when migration complete                 │
│     └─▶ VM now fully local                                          │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Database Schema

### Required Changes to Existing Tables

The `engine` and `source_type` enums in `s3_cloudbackup_jobs` need to be extended:

```sql
-- Extend engine enum to include hyperv
ALTER TABLE s3_cloudbackup_jobs 
MODIFY COLUMN engine ENUM('sync', 'kopia', 'disk_image', 'hyperv') NOT NULL DEFAULT 'sync';

-- Extend source_type enum to include hyperv
ALTER TABLE s3_cloudbackup_jobs 
MODIFY COLUMN source_type ENUM('local', 'network_share', 'disk_volume', 'hyperv') NOT NULL DEFAULT 'local';

-- Add Hyper-V specific columns to jobs table
ALTER TABLE s3_cloudbackup_jobs 
ADD COLUMN hyperv_enabled BOOLEAN DEFAULT FALSE,
ADD COLUMN hyperv_config JSON NULL;
-- hyperv_config example:
-- {
--   "vms": ["VM1", "VM2"],
--   "exclude_vms": [],
--   "backup_all_vms": false,
--   "enable_rct": true,
--   "consistency_level": "application",
--   "quiesce_timeout_seconds": 300,
--   "instant_restore_enabled": true
-- }

-- Add column for storing per-disk manifests for Hyper-V runs
ALTER TABLE s3_cloudbackup_runs
ADD COLUMN disk_manifests_json JSON NULL;
-- Example: {"C:\\VMs\\disk0.vhdx": "manifest123", "C:\\VMs\\disk1.vhdx": "manifest456"}
```

### New Tables

```sql
-- =====================================================
-- HYPER-V BACKUP ENGINE SCHEMA
-- =====================================================

-- VM Registry: tracks VMs configured for backup
CREATE TABLE s3_hyperv_vms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    vm_name VARCHAR(255) NOT NULL,
    vm_guid VARCHAR(64),                          -- Hyper-V VM GUID
    generation TINYINT DEFAULT 2,                 -- VM Generation (1 or 2)
    is_linux BOOLEAN DEFAULT FALSE,
    integration_services BOOLEAN DEFAULT TRUE,
    rct_enabled BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (job_id) REFERENCES s3_cloudbackup_jobs(id) ON DELETE CASCADE,
    UNIQUE KEY uk_job_vm (job_id, vm_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- VM Disks: tracks VHDX files for each VM
CREATE TABLE s3_hyperv_vm_disks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vm_id INT NOT NULL,
    disk_path VARCHAR(1024) NOT NULL,             -- Full path to VHDX
    controller_type ENUM('SCSI', 'IDE') DEFAULT 'SCSI',
    controller_number INT DEFAULT 0,
    controller_location INT DEFAULT 0,
    vhd_format ENUM('VHDX', 'VHD') DEFAULT 'VHDX',
    size_bytes BIGINT,                            -- Virtual size
    rct_enabled BOOLEAN DEFAULT FALSE,
    current_rct_id VARCHAR(128),                  -- Current RCT tracking ID
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (vm_id) REFERENCES s3_hyperv_vms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Checkpoints: tracks backup reference points for RCT
CREATE TABLE s3_hyperv_checkpoints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vm_id INT NOT NULL,
    run_id INT,                                   -- Associated backup run
    checkpoint_id VARCHAR(64) NOT NULL,           -- Hyper-V checkpoint GUID
    checkpoint_name VARCHAR(255),
    checkpoint_type ENUM('Production', 'Standard', 'Reference') DEFAULT 'Production',
    rct_ids JSON,                                 -- RCT IDs for each disk at this point
    is_active BOOLEAN DEFAULT TRUE,               -- Is this the current reference point?
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    merged_at TIMESTAMP NULL,                     -- When checkpoint was merged
    
    FOREIGN KEY (vm_id) REFERENCES s3_hyperv_vms(id) ON DELETE CASCADE,
    FOREIGN KEY (run_id) REFERENCES s3_cloudbackup_runs(id) ON DELETE SET NULL,
    INDEX idx_vm_active (vm_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Backup Points: tracks backup metadata for restore
CREATE TABLE s3_hyperv_backup_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vm_id INT NOT NULL,
    run_id INT NOT NULL,
    backup_type ENUM('Full', 'Incremental') NOT NULL,
    manifest_id VARCHAR(128) NOT NULL,            -- Kopia manifest ID
    parent_backup_id INT NULL,                    -- For incremental: points to base
    vm_config_json JSON,                          -- VM configuration at backup time
    disk_manifests JSON,                          -- Manifest IDs per disk
    total_size_bytes BIGINT,
    changed_size_bytes BIGINT,                    -- For incremental
    duration_seconds INT,
    consistency_level ENUM('Crash', 'Application') DEFAULT 'Application',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,                    -- Based on retention policy
    
    FOREIGN KEY (vm_id) REFERENCES s3_hyperv_vms(id) ON DELETE CASCADE,
    FOREIGN KEY (run_id) REFERENCES s3_cloudbackup_runs(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_backup_id) REFERENCES s3_hyperv_backup_points(id) ON DELETE SET NULL,
    INDEX idx_vm_created (vm_id, created_at DESC),
    INDEX idx_manifest (manifest_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Instant Restore Sessions: tracks active instant restore sessions
CREATE TABLE s3_hyperv_instant_restore_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    backup_point_id INT NOT NULL,
    target_host VARCHAR(255),                     -- Target Hyper-V host
    restored_vm_name VARCHAR(255),
    session_type ENUM('NBD', 'iSCSI', 'Direct') DEFAULT 'NBD',
    nbd_address VARCHAR(64),                      -- NBD server address:port
    iscsi_target_iqn VARCHAR(255),
    differential_vhdx_path VARCHAR(1024),         -- Path to write differential
    status ENUM('Starting', 'Active', 'Migrating', 'Completed', 'Failed') DEFAULT 'Starting',
    migration_progress INT DEFAULT 0,             -- 0-100%
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    
    FOREIGN KEY (backup_point_id) REFERENCES s3_hyperv_backup_points(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Schema Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         HYPER-V SCHEMA RELATIONSHIPS                        │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  s3_cloudbackup_jobs                                                        │
│  ┌─────────────────┐                                                        │
│  │ id              │◀────────────────────────┐                              │
│  │ engine='hyperv' │                         │                              │
│  │ hyperv_enabled  │                         │                              │
│  │ hyperv_config   │                         │                              │
│  └─────────────────┘                         │                              │
│           │                                  │                              │
│           │ 1:N                              │                              │
│           ▼                                  │                              │
│  s3_hyperv_vms                               │                              │
│  ┌─────────────────┐                         │                              │
│  │ id              │◀─────────────┐          │                              │
│  │ job_id ─────────┼──────────────┼──────────┘                              │
│  │ vm_name         │              │                                         │
│  │ vm_guid         │              │                                         │
│  │ is_linux        │              │                                         │
│  │ rct_enabled     │              │                                         │
│  └─────────────────┘              │                                         │
│           │                       │                                         │
│           │ 1:N                   │ 1:N                                     │
│           ▼                       │                                         │
│  s3_hyperv_vm_disks               │         s3_hyperv_checkpoints           │
│  ┌─────────────────┐              │         ┌─────────────────┐             │
│  │ id              │              │         │ id              │             │
│  │ vm_id ──────────┼──────────────┼────────▶│ vm_id           │             │
│  │ disk_path       │              │         │ run_id          │             │
│  │ current_rct_id  │              │         │ checkpoint_id   │             │
│  └─────────────────┘              │         │ rct_ids         │             │
│                                   │         └─────────────────┘             │
│                                   │                                         │
│                                   │                                         │
│  s3_hyperv_backup_points          │                                         │
│  ┌─────────────────┐              │                                         │
│  │ id              │◀─────────────┼────────────────────┐                    │
│  │ vm_id ──────────┼──────────────┘                    │                    │
│  │ run_id          │                                   │                    │
│  │ backup_type     │                                   │                    │
│  │ manifest_id     │                                   │                    │
│  │ parent_backup_id├───────────────────────────────────┘ (self-ref)         │
│  └─────────────────┘                                                        │
│           │                                                                 │
│           │ 1:N                                                             │
│           ▼                                                                 │
│  s3_hyperv_instant_restore_sessions                                         │
│  ┌─────────────────┐                                                        │
│  │ id              │                                                        │
│  │ backup_point_id │                                                        │
│  │ status          │                                                        │
│  │ nbd_address     │                                                        │
│  └─────────────────┘                                                        │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## API Endpoints

### Changes to Existing Agent APIs

#### `agent_next_run.php` – Extend Response for Hyper-V

```php
// When engine='hyperv', include additional Hyper-V fields:
{
    "status": "success",
    "run": {
        "run_id": 123,
        "job_id": 456,
        "engine": "hyperv",
        "source_path": "",  // Not used for Hyper-V
        // ... existing S3 destination fields ...
        
        // NEW: Hyper-V specific fields
        "hyperv_config": {
            "vms": ["WebServer01", "SQLServer01"],
            "enable_rct": true,
            "consistency_level": "application"
        },
        "hyperv_vms": [
            {
                "vm_id": 1,
                "vm_name": "WebServer01",
                "vm_guid": "abc123-...",
                "last_checkpoint_id": "ckpt-guid",
                "last_rct_ids": {
                    "C:\\VMs\\disk0.vhdx": "rct-id-123"
                }
            }
        ]
    }
}
```

#### `agent_update_run.php` – Accept Hyper-V Results

```php
// Additional fields for Hyper-V runs:
{
    "run_id": 123,
    "status": "success",
    "manifest_id": "main-manifest-id",
    "disk_manifests_json": {
        "C:\\VMs\\disk0.vhdx": "manifest-disk0",
        "C:\\VMs\\disk1.vhdx": "manifest-disk1",
        "_config": "manifest-vmconfig"
    },
    "hyperv_results": [
        {
            "vm_id": 1,
            "backup_type": "Incremental",
            "checkpoint_id": "new-ckpt-guid",
            "rct_ids": {
                "C:\\VMs\\disk0.vhdx": "new-rct-id-456"
            },
            "changed_bytes": 1073741824,
            "consistency_level": "Application"
        }
    ]
}
```

### Agent APIs (New)

```php
// agent_hyperv_discover.php - Discovers VMs on the Hyper-V host
// POST /agent_hyperv_discover.php
// Headers: X-Agent-ID, X-Agent-Token
// Response:
{
    "success": true,
    "vms": [
        {
            "id": "abc123-...",
            "name": "DC01",
            "state": "Running",
            "generation": 2,
            "is_linux": false,
            "rct_enabled": true,
            "disks": [
                {
                    "path": "C:\\VMs\\DC01\\disk0.vhdx",
                    "size_bytes": 107374182400,
                    "used_bytes": 42949672960,
                    "rct_enabled": true
                }
            ]
        }
    ]
}

// agent_hyperv_update_checkpoint.php - Updates checkpoint info after backup
// POST /agent_hyperv_update_checkpoint.php
// Headers: X-Agent-ID, X-Agent-Token
// Body:
{
    "vm_id": 123,
    "run_id": 456,
    "checkpoint_id": "ckpt-guid",
    "rct_ids": {
        "C:\\VMs\\DC01\\disk0.vhdx": "rct-tracking-id-123"
    },
    "backup_type": "Incremental",
    "changed_bytes": 1073741824
}

// agent_hyperv_get_restore_info.php - Gets info for restore operation
// GET /agent_hyperv_get_restore_info.php?backup_point_id=789
// Headers: X-Agent-ID, X-Agent-Token
// Response:
{
    "success": true,
    "backup_point": {
        "id": 789,
        "vm_name": "DC01",
        "backup_type": "Full",
        "manifest_id": "kopia-manifest-id",
        "vm_config": { ... },
        "disk_manifests": {
            "disk0": "manifest-disk0",
            "disk1": "manifest-disk1"
        }
    },
    "restore_chain": [
        { "id": 780, "type": "Full", "manifest_id": "..." },
        { "id": 785, "type": "Incremental", "manifest_id": "..." },
        { "id": 789, "type": "Incremental", "manifest_id": "..." }
    ]
}
```

### Client Area APIs (New)

```php
// cloudbackup_hyperv_vms.php - List VMs for a job
// GET /cloudbackup_hyperv_vms.php?job_id=123
// Returns list of configured VMs and their backup status

// cloudbackup_hyperv_backup_points.php - List backup points (restore points)
// GET /cloudbackup_hyperv_backup_points.php?vm_id=456
// Returns list of available restore points with chain info

// cloudbackup_hyperv_start_restore.php - Initiate VM restore
// POST /cloudbackup_hyperv_start_restore.php
// Body:
{
    "backup_point_id": 789,
    "restore_type": "instant",  // "instant" | "full"
    "target_vm_name": "DC01-Restored",
    "target_host": "HVHOST02",  // Optional, defaults to original
    "restore_options": {
        "power_on": true,
        "connect_network": false,
        "overwrite_existing": false
    }
}

// cloudbackup_hyperv_instant_restore_status.php - Get instant restore session status
// GET /cloudbackup_hyperv_instant_restore_status.php?session_id=123
// Returns session status, migration progress, etc.
```

---

## Agent Implementation

### File Structure

```
e3-backup-agent/
├── cmd/
│   └── agent/
│       └── main.go
├── internal/
│   └── agent/
│       ├── config.go
│       ├── runner.go           # Add case "hyperv": return r.runHyperV(run)
│       ├── api_client.go       # Extend NextRunResponse, RunUpdate
│       ├── kopia.go            # Existing - provides kopiaSnapshotWithEntry
│       ├── nas.go              # Existing - wire up mapDriveInUserSession
│       │
│       ├── hyperv_backup.go    # NEW: runHyperV() and orchestration
│       ├── hyperv_stub.go      # NEW: Build tag !windows stub
│       │
│       └── hyperv/             # NEW: Hyper-V package
│           ├── manager.go      # Hyper-V WMI/PowerShell operations
│           ├── rct.go          # RCT change tracking
│           ├── vss.go          # VSS integration
│           ├── vhdx.go         # VHDX file handling
│           ├── backup.go       # Backup orchestration types
│           ├── restore.go      # Restore operations
│           └── instant.go      # Instant restore (NBD/iSCSI)
└── go.mod
```

### API Client Extensions

```go
// api_client.go - Extend NextRunResponse

type NextRunResponse struct {
    RunID                   int64          `json:"run_id"`
    JobID                   int64          `json:"job_id"`
    Engine                  string         `json:"engine"`
    SourcePath              string         `json:"source_path"`
    // ... existing fields ...
    
    // NEW: Hyper-V specific fields
    HyperVConfig            *HyperVConfig  `json:"hyperv_config,omitempty"`
    HyperVVMs               []HyperVVMRun  `json:"hyperv_vms,omitempty"`
}

// HyperVConfig contains job-level Hyper-V settings
type HyperVConfig struct {
    VMs                []string `json:"vms"`
    ExcludeVMs         []string `json:"exclude_vms"`
    BackupAllVMs       bool     `json:"backup_all_vms"`
    EnableRCT          bool     `json:"enable_rct"`
    ConsistencyLevel   string   `json:"consistency_level"` // "application" or "crash"
    QuiesceTimeoutSecs int      `json:"quiesce_timeout_seconds"`
}

// HyperVVMRun contains per-VM context for a backup run
type HyperVVMRun struct {
    VMID             int64             `json:"vm_id"`
    VMName           string            `json:"vm_name"`
    VMGUID           string            `json:"vm_guid"`
    LastCheckpointID string            `json:"last_checkpoint_id"`
    LastRCTIDs       map[string]string `json:"last_rct_ids"` // disk path -> RCT ID
}

// api_client.go - Extend RunUpdate

type RunUpdate struct {
    // ... existing fields ...
    
    // NEW: Hyper-V specific fields
    DiskManifestsJSON map[string]string  `json:"disk_manifests_json,omitempty"`
    HyperVResults     []HyperVVMResult   `json:"hyperv_results,omitempty"`
}

type HyperVVMResult struct {
    VMID             int64             `json:"vm_id"`
    BackupType       string            `json:"backup_type"` // "Full" or "Incremental"
    CheckpointID     string            `json:"checkpoint_id"`
    RCTIDs           map[string]string `json:"rct_ids"`
    ChangedBytes     int64             `json:"changed_bytes"`
    ConsistencyLevel string            `json:"consistency_level"`
    Error            string            `json:"error,omitempty"`
}
```

### Runner Integration

```go
// runner.go - Add Hyper-V engine case

func (r *Runner) runRun(run *NextRunResponse) error {
    if run.RunID == 0 {
        return fmt.Errorf("next run returned no run_id")
    }

    // Authenticate to network share if credentials provided
    if run.NetworkCredentials != nil && run.NetworkCredentials.Username != "" {
        if err := r.authenticateNetworkPath(run.SourcePath, run.NetworkCredentials); err != nil {
            log.Printf("agent: run %d network auth failed: %v", run.RunID, err)
            return fmt.Errorf("network authentication failed: %w", err)
        }
        defer r.disconnectNetworkPath(run.SourcePath)
    }

    engine := strings.ToLower(strings.TrimSpace(run.Engine))
    if engine == "" {
        engine = "sync"
    }
    switch engine {
    case "kopia":
        return r.runKopia(run)
    case "disk_image":
        return r.runDiskImage(run)
    case "hyperv":
        return r.runHyperV(run)  // NEW
    default:
        return r.runSync(run)
    }
}
```

### Hyper-V Backup Implementation

```go
// hyperv_backup.go - Main Hyper-V backup orchestration

//go:build windows

package agent

import (
    "context"
    "fmt"
    "log"
    "time"
    
    "github.com/eazybackup/agent/internal/agent/hyperv"
)

// runHyperV executes a Hyper-V VM backup job
func (r *Runner) runHyperV(run *NextRunResponse) error {
    startedAt := time.Now().UTC()
    _ = r.client.UpdateRun(RunUpdate{
        RunID:     run.RunID,
        Status:    "running",
        StartedAt: startedAt.Format(time.RFC3339),
    })
    r.pushEvents(run.RunID, RunEvent{
        Type:      "info",
        Level:     "info",
        MessageID: "BACKUP_STARTING",
        ParamsJSON: map[string]any{
            "engine": "hyperv",
        },
    })

    ctx, cancel := context.WithCancel(context.Background())
    defer cancel()

    // Validate Hyper-V configuration
    if run.HyperVConfig == nil || len(run.HyperVVMs) == 0 {
        return fmt.Errorf("hyperv: no VMs configured for backup")
    }

    // Initialize Hyper-V manager
    mgr := hyperv.NewManager()
    rct := hyperv.NewRCTEngine(mgr)

    // Process each VM
    var allResults []HyperVVMResult
    diskManifests := make(map[string]string)
    var lastErr error

    for _, vmRun := range run.HyperVVMs {
        log.Printf("agent: hyperv backup starting VM=%s", vmRun.VMName)
        r.pushEvents(run.RunID, RunEvent{
            Type:      "info",
            Level:     "info",
            MessageID: "HYPERV_VM_STARTING",
            ParamsJSON: map[string]any{
                "vm_name": vmRun.VMName,
            },
        })

        result, err := r.backupHyperVVM(ctx, run, vmRun, mgr, rct)
        if err != nil {
            log.Printf("agent: hyperv VM %s backup failed: %v", vmRun.VMName, err)
            result.Error = err.Error()
            lastErr = err
            r.pushEvents(run.RunID, RunEvent{
                Type:      "error",
                Level:     "error",
                MessageID: "HYPERV_VM_FAILED",
                ParamsJSON: map[string]any{
                    "vm_name": vmRun.VMName,
                    "error":   err.Error(),
                },
            })
        } else {
            log.Printf("agent: hyperv VM %s backup complete: type=%s manifest=%s",
                vmRun.VMName, result.BackupType, result.CheckpointID)
            r.pushEvents(run.RunID, RunEvent{
                Type:      "info",
                Level:     "info",
                MessageID: "HYPERV_VM_COMPLETE",
                ParamsJSON: map[string]any{
                    "vm_name":     vmRun.VMName,
                    "backup_type": result.BackupType,
                },
            })
        }
        allResults = append(allResults, result)
    }

    // Finalize run
    status := "success"
    errMsg := ""
    if lastErr != nil {
        status = "failed"
        errMsg = lastErr.Error()
    }

    finishedAt := time.Now().UTC().Format(time.RFC3339)
    _ = r.client.UpdateRun(RunUpdate{
        RunID:             run.RunID,
        Status:            status,
        ErrorSummary:      errMsg,
        FinishedAt:        finishedAt,
        DiskManifestsJSON: diskManifests,
        HyperVResults:     allResults,
    })

    return lastErr
}

// backupHyperVVM backs up a single VM and returns the result
func (r *Runner) backupHyperVVM(
    ctx context.Context,
    run *NextRunResponse,
    vmRun HyperVVMRun,
    mgr *hyperv.Manager,
    rct *hyperv.RCTEngine,
) (HyperVVMResult, error) {
    result := HyperVVMResult{
        VMID:   vmRun.VMID,
        RCTIDs: make(map[string]string),
    }

    // Get VM info
    vm, err := mgr.GetVM(ctx, vmRun.VMName)
    if err != nil {
        return result, fmt.Errorf("get VM info: %w", err)
    }

    // Determine backup type
    backupType := "full"
    if vmRun.LastCheckpointID != "" && run.HyperVConfig.EnableRCT {
        valid, err := rct.ValidateRCTChain(ctx, vmRun.VMName, vmRun.LastRCTIDs)
        if err == nil && valid {
            backupType = "incremental"
        }
    }
    result.BackupType = backupType

    // Create application-consistent checkpoint
    var checkpoint *hyperv.CheckpointInfo
    if vm.IsLinux {
        checkpoint, err = mgr.CreateLinuxConsistentCheckpoint(ctx, vmRun.VMName)
    } else {
        checkpoint, err = mgr.CreateVSSCheckpoint(ctx, vmRun.VMName)
    }
    if err != nil {
        // Fall back to crash-consistent
        log.Printf("agent: hyperv app-consistent checkpoint failed, trying crash-consistent: %v", err)
        checkpoint, err = mgr.CreateReferenceCheckpoint(ctx, vmRun.VMName)
        if err != nil {
            return result, fmt.Errorf("create checkpoint: %w", err)
        }
        result.ConsistencyLevel = "Crash"
    } else {
        result.ConsistencyLevel = "Application"
    }
    result.CheckpointID = checkpoint.ID

    // Back up each disk
    for _, disk := range vm.Disks {
        log.Printf("agent: hyperv backing up disk: %s", disk.Path)

        var manifestID string
        var changedBytes int64

        if backupType == "incremental" && vmRun.LastCheckpointID != "" {
            // Get changed blocks
            rctInfos, err := rct.GetChangedBlocks(ctx, vmRun.VMName, vmRun.LastCheckpointID)
            if err != nil {
                log.Printf("agent: hyperv RCT query failed, falling back to full for disk %s: %v", disk.Path, err)
                manifestID, err = r.backupFullVHDX(ctx, run, disk.Path)
            } else {
                // Find RCT info for this disk
                var diskRCT *hyperv.RCTInfo
                for i := range rctInfos {
                    if rctInfos[i].DiskPath == disk.Path {
                        diskRCT = &rctInfos[i]
                        break
                    }
                }
                if diskRCT != nil && len(diskRCT.ChangedBlocks) > 0 {
                    manifestID, err = r.backupChangedVHDXBlocks(ctx, run, disk.Path, diskRCT.ChangedBlocks)
                    changedBytes = diskRCT.TotalChanged
                    result.RCTIDs[disk.Path] = diskRCT.RCTID
                } else {
                    manifestID, err = r.backupFullVHDX(ctx, run, disk.Path)
                }
            }
        } else {
            manifestID, err = r.backupFullVHDX(ctx, run, disk.Path)
        }

        if err != nil {
            return result, fmt.Errorf("backup disk %s: %w", disk.Path, err)
        }

        result.ChangedBytes += changedBytes
        log.Printf("agent: hyperv disk %s backed up, manifest=%s", disk.Path, manifestID)
    }

    // Merge checkpoint (cleanup)
    if err := mgr.MergeCheckpoint(ctx, vmRun.VMName, checkpoint.ID); err != nil {
        log.Printf("agent: hyperv warning: failed to merge checkpoint: %v", err)
    }

    return result, nil
}

// backupFullVHDX backs up an entire VHDX file using Kopia
func (r *Runner) backupFullVHDX(ctx context.Context, run *NextRunResponse, vhdxPath string) (string, error) {
    // Use existing kopiaSnapshotWithEntry with the VHDX as the source
    // This leverages Kopia's deduplication for unchanged blocks
    
    // For now, use direct file backup; in production, may want to stream from checkpoint
    origSource := run.SourcePath
    run.SourcePath = vhdxPath
    defer func() { run.SourcePath = origSource }()
    
    if err := r.kopiaSnapshot(ctx, run); err != nil {
        return "", err
    }
    
    // The manifest ID should be available from the run update
    // This is a simplification; actual implementation should return the manifest
    return "", nil
}

// backupChangedVHDXBlocks backs up only changed blocks using RCT
func (r *Runner) backupChangedVHDXBlocks(
    ctx context.Context,
    run *NextRunResponse,
    vhdxPath string,
    changedBlocks []hyperv.ChangedBlockRange,
) (string, error) {
    // TODO: Implement sparse block backup
    // For now, fall back to full backup
    log.Printf("agent: hyperv incremental block backup not yet implemented, using full")
    return r.backupFullVHDX(ctx, run, vhdxPath)
}
```

### Stub for Non-Windows Builds

```go
// hyperv_stub.go - Build tag for non-Windows

//go:build !windows

package agent

import (
    "context"
    "fmt"
)

// runHyperV is not supported on non-Windows platforms
func (r *Runner) runHyperV(run *NextRunResponse) error {
    return fmt.Errorf("hyperv backup engine is only supported on Windows")
}
```

---

## Development Roadmap

### Phase 1: Foundation (Weeks 1-3)

**Goal:** Basic full VM backup working end-to-end

| Task | Description | Estimate |
|------|-------------|----------|
| 1.1 | Database schema implementation | 2 days |
| 1.2 | Hyper-V Manager (PowerShell wrapper) | 3 days |
| 1.3 | VM discovery API | 1 day |
| 1.4 | VSS checkpoint creation | 2 days |
| 1.5 | Full VHDX backup to Kopia | 3 days |
| 1.6 | VM configuration backup | 1 day |
| 1.7 | Basic client UI for VM selection | 2 days |
| 1.8 | Integration testing | 2 days |

**Deliverables:**
- Full VM backup (no RCT yet)
- Application-consistent checkpoints
- VM list and selection in client area

### Phase 2: RCT Integration (Weeks 4-6)

**Goal:** Efficient incremental backups using RCT

| Task | Description | Estimate |
|------|-------------|----------|
| 2.1 | RCT enable/query implementation | 3 days |
| 2.2 | Changed block tracking in DB | 2 days |
| 2.3 | Kopia integration for block-level backup | 4 days |
| 2.4 | RCT chain validation | 2 days |
| 2.5 | Backup type selection logic | 1 day |
| 2.6 | Client UI for backup history/type | 2 days |
| 2.7 | Testing with large VMs | 3 days |

**Deliverables:**
- Incremental backups via RCT
- ~90% reduction in backup size for typical workloads
- Automatic fallback to full when RCT chain breaks

### Phase 3: Linux VM Support (Weeks 7-8)

**Goal:** Full support for Linux guest VMs

| Task | Description | Estimate |
|------|-------------|----------|
| 3.1 | Linux VM detection | 1 day |
| 3.2 | fsfreeze integration via Hyper-V IC | 2 days |
| 3.3 | Pre/post-thaw script support | 2 days |
| 3.4 | Linux-specific testing | 3 days |

**Deliverables:**
- Application-consistent Linux VM backups
- Custom pre/post-freeze scripts support

### Phase 4: Full Restore (Weeks 9-11)

**Goal:** Complete VM restore from backup

| Task | Description | Estimate |
|------|-------------|----------|
| 4.1 | Restore chain calculation | 2 days |
| 4.2 | VHDX reconstruction from incrementals | 4 days |
| 4.3 | VM re-registration on Hyper-V | 2 days |
| 4.4 | Cross-host restore support | 3 days |
| 4.5 | Restore wizard UI | 3 days |
| 4.6 | Restore testing and validation | 3 days |

**Deliverables:**
- Full VM restore to same or different host
- Restore wizard in client area
- Point-in-time recovery selection

### Phase 5: Instant Restore (Weeks 12-15)

**Goal:** Boot VMs directly from backup

| Task | Description | Estimate |
|------|-------------|----------|
| 5.1 | NBD server implementation | 5 days |
| 5.2 | Differential VHDX for writes | 3 days |
| 5.3 | Background storage migration | 4 days |
| 5.4 | Session management | 2 days |
| 5.5 | iSCSI target alternative | 4 days |
| 5.6 | Instant restore UI | 3 days |
| 5.7 | Performance optimization | 3 days |

**Deliverables:**
- Boot VM from backup in < 2 minutes
- Background migration to local storage
- Instant restore session management

### Phase 6: Advanced Features (Weeks 16-18)

**Goal:** Production hardening and extras

| Task | Description | Estimate |
|------|-------------|----------|
| 6.1 | Granular file restore from VHDX | 5 days |
| 6.2 | Backup verification/integrity | 3 days |
| 6.3 | Reporting and alerts | 2 days |
| 6.4 | Documentation | 3 days |
| 6.5 | Performance benchmarking | 2 days |

**Deliverables:**
- File-level restore from VM backups
- Automated backup verification
- Comprehensive documentation

---

### Timeline Summary

```
Week    1   2   3   4   5   6   7   8   9  10  11  12  13  14  15  16  17  18
        ├───────────┼───────────┼───────┼───────────┼───────────────┼───────────┤
Phase 1 │███████████│           │       │           │               │           │
        │ Foundation│           │       │           │               │           │
        ├───────────┼───────────┼───────┼───────────┼───────────────┼───────────┤
Phase 2 │           │███████████│       │           │               │           │
        │           │    RCT    │       │           │               │           │
        ├───────────┼───────────┼───────┼───────────┼───────────────┼───────────┤
Phase 3 │           │           │███████│           │               │           │
        │           │           │ Linux │           │               │           │
        ├───────────┼───────────┼───────┼───────────┼───────────────┼───────────┤
Phase 4 │           │           │       │███████████│               │           │
        │           │           │       │Full Restore               │           │
        ├───────────┼───────────┼───────┼───────────┼───────────────┼───────────┤
Phase 5 │           │           │       │           │███████████████│           │
        │           │           │       │           │Instant Restore│           │
        ├───────────┼───────────┼───────┼───────────┼───────────────┼───────────┤
Phase 6 │           │           │       │           │               │███████████│
        │           │           │       │           │               │ Advanced  │
        └───────────┴───────────┴───────┴───────────┴───────────────┴───────────┘
```

---

## Security Considerations

### Agent Privileges

The backup agent runs as a Windows Service under the **SYSTEM** account, which provides:

```
SYSTEM Account Permissions:
├── Hyper-V Administrators (implicit) ✓
├── Backup Operators (implicit) ✓
├── Full VSS access ✓
├── Read/write all VM storage locations ✓
├── Network listener for WebDAV (Cloud NAS) ✓
└── Task Scheduler access (for Cloud NAS drive mapping) ✓
```

### Data Security

| Layer | Protection |
|-------|------------|
| In Transit | TLS 1.3 for all API calls, S3 HTTPS |
| At Rest | Kopia repository encryption (AES-256-GCM) |
| VM Data | Never stored unencrypted, even temporarily |
| Credentials | Agent token, S3 keys never logged |

### Access Control

- Per-client isolation (clients can only see their VMs)
- Agent authentication via token headers
- WHMCS permission integration for client area

---

## Appendix A: PowerShell Reference Commands

```powershell
# List all VMs with RCT status
Get-VM | ForEach-Object {
    $vm = $_
    $disks = Get-VMHardDiskDrive -VM $vm | ForEach-Object {
        $vhd = Get-VHD -Path $_.Path
        [PSCustomObject]@{
            VM = $vm.Name
            Disk = $_.Path
            RCTEnabled = $vhd.ResilientChangeTrackingEnabled
        }
    }
    $disks
}

# Enable RCT on a VM
$vm = Get-VM -Name "MyVM"
Get-VMHardDiskDrive -VM $vm | ForEach-Object {
    Set-VMHardDiskDrive -VMHardDiskDrive $_ -SupportPersistentReservations $true
}

# Create production (app-consistent) checkpoint
Checkpoint-VM -Name "MyVM" -SnapshotName "Backup" -SnapshotType Production

# Query changed blocks since checkpoint
$vm = Get-VM -Name "MyVM"
$checkpoint = Get-VMSnapshot -VM $vm | Where-Object { $_.Name -eq "Backup" }
Get-VMHardDiskDrive -VM $vm | ForEach-Object {
    Get-VMHardDiskDriveChangedBlockInformation -VMHardDiskDrive $_ -BaseSnapshot $checkpoint
}

# Export VM configuration
Export-VM -Name "MyVM" -Path "C:\Exports" -CaptureLiveState None

# Merge checkpoint (make it reference point only)
$cp = Get-VMSnapshot -VMName "MyVM" -Name "Backup"
Remove-VMSnapshot -VMSnapshot $cp
```

---

## Appendix B: Error Handling Matrix

| Error | Cause | Recovery |
|-------|-------|----------|
| VSS timeout | Guest VSS writer slow | Retry with longer timeout, fall back to crash-consistent |
| RCT chain broken | VM migrated/compacted | Force full backup, re-establish RCT baseline |
| Checkpoint creation failed | VM in bad state | Log error, skip VM, alert admin |
| Disk read error | Storage issue | Retry with backoff, fail run if persistent |
| Kopia upload failed | Network/S3 issue | Retry with exponential backoff |
| NBD connection lost | Network issue (instant restore) | Pause VM, attempt reconnect |

---

## Appendix C: Cloud NAS Session Isolation Fix

The `mapDriveInUserSession()` function in `nas.go` must be wired up in `mountNASDrive()` to ensure Cloud NAS drives are visible in Explorer when the agent runs as a Windows Service.

**Required Change in `nas.go`:**

```go
func (r *Runner) mountNASDrive(ctx context.Context, payload MountNASPayload) error {
    // ... existing WebDAV server setup code ...
    
    // After WebDAV server starts and before direct mapping:
    httpTarget := fmt.Sprintf("http://127.0.0.1:%d/", port)
    
    // Phase 1: Try to map in user session (for service mode)
    // This creates the drive mapping in the interactive user's session
    // so it appears in Explorer
    if err := r.mapDriveInUserSession(ctx, driveLetter, httpTarget); err != nil {
        log.Printf("agent: user session mapping failed (may be running interactively or no user logged in): %v", err)
        // Continue to fallback - don't return error
    }
    
    // Phase 2: Fallback direct mapping (for interactive mode or if session mapping failed)
    // ... existing PowerShell mapping code ...
}
```

**Note:** The `mapDriveInUserSession()` function already exists in `nas.go` (lines 83-194) and is fully implemented. It just needs to be called from `mountNASDrive()`.

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | Dec 2025 | EazyBackup Team | Initial design document |
| 1.1 | Dec 2025 | EazyBackup Team | Added agent integration section, Windows Service mode, Cloud NAS session isolation, aligned with existing runner.go/kopia.go patterns, removed KopiaSnapshotter interface in favor of Runner methods |

# Hyper-V Backup Engine Integration – Project Description

**Version:** 1.0  
**Date:** December 2025  
**Companion Document:** `HYPERV_BACKUP_ENGINE.md` (detailed technical specifications)

---

## Overview

This document provides a high-level walkthrough for developers integrating the Hyper-V backup engine into the existing e3-backup-agent. It describes the current architecture, what exists today, and the step-by-step plan to add Hyper-V VM backup capability.

**Goal:** Add Hyper-V virtual machine backup as a new engine type (`hyperv`) to the existing Windows backup agent, alongside the existing `sync`, `kopia`, and `disk_image` engines.

---

## Table of Contents

1. [Project Scope](#project-scope)
2. [Current Agent Architecture](#current-agent-architecture)
3. [Codebase Map](#codebase-map)
4. [What We Have Today](#what-we-have-today)
5. [What We're Building](#what-were-building)
6. [Integration Points](#integration-points)
7. [Implementation Plan](#implementation-plan)
8. [Files to Create](#files-to-create)
9. [Files to Modify](#files-to-modify)
10. [Testing Strategy](#testing-strategy)
11. [Reference Documentation](#reference-documentation)

---

## Project Scope

### In Scope
- Full VM backup (all disks + configuration) to existing Kopia/S3 infrastructure
- Incremental backup using RCT (Resilient Change Tracking)
- Application-consistent backups via VSS for Windows VMs
- Linux VM backup with fsfreeze support
- Integration with existing WHMCS dashboard and job management
- Single agent executable supporting all engines simultaneously

### Out of Scope (Future Phases)
- Instant restore (NBD/iSCSI server) – Phase 5
- Granular file restore from VHDX – Phase 6
- Cross-host restore – Phase 4

---

## Current Agent Architecture

### High-Level Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              E3-BACKUP-AGENT                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ENTRY POINT: cmd/agent/main.go                                             │
│  └──▶ Loads config, starts service, calls runner.Start()                   │
│                                                                             │
│  POLLING LOOP: internal/agent/runner.go                                     │
│  └──▶ Runner.Start() polls server every N seconds                          │
│      ├──▶ Runner.pollOnce() → client.NextRun() → runRun()                   │
│      ├──▶ Runner.commandLoop() → PollPendingCommands() → restore/maint      │
│      └──▶ Runner.reportVolumesLoop() → reports available volumes            │
│                                                                             │
│  ENGINE DISPATCH: runner.go:runRun()                                        │
│  └──▶ switch run.Engine {                                                   │
│        case "kopia":      return r.runKopia(run)                            │
│        case "disk_image": return r.runDiskImage(run)                        │
│        case "hyperv":     return r.runHyperV(run)     ◀── NEW               │
│        default:           return r.runSync(run)                             │
│      }                                                                      │
│                                                                             │
│  BACKUP ENGINES:                                                            │
│  ├── runSync()      → Embedded rclone sync to S3                            │
│  ├── runKopia()     → Kopia snapshot for file/folder backup                 │
│  ├── runDiskImage() → VSS + stream whole disk to Kopia                      │
│  └── runHyperV()    → Hyper-V checkpoint + VHDX to Kopia    ◀── NEW         │
│                                                                             │
│  STORAGE LAYER: internal/agent/kopia.go                                     │
│  └──▶ Kopia repository management, snapshot, restore                        │
│                                                                             │
│  API COMMUNICATION: internal/agent/api_client.go                            │
│  └──▶ HTTP client for WHMCS addon (enroll, next_run, update, events)        │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Key Design Patterns

1. **Engine as a method on Runner** – Each backup engine is a method like `runKopia()`, `runDiskImage()`. No interfaces; direct method calls.

2. **Progress via callbacks** – Engines report progress by calling `r.client.UpdateRun()` and `r.pushEvents()`.

3. **Context cancellation** – All engines accept `context.Context` and check for cancel via command polling.

4. **Build tags for platform-specific code** – Windows-only code uses `//go:build windows`, with stubs for other platforms.

5. **Kopia as the storage layer** – All engines use Kopia for deduplication, encryption, and S3 upload.

---

## Codebase Map

```
e3-backup-agent/
├── cmd/
│   └── agent/
│       └── main.go                 # Entry point, service setup
│
├── internal/
│   └── agent/
│       ├── config.go               # AgentConfig struct, YAML loading
│       ├── runner.go               # Main polling loop, engine dispatch
│       ├── api_client.go           # HTTP client for WHMCS APIs
│       ├── kopia.go                # Kopia snapshot, restore, maintenance
│       ├── nas.go                  # Cloud NAS WebDAV mounting
│       │
│       ├── disk_image.go           # Disk image engine (cross-platform)
│       ├── disk_image_windows.go   # Windows VSS + device streaming
│       ├── disk_image_linux.go     # Linux device streaming
│       ├── disk_image_stub.go      # Stub for unsupported platforms
│       │
│       ├── stream_entry.go         # Kopia entry for streaming data
│       ├── stream_entry_windows.go # Windows-specific streaming
│       ├── stream_entry_parallel.go# Parallel upload support
│       │
│       ├── volumes.go              # Volume/drive detection
│       ├── volumes_windows.go      # Windows volume detection
│       ├── volumes_linux.go        # Linux volume detection
│       │
│       ├── network_share.go        # UNC path authentication
│       ├── filesystem.go           # Directory browsing
│       ├── backends.go             # Backend helpers
│       ├── block_cache.go          # Block caching utilities
│       │
│       └── vhdx/
│           └── writer.go           # VHDX file writing (unused currently)
│
├── go.mod                          # Go module (uses Kopia, rclone, go-vss)
├── go.sum
├── Makefile                        # Build targets
└── README.md
```

### Key Files to Understand

| File | Purpose | Lines |
|------|---------|-------|
| `runner.go` | Polling loop, engine switch, progress reporting | ~1100 |
| `api_client.go` | All WHMCS API communication, NextRunResponse/RunUpdate structs | ~530 |
| `kopia.go` | Kopia snapshot/restore, repository management, progress | ~1460 |
| `disk_image_windows.go` | VSS snapshot creation, device streaming (reference for VSS) | ~200 |
| `nas.go` | Cloud NAS mounting (reference for mapDriveInUserSession) | ~920 |

---

## What We Have Today

### Existing Backup Engines

| Engine | File(s) | Description |
|--------|---------|-------------|
| `sync` | `runner.go:runSync()` | Rclone-based file sync to S3 (legacy) |
| `kopia` | `runner.go:runKopia()`, `kopia.go` | Kopia dedup backup for files/folders |
| `disk_image` | `runner.go:runDiskImage()`, `disk_image_*.go` | Whole disk image via VSS |

### VSS Support (Already Implemented)

The disk_image engine already uses VSS via `github.com/mxk/go-vss`:

```go
// disk_image_windows.go (existing pattern)
import "github.com/mxk/go-vss"

func (r *Runner) createDiskImageStream(ctx context.Context, volume string) (*deviceStreamReader, error) {
    // Create VSS snapshot
    snap, err := vss.Create(volume + "\\")
    if err != nil {
        return nil, fmt.Errorf("vss create: %w", err)
    }
    // ... stream from snapshot device path ...
}
```

### Kopia Integration (Already Implemented)

All engines use the same Kopia repository infrastructure:

```go
// kopia.go (existing patterns to follow)
func (r *Runner) kopiaSnapshot(ctx context.Context, run *NextRunResponse) error { ... }
func (r *Runner) kopiaSnapshotWithEntry(ctx context.Context, run *NextRunResponse, entryOverride kopiafs.Entry, declaredSize int64) error { ... }
func (r *Runner) kopiaRestore(ctx context.Context, run *NextRunResponse, manifestID, targetPath string) error { ... }
func (r *Runner) kopiaMaintenance(ctx context.Context, run *NextRunResponse, mode string) error { ... }
```

### API Structs (Need Extension)

```go
// api_client.go - Current NextRunResponse
type NextRunResponse struct {
    RunID                   int64          `json:"run_id"`
    JobID                   int64          `json:"job_id"`
    Engine                  string         `json:"engine"`  // "sync", "kopia", "disk_image"
    SourcePath              string         `json:"source_path"`
    // ... S3 destination fields ...
    DiskSourceVolume        string         `json:"disk_source_volume"`
    DiskImageFormat         string         `json:"disk_image_format"`
    // Need to add: HyperVConfig, HyperVVMs
}
```

---

## What We're Building

### New Hyper-V Backup Engine

A new engine type `hyperv` that:

1. **Discovers VMs** – Queries Hyper-V for VM list via PowerShell
2. **Creates VSS checkpoints** – Application-consistent snapshots of running VMs
3. **Reads changed blocks** – Uses RCT (Resilient Change Tracking) for incremental backups
4. **Streams VHDX to Kopia** – Leverages existing Kopia infrastructure for dedup/upload
5. **Manages checkpoint lifecycle** – Creates, merges, removes Hyper-V checkpoints
6. **Reports progress** – Uses existing UpdateRun/pushEvents patterns

### Key Components

```
┌──────────────────────────────────────────────────────────────────────────┐
│                     HYPER-V ENGINE COMPONENTS                            │
├──────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  runner.go                                                               │
│  └── runHyperV(run) ─────────┬──▶ hyperv_backup.go                       │
│                              │     └── backupHyperVVM()                  │
│                              │           │                               │
│                              │           ▼                               │
│                              │     hyperv/manager.go                     │
│                              │     ├── ListVMs()                         │
│                              │     ├── GetVM()                           │
│                              │     ├── CreateVSSCheckpoint()             │
│                              │     ├── CreateLinuxConsistentCheckpoint() │
│                              │     ├── MergeCheckpoint()                 │
│                              │     └── (PowerShell execution)            │
│                              │                                           │
│                              │     hyperv/rct.go                         │
│                              │     ├── GetChangedBlocks()                │
│                              │     ├── ValidateRCTChain()                │
│                              │     └── GetCurrentRCTIDs()                │
│                              │                                           │
│                              └──▶ kopia.go (existing)                    │
│                                   └── kopiaSnapshotWithEntry()           │
│                                                                          │
└──────────────────────────────────────────────────────────────────────────┘
```

---

## Integration Points

### 1. Runner Engine Dispatch

**File:** `internal/agent/runner.go`  
**Location:** `runRun()` method (around line 465)

```go
// BEFORE:
switch engine {
case "kopia":
    return r.runKopia(run)
case "disk_image":
    return r.runDiskImage(run)
default:
    return r.runSync(run)
}

// AFTER:
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
```

### 2. API Response Extension

**File:** `internal/agent/api_client.go`

Add Hyper-V fields to `NextRunResponse`:

```go
type NextRunResponse struct {
    // ... existing fields ...
    
    // NEW: Hyper-V specific fields
    HyperVConfig *HyperVConfig `json:"hyperv_config,omitempty"`
    HyperVVMs    []HyperVVMRun `json:"hyperv_vms,omitempty"`
}

// NEW types
type HyperVConfig struct {
    VMs              []string `json:"vms"`
    EnableRCT        bool     `json:"enable_rct"`
    ConsistencyLevel string   `json:"consistency_level"`
}

type HyperVVMRun struct {
    VMID             int64             `json:"vm_id"`
    VMName           string            `json:"vm_name"`
    VMGUID           string            `json:"vm_guid"`
    LastCheckpointID string            `json:"last_checkpoint_id"`
    LastRCTIDs       map[string]string `json:"last_rct_ids"`
}
```

Add Hyper-V results to `RunUpdate`:

```go
type RunUpdate struct {
    // ... existing fields ...
    
    // NEW: Hyper-V specific fields
    DiskManifestsJSON map[string]string  `json:"disk_manifests_json,omitempty"`
    HyperVResults     []HyperVVMResult   `json:"hyperv_results,omitempty"`
}

type HyperVVMResult struct {
    VMID             int64             `json:"vm_id"`
    BackupType       string            `json:"backup_type"`
    CheckpointID     string            `json:"checkpoint_id"`
    RCTIDs           map[string]string `json:"rct_ids"`
    ChangedBytes     int64             `json:"changed_bytes"`
    ConsistencyLevel string            `json:"consistency_level"`
    Error            string            `json:"error,omitempty"`
}
```

### 3. Command Handler Extension

**File:** `internal/agent/runner.go`  
**Location:** `executePendingCommand()` (around line 196)

Add handler for Hyper-V restore commands when restore is implemented:

```go
case "hyperv_restore":
    r.executeHyperVRestoreCommand(ctx, cmd)
```

---

## Implementation Plan

### Phase 1: Foundation (Recommended First)

**Goal:** Basic full VM backup working end-to-end without RCT

| Step | Task | Files | Est. |
|------|------|-------|------|
| 1.1 | Create `hyperv/` package skeleton | `hyperv/manager.go`, `hyperv/rct.go` | 1 day |
| 1.2 | Implement `Manager.ListVMs()` | `hyperv/manager.go` | 1 day |
| 1.3 | Implement `Manager.GetVM()` | `hyperv/manager.go` | 0.5 day |
| 1.4 | Implement `Manager.CreateVSSCheckpoint()` | `hyperv/manager.go` | 1 day |
| 1.5 | Create `hyperv_backup.go` with `runHyperV()` | `hyperv_backup.go` | 2 days |
| 1.6 | Create `hyperv_stub.go` for non-Windows | `hyperv_stub.go` | 0.5 day |
| 1.7 | Extend API structs | `api_client.go` | 0.5 day |
| 1.8 | Add engine case in `runner.go` | `runner.go` | 0.5 day |
| 1.9 | Test full backup of single VM | - | 2 days |

**Deliverable:** Can backup a single Windows VM to S3 using full VHDX snapshot.

### Phase 2: RCT Integration

**Goal:** Efficient incremental backups

| Step | Task | Files | Est. |
|------|------|-------|------|
| 2.1 | Implement `Manager.EnableRCT()` | `hyperv/manager.go` | 0.5 day |
| 2.2 | Implement `RCTEngine.GetChangedBlocks()` | `hyperv/rct.go` | 2 days |
| 2.3 | Implement `RCTEngine.ValidateRCTChain()` | `hyperv/rct.go` | 1 day |
| 2.4 | Add incremental backup logic to `backupHyperVVM()` | `hyperv_backup.go` | 2 days |
| 2.5 | Handle RCT chain breaks (fallback to full) | `hyperv_backup.go` | 1 day |
| 2.6 | Test incremental backup cycle | - | 2 days |

**Deliverable:** Second backup of a VM only transfers changed blocks.

### Phase 3: Linux VM Support

**Goal:** Support Linux guest VMs with fsfreeze

| Step | Task | Files | Est. |
|------|------|-------|------|
| 3.1 | Implement Linux VM detection | `hyperv/manager.go` | 1 day |
| 3.2 | Implement `CreateLinuxConsistentCheckpoint()` | `hyperv/manager.go` | 1 day |
| 3.3 | Test Linux VM backup | - | 2 days |

**Deliverable:** Can backup Linux VMs with application consistency.

---

## Files to Create

### New Files in `internal/agent/hyperv/`

| File | Purpose |
|------|---------|
| `manager.go` | Hyper-V WMI/PowerShell operations (ListVMs, GetVM, checkpoints) |
| `rct.go` | RCT change tracking (GetChangedBlocks, ValidateRCTChain) |
| `vss.go` | VSS-specific checkpoint creation helpers |
| `backup.go` | Type definitions (VMInfo, DiskInfo, CheckpointInfo, etc.) |

### New Files in `internal/agent/`

| File | Purpose |
|------|---------|
| `hyperv_backup.go` | Windows: `runHyperV()`, `backupHyperVVM()` |
| `hyperv_stub.go` | Non-Windows: stub returning "not supported" error |

---

## Files to Modify

### Must Modify

| File | Changes |
|------|---------|
| `runner.go` | Add `case "hyperv": return r.runHyperV(run)` in engine switch |
| `api_client.go` | Add `HyperVConfig`, `HyperVVMs` to `NextRunResponse`; add `DiskManifestsJSON`, `HyperVResults` to `RunUpdate` |

### May Modify (For Features)

| File | Potential Changes |
|------|-------------------|
| `kopia.go` | May need helper for streaming VHDX blocks (or reuse `kopiaSnapshotWithEntry`) |
| `nas.go` | Wire up `mapDriveInUserSession()` in `mountNASDrive()` (unrelated but documented) |

---

## Testing Strategy

### Unit Testing

1. **Mock PowerShell output** – Test JSON parsing of VM list, RCT data
2. **RCT chain validation** – Test detection of chain breaks
3. **Backup type selection** – Test full vs incremental decision logic

### Integration Testing

1. **Local Hyper-V Host**
   - Create test Gen2 VM with VHDX
   - Run full backup, verify manifest created
   - Run incremental, verify RCT used
   - Simulate RCT break, verify fallback to full

2. **S3 Verification**
   - Verify deduplicated blocks in Kopia repository
   - Test restore from backup

### Test VMs

Create these VMs for testing:

| VM Name | Generation | OS | Disk Size | Purpose |
|---------|------------|-----|-----------|---------|
| `TestVM-Win` | 2 | Windows Server 2022 | 40GB | Windows VSS testing |
| `TestVM-Linux` | 2 | Ubuntu 22.04 | 20GB | Linux fsfreeze testing |
| `TestVM-Large` | 2 | Windows 10 | 200GB | Performance/RCT testing |

---

## Reference Documentation

### Project Documents

| Document | Location | Purpose |
|----------|----------|---------|
| **Hyper-V Engine Spec** | `docs/HYPERV_BACKUP_ENGINE.md` | Complete technical design, API specs, DB schema |
| **Agent Overview** | `docs/LOCAL_AGENT_OVERVIEW.md` | Full agent architecture and existing engines |
| **Disk Image Engine** | `docs/LOCAL_AGENT_DISK_IMAGE.md` | Reference for VSS patterns |
| **Cloud NAS** | `docs/CLOUD_NAS.md` | Reference for PowerShell patterns |

### External References

| Topic | Link |
|-------|------|
| Hyper-V PowerShell | https://docs.microsoft.com/en-us/powershell/module/hyper-v/ |
| RCT Documentation | https://docs.microsoft.com/en-us/windows-server/virtualization/hyper-v/manage/resilient-change-tracking |
| Kopia Documentation | https://kopia.io/docs/ |
| go-vss Library | https://github.com/mxk/go-vss |

### Key PowerShell Commands

```powershell
# List VMs
Get-VM | Select-Object Name, State, Generation, VMId

# Create production checkpoint (VSS)
Checkpoint-VM -Name "MyVM" -SnapshotName "Backup" -SnapshotType Production

# Query changed blocks
Get-VMHardDiskDriveChangedBlockInformation -VMHardDiskDrive $disk -BaseSnapshot $checkpoint

# Enable RCT
Set-VMHardDiskDrive -VMHardDiskDrive $disk -SupportPersistentReservations $true
```

---

## Development Environment Setup

### Prerequisites

1. **Windows 10/11 Pro or Server 2019+** with Hyper-V enabled
2. **Go 1.24+** installed
3. **PowerShell 5.1+** (comes with Windows)
4. **Test Hyper-V VM** (Generation 2, VHDX format)

### Build Commands

```bash
# Build Windows agent
cd e3-backup-agent
go build -o bin/e3-backup-agent.exe ./cmd/agent

# Run tests
go test ./internal/agent/...

# Run with verbose logging
./bin/e3-backup-agent.exe -config agent.conf -debug
```

### Agent Config for Testing

```yaml
api_base_url: "https://your-whmcs-server/modules/addons/cloudstorage"
agent_token: "your-test-token"
poll_interval_secs: 10
run_dir: "C:\\EazyBackup\\runs"
log_level: debug
```

---

## Success Criteria

### Phase 1 Complete When:

- [ ] `runHyperV()` executes for engine="hyperv" jobs
- [ ] VM list discovery works via PowerShell
- [ ] VSS checkpoint created for running VM
- [ ] VHDX file backed up to Kopia repository
- [ ] Manifest ID stored in database
- [ ] Progress events visible in dashboard

### Phase 2 Complete When:

- [ ] RCT enabled on VM disks
- [ ] Changed blocks queried after first backup
- [ ] Second backup transfers only changed data
- [ ] RCT chain break detected and handled
- [ ] Backup type (Full/Incremental) visible in dashboard

### Phase 3 Complete When:

- [ ] Linux VMs detected correctly
- [ ] fsfreeze triggered via Hyper-V IC
- [ ] Linux VM backups are application-consistent

---

## Getting Started

1. **Read** `HYPERV_BACKUP_ENGINE.md` for complete technical specifications
2. **Understand** `runner.go` engine dispatch pattern
3. **Study** `disk_image_windows.go` for VSS usage reference
4. **Create** `internal/agent/hyperv/` directory
5. **Start with** `manager.go` ListVMs() – simplest PowerShell integration
6. **Test incrementally** – each PowerShell function before moving on

Hyper-V Backup Engine Integration Plan
Architecture Overview
flowchart TB
    subgraph WHMCS[WHMCS Server]
        UI[Client Area UI]
        API[Agent APIs]
        DB[(MySQL Database)]
    end

    subgraph Agent[e3-backup-agent]
        Runner[runner.go]
        HyperVBackup[hyperv_backup.go]
        HyperVPkg[hyperv package]
        Kopia[kopia.go]
    end

    subgraph HyperVHost[Hyper-V Host]
        VMs[Virtual Machines]
        VSS[VSS Service]
        RCT[RCT Tracking]
    end

    subgraph Storage[S3 Storage]
        Repo[Kopia Repository]
    end

    UI --> API
    API --> DB
    Runner --> HyperVBackup
    HyperVBackup --> HyperVPkg
    HyperVPkg --> VSS
    HyperVPkg --> RCT
    HyperVBackup --> Kopia
    Kopia --> Repo
    VMs --> VSS
Data Flow - Backup Execution
sequenceDiagram
    participant WHMCS
    participant Agent
    participant HyperV as Hyper-V Manager
    participant RCT as RCT Engine
    participant Kopia

    WHMCS->>Agent: NextRun with hyperv_config
    Agent->>HyperV: GetVM
    Agent->>RCT: ValidateRCTChain
    alt RCT Valid
        Agent->>Agent: backupType = incremental
    else RCT Invalid
        Agent->>Agent: backupType = full
    end
    Agent->>HyperV: CreateVSSCheckpoint
    alt Incremental
        Agent->>RCT: GetChangedBlocks
        Agent->>Kopia: Stream sparse blocks
    else Full
        Agent->>Kopia: Stream full VHDX
    end
    Agent->>HyperV: MergeCheckpoint
    Agent->>WHMCS: UpdateRun with results
---

Component Breakdown
1. Go Agent (New Files)
| File | Purpose | Est. Lines |

|------|---------|------------|

| internal/agent/hyperv/manager.go | PowerShell wrapper for Hyper-V operations | ~300 |

| internal/agent/hyperv/rct.go | RCT change tracking queries | ~200 |

| internal/agent/hyperv/types.go | Shared types (VMInfo, DiskInfo, etc.) | ~100 |

| internal/agent/hyperv_backup.go | Windows: runHyperV(), backup orchestration | ~400 |

| internal/agent/hyperv_stub.go | Non-Windows stub | ~15 |

| internal/agent/hyperv_stream.go | Sparse VHDX block streaming for Kopia | ~250 |

2. Go Agent (Modifications)
| File | Changes |

|------|---------|

| runner.go | Add case "hyperv" at line 465 |

| api_client.go | Extend NextRunResponse, RunUpdate with Hyper-V fields |

3. PHP/WHMCS (New Files)
| File | Purpose |

|------|---------|

| sql/hyperv_schema.sql | Database migration for Hyper-V tables |

| api/agent_hyperv_discover.php | VM discovery endpoint |

| api/agent_hyperv_update_checkpoint.php | Checkpoint/RCT state updates |

| pages/cloudbackup_hyperv.php | Client area Hyper-V management page |

| templates/cloudbackup_hyperv.tpl | Smarty template for VM list/config |

4. PHP/WHMCS (Modifications)
| File | Changes |

|------|---------|

| api/agent_next_run.php | Add hyperv_config, hyperv_vms to response |

| api/agent_update_run.php | Handle disk_manifests_json, hyperv_results |

---

Implementation Phases
Phase A: Database Schema (Day 1)
Create schema file and apply migrations:

File: sql/hyperv_schema.sql

-- Extend jobs table
ALTER TABLE s3_cloudbackup_jobs 
  MODIFY COLUMN engine ENUM('sync','kopia','disk_image','hyperv') DEFAULT 'sync',
  MODIFY COLUMN source_type ENUM('local','network_share','disk_volume','hyperv') DEFAULT 'local',
  ADD COLUMN hyperv_enabled BOOLEAN DEFAULT FALSE,
  ADD COLUMN hyperv_config JSON NULL;

-- Add disk_manifests to runs table  
ALTER TABLE s3_cloudbackup_runs
  ADD COLUMN disk_manifests_json JSON NULL;

-- New tables (see HYPERV_BACKUP_ENGINE.md for full schema)
CREATE TABLE s3_hyperv_vms (...);
CREATE TABLE s3_hyperv_vm_disks (...);
CREATE TABLE s3_hyperv_checkpoints (...);
CREATE TABLE s3_hyperv_backup_points (...);
Phase B: Go Agent Core (Days 2-6)
B1. Type Definitions
File: internal/agent/hyperv/types.go

package hyperv

type VMInfo struct {
    ID              string     `json:"id"`
    Name            string     `json:"name"`
    State           string     `json:"state"`
    Generation      int        `json:"generation"`
    IsLinux         bool       `json:"is_linux"`
    RCTEnabled      bool       `json:"rct_enabled"`
    Disks           []DiskInfo `json:"disks"`
}

type DiskInfo struct {
    Path         string `json:"path"`
    SizeBytes    int64  `json:"size_bytes"`
    RCTEnabled   bool   `json:"rct_enabled"`
    RCTID        string `json:"rct_id"`
}

type ChangedBlockRange struct {
    Offset int64 `json:"offset"`
    Length int64 `json:"length"`
}
B2. Hyper-V Manager
File: internal/agent/hyperv/manager.go

Key functions:

ListVMs(ctx) ([]VMInfo, error) - PowerShell Get-VM wrapper
GetVM(ctx, name) (*VMInfo, error) - Single VM details
EnableRCT(ctx, vmName) error - Enable change tracking
CreateVSSCheckpoint(ctx, vmName) (*CheckpointInfo, error) - Production checkpoint
CreateReferenceCheckpoint(ctx, vmName) (*CheckpointInfo, error) - Standard checkpoint
MergeCheckpoint(ctx, vmName, cpID) error - Remove checkpoint
B3. RCT Engine
File: internal/agent/hyperv/rct.go

Key functions:

GetChangedBlocks(ctx, vmName, baseCheckpointID) ([]RCTInfo, error)
`GetCurrentRCTIDs(ctx, vmName) (map[string]string, error)`
ValidateRCTChain(ctx, vmName, expectedIDs) (bool, error)
B4. Sparse Stream Reader
File: internal/agent/hyperv_stream.go (Windows only)

// sparseVHDXReader implements io.Reader for Kopia streaming
// Only reads changed block ranges, returns zeros for unchanged ranges
type sparseVHDXReader struct {
    file          *os.File
    totalSize     int64
    changedRanges []ChangedBlockRange
    position      int64
}

func (r *sparseVHDXReader) Read(p []byte) (int, error) {
    // If position is in a changed range, read from file
    // Otherwise, fill with zeros (Kopia will dedupe against previous snapshot)
}
B5. API Client Extensions
File: internal/agent/api_client.go

Add to NextRunResponse:

HyperVConfig *HyperVConfig  `json:"hyperv_config,omitempty"`
HyperVVMs    []HyperVVMRun  `json:"hyperv_vms,omitempty"`
Add to RunUpdate:

DiskManifestsJSON map[string]string `json:"disk_manifests_json,omitempty"`
HyperVResults     []HyperVVMResult  `json:"hyperv_results,omitempty"`
B6. Backup Orchestration
File: internal/agent/hyperv_backup.go (Windows only)

func (r *Runner) runHyperV(run *NextRunResponse) error {
    // 1. Validate config
    // 2. For each VM in run.HyperVVMs:
    //    - Determine backup type (full vs incremental)
    //    - Create checkpoint
    //    - For each disk:
    //      - If incremental: get changed blocks, stream sparse
    //      - If full: stream entire VHDX
    //    - Merge checkpoint
    //    - Collect results
    // 3. Report all results (continue on VM failure)
}
B7. Runner Integration
File: internal/agent/runner.go - Line 465

switch engine {
case "kopia":
    return r.runKopia(run)
case "disk_image":
    return r.runDiskImage(run)
case "hyperv":
    return r.runHyperV(run)  // ADD THIS
default:
    return r.runSync(run)
}
Phase C: PHP API Extensions (Days 7-9)
C1. Extend agent_next_run.php
Add Hyper-V data fetching after line 400:

// Check if job is hyperv type
if ($engineVal === 'hyperv' && ($job->hyperv_enabled ?? false)) {
    $hypervConfig = json_decode($job->hyperv_config ?? '{}', true);
    $runData['hyperv_config'] = $hypervConfig;
    
    // Fetch VMs configured for this job with last checkpoint info
    $vms = Capsule::table('s3_hyperv_vms')
        ->where('job_id', $job->id)
        ->get();
    
    $runData['hyperv_vms'] = [];
    foreach ($vms as $vm) {
        $lastCheckpoint = Capsule::table('s3_hyperv_checkpoints')
            ->where('vm_id', $vm->id)
            ->where('is_active', true)
            ->first();
        
        $runData['hyperv_vms'][] = [
            'vm_id' => $vm->id,
            'vm_name' => $vm->vm_name,
            'vm_guid' => $vm->vm_guid,
            'last_checkpoint_id' => $lastCheckpoint->checkpoint_id ?? null,
            'last_rct_ids' => json_decode($lastCheckpoint->rct_ids ?? '{}', true),
        ];
    }
}
C2. Extend agent_update_run.php
Handle new fields in request body:

// Handle Hyper-V results
if (isset($body['hyperv_results']) && is_array($body['hyperv_results'])) {
    foreach ($body['hyperv_results'] as $vmResult) {
        // Update s3_hyperv_checkpoints
        // Create s3_hyperv_backup_points record
    }
}

// Handle disk manifests
if (isset($body['disk_manifests_json'])) {
    $updates['disk_manifests_json'] = json_encode($body['disk_manifests_json']);
}
C3. New Discovery Endpoint
File: api/agent_hyperv_discover.php

Receives VM list from agent, updates s3_hyperv_vms table.

Phase D: Client Area UI (Days 10-12)
D1. Hyper-V Management Page
File: pages/cloudbackup_hyperv.php

List VMs discovered on agent
Enable/disable VMs for backup
Configure RCT settings
View backup history per VM
D2. Template
File: templates/cloudbackup_hyperv.tpl

Following existing style guide from CLIENT_AREA_STYLEGUIDE.MD.

---

Task Dependency Graph
flowchart LR
    subgraph PhaseA[Phase A: Schema]
        A1[hyperv_schema.sql]
    end

    subgraph PhaseB[Phase B: Go Agent]
        B1[hyperv/types.go]
        B2[hyperv/manager.go]
        B3[hyperv/rct.go]
        B4[hyperv_stream.go]
        B5[api_client.go extensions]
        B6[hyperv_backup.go]
        B7[runner.go case]
        B8[hyperv_stub.go]
    end

    subgraph PhaseC[Phase C: PHP APIs]
        C1[agent_next_run.php]
        C2[agent_update_run.php]
        C3[agent_hyperv_discover.php]
    end

    subgraph PhaseD[Phase D: UI]
        D1[cloudbackup_hyperv.php]
        D2[cloudbackup_hyperv.tpl]
    end

    A1 --> C1
    A1 --> C2
    A1 --> C3
    
    B1 --> B2
    B1 --> B3
    B2 --> B6
    B3 --> B6
    B4 --> B6
    B5 --> B6
    B6 --> B7
    B6 --> B8
    
    C1 --> B6
    C2 --> B6
    
    C1 --> D1
    C2 --> D1
    C3 --> D1
    D1 --> D2
---

Suggested Implementation Order
| Order | Task ID | File/Component | Dependencies | Est. Effort |

|-------|---------|----------------|--------------|-------------|

| 1 | A1 | sql/hyperv_schema.sql | None | 0.5 day |

| 2 | B1 | hyperv/types.go | None | 0.5 day |

| 3 | B2 | hyperv/manager.go | B1 | 2 days |

| 4 | B3 | hyperv/rct.go | B1, B2 | 1.5 days |

| 5 | B5 | api_client.go extensions | None | 0.5 day |

| 6 | C1 | agent_next_run.php | A1 | 0.5 day |

| 7 | C2 | agent_update_run.php | A1 | 0.5 day |

| 8 | C3 | agent_hyperv_discover.php | A1 | 0.5 day |

| 9 | B4 | hyperv_stream.go | B1, B3 | 2 days |

| 10 | B6 | hyperv_backup.go | B2, B3, B4, B5 | 3 days |

| 11 | B7 | runner.go modification | B6 | 0.25 day |

| 12 | B8 | hyperv_stub.go | B6 | 0.25 day |

| 13 | -- | Integration Testing | All B, C | 2 days |

| 14 | D1 | cloudbackup_hyperv.php | C1, C2, C3 | 1.5 days |

| 15 | D2 | cloudbackup_hyperv.tpl | D1 | 1 day |

Total Estimated Effort: ~16.5 days

---

Key Technical Decisions
1. Sparse Stream for RCT Incremental
The sparseVHDXReader will:

Accept a list of ChangedBlockRange from RCT
When Read() is called, check if current position falls within a changed range
If in changed range: read actual bytes from VHDX file
If not in changed range: return zeros (Kopia will dedupe these against previous snapshot)
This minimizes disk I/O while maintaining Kopia's content-addressed deduplication.

2. Multi-VM Failure Handling
In runHyperV(), each VM backup is wrapped in error handling:

for _, vmRun := range run.HyperVVMs {
    result, err := r.backupHyperVVM(ctx, run, vmRun, mgr, rct)
    if err != nil {
        result.Error = err.Error()
        lastErr = err  // Track for final status
        // Continue to next VM - don't return
    }
    allResults = append(allResults, result)
}
Final status is "partial_success" if some VMs succeeded and some failed.

3. RCT Chain Validation
Before each backup:

Check if last_checkpoint_id exists
Query current RCT IDs from Hyper-V
Compare with stored last_rct_ids
If mismatch: force full backup, log warning
If match: proceed with incremental
---

Files Summary
New Files (12 total)
Go Agent (7 files):

internal/agent/hyperv/types.go
internal/agent/hyperv/manager.go
internal/agent/hyperv/rct.go
internal/agent/hyperv_backup.go
internal/agent/hyperv_stub.go
internal/agent/hyperv_stream.go
PHP/WHMCS (5 files):

sql/hyperv_schema.sql
api/agent_hyperv_discover.php
api/agent_hyperv_update_checkpoint.php
pages/cloudbackup_hyperv.php
templates/cloudbackup_hyperv.tpl
Modified Files (4 total)
e3-backup-agent/internal/agent/runner.go - Line 465
e3-backup-agent/internal/agent/api_client.go - NextRunResponse, RunUpdate
accounts/modules/addons/cloudstorage/api/agent_next_run.php - Lines 378-410
accounts/modules/addons/cloudstorage/api/agent_update_run.php


# Hyper-V Restore - Phase 1: Foundation & Export-to-File

**Status**: Implemented  
**Date**: December 2025  
**Version**: 1.0.0

## Overview

Phase 1 implements the foundational restore capability for Hyper-V backups: exporting VM disk images (VHDX files) from Kopia snapshots to local filesystem paths. This provides a working restore path that users can manually attach to VMs in Hyper-V Manager.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           WHMCS Client Area                             │
│  ┌──────────────────┐    ┌──────────────────┐   ┌──────────────────┐   │
│  │ Hyper-V VMs Page │───▶│ Restore Wizard   │──▶│ Live Progress    │   │
│  │ (cloudbackup_    │    │ (cloudbackup_    │   │ (cloudbackup_    │   │
│  │  hyperv.tpl)     │    │  hyperv_restore) │   │  live.tpl)       │   │
│  └──────────────────┘    └──────────────────┘   └──────────────────┘   │
└───────────────────────────────────┬─────────────────────────────────────┘
                                    │ API Calls
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                           PHP API Layer                                  │
│  ┌──────────────────────────────┐   ┌──────────────────────────────┐   │
│  │ cloudbackup_hyperv_          │   │ cloudbackup_hyperv_          │   │
│  │ backup_points.php            │   │ start_restore.php            │   │
│  │ (List restore points)        │   │ (Initiate restore)           │   │
│  └──────────────────────────────┘   └──────────────────────────────┘   │
└───────────────────────────────────┬─────────────────────────────────────┘
                                    │ Database
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                           MySQL Database                                 │
│  ┌──────────────────┐   ┌──────────────────┐   ┌──────────────────┐    │
│  │ s3_hyperv_       │   │ s3_cloudbackup_  │   │ s3_cloudbackup_  │    │
│  │ backup_points    │   │ runs             │   │ run_commands     │    │
│  └──────────────────┘   └──────────────────┘   └──────────────────┘    │
└───────────────────────────────────┬─────────────────────────────────────┘
                                    │ Agent Poll
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                           Windows Agent                                  │
│  ┌──────────────────┐   ┌──────────────────┐   ┌──────────────────┐    │
│  │ runner.go        │──▶│ hyperv_restore   │──▶│ kopia.go         │    │
│  │ (Command router) │   │ .go              │   │ (VHDX restore)   │    │
│  └──────────────────┘   └──────────────────┘   └──────────────────┘    │
└───────────────────────────────────┬─────────────────────────────────────┘
                                    │ Kopia SDK
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                           S3-Compatible Storage                          │
│                      (Kopia Repository with VHDX snapshots)             │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Implementation Details

### 1. API Endpoints

#### `cloudbackup_hyperv_backup_points.php`

**Purpose**: List available backup points (restore points) for a Hyper-V VM.

**Location**: `accounts/modules/addons/cloudstorage/api/cloudbackup_hyperv_backup_points.php`

**Method**: GET

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `vm_id` | int | Yes | The VM ID to list backup points for |
| `limit` | int | No | Page size (default: 50, max: 200) |
| `offset` | int | No | Pagination offset (default: 0) |
| `type` | string | No | Filter by backup type: "Full" or "Incremental" |

**Response**:
```json
{
  "status": "success",
  "vm": {
    "id": 123,
    "vm_name": "DC01",
    "vm_guid": "abc-123-def-456",
    "generation": 2,
    "rct_enabled": true,
    "job_id": 42,
    "job_name": "Hyper-V Backup Job"
  },
  "disks": [
    {
      "id": 1,
      "disk_path": "C:\\VMs\\DC01\\Virtual Hard Disks\\disk0.vhdx",
      "disk_name": "disk0.vhdx",
      "controller_type": "SCSI",
      "vhd_format": "VHDX",
      "size_bytes": 53687091200
    }
  ],
  "backup_points": [
    {
      "id": 456,
      "run_id": 789,
      "backup_type": "Full",
      "manifest_id": "k3a9b2c1d4e5f6g7",
      "parent_backup_id": null,
      "disk_manifests": {
        "C:\\VMs\\DC01\\Virtual Hard Disks\\disk0.vhdx": "m1abc123"
      },
      "disk_count": 1,
      "total_size_bytes": 53687091200,
      "changed_size_bytes": null,
      "duration_seconds": 1234,
      "consistency_level": "Application",
      "created_at": "2025-12-10T03:00:00Z",
      "has_warnings": false,
      "warning_code": null,
      "warnings": [],
      "restore_chain": ["k3a9b2c1d4e5f6g7"],
      "restore_chain_length": 1,
      "is_restorable": true
    }
  ],
  "total": 25,
  "limit": 50,
  "offset": 0
}
```

**Notes**:
- Only shows backup points from successful or warning runs
- Calculates restore chain for incremental backups
- Returns `is_restorable: false` if chain is broken or disk manifests are missing

---

#### `cloudbackup_hyperv_start_restore.php`

**Purpose**: Initiate a Hyper-V disk restore operation.

**Location**: `accounts/modules/addons/cloudstorage/api/cloudbackup_hyperv_start_restore.php`

**Method**: POST

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `backup_point_id` | int | Yes | The backup point ID to restore from |
| `target_path` | string | Yes | Target directory path on agent machine |
| `disk_filter` | JSON array | No | Array of disk paths to restore (empty = all) |

**Response**:
```json
{
  "status": "success",
  "message": "Hyper-V restore started",
  "restore_run_id": 890,
  "restore_run_uuid": "abc-123-def-456",
  "command_id": 567,
  "job_id": 42,
  "vm_name": "DC01",
  "disks_to_restore": 2,
  "estimated_size_bytes": 107374182400,
  "backup_type": "Full",
  "restore_chain_length": 1
}
```

**Notes**:
- Creates a new run in `s3_cloudbackup_runs` with `run_type=hyperv_restore`
- Queues `hyperv_restore` command in `s3_cloudbackup_run_commands`
- Validates backup point ownership through VM -> Job -> Client chain
- For incremental backups, calculates and includes the full restore chain

---

### 2. Agent Implementation

#### Command Handler: `hyperv_restore.go`

**Location**: `e3-backup-agent/internal/agent/hyperv_restore.go` (Windows only)

**Stub**: `e3-backup-agent/internal/agent/hyperv_restore_stub.go` (non-Windows)

**Key Function**: `executeHyperVRestoreCommand(ctx context.Context, cmd PendingCommand)`

**Workflow**:
1. Parse command payload (backup_point_id, target_path, disk_manifests, restore_chain)
2. Create target directory
3. Update restore run status to "running"
4. For each disk in disk_manifests:
   - Push `HYPERV_RESTORE_DISK_STARTING` event
   - Call `kopiaRestoreVHDX()` to restore disk
   - Track per-disk progress
   - Push `HYPERV_RESTORE_DISK_COMPLETE` or `HYPERV_RESTORE_DISK_FAILED` event
5. Complete command with final status

**Command Payload Structure**:
```json
{
  "backup_point_id": 456,
  "vm_name": "DC01",
  "vm_guid": "abc-123-def-456",
  "target_path": "C:\\Restored\\DC01",
  "disk_manifests": {
    "C:\\VMs\\disk0.vhdx": "manifest_id_1",
    "C:\\VMs\\disk1.vhdx": "manifest_id_2"
  },
  "restore_chain": [
    {
      "backup_point_id": 400,
      "manifest_id": "base_manifest",
      "backup_type": "Full",
      "disk_manifests": { ... }
    },
    {
      "backup_point_id": 456,
      "manifest_id": "incr_manifest",
      "backup_type": "Incremental",
      "disk_manifests": { ... }
    }
  ],
  "backup_type": "Incremental",
  "restore_run_id": 890,
  "restore_run_uuid": "abc-123-def-456"
}
```

---

#### Kopia VHDX Restore: `kopiaRestoreVHDX()`

**Location**: `e3-backup-agent/internal/agent/kopia.go`

**Purpose**: Restore a single VHDX disk from its Kopia manifest to a local file.

**Key Features**:
- Connects to remote Kopia repository if local config doesn't exist
- Loads snapshot manifest by ID
- Uses `restore.Entry()` with `fullRestoreOutput` to stream actual content (not shallow entries)
- Progress tracking via `restoreProgressCounter`
- Handles file renaming if restored name differs from target name

---

### 3. UI Components

#### Hyper-V Restore Wizard

**Page Controller**: `accounts/modules/addons/cloudstorage/pages/cloudbackup_hyperv_restore.php`

**Template**: `accounts/modules/addons/cloudstorage/templates/cloudbackup_hyperv_restore.tpl`

**URL**: `index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_hyperv_restore&vm_id={vm_id}`

**Features**:
- 3-step wizard: Select Backup Point → Configure Restore → Review & Start
- Paginated backup point list with type filter (Full/Incremental)
- Disk selection checkboxes
- Target path configuration
- Warning for incremental backup selection
- Live progress redirect after restore start

**JavaScript (Alpine.js)**:
- Fetches backup points from API
- Manages step progression
- Handles restore submission
- Formats dates and byte sizes

---

#### Restore Button in Hyper-V VMs Page

**Template**: `accounts/modules/addons/cloudstorage/templates/cloudbackup_hyperv.tpl`

**Addition**: Restore button added to Actions column for each VM, linking to the restore wizard.

---

#### Enhanced Live Progress Page

**Page Controller**: `accounts/modules/addons/cloudstorage/pages/cloudbackup_live.php`

**Template**: `accounts/modules/addons/cloudstorage/templates/cloudbackup_live.tpl`

**Enhancements**:
- Detects `run_type=hyperv_restore` and `type=hyperv_restore` in stats_json
- Shows Hyper-V specific info box with VM name, disk count, and backup type
- Displays restore chain length for incremental restores

---

### 4. Event Messages

**Location**: `accounts/modules/addons/cloudstorage/lib/Client/CloudBackupEventFormatter.php`

**New Message IDs**:

| Message ID | Template |
|------------|----------|
| `HYPERV_RESTORE_STARTING` | Starting Hyper-V disk restore for VM "{vm_name}" ({disk_count} disks) to {target_path}. |
| `HYPERV_RESTORE_DISK_STARTING` | Restoring disk {disk_index}/{total_disks}: {disk_name}. |
| `HYPERV_RESTORE_DISK_PROGRESS` | Restoring disk: {disk_name} - {bytes_done} of {bytes_total}. |
| `HYPERV_RESTORE_DISK_COMPLETE` | Disk {disk_name} restored successfully ({disk_index}/{total_disks}). |
| `HYPERV_RESTORE_DISK_FAILED` | Disk {disk_name} restore failed: {message}. |
| `HYPERV_RESTORE_COMPLETE` | Hyper-V restore completed: {restored_disks}/{total_disks} disks restored to {target_path}. |
| `HYPERV_RESTORE_FAILED` | Hyper-V restore failed: {message}. |
| `HYPERV_RESTORE_QUEUED` | Hyper-V restore queued for VM "{vm_name}" ({disk_count} disks). |

---

## Files Summary

### New Files

| File | Purpose |
|------|---------|
| `api/cloudbackup_hyperv_backup_points.php` | List backup points for VM |
| `api/cloudbackup_hyperv_start_restore.php` | Initiate restore |
| `internal/agent/hyperv_restore.go` | Windows restore handler |
| `internal/agent/hyperv_restore_stub.go` | Non-Windows stub |
| `pages/cloudbackup_hyperv_restore.php` | Restore page controller |
| `templates/cloudbackup_hyperv_restore.tpl` | Restore UI template |
| `docs/HYPERV_RESTORE_PLAN.md` | This documentation |

### Modified Files

| File | Changes |
|------|---------|
| `internal/agent/runner.go` | Added `hyperv_restore` case |
| `internal/agent/kopia.go` | Added `kopiaRestoreVHDX()` function |
| `templates/cloudbackup_hyperv.tpl` | Added Restore button |
| `templates/cloudbackup_live.tpl` | Hyper-V restore info box |
| `pages/cloudbackup_live.php` | Hyper-V restore detection |
| `lib/Client/CloudBackupEventFormatter.php` | New event message IDs |
| `cloudstorage.php` | Added routes for hyperv views |

---

## Usage Guide

### Restoring Hyper-V VM Disks

1. Navigate to **Backup Jobs** → **Hyper-V** tab
2. Select a Hyper-V job to view VMs
3. Click **Restore** button next to the VM you want to restore
4. **Step 1: Select Backup Point**
   - Browse available restore points
   - Use type filter to show only Full backups (recommended for simpler recovery)
   - Select a backup point (must show "Ready" status)
5. **Step 2: Configure Restore**
   - Enter target path (e.g., `C:\Restored\DC01`)
   - Select which disks to restore (default: all)
6. **Step 3: Review & Start**
   - Verify settings
   - Click **Start Restore**
7. Monitor progress on the Live Progress page
8. After completion, manually attach VHDX files to a new/existing VM in Hyper-V Manager

---

## Known Limitations

### Phase 1 Scope

1. **Full backups only (recommended)** - While incremental backup selection is available, full backups provide simpler, more reliable recovery.

2. **Export only** - VHDX files are restored to disk; no automatic VM re-registration. User must manually attach disks to VM.

3. **No granular file recovery** - Cannot browse inside VHDX to restore individual files. This requires mounting the VHDX (Phase 2+).

4. **No cross-host restore validation** - Restore targets the same agent that performed the backup. Cross-host scenarios require additional configuration.

---

## Phase 2+ Roadmap

### Phase 2: Instant VM Recovery
- Live mount VHDX from Kopia using NBD/iSCSI
- Create VM configuration pointing to mounted disks
- Allow immediate VM boot from backup
- Background migration to production storage

### Phase 3: Granular File Recovery
- Mount VHDX read-only
- Browse files/folders inside VM disk
- Selective file/folder download
- Guest OS awareness for application item-level recovery

### Phase 4: Cross-Host Restore
- Restore to different Hyper-V host
- Network-based VHDX transfer
- VM configuration adaptation for new host
- Storage migration options

### Phase 5: Disaster Recovery Automation
- Pre-configured DR plans
- Automated failover testing
- One-click recovery orchestration
- Cloud DR target support

---

## Testing Checklist

- [ ] User can view list of Hyper-V backup points for a VM
- [ ] User can filter backup points by type (Full/Incremental)
- [ ] User can select a Full backup point and initiate restore
- [ ] Agent receives `hyperv_restore` command and executes it
- [ ] VHDX files are restored to specified target path
- [ ] Live progress shows per-disk restore status
- [ ] Restored VHDX can be manually attached to a new VM in Hyper-V Manager
- [ ] Error handling works correctly for missing manifests
- [ ] Cancellation works during restore operation

---

## Troubleshooting

### Restore fails with "Chain is broken"
- This indicates a required backup in the incremental chain is missing
- Try restoring from a Full backup instead
- Check that intermediate backups haven't been deleted

### Agent reports "Repo config not found"
- Agent will attempt to connect to remote repository
- Ensure S3 credentials are valid
- Check network connectivity to storage endpoint

### VHDX file is 0 bytes or corrupted
- Check agent logs for Kopia errors during restore
- Verify sufficient disk space on target path
- Check that source backup completed successfully

### Restore hangs at 0%
- Verify agent is running and connected
- Check for network issues between agent and storage
- Review agent logs for errors

---

## API Error Codes

| Error | Cause | Solution |
|-------|-------|----------|
| `vm_id is required` | Missing VM ID parameter | Provide valid vm_id |
| `VM not found or access denied` | Invalid VM ID or unauthorized | Check VM exists and user has access |
| `backup_point_id is required` | Missing backup point ID | Provide valid backup_point_id |
| `Backup point not found` | Invalid or unauthorized backup point | Check backup point exists |
| `No disks selected` | Empty disk filter with no default | Select at least one disk |
| `Restore chain is broken` | Missing parent backup in chain | Use Full backup or restore missing incrementals |
| `Hyper-V tables not initialized` | Database schema missing | Run module activation to create tables |

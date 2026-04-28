//go:build windows
// +build windows

package hyperv

import (
	"context"
	"encoding/json"
	"fmt"
	"strings"
	"time"

	"github.com/go-ole/go-ole"
	"github.com/go-ole/go-ole/oleutil"
)

// jsonUnmarshalLenient strips a UTF-8 BOM (which PowerShell sometimes emits
// when piping ConvertTo-Json through Out-Default) before unmarshalling.
func jsonUnmarshalLenient(s string, v any) error {
	s = strings.TrimSpace(s)
	s = strings.TrimPrefix(s, "\ufeff")
	return json.Unmarshal([]byte(s), v)
}

// ReferencePointInfo identifies a Hyper-V RCT reference point.
//
// Hyper-V models reference points as Msvm_VirtualSystemReferencePoint
// instances; their InstanceID is the durable identifier we hand back to the
// server (it is what the next backup will pass through as
// HyperVVMRun.LastCheckpointID).
type ReferencePointInfo struct {
	InstanceID   string    // Msvm_VirtualSystemReferencePoint.InstanceID
	WMIPath      string    // __PATH for use as a WMI REF
	VMID         string    // Owning Msvm_ComputerSystem.Name (VM GUID)
	CreationTime time.Time // ConfigurationDataRoot CreationTime, when present
}

// hostHasReferencePointService probes whether the connected host exposes
// Msvm_VirtualSystemReferencePointService. False on Windows Server 2012R2
// and older, where RCT does not exist and we must stay on Full backups.
//
// The probe is cached for the lifetime of the process: if the host is
// upgraded mid-backup the agent restart will pick up the new capability.
var refPointServiceCache onceCache

// HostHasReferencePointService returns true if RCT-based incrementals are
// possible on the local Hyper-V host.
func HostHasReferencePointService(ctx context.Context) bool {
	return refPointServiceCache.get(func() bool {
		sess, err := newWMISession()
		if err != nil {
			return false
		}
		defer sess.Close()
		return sess.classExists("Msvm_VirtualSystemReferencePointService")
	})
}

// RefPointConsistency selects the ConsistencyLevel passed in
// Msvm_VirtualSystemReferencePointSettingData. Application-consistent is
// the default and matches how Microsoft's docs recommend RCT be anchored;
// crash-consistent is the documented fallback when the VM cannot quiesce
// (e.g. Linux guests without a working hv_vss_daemon, or Windows guests
// where a guest VSS writer rejects the freeze). Both produce a usable RCT
// generation, and an incremental backup keyed off either is byte-identical
// from the agent's perspective — this only changes how Hyper-V flushes the
// guest before pinning the marker.
type RefPointConsistency uint8

const (
	// RefPointApplication = ConsistencyLevel 1 (Application).
	RefPointApplication RefPointConsistency = 1
	// RefPointCrash = ConsistencyLevel 0 (Crash).
	RefPointCrash RefPointConsistency = 0
)

// CreateReferencePoint pins an Application-consistent RCT reference point.
// Equivalent to CreateReferencePointWithConsistency(ctx, vmName, RefPointApplication).
func (m *Manager) CreateReferencePoint(ctx context.Context, vmName string) (*ReferencePointInfo, error) {
	return m.CreateReferencePointWithConsistency(ctx, vmName, RefPointApplication)
}

// CreateReferencePointWithConsistency pins a new RCT reference point
// against the live VM at the requested ConsistencyLevel.
//
// The reference point captures the current RCT generation of every VHDX
// attached to the VM. It is suitable as the LimitId input to a subsequent
// Msvm_ImageManagementService::GetVirtualDiskChanges call.
//
// We invoke the WMI method via PowerShell's Invoke-CimMethod rather than
// calling Msvm_VirtualSystemReferencePointService::CreateReferencePoint
// through go-ole / SWbem ExecMethod_ directly. The SWbem path repeatedly
// returns Hyper-V job error 32775 ("Element Not Available") on Windows
// Server 2025 even when the equivalent Invoke-CimMethod call from the
// same host succeeds; routing through Invoke-CimMethod is the supported
// surface and dodges whatever marshalling difference SWbem trips over.
//
// The previous reference point (if any) is NOT destroyed by this call;
// callers should keep the prior ID until the new backup has committed and
// then call DestroyReferencePoint(prior).
//
// On failure the returned error includes the Hyper-V job's ErrorCode,
// OperationalStatus, ErrorSummaryDescription and ErrorDescription verbatim
// so callers can branch on the actual reason (notably ErrorCode 32775,
// which means the VM still has an active production checkpoint or the
// AVHDX merge has not completed yet).
func (m *Manager) CreateReferencePointWithConsistency(ctx context.Context, vmName string, consistency RefPointConsistency) (*ReferencePointInfo, error) {
	script := fmt.Sprintf(`
$ErrorActionPreference = 'Stop'
$vm = Get-CimInstance -Namespace root/virtualization/v2 -ClassName Msvm_ComputerSystem -Filter "ElementName='%s'"
if ($null -eq $vm) { throw "VM '%s' not found" }
$svc = Get-CimInstance -Namespace root/virtualization/v2 -ClassName Msvm_VirtualSystemReferencePointService
$settings = New-CimInstance -Namespace root/virtualization/v2 -ClassName Msvm_VirtualSystemReferencePointSettingData -ClientOnly -Property @{ ConsistencyLevel = [byte]%d }
$ser = [Microsoft.Management.Infrastructure.Serialization.CimSerializer]::Create()
$mof = [System.Text.Encoding]::Unicode.GetString($ser.Serialize($settings, [Microsoft.Management.Infrastructure.Serialization.InstanceSerializationOptions]::None))
$r = Invoke-CimMethod -InputObject $svc -MethodName CreateReferencePoint -Arguments @{ AffectedSystem = $vm; ReferencePointSettings = $mof; ReferencePointType = [uint16]1 }
if ($r.ReturnValue -ne 0 -and $r.ReturnValue -ne 4096) { throw "CreateReferencePoint returned $($r.ReturnValue)" }
if ($r.Job) {
  $job = Get-CimInstance -InputObject $r.Job
  while ($job.JobState -lt 7) { Start-Sleep -Milliseconds 500; $job = Get-CimInstance -InputObject $r.Job }
  if ($job.JobState -ne 7) {
    $errCode = $job.ErrorCode
    $errDesc = $job.ErrorDescription
    $errSum  = $job.ErrorSummaryDescription
    $opStat  = ($job.OperationalStatus -join ',')
    $statDescs = ($job.StatusDescriptions -join '|')
    throw "Job failed state=$($job.JobState) errCode=$errCode opStatus=$opStat errSummary=$errSum errDesc=$errDesc statusDescs=$statDescs"
  }
}
$rp = $null
if ($r.ResultingReferencePoint) {
  $rp = Get-CimInstance -InputObject $r.ResultingReferencePoint
}
if ($null -eq $rp) {
  # Hyper-V on Server 2016-2022 set ResultingReferencePoint reliably,
  # but on Server 2025 it is frequently null even when the call
  # succeeds. Walk the namespace and match by VirtualSystemIdentifier
  # (the owning VM's GUID, which is $vm.Name in Msvm_ComputerSystem).
  # The legacy "Microsoft:<vmguid>\<rpguid>" InstanceID prefix used by
  # 2016-2022 has been replaced with a bare "Microsoft:<rpguid>" on
  # 2025, so the InstanceID-prefix filter we used to use no longer
  # works on the latest host. VirtualSystemIdentifier is exposed on
  # every supported version.
  $candidates = Get-CimInstance -Namespace root/virtualization/v2 -ClassName Msvm_VirtualSystemReferencePoint |
                Where-Object { $_.VirtualSystemIdentifier -eq $vm.Name }
  if (-not $candidates) {
    # Belt-and-braces fallback for the (unlikely) case that
    # VirtualSystemIdentifier is also blank on some build: match the
    # legacy InstanceID prefix.
    $candidates = Get-CimInstance -Namespace root/virtualization/v2 -ClassName Msvm_VirtualSystemReferencePoint |
                  Where-Object { $_.InstanceID -like ("Microsoft:" + $vm.Name + "*") }
  }
  if ($candidates) {
    $sorted = $candidates | Sort-Object CreationTime -Descending
    $rp = $sorted | Select-Object -First 1
  }
}
if ($null -eq $rp) { throw "no reference point returned" }
$ct = ""
if ($rp.CreationTime) { try { $ct = $rp.CreationTime.ToString("o") } catch {} }
@{ InstanceID = $rp.InstanceID; VMID = $vm.Name; CreationTime = $ct } | ConvertTo-Json -Compress
`, escapePSString(vmName), escapePSString(vmName), uint8(consistency))

	out, err := m.runPS(ctx, script)
	if err != nil {
		return nil, fmt.Errorf("CreateReferencePoint(%s, consistency=%d): %w", vmName, uint8(consistency), err)
	}
	out = strings.TrimSpace(out)
	if out == "" {
		return nil, fmt.Errorf("CreateReferencePoint(%s): empty PS output", vmName)
	}
	var raw struct {
		InstanceID   string `json:"InstanceID"`
		VMID         string `json:"VMID"`
		CreationTime string `json:"CreationTime"`
	}
	if err := jsonUnmarshalLenient(out, &raw); err != nil {
		return nil, fmt.Errorf("CreateReferencePoint(%s): parse PS output %q: %w", vmName, out, err)
	}
	info := &ReferencePointInfo{
		InstanceID: raw.InstanceID,
		VMID:       raw.VMID,
	}
	if t, perr := time.Parse(time.RFC3339Nano, raw.CreationTime); perr == nil {
		info.CreationTime = t
	}
	return info, nil
}

// DestroyReferencePoint removes a reference point previously returned by
// CreateReferencePoint. Safe to call with an empty/unknown ID; in that case
// it is a no-op.
//
// Like CreateReferencePoint, this routes through Invoke-CimMethod for
// reliability across Hyper-V versions.
func (m *Manager) DestroyReferencePoint(ctx context.Context, refPointInstanceID string) error {
	refPointInstanceID = strings.TrimSpace(refPointInstanceID)
	if refPointInstanceID == "" {
		return nil
	}
	script := fmt.Sprintf(`
$ErrorActionPreference = 'Stop'
$rp = Get-CimInstance -Namespace root/virtualization/v2 -ClassName Msvm_VirtualSystemReferencePoint -Filter "InstanceID='%s'"
if ($null -eq $rp) { exit 0 }
$svc = Get-CimInstance -Namespace root/virtualization/v2 -ClassName Msvm_VirtualSystemReferencePointService
$r = Invoke-CimMethod -InputObject $svc -MethodName DestroyReferencePoint -Arguments @{ AffectedReferencePoint = $rp }
if ($r.ReturnValue -ne 0 -and $r.ReturnValue -ne 4096) { throw "DestroyReferencePoint returned $($r.ReturnValue)" }
if ($r.Job) {
  $job = Get-CimInstance -InputObject $r.Job
  while ($job.JobState -lt 7) { Start-Sleep -Milliseconds 500; $job = Get-CimInstance -InputObject $r.Job }
  if ($job.JobState -ne 7) { throw "Job failed (state=$($job.JobState)): $($job.ErrorDescription)" }
}
`, escapePSString(refPointInstanceID))
	if _, err := m.runPS(ctx, script); err != nil {
		return fmt.Errorf("DestroyReferencePoint(%s): %w", refPointInstanceID, err)
	}
	return nil
}

// ListReferencePoints returns all RCT reference points associated with the
// named VM, newest first. Used both for diagnostics and to verify that the
// prior reference point still exists before deciding to do an incremental.
func (m *Manager) ListReferencePoints(ctx context.Context, vmName string) ([]ReferencePointInfo, error) {
	script := fmt.Sprintf(`
$ErrorActionPreference = 'Stop'
$vm = Get-CimInstance -Namespace root/virtualization/v2 -ClassName Msvm_ComputerSystem -Filter "ElementName='%s'"
if ($null -eq $vm) { '[]' ; exit 0 }
$rps = Get-CimInstance -Namespace root/virtualization/v2 -ClassName Msvm_VirtualSystemReferencePoint |
       Where-Object { $_.VirtualSystemIdentifier -eq $vm.Name -or $_.InstanceID -like ("Microsoft:" + $vm.Name + "*") } |
       Sort-Object CreationTime -Descending
$out = @()
foreach ($r in $rps) {
  $ct = ""
  if ($r.CreationTime) { try { $ct = $r.CreationTime.ToString("o") } catch {} }
  $out += @{ InstanceID = $r.InstanceID; VMID = $vm.Name; CreationTime = $ct }
}
,$out | ConvertTo-Json -Compress
`, escapePSString(vmName))
	out, err := m.runPS(ctx, script)
	if err != nil {
		return nil, fmt.Errorf("ListReferencePoints(%s): %w", vmName, err)
	}
	out = strings.TrimSpace(out)
	if out == "" || out == "null" || out == "[]" {
		return nil, nil
	}
	type rpRow struct {
		InstanceID   string `json:"InstanceID"`
		VMID         string `json:"VMID"`
		CreationTime string `json:"CreationTime"`
	}
	var raw []rpRow
	if err := jsonUnmarshalLenient(out, &raw); err != nil {
		// PowerShell on Server 2025 / PS7 sometimes wraps single-element
		// arrays as {"value":[...],"Count":N} when ConvertTo-Json runs in
		// pipeline context, breaking the bare-array unmarshal. Fall back
		// to unwrapping that envelope, then to single-object decoding for
		// pre-2025 hosts that emit one row without array brackets.
		var envelope struct {
			Value []rpRow `json:"value"`
		}
		if jerr := jsonUnmarshalLenient(out, &envelope); jerr == nil && len(envelope.Value) > 0 {
			raw = envelope.Value
		} else {
			var single rpRow
			if jerr := jsonUnmarshalLenient(out, &single); jerr == nil && single.InstanceID != "" {
				raw = append(raw, single)
			} else {
				return nil, fmt.Errorf("ListReferencePoints(%s): parse %q: %w", vmName, out, err)
			}
		}
	}
	infos := make([]ReferencePointInfo, 0, len(raw))
	for _, r := range raw {
		info := ReferencePointInfo{InstanceID: r.InstanceID, VMID: r.VMID}
		if t, perr := time.Parse(time.RFC3339Nano, r.CreationTime); perr == nil {
			info.CreationTime = t
		}
		infos = append(infos, info)
	}
	return infos, nil
}

// ReferencePointDiskRCTIDs returns the per-VHDX RCT tracking ID captured
// inside the given reference point. The returned map is keyed by the disk's
// host-side path (e.g. C:\VMs\foo\Virtual Hard Disks\foo.vhdx).
//
// These are the IDs the agent must hand back to the server so the next
// backup can pass them as LimitId to GetVirtualDiskChanges.
func (m *Manager) ReferencePointDiskRCTIDs(ctx context.Context, refPointInstanceID string) (map[string]string, error) {
	result := map[string]string{}
	if strings.TrimSpace(refPointInstanceID) == "" {
		return result, nil
	}
	script := fmt.Sprintf(`
$ErrorActionPreference = 'Stop'
$rp = Get-CimInstance -Namespace root/virtualization/v2 -ClassName Msvm_VirtualSystemReferencePoint -Filter "InstanceID='%s'"
if ($null -eq $rp) { '{}' ; exit 0 }
$out = @{}

# Path A (Server 2016-2022): the RP exposes one Msvm_StorageAllocationSettingData
# per disk via Get-CimAssociatedInstance, and that SASD carries the per-disk
# RCT generation in its VirtualSystemIdentifiers[0].
$assoc = Get-CimAssociatedInstance -InputObject $rp -ResultClassName Msvm_StorageAllocationSettingData -ErrorAction SilentlyContinue
foreach ($a in $assoc) {
  if ($null -eq $a.HostResource) { continue }
  $disk = $a.HostResource | Where-Object { $_ -match '\.(vhdx|vhd|avhdx)$' } | Select-Object -First 1
  if (-not $disk) { continue }
  $rct = $null
  if ($a.VirtualSystemIdentifiers -and $a.VirtualSystemIdentifiers.Length -gt 0) {
    $rct = $a.VirtualSystemIdentifiers[0]
  }
  if (-not $rct) { continue }
  $out[$disk] = $rct
}

# Path B (Server 2025): the SASD-via-association lookup returns nothing on
# Server 2025; the per-disk RCT generation lives directly on the reference
# point itself in two parallel arrays:
#   ResilientChangeTrackingIdentifiers[i]  = the LimitId for disk i
#   VirtualDiskIdentifiers[i]              = the disk identity (Microsoft:<vmguid>\<diskguid>\<C>\<L>\<TYPE>)
# We map each disk identity back to its host-side VHDX path by walking the
# matching Msvm_StorageAllocationSettingData instance, whose own InstanceID
# uses the same Microsoft:<vmguid>\<diskguid>\<C>\<L>\<TYPE> form. (Server
# 2025 currently leaves ResilientChangeTrackingIdentifiers empty; we still
# emit one entry per disk so the server tracks the chain, and downstream
# code treats an empty value as "no usable RCT generation".)
if ($out.Count -eq 0 -and $rp.VirtualDiskIdentifiers -and $rp.VirtualDiskIdentifiers.Length -gt 0) {
  $sasd = Get-CimInstance -Namespace root/virtualization/v2 -ClassName Msvm_StorageAllocationSettingData -ErrorAction SilentlyContinue
  for ($i = 0; $i -lt $rp.VirtualDiskIdentifiers.Length; $i++) {
    $vdi = $rp.VirtualDiskIdentifiers[$i]
    if (-not $vdi) { continue }
    $rct = ""
    if ($rp.ResilientChangeTrackingIdentifiers -and $i -lt $rp.ResilientChangeTrackingIdentifiers.Length) {
      $rct = [string]$rp.ResilientChangeTrackingIdentifiers[$i]
    }
    # Match the SASD whose InstanceID equals the disk identifier (or
    # differs only in the trailing type suffix, e.g. ...\L vs ...\D).
    $vdiBase = $vdi -replace '\\[A-Z]$',''
    $hit = $sasd | Where-Object {
      $iid = [string]$_.InstanceID
      $iid -eq $vdi -or ($iid -replace '\\[A-Z]$','') -eq $vdiBase
    } | Select-Object -First 1
    if (-not $hit) { continue }
    $disk = $hit.HostResource | Where-Object { $_ -match '\.(vhdx|vhd|avhdx)$' } | Select-Object -First 1
    if (-not $disk) { continue }
    if (-not $rct) { $rct = [string]$vdi }
    $out[$disk] = $rct
  }
}

$out | ConvertTo-Json -Compress
`, escapePSString(refPointInstanceID))
	out, err := m.runPS(ctx, script)
	if err != nil {
		return nil, fmt.Errorf("ReferencePointDiskRCTIDs(%s): %w", refPointInstanceID, err)
	}
	out = strings.TrimSpace(out)
	if out == "" || out == "{}" || out == "null" {
		return result, nil
	}
	if err := jsonUnmarshalLenient(out, &result); err != nil {
		return nil, fmt.Errorf("ReferencePointDiskRCTIDs(%s): parse %q: %w", refPointInstanceID, out, err)
	}
	return result, nil
}

// findVMByName resolves a VM (Msvm_ComputerSystem) by its ElementName.
// Caller MUST Release() the returned dispatch.
func (s *wmiSession) findVMByName(vmName string) (*ole.IDispatch, error) {
	wql := fmt.Sprintf("SELECT * FROM Msvm_ComputerSystem WHERE ElementName=%s", quoteWQL(vmName))
	set, err := s.execQuery(wql)
	if err != nil {
		return nil, err
	}
	defer set.Release()

	var found *ole.IDispatch
	err = forEachInstance(set, func(item *ole.IDispatch) error {
		if found != nil {
			return nil
		}
		item.AddRef()
		found = item
		return nil
	})
	if err != nil {
		if found != nil {
			found.Release()
		}
		return nil, err
	}
	if found == nil {
		return nil, fmt.Errorf("VM %s not found", vmName)
	}
	return found, nil
}

// findReferencePointByInstanceID returns the reference point dispatch and
// its __PATH. The dispatch must be Release()d by the caller.
func (s *wmiSession) findReferencePointByInstanceID(instanceID string) (*ole.IDispatch, string, error) {
	wql := fmt.Sprintf("SELECT * FROM Msvm_VirtualSystemReferencePoint WHERE InstanceID=%s", quoteWQL(instanceID))
	set, err := s.execQuery(wql)
	if err != nil {
		return nil, "", err
	}
	defer set.Release()

	var found *ole.IDispatch
	var path string
	err = forEachInstance(set, func(item *ole.IDispatch) error {
		if found != nil {
			return nil
		}
		item.AddRef()
		found = item
		path = getProp(item, "__PATH")
		return nil
	})
	if err != nil {
		if found != nil {
			found.Release()
		}
		return nil, "", err
	}
	if found == nil {
		return nil, "", fmt.Errorf("reference point %s not found", instanceID)
	}
	return found, path, nil
}

// listReferencePointsForVM returns all reference points whose owning VM
// (Msvm_ComputerSystem.Name) matches vmID, newest first.
func (s *wmiSession) listReferencePointsForVM(vmID string) ([]ReferencePointInfo, error) {
	if strings.TrimSpace(vmID) == "" {
		return nil, fmt.Errorf("listReferencePointsForVM: empty VM ID")
	}
	// Reference points carry the owning VM ID in their InstanceID prefix in
	// the form "Microsoft:GUID\..." — but rather than parse strings we walk
	// every reference point and compare its associated VM id.
	set, err := s.execQuery("SELECT * FROM Msvm_VirtualSystemReferencePoint")
	if err != nil {
		return nil, err
	}
	defer set.Release()

	var out []ReferencePointInfo
	err = forEachInstance(set, func(item *ole.IDispatch) error {
		info := refPointFromInstance(item)
		// A reference point's InstanceID has the form
		// "Microsoft:<VMGUID>\<RPGUID>" on every Hyper-V version we care
		// about; this avoids a second WMI round-trip per item.
		lower := strings.ToLower(info.InstanceID)
		if strings.Contains(lower, strings.ToLower(vmID)) {
			info.VMID = vmID
			out = append(out, *info)
		}
		return nil
	})
	if err != nil {
		return nil, err
	}
	// Newest first.
	for i, j := 0, len(out)-1; i < j; i, j = i+1, j-1 {
		if out[i].CreationTime.Before(out[j].CreationTime) {
			out[i], out[j] = out[j], out[i]
		}
	}
	return out, nil
}

func refPointFromInstance(rp *ole.IDispatch) *ReferencePointInfo {
	info := &ReferencePointInfo{
		InstanceID: getProp(rp, "InstanceID"),
		WMIPath:    getProp(rp, "__PATH"),
	}
	if t := getProp(rp, "CreationTime"); t != "" {
		// CIM_DATETIME format: yyyymmddHHMMSS.mmmmmmsUUU
		if len(t) >= 14 {
			parsed, err := time.Parse("20060102150405", t[:14])
			if err == nil {
				info.CreationTime = parsed
			}
		}
	}
	// We dereference and overwrite a value receiver, so callers see fields.
	out := *info
	return &out
}

// quoteWQL wraps a value as a WQL string literal, escaping single quotes
// and backslashes.
func quoteWQL(s string) string {
	r := strings.ReplaceAll(s, `\`, `\\`)
	r = strings.ReplaceAll(r, `'`, `\'`)
	return "'" + r + "'"
}

// escapeWQLPath escapes a __PATH for inclusion inside WQL ASSOCIATORS OF
// {...}. Backslashes must be doubled, and single quotes escaped.
func escapeWQLPath(p string) string {
	r := strings.ReplaceAll(p, `\`, `\\`)
	r = strings.ReplaceAll(r, `"`, `\"`)
	return r
}

// getStringArrayProp reads a SAFEARRAY-of-strings WMI property as []string.
func getStringArrayProp(obj *ole.IDispatch, name string) []string {
	v, err := oleutil.GetProperty(obj, name)
	if err != nil {
		return nil
	}
	defer v.Clear()
	if v.VT == ole.VT_NULL || v.VT == ole.VT_EMPTY {
		return nil
	}
	// String array surfaces as VT_ARRAY|VT_BSTR.
	if v.VT&ole.VT_ARRAY == 0 {
		single := v.ToString()
		if single == "" {
			return nil
		}
		return []string{single}
	}
	sa := v.ToArray()
	if sa == nil {
		return nil
	}
	raw := sa.ToValueArray()
	out := make([]string, 0, len(raw))
	for _, r := range raw {
		if s, ok := r.(string); ok && s != "" {
			out = append(out, s)
		}
	}
	return out
}

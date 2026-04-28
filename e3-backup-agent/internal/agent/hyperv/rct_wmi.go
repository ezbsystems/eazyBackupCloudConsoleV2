//go:build windows
// +build windows

package hyperv

import (
	"context"
	"fmt"
	"os"
	"strconv"
	"strings"
)

// getVirtualDiskChangesWMI calls
// Msvm_ImageManagementService::GetVirtualDiskChanges for a single VHDX,
// returning the (offset,length) byte ranges that changed since the prior
// RCT generation identified by limitID.
//
// limitID is the RCT ID captured at the previous reference point for THIS
// disk (typically obtained from ReferencePointDiskRCTIDs). Passing an empty
// or stale limitID causes WMI to reject the call; callers MUST treat that
// as a per-disk fallback to Full.
//
// diskSize is required so we can submit the request as a single
// "ByteOffset=0, ByteLength=size" range; the WMI method otherwise wants the
// caller to specify the regions of interest.
func getVirtualDiskChangesWMI(ctx context.Context, diskPath, limitID string, diskSize int64) ([]ChangedBlockRange, error) {
	if strings.TrimSpace(diskPath) == "" {
		return nil, fmt.Errorf("getVirtualDiskChanges: empty disk path")
	}
	if strings.TrimSpace(limitID) == "" {
		return nil, fmt.Errorf("getVirtualDiskChanges: empty limit ID for %s", diskPath)
	}
	if diskSize <= 0 {
		// Fall back to the actual file size so we can still submit a bounded
		// request even when the caller did not pre-compute the size.
		if fi, err := os.Stat(diskPath); err == nil {
			diskSize = fi.Size()
		}
	}
	if diskSize <= 0 {
		return nil, fmt.Errorf("getVirtualDiskChanges: cannot determine size of %s", diskPath)
	}

	sess, err := newWMISession()
	if err != nil {
		return nil, err
	}
	defer sess.Close()

	svc, err := sess.getSingletonInstance("Msvm_ImageManagementService")
	if err != nil {
		return nil, fmt.Errorf("Msvm_ImageManagementService: %w", err)
	}
	defer svc.Release()

	// GetVirtualDiskChanges(Path, LimitId, ByteOffset, ByteLength,
	//                       [out] ChangedRanges, [out] ProcessedByteLength, [out] Job)
	out, err := sess.execMethod(svc, "Msvm_ImageManagementService", "GetVirtualDiskChanges",
		[][2]any{
			{"Path", diskPath},
			{"LimitId", limitID},
			{"ByteOffset", uint64(0)},
			{"ByteLength", uint64(diskSize)},
		})
	if err != nil {
		return nil, fmt.Errorf("GetVirtualDiskChanges(%s): %w", diskPath, err)
	}
	defer out.Release()

	ret := getProp(out, "ReturnValue")
	jobRef := getProp(out, "Job")

	if ret != "0" && ret != "4096" {
		return nil, fmt.Errorf("GetVirtualDiskChanges(%s): WMI return %s", diskPath, ret)
	}
	if err := sess.waitForJob(jobRef); err != nil {
		return nil, fmt.Errorf("GetVirtualDiskChanges(%s): %w", diskPath, err)
	}

	rawRanges := getStringArrayProp(out, "ChangedRanges")
	ranges := make([]ChangedBlockRange, 0, len(rawRanges))
	for _, raw := range rawRanges {
		// Hyper-V encodes each range as "offset:length" or "offset length"
		// depending on host version; accept both.
		parts := strings.FieldsFunc(raw, func(r rune) bool { return r == ':' || r == ' ' || r == '\t' })
		if len(parts) != 2 {
			continue
		}
		off, err1 := strconv.ParseInt(parts[0], 10, 64)
		ln, err2 := strconv.ParseInt(parts[1], 10, 64)
		if err1 != nil || err2 != nil || ln <= 0 {
			continue
		}
		ranges = append(ranges, ChangedBlockRange{Offset: off, Length: ln})
	}
	return ranges, nil
}

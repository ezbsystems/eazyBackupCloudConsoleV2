//go:build windows
// +build windows

package agent

import (
	"context"
	"errors"
	"fmt"
	"log"
	"strings"
	"syscall"
	"unsafe"

	"golang.org/x/sys/windows"
)

const (
	fsctlQueryUsnJournal      = 0x000900f4
	fsctlReadUsnJournal       = 0x000900bb
	fsctlGetRetrievalPointers = 0x00090073
)

const (
	usnReasonDataOverwrite       = 0x00000001
	usnReasonDataExtend          = 0x00000002
	usnReasonDataTruncation      = 0x00000004
	usnReasonNamedDataOverwrite  = 0x00000010
	usnReasonNamedDataExtend     = 0x00000020
	usnReasonNamedDataTruncation = 0x00000040
	usnReasonFileCreate          = 0x00000100
	usnReasonFileDelete          = 0x00000200
	usnReasonRenameOldName       = 0x00001000
	usnReasonRenameNewName       = 0x00002000
	usnReasonBasicInfoChange     = 0x00008000
	usnReasonSecurityChange      = 0x00010000
	usnReasonReparsePointChange  = 0x00100000
	usnReasonStreamChange        = 0x00200000
	usnReasonClose               = 0x80000000
)

const (
	fileIdType = 0x0
)

var (
	errCBTNoBaseline = errors.New("cbt baseline missing")
	errCBTInvalid    = errors.New("cbt state invalid")
)

var procOpenFileById = syscall.NewLazyDLL("kernel32.dll").NewProc("OpenFileById")

const errJournalEntryDeleted syscall.Errno = 1176

type diskImageReadPlan struct {
	Mode      string
	Reason    string
	Extents   []DiskExtent
	ReadBytes int64
	UsedBytes int64
	CBTState  *CBTState
	CBTStats  *CBTStats
}

type usnJournalData struct {
	UsnJournalID    uint64
	FirstUsn        int64
	NextUsn         int64
	LowestValidUsn  int64
	MaxUsn          int64
	MaximumSize     uint64
	AllocationDelta uint64
}

type readUsnJournalData struct {
	StartUsn          int64
	ReasonMask        uint32
	ReturnOnlyOnClose uint32
	Timeout           uint64
	BytesToWaitFor    uint64
	UsnJournalID      uint64
}

type usnRecordV2 struct {
	RecordLength              uint32
	MajorVersion              uint16
	MinorVersion              uint16
	FileReferenceNumber       uint64
	ParentFileReferenceNumber uint64
	Usn                       int64
	TimeStamp                 int64
	Reason                    uint32
	SourceInfo                uint32
	SecurityId                uint32
	FileAttributes            uint32
	FileNameLength            uint16
	FileNameOffset            uint16
}

type startingVcnInputBuffer struct {
	StartingVcn int64
}

type retrievalPointersBuffer struct {
	ExtentCount uint32
	StartingVcn int64
	Extents     [1]extent
}

type extent struct {
	NextVcn int64
	Lcn     int64
}

type fileIdDescriptor struct {
	Size   uint32
	Type   uint32
	FileId uint64
}

func (r *Runner) buildDiskImageReadPlanWindows(ctx context.Context, run *NextRunResponse, opts diskImageOptions, stableSourcePath string, prevAvailable bool) *diskImageReadPlan {
	plan := &diskImageReadPlan{
		Mode: "full",
	}
	if isWindowsPhysicalDiskPath(stableSourcePath) {
		plan.Reason = "physical_disk"
		plan.CBTStats = &CBTStats{Mode: "full", Reason: plan.Reason}
		return plan
	}

	volLetter := normalizeVolumeLetter(stableSourcePath)
	if volLetter == "" {
		plan.Reason = "volume_unresolved"
		plan.CBTStats = &CBTStats{Mode: "full", Reason: plan.Reason}
		return plan
	}

	useCBT := policyBoolDefault(run.PolicyJSON, "disk_image_cbt", true)
	if v := policyBool(run.PolicyJSON, "disk_image_change_tracking"); v != nil {
		useCBT = *v
	}
	useBitmap := policyBoolDefault(run.PolicyJSON, "disk_image_bitmap", true)

	var fallbackReason string
	if useCBT && prevAvailable {
		state := loadCBTState(r.cfg.RunDir, run.JobID, volLetter)
		extents, newState, stats, err := getChangedExtentsUSN(ctx, volLetter, state)
		if err == nil && stats != nil {
			plan.Mode = "cbt"
			plan.Extents = extents
			plan.ReadBytes = sumExtentsBytes(extents)
			plan.CBTState = newState
			stats.ReadRanges = len(extents)
			stats.ReadBytes = plan.ReadBytes
			plan.CBTStats = stats
			return plan
		}
		if err != nil {
			fallbackReason = err.Error()
		} else {
			fallbackReason = "cbt_unavailable"
		}
		plan.Reason = fallbackReason
		log.Printf("agent: disk image cbt fallback to bitmap: %s", fallbackReason)
	}

	if useBitmap {
		extents, _, err := volumeBitmapExtents(volLetter)
		if err == nil && len(extents) > 0 {
			plan.Mode = "bitmap"
			plan.Extents = extents
			plan.ReadBytes = sumExtentsBytes(extents)
			plan.UsedBytes = plan.ReadBytes
			plan.CBTState = buildCBTBaseline(volLetter, r.cfg.RunDir, run.JobID)
			plan.CBTStats = &CBTStats{
				Mode:           "bitmap",
				Reason:         fallbackReason,
				ReadRanges:     len(extents),
				ReadBytes:      plan.ReadBytes,
				ChangedExtents: len(extents),
				ChangedBytes:   plan.ReadBytes,
			}
			return plan
		}
		if err != nil {
			log.Printf("agent: disk image bitmap fallback failed: %v", err)
		}
	}

	plan.CBTStats = &CBTStats{Mode: "full", Reason: plan.Reason}
	return plan
}

func policyBoolDefault(policy map[string]any, key string, def bool) bool {
	if v := policyBool(policy, key); v != nil {
		return *v
	}
	return def
}

func normalizeVolumeLetter(raw string) string {
	s := strings.TrimSpace(raw)
	s = strings.TrimSuffix(s, "\\")
	if len(s) >= 2 && s[1] == ':' {
		return strings.ToUpper(s[:1])
	}
	return ""
}

func buildCBTBaseline(volumeLetter, runDir string, jobID int64) *CBTState {
	volHandle, err := openVolumeHandle(volumeLetter)
	if err != nil {
		return nil
	}
	defer syscall.CloseHandle(volHandle)

	root := fmt.Sprintf("%s:\\", strings.ToUpper(volumeLetter))
	serial, err := getVolumeSerial(root)
	if err != nil {
		return nil
	}
	journal, err := queryUsnJournal(volHandle)
	if err != nil {
		return nil
	}
	state := loadCBTState(runDir, jobID, volumeLetter)
	state.VolumeSerial = serial
	state.JournalID = journal.UsnJournalID
	state.LastUSN = journal.NextUsn
	return state
}

func getChangedExtentsUSN(ctx context.Context, volumeLetter string, state *CBTState) ([]DiskExtent, *CBTState, *CBTStats, error) {
	root := fmt.Sprintf("%s:\\", strings.ToUpper(volumeLetter))
	serial, err := getVolumeSerial(root)
	if err != nil {
		return nil, state, &CBTStats{Mode: "cbt", Reason: "volume_serial_failed"}, err
	}

	volHandle, err := openVolumeHandle(volumeLetter)
	if err != nil {
		return nil, state, &CBTStats{Mode: "cbt", Reason: "volume_open_failed"}, err
	}
	defer syscall.CloseHandle(volHandle)

	journal, err := queryUsnJournal(volHandle)
	if err != nil {
		return nil, state, &CBTStats{Mode: "cbt", Reason: "usn_query_failed"}, err
	}

	if state == nil || state.LastUSN == 0 {
		return nil, state, &CBTStats{Mode: "cbt", Reason: "no_baseline"}, errCBTNoBaseline
	}
	if state.VolumeSerial != 0 && state.VolumeSerial != serial {
		return nil, state, &CBTStats{Mode: "cbt", Reason: "volume_changed"}, errCBTInvalid
	}
	if state.JournalID != 0 && state.JournalID != journal.UsnJournalID {
		return nil, state, &CBTStats{Mode: "cbt", Reason: "journal_reset"}, errCBTInvalid
	}

	records, nextUsn, err := readUsnRecords(volHandle, journal.UsnJournalID, state.LastUSN, journal.NextUsn)
	if err != nil {
		return nil, state, &CBTStats{Mode: "cbt", Reason: "usn_read_failed"}, err
	}

	newState := &CBTState{
		Path:         state.Path,
		JobID:        state.JobID,
		Volume:       volumeLetter,
		VolumeSerial: serial,
		JournalID:    journal.UsnJournalID,
		LastUSN:      nextUsn,
	}

	changedIDs := collectChangedFileIDs(records)
	stats := &CBTStats{
		Mode:         "cbt",
		ChangedFiles: len(changedIDs),
		JournalID:    journal.UsnJournalID,
		LastUSN:      state.LastUSN,
		NextUSN:      nextUsn,
	}
	if len(changedIDs) > 200000 {
		stats.Reason = "too_many_changes"
		return nil, newState, stats, errCBTInvalid
	}

	clusterBytes, err := getClusterBytes(root)
	if err != nil || clusterBytes <= 0 {
		stats.Reason = "cluster_bytes_failed"
		return nil, newState, stats, err
	}

	extents := make([]DiskExtent, 0, len(changedIDs))
	for fileID := range changedIDs {
		if ctx.Err() != nil {
			return nil, newState, stats, ctx.Err()
		}
		h, openErr := openFileByID(volHandle, fileID)
		if openErr != nil {
			continue
		}
		fileExtents, extErr := getFileExtentsByHandle(h, clusterBytes)
		_ = syscall.CloseHandle(h)
		if extErr != nil {
			continue
		}
		extents = append(extents, fileExtents...)
	}

	metaExtents, metaBytes := metadataFileExtents(volumeLetter, clusterBytes)
	if len(metaExtents) > 0 {
		stats.MetaExtents = len(metaExtents)
		stats.MetaBytes = metaBytes
		extents = append(extents, metaExtents...)
	}

	extents = mergeDiskExtents(extents)
	stats.ChangedExtents = len(extents)
	stats.ChangedBytes = sumExtentsBytes(extents)
	if len(extents) == 0 {
		stats.Reason = "no_changes"
	}

	return extents, newState, stats, nil
}

func sumExtentsBytes(extents []DiskExtent) int64 {
	var total int64
	for _, e := range extents {
		if e.LengthBytes > 0 {
			total += e.LengthBytes
		}
	}
	return total
}

func collectChangedFileIDs(records []usnRecordV2) map[uint64]struct{} {
	ids := map[uint64]struct{}{}
	mask := uint32(usnReasonDataOverwrite | usnReasonDataExtend | usnReasonDataTruncation |
		usnReasonNamedDataOverwrite | usnReasonNamedDataExtend | usnReasonNamedDataTruncation |
		usnReasonFileCreate | usnReasonFileDelete |
		usnReasonRenameOldName | usnReasonRenameNewName |
		usnReasonBasicInfoChange | usnReasonSecurityChange |
		usnReasonReparsePointChange | usnReasonStreamChange)
	for _, r := range records {
		if r.Reason&mask == 0 {
			continue
		}
		ids[r.FileReferenceNumber] = struct{}{}
	}
	return ids
}

func openVolumeHandle(letter string) (syscall.Handle, error) {
	path := fmt.Sprintf(`\\.\%s:`, strings.ToUpper(letter))
	ptr, err := syscall.UTF16PtrFromString(path)
	if err != nil {
		return 0, err
	}
	h, err := syscall.CreateFile(ptr, syscall.GENERIC_READ, syscall.FILE_SHARE_READ|syscall.FILE_SHARE_WRITE|syscall.FILE_SHARE_DELETE, nil, syscall.OPEN_EXISTING, 0, 0)
	if err != nil {
		return 0, err
	}
	return h, nil
}

func getVolumeSerial(root string) (uint32, error) {
	rootPtr, err := syscall.UTF16PtrFromString(root)
	if err != nil {
		return 0, err
	}
	var serial uint32
	err = windows.GetVolumeInformation(rootPtr, nil, 0, &serial, nil, nil, nil, 0)
	if err != nil {
		return 0, err
	}
	return serial, nil
}

func queryUsnJournal(h syscall.Handle) (*usnJournalData, error) {
	var data usnJournalData
	var bytesReturned uint32
	err := syscall.DeviceIoControl(h, fsctlQueryUsnJournal, nil, 0, (*byte)(unsafe.Pointer(&data)), uint32(unsafe.Sizeof(data)), &bytesReturned, nil)
	if err != nil {
		return nil, err
	}
	return &data, nil
}

func readUsnRecords(h syscall.Handle, journalID uint64, startUsn int64, maxUsn int64) ([]usnRecordV2, int64, error) {
	var records []usnRecordV2
	curr := startUsn
	reasonMask := uint32(0xFFFFFFFF) &^ usnReasonClose
	for {
		in := readUsnJournalData{
			StartUsn:          curr,
			ReasonMask:        reasonMask,
			ReturnOnlyOnClose: 0,
			Timeout:           0,
			BytesToWaitFor:    0,
			UsnJournalID:      journalID,
		}
		buf := make([]byte, 1024*1024)
		var bytesReturned uint32
		err := syscall.DeviceIoControl(h, fsctlReadUsnJournal, (*byte)(unsafe.Pointer(&in)), uint32(unsafe.Sizeof(in)), &buf[0], uint32(len(buf)), &bytesReturned, nil)
		if err != nil {
			if errors.Is(err, syscall.ERROR_HANDLE_EOF) {
				break
			}
			if errors.Is(err, errJournalEntryDeleted) {
				return records, curr, err
			}
			return records, curr, err
		}
		if bytesReturned < 8 {
			break
		}
		nextUsn := *(*int64)(unsafe.Pointer(&buf[0]))
		offset := int64(8)
		for offset < int64(bytesReturned) {
			rec := (*usnRecordV2)(unsafe.Pointer(&buf[offset]))
			if rec.RecordLength == 0 {
				break
			}
			if rec.MajorVersion == 2 {
				records = append(records, *rec)
			}
			offset += int64(rec.RecordLength)
		}
		if nextUsn <= curr {
			break
		}
		curr = nextUsn
		if maxUsn > 0 && curr >= maxUsn {
			break
		}
	}
	return records, curr, nil
}

func openFileByID(volume syscall.Handle, fileID uint64) (syscall.Handle, error) {
	desc := fileIdDescriptor{
		Size:   uint32(unsafe.Sizeof(fileIdDescriptor{})),
		Type:   fileIdType,
		FileId: fileID,
	}
	handle, _, err := procOpenFileById.Call(
		uintptr(volume),
		uintptr(unsafe.Pointer(&desc)),
		uintptr(syscall.GENERIC_READ),
		uintptr(syscall.FILE_SHARE_READ|syscall.FILE_SHARE_WRITE|syscall.FILE_SHARE_DELETE),
		0,
		uintptr(syscall.FILE_FLAG_BACKUP_SEMANTICS),
	)
	if handle == 0 {
		return 0, err
	}
	return syscall.Handle(handle), nil
}

func getFileExtentsByHandle(h syscall.Handle, clusterBytes int64) ([]DiskExtent, error) {
	var extents []DiskExtent
	startVcn := int64(0)
	for {
		in := startingVcnInputBuffer{StartingVcn: startVcn}
		buf := make([]byte, 1024*1024)
		var bytesReturned uint32
		err := syscall.DeviceIoControl(h, fsctlGetRetrievalPointers, (*byte)(unsafe.Pointer(&in)), uint32(unsafe.Sizeof(in)), &buf[0], uint32(len(buf)), &bytesReturned, nil)
		moreData := errors.Is(err, syscall.ERROR_MORE_DATA)
		if err != nil && !moreData {
			return extents, err
		}
		if bytesReturned < uint32(unsafe.Offsetof(retrievalPointersBuffer{}.Extents)) {
			break
		}
		extentCount := *(*uint32)(unsafe.Pointer(&buf[0]))
		startingVcn := *(*int64)(unsafe.Pointer(&buf[8]))
		baseOffset := unsafe.Offsetof(retrievalPointersBuffer{}.Extents)
		prevVcn := startingVcn
		var lastNextVcn int64
		for i := uint32(0); i < extentCount; i++ {
			off := baseOffset + uintptr(i)*unsafe.Sizeof(extent{})
			if off+unsafe.Sizeof(extent{}) > uintptr(len(buf)) {
				break
			}
			ext := (*extent)(unsafe.Pointer(&buf[off]))
			if ext.Lcn >= 0 {
				length := (ext.NextVcn - prevVcn) * clusterBytes
				if length > 0 {
					extents = append(extents, DiskExtent{
						OffsetBytes: ext.Lcn * clusterBytes,
						LengthBytes: length,
					})
				}
			}
			prevVcn = ext.NextVcn
			lastNextVcn = ext.NextVcn
		}
		if extentCount == 0 {
			break
		}
		if !moreData {
			break
		}
		if lastNextVcn <= startVcn {
			break
		}
		startVcn = lastNextVcn
	}
	return extents, nil
}

func metadataFileExtents(volumeLetter string, clusterBytes int64) ([]DiskExtent, int64) {
	metadata := []string{
		`$MFT`,
		`$MFTMirr`,
		`$LogFile`,
		`$Bitmap`,
		`$Boot`,
		`$Secure`,
		`$UpCase`,
		`$Extend\\$UsnJrnl`,
	}
	var extents []DiskExtent
	var total int64
	for _, name := range metadata {
		path := fmt.Sprintf(`\\.\%s:\%s`, strings.ToUpper(volumeLetter), name)
		h, err := openFileByPath(path)
		if err != nil {
			continue
		}
		fileExtents, extErr := getFileExtentsByHandle(h, clusterBytes)
		_ = syscall.CloseHandle(h)
		if extErr != nil {
			continue
		}
		if len(fileExtents) > 0 {
			extents = append(extents, fileExtents...)
			total += sumExtentsBytes(fileExtents)
		}
	}
	return extents, total
}

func openFileByPath(path string) (syscall.Handle, error) {
	ptr, err := syscall.UTF16PtrFromString(path)
	if err != nil {
		return 0, err
	}
	h, err := syscall.CreateFile(
		ptr,
		syscall.GENERIC_READ,
		syscall.FILE_SHARE_READ|syscall.FILE_SHARE_WRITE|syscall.FILE_SHARE_DELETE,
		nil,
		syscall.OPEN_EXISTING,
		syscall.FILE_FLAG_BACKUP_SEMANTICS,
		0,
	)
	if err != nil {
		return 0, err
	}
	return h, nil
}

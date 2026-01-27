//go:build windows
// +build windows

package agent

import (
	"bytes"
	"encoding/binary"
	"encoding/json"
	"fmt"
	"log"
	"os/exec"
	"strconv"
	"strings"
	"syscall"
	"unsafe"
)

const (
	fsctlGetVolumeBitmap = 0x0009006f
)

type psDiskLayout struct {
	DiskNumber     int           `json:"disk_number"`
	DiskPath       string        `json:"disk_path"`
	Model          string        `json:"model"`
	Serial         string        `json:"serial"`
	SizeBytes      int64         `json:"size_bytes"`
	PartitionStyle string        `json:"partition_style"`
	BusType        string        `json:"bus_type"`
	BootMode       int           `json:"boot_mode"`
	Partitions     []psPartition `json:"partitions"`
}

type psPartition struct {
	PartitionNumber int     `json:"partition_number"`
	DriveLetter     string  `json:"drive_letter"`
	Offset          int64   `json:"offset"`
	Size            int64   `json:"size"`
	Type            string  `json:"type"`
	GptType         string  `json:"gpt_type"`
	IsBoot          bool    `json:"is_boot"`
	IsSystem        bool    `json:"is_system"`
	IsHidden        bool    `json:"is_hidden"`
	FileSystem      string  `json:"filesystem"`
	Label           string  `json:"label"`
	SizeRemaining   float64 `json:"size_remaining"`
}

func collectDiskLayout(source string) (*DiskLayout, error) {
	diskNumber, diskPath, err := resolveWindowsDiskNumber(source)
	if err != nil {
		return nil, err
	}
	psLayout, err := fetchWindowsDiskLayout(diskNumber)
	if err != nil {
		return nil, err
	}

	layout := &DiskLayout{
		DiskPath:       diskPath,
		DiskNumber:     diskNumber,
		Model:          psLayout.Model,
		Serial:         psLayout.Serial,
		BusType:        psLayout.BusType,
		PartitionStyle: normalizePartitionStyle(psLayout.PartitionStyle),
		BootMode:       normalizeBootMode(psLayout.BootMode),
		TotalBytes:     psLayout.SizeBytes,
	}

	for _, p := range psLayout.Partitions {
		part := DiskPartition{
			Index:      p.PartitionNumber,
			Name:       fmt.Sprintf("Partition %d", p.PartitionNumber),
			Path:       diskPath,
			StartBytes: p.Offset,
			SizeBytes:  p.Size,
			FileSystem: strings.ToLower(p.FileSystem),
			Label:      p.Label,
			Type:       p.Type,
			PartType:   p.GptType,
			IsBoot:     p.IsBoot,
			IsSystem:   p.IsSystem,
		}
		if p.SizeRemaining > 0 && p.Size > 0 {
			part.UsedBytes = p.Size - int64(p.SizeRemaining)
		}
		if isWindowsEFI(p.GptType, p.Type) {
			part.IsEFI = true
		}
		if isWindowsRecovery(p.GptType, p.Type) {
			part.IsRecovery = true
		}

		if p.DriveLetter != "" && strings.EqualFold(p.FileSystem, "NTFS") {
			extents, clusterBytes, err := volumeBitmapExtents(p.DriveLetter)
			if err != nil {
				log.Printf("agent: volume bitmap failed for %s: %v", p.DriveLetter, err)
			} else {
				part.UsedExtents = extents
				part.ClusterBytes = clusterBytes
				layout.BlockMapSource = "ntfs_volume_bitmap"
			}
		}
		layout.Partitions = append(layout.Partitions, part)
	}

	return layout, nil
}

func normalizeBootMode(mode int) string {
	switch mode {
	case 2:
		return "uefi"
	case 1:
		return "bios"
	default:
		return "unknown"
	}
}

func isWindowsEFI(gptType, partType string) bool {
	val := strings.ToLower(strings.TrimSpace(gptType))
	return strings.Contains(val, "c12a7328-f81f-11d2-ba4b-00a0c93ec93b") || strings.Contains(strings.ToLower(partType), "efi")
}

func isWindowsRecovery(gptType, partType string) bool {
	val := strings.ToLower(strings.TrimSpace(gptType))
	return strings.Contains(val, "de94bba4-06d1-4d40-a16a-bfd50179d6ac") || strings.Contains(strings.ToLower(partType), "recovery")
}

func resolveWindowsDiskNumber(source string) (int, string, error) {
	trimmed := strings.TrimSpace(source)
	lower := strings.ToLower(trimmed)
	if strings.Contains(lower, "physicaldrive") {
		idx := strings.LastIndex(lower, "physicaldrive")
		numStr := lower[idx+len("physicaldrive"):]
		numStr = strings.TrimPrefix(numStr, `\`)
		numStr = strings.TrimPrefix(numStr, `.`)
		numStr = strings.Trim(numStr, "\\/")
		num, err := strconv.Atoi(numStr)
		if err == nil {
			return num, fmt.Sprintf(`\\.\PhysicalDrive%d`, num), nil
		}
	}
	driveLetter := ""
	if len(trimmed) >= 2 && trimmed[1] == ':' {
		driveLetter = strings.ToUpper(trimmed[:1])
	}
	if driveLetter == "" {
		return 0, "", fmt.Errorf("unable to resolve disk number from source: %s", source)
	}
	cmd := fmt.Sprintf(`(Get-Partition -DriveLetter %s | Select-Object -First 1 -ExpandProperty DiskNumber)`, driveLetter)
	out, err := runPowerShell(cmd)
	if err != nil {
		return 0, "", err
	}
	num, err := strconv.Atoi(strings.TrimSpace(out))
	if err != nil {
		return 0, "", fmt.Errorf("invalid disk number from powershell: %s", out)
	}
	return num, fmt.Sprintf(`\\.\PhysicalDrive%d`, num), nil
}

func fetchWindowsDiskLayout(diskNumber int) (*psDiskLayout, error) {
	ps := fmt.Sprintf(`
$disk = Get-Disk -Number %d
$parts = Get-Partition -DiskNumber %d | Sort-Object Offset | ForEach-Object {
    $vol = $_ | Get-Volume -ErrorAction SilentlyContinue
    [pscustomobject]@{
        partition_number = $_.PartitionNumber
        drive_letter = $_.DriveLetter
        offset = $_.Offset
        size = $_.Size
        type = $_.Type
        gpt_type = $_.GptType
        is_boot = $_.IsBoot
        is_system = $_.IsSystem
        is_hidden = $_.IsHidden
        filesystem = $vol.FileSystem
        label = $vol.FileSystemLabel
        size_remaining = $vol.SizeRemaining
    }
}
$boot = (Get-ItemProperty -Path 'HKLM:\SYSTEM\CurrentControlSet\Control' -Name PEFirmwareType -ErrorAction SilentlyContinue).PEFirmwareType
[pscustomobject]@{
    disk_number = $disk.Number
    disk_path = ('\\\\.\\PhysicalDrive' + $disk.Number)
    model = $disk.FriendlyName
    serial = $disk.SerialNumber
    size_bytes = $disk.Size
    partition_style = $disk.PartitionStyle
    bus_type = $disk.BusType
    boot_mode = $boot
    partitions = $parts
} | ConvertTo-Json -Depth 6
`, diskNumber, diskNumber)

	out, err := runPowerShell(ps)
	if err != nil {
		return nil, err
	}
	var layout psDiskLayout
	if err := json.Unmarshal([]byte(out), &layout); err != nil {
		return nil, fmt.Errorf("failed to parse disk layout: %w", err)
	}
	return &layout, nil
}

func runPowerShell(script string) (string, error) {
	cmd := exec.Command("powershell", "-NoProfile", "-Command", script)
	var buf bytes.Buffer
	cmd.Stdout = &buf
	cmd.Stderr = &buf
	if err := cmd.Run(); err != nil {
		return "", fmt.Errorf("powershell failed: %v (%s)", err, buf.String())
	}
	return strings.TrimSpace(buf.String()), nil
}

func getClusterBytes(root string) (int64, error) {
	ptr, err := syscall.UTF16PtrFromString(root)
	if err != nil {
		return 0, err
	}
	var sectorsPerCluster, bytesPerSector, freeClusters, totalClusters uint32
	r, _, callErr := procGetDiskFreeSpace.Call(
		uintptr(unsafe.Pointer(ptr)),
		uintptr(unsafe.Pointer(&sectorsPerCluster)),
		uintptr(unsafe.Pointer(&bytesPerSector)),
		uintptr(unsafe.Pointer(&freeClusters)),
		uintptr(unsafe.Pointer(&totalClusters)),
	)
	if r == 0 {
		return 0, fmt.Errorf("GetDiskFreeSpace: %v", callErr)
	}
	return int64(sectorsPerCluster) * int64(bytesPerSector), nil
}

func volumeBitmapExtents(driveLetter string) ([]DiskExtent, int64, error) {
	root := fmt.Sprintf("%s:\\", strings.ToUpper(driveLetter))
	clusterBytes, err := getClusterBytes(root)
	if err != nil {
		return nil, 0, err
	}

	volPath := fmt.Sprintf(`\\.\%s:`, strings.ToUpper(driveLetter))
	h, err := syscall.CreateFile(syscall.StringToUTF16Ptr(volPath), syscall.GENERIC_READ, syscall.FILE_SHARE_READ|syscall.FILE_SHARE_WRITE, nil, syscall.OPEN_EXISTING, 0, 0)
	if err != nil {
		return nil, clusterBytes, fmt.Errorf("open volume: %w", err)
	}
	defer syscall.CloseHandle(h)

	const headerSize = 16
	var extents []DiskExtent
	var startLCN int64
	maxExtents := 50000

	for {
		inBuf := make([]byte, 8)
		binary.LittleEndian.PutUint64(inBuf, uint64(startLCN))
		outBuf := make([]byte, 1024*1024)
		var bytesReturned uint32
		err = syscall.DeviceIoControl(h, fsctlGetVolumeBitmap, &inBuf[0], uint32(len(inBuf)), &outBuf[0], uint32(len(outBuf)), &bytesReturned, nil)
		if err != nil && err != syscall.ERROR_MORE_DATA {
			return extents, clusterBytes, fmt.Errorf("DeviceIoControl bitmap: %w", err)
		}
		if bytesReturned < headerSize {
			break
		}
		start := int64(binary.LittleEndian.Uint64(outBuf[0:8]))
		bitmapSize := int64(binary.LittleEndian.Uint64(outBuf[8:16]))
		bitmap := outBuf[16:bytesReturned]
		extents = appendExtentsFromBitmap(extents, bitmap, start, bitmapSize, clusterBytes, maxExtents)
		bitsInBuffer := int64(len(bitmap) * 8)
		startLCN = start + bitsInBuffer
		if startLCN >= bitmapSize {
			break
		}
		if len(extents) >= maxExtents {
			break
		}
	}

	return extents, clusterBytes, nil
}

func appendExtentsFromBitmap(extents []DiskExtent, bitmap []byte, startLCN, bitmapSize, clusterBytes int64, limit int) []DiskExtent {
	var runStart int64 = -1
	for i, b := range bitmap {
		for bit := 0; bit < 8; bit++ {
			lcn := startLCN + int64(i*8+bit)
			if lcn >= bitmapSize {
				break
			}
			used := (b & (1 << uint(bit))) != 0
			if used && runStart < 0 {
				runStart = lcn
			}
			if !used && runStart >= 0 {
				extents = appendExtent(extents, runStart, lcn, clusterBytes, limit)
				runStart = -1
				if len(extents) >= limit {
					return extents
				}
			}
		}
	}
	if runStart >= 0 {
		extents = appendExtent(extents, runStart, startLCN+int64(len(bitmap)*8), clusterBytes, limit)
	}
	return extents
}

func appendExtent(extents []DiskExtent, startLCN, endLCN, clusterBytes int64, limit int) []DiskExtent {
	if len(extents) >= limit {
		return extents
	}
	if endLCN <= startLCN {
		return extents
	}
	extents = append(extents, DiskExtent{
		OffsetBytes: startLCN * clusterBytes,
		LengthBytes: (endLCN - startLCN) * clusterBytes,
	})
	return extents
}

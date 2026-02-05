//go:build windows
// +build windows

package agent

import (
	"bytes"
	"encoding/json"
	"fmt"
	"os/exec"
	"regexp"
	"strconv"
	"strings"
)

type psDiskList struct {
	Disks []psDiskInfo `json:"disks"`
}

type psDiskInfo struct {
	Number         int          `json:"number"`
	Path           string       `json:"path"`
	Model          string       `json:"model"`
	Serial         string       `json:"serial"`
	SizeBytes      int64        `json:"size_bytes"`
	PartitionStyle string       `json:"partition_style"`
	BusType        string       `json:"bus_type"`
	Partitions     []psPartInfo `json:"partitions"`
}

type psPartInfo struct {
	PartitionNumber int    `json:"partition_number"`
	Offset          int64  `json:"offset"`
	Size            int64  `json:"size"`
	DriveLetter     string `json:"drive_letter"`
	FileSystem      string `json:"filesystem"`
	Label           string `json:"label"`
	Type            string `json:"type"`
	GptType         string `json:"gpt_type"`
	IsSystem        bool   `json:"is_system"`
	IsHidden        bool   `json:"is_hidden"`
}

func enumerateDisks() ([]DiskInfo, error) {
	ps := `
$disks = @(Get-Disk | ForEach-Object {
    $parts = @(Get-Partition -DiskNumber $_.Number | Sort-Object Offset | ForEach-Object {
        $vol = $_ | Get-Volume -ErrorAction SilentlyContinue
        [pscustomobject]@{
            partition_number = $_.PartitionNumber
            offset = $_.Offset
            size = $_.Size
            drive_letter = $_.DriveLetter
            filesystem = $vol.FileSystem
            label = $vol.FileSystemLabel
            type = $_.Type
            gpt_type = $_.GptType
            is_system = $_.IsSystem
            is_hidden = $_.IsHidden
        }
    })
    [pscustomobject]@{
        number = $_.Number
        path = ('\\.\PhysicalDrive' + $_.Number)
        model = $_.FriendlyName
        serial = $_.SerialNumber
        size_bytes = $_.Size
        partition_style = $_.PartitionStyle
        bus_type = $_.BusType
        partitions = $parts
    }
})
[pscustomobject]@{ disks = $disks } | ConvertTo-Json -Depth 5
`
	out, err := runPowerShell(ps)
	if err == nil {
		var parsed psDiskList
		if err := json.Unmarshal([]byte(out), &parsed); err == nil {
			var disks []DiskInfo
			for _, d := range parsed.Disks {
				info := DiskInfo{
					Path:           d.Path,
					Name:           fmt.Sprintf("Disk %d", d.Number),
					Model:          d.Model,
					Serial:         d.Serial,
					BusType:        d.BusType,
					PartitionStyle: normalizePartitionStyle(d.PartitionStyle),
					SizeBytes:      uint64(d.SizeBytes),
				}
				for _, p := range d.Partitions {
					part := DiskPartitionSummary{
						Name:       fmt.Sprintf("Partition %d", p.PartitionNumber),
						Path:       strings.TrimSpace(p.DriveLetter),
						StartBytes: p.Offset,
						SizeBytes:  p.Size,
						FileSystem: strings.ToLower(p.FileSystem),
						Label:      p.Label,
						PartType:   p.GptType,
						IsSystem:   p.IsSystem,
						IsRecovery: isWindowsRecovery(p.GptType, p.Type),
						IsEFI:      isWindowsEFI(p.GptType, p.Type),
					}
					if p.DriveLetter != "" {
						part.Mountpoint = p.DriveLetter + ":\\"
						part.Path = part.Mountpoint
					}
					info.Partitions = append(info.Partitions, part)
				}
				disks = append(disks, info)
			}
			if len(disks) > 0 {
				return disks, nil
			}
		}
	}

	disks, fbErr := enumerateDisksDiskpart()
	if fbErr != nil {
		if err != nil {
			return nil, fmt.Errorf("disk list failed: powershell=%v; diskpart=%v", err, fbErr)
		}
		return nil, fbErr
	}
	return disks, nil
}

func enumerateDisksDiskpart() ([]DiskInfo, error) {
	cmd := exec.Command("cmd", "/c", "echo list disk | diskpart")
	var buf bytes.Buffer
	cmd.Stdout = &buf
	cmd.Stderr = &buf
	if err := cmd.Run(); err != nil {
		return nil, fmt.Errorf("diskpart failed: %v (%s)", err, buf.String())
	}
	return parseDiskpartList(buf.String()), nil
}

func parseDiskpartList(output string) []DiskInfo {
	var disks []DiskInfo
	re := regexp.MustCompile(`(?i)^\s*Disk\s+(\d+)\s+\S+\s+(\d+(?:\.\d+)?)\s*(B|KB|MB|GB|TB)\b`)
	lines := strings.Split(output, "\n")
	for _, line := range lines {
		line = strings.TrimSpace(line)
		if line == "" || !strings.HasPrefix(strings.ToLower(line), "disk") {
			continue
		}
		matches := re.FindStringSubmatch(line)
		if len(matches) < 4 {
			continue
		}
		num, _ := strconv.Atoi(matches[1])
		sizeVal, _ := strconv.ParseFloat(matches[2], 64)
		sizeBytes := parseDiskpartSize(sizeVal, matches[3])
		info := DiskInfo{
			Path:      fmt.Sprintf(`\\.\PhysicalDrive%d`, num),
			Name:      fmt.Sprintf("Disk %d", num),
			SizeBytes: uint64(sizeBytes),
		}
		disks = append(disks, info)
	}
	return disks
}

func parseDiskpartSize(value float64, unit string) int64 {
	switch strings.ToUpper(unit) {
	case "TB":
		return int64(value * 1024 * 1024 * 1024 * 1024)
	case "GB":
		return int64(value * 1024 * 1024 * 1024)
	case "MB":
		return int64(value * 1024 * 1024)
	case "KB":
		return int64(value * 1024)
	default:
		return int64(value)
	}
}

//go:build linux
// +build linux

package agent

import (
	"bytes"
	"encoding/json"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"regexp"
	"strconv"
	"strings"
)

type lsblkOutput struct {
	Blockdevices []lsblkDevice `json:"blockdevices"`
}

type lsblkDevice struct {
	Name       string        `json:"name"`
	Path       string        `json:"path"`
	Type       string        `json:"type"`
	Size       string        `json:"size"`
	Start      string        `json:"start"`
	Fstype     string        `json:"fstype"`
	Mountpoint string        `json:"mountpoint"`
	Label      string        `json:"label"`
	PartUUID   string        `json:"partuuid"`
	UUID       string        `json:"uuid"`
	PartType   string        `json:"parttype"`
	PartLabel  string        `json:"partlabel"`
	PkName     string        `json:"pkname"`
	Model      string        `json:"model"`
	Serial     string        `json:"serial"`
	Tran       string        `json:"tran"`
	PTType     string        `json:"pttype"`
	Children   []lsblkDevice `json:"children"`
}

func collectDiskLayout(source string) (*DiskLayout, error) {
	devs, err := readLsblk()
	if err != nil {
		return nil, err
	}
	flat := flattenLsblk(devs)

	sourcePath := strings.TrimSpace(source)
	if sourcePath == "" {
		return nil, fmt.Errorf("source path is empty")
	}

	diskDevice := findDiskForSource(flat, sourcePath)
	if diskDevice == nil {
		return nil, fmt.Errorf("disk not found for source %s", sourcePath)
	}

	diskBlockSize := readBlockSize(diskDevice.Name)
	if diskBlockSize == 0 {
		diskBlockSize = 512
	}

	usedMap := readUsedBytesMap()

	layout := &DiskLayout{
		DiskPath:       diskDevice.Path,
		Model:          diskDevice.Model,
		Serial:         diskDevice.Serial,
		BusType:        diskDevice.Tran,
		PartitionStyle: normalizePartitionStyle(diskDevice.PTType),
		TotalBytes:     parseInt64(diskDevice.Size),
	}

	for _, d := range flat {
		if d.Type != "part" {
			continue
		}
		if d.PkName != diskDevice.Name {
			continue
		}
		startBytes := parseInt64(d.Start) * diskBlockSize
		part := DiskPartition{
			Index:      parsePartitionIndex(d.Name),
			Name:       d.Name,
			Path:       d.Path,
			StartBytes: startBytes,
			SizeBytes:  parseInt64(d.Size),
			FileSystem: strings.ToLower(strings.TrimSpace(d.Fstype)),
			Label:      d.Label,
			PartType:   d.PartType,
			PartUUID:   d.PartUUID,
			Mountpoint: d.Mountpoint,
		}
		if used, ok := usedMap[d.Path]; ok {
			part.UsedBytes = used
		}

		if part.FileSystem == "ext4" {
			blockSize := ext4BlockSize(d.Path)
			if blockSize > 0 {
				part.ClusterBytes = blockSize
			}
			extents, err := ext4UsedExtents(d.Path, blockSize)
			if err == nil && len(extents) > 0 {
				part.UsedExtents = extents
				layout.BlockMapSource = "ext4_bmap2extent"
			}
		}
		layout.Partitions = append(layout.Partitions, part)
	}

	return layout, nil
}

func readLsblk() (*lsblkOutput, error) {
	cmd := exec.Command("lsblk", "-J", "-b", "-o", "NAME,PATH,TYPE,SIZE,START,FSTYPE,MOUNTPOINT,LABEL,PARTUUID,UUID,PARTTYPE,PARTLABEL,PKNAME,MODEL,SERIAL,TRAN,PTTYPE")
	var buf bytes.Buffer
	cmd.Stdout = &buf
	cmd.Stderr = &buf
	if err := cmd.Run(); err != nil {
		return nil, fmt.Errorf("lsblk failed: %v (%s)", err, buf.String())
	}
	var out lsblkOutput
	if err := json.Unmarshal(buf.Bytes(), &out); err != nil {
		return nil, fmt.Errorf("lsblk parse failed: %w", err)
	}
	return &out, nil
}

func flattenLsblk(out *lsblkOutput) []lsblkDevice {
	var res []lsblkDevice
	var walk func(d lsblkDevice)
	walk = func(d lsblkDevice) {
		res = append(res, d)
		for _, c := range d.Children {
			walk(c)
		}
	}
	for _, d := range out.Blockdevices {
		walk(d)
	}
	return res
}

func findDiskForSource(flat []lsblkDevice, source string) *lsblkDevice {
	for i := range flat {
		if flat[i].Path == source && flat[i].Type == "disk" {
			return &flat[i]
		}
	}
	for i := range flat {
		if flat[i].Path == source && flat[i].Type == "part" {
			parent := flat[i].PkName
			if parent == "" {
				continue
			}
			for j := range flat {
				if flat[j].Name == parent && flat[j].Type == "disk" {
					return &flat[j]
				}
			}
		}
	}
	return nil
}

func readBlockSize(diskName string) int64 {
	if diskName == "" {
		return 0
	}
	path := filepath.Join("/sys/block", diskName, "queue", "logical_block_size")
	b, err := os.ReadFile(path)
	if err != nil {
		return 0
	}
	return parseInt64(strings.TrimSpace(string(b)))
}

func parseInt64(raw string) int64 {
	if raw == "" {
		return 0
	}
	n, _ := strconv.ParseInt(raw, 10, 64)
	return n
}

func parsePartitionIndex(name string) int {
	re := regexp.MustCompile(`\d+$`)
	match := re.FindString(name)
	if match == "" {
		return 0
	}
	val, _ := strconv.Atoi(match)
	return val
}

func readUsedBytesMap() map[string]int64 {
	used := map[string]int64{}
	cmd := exec.Command("df", "-B1", "--output=source,used")
	var buf bytes.Buffer
	cmd.Stdout = &buf
	cmd.Stderr = &buf
	if err := cmd.Run(); err != nil {
		return used
	}
	lines := strings.Split(buf.String(), "\n")
	for _, line := range lines[1:] {
		fields := strings.Fields(line)
		if len(fields) < 2 {
			continue
		}
		used[fields[0]] = parseInt64(fields[1])
	}
	return used
}

func ext4BlockSize(devicePath string) int64 {
	cmd := exec.Command("dumpe2fs", "-h", devicePath)
	var buf bytes.Buffer
	cmd.Stdout = &buf
	cmd.Stderr = &buf
	if err := cmd.Run(); err != nil {
		return 0
	}
	re := regexp.MustCompile(`(?m)^Block size:\\s+(\\d+)`)
	match := re.FindStringSubmatch(buf.String())
	if len(match) < 2 {
		return 0
	}
	return parseInt64(match[1])
}

func ext4UsedExtents(devicePath string, blockSize int64) ([]DiskExtent, error) {
	if blockSize <= 0 {
		return nil, fmt.Errorf("invalid block size")
	}
	cmd := exec.Command("e2fsck", "-E", "bmap2extent", "-n", devicePath)
	var buf bytes.Buffer
	cmd.Stdout = &buf
	cmd.Stderr = &buf
	if err := cmd.Run(); err != nil {
		return nil, fmt.Errorf("e2fsck bmap2extent failed: %v (%s)", err, buf.String())
	}
	reRange := regexp.MustCompile(`(\\d+)\\s*-\\s*(\\d+)`)
	reSingle := regexp.MustCompile(`\\b(\\d+)\\b`)
	var extents []DiskExtent
	maxExtents := 50000
	lines := strings.Split(buf.String(), "\n")
	for _, line := range lines {
		line = strings.TrimSpace(line)
		if line == "" {
			continue
		}
		if matches := reRange.FindAllStringSubmatch(line, -1); len(matches) > 0 {
			for _, m := range matches {
				start := parseInt64(m[1])
				end := parseInt64(m[2])
				if end <= start {
					continue
				}
				extents = append(extents, DiskExtent{
					OffsetBytes: start * blockSize,
					LengthBytes: (end-start+1) * blockSize,
				})
				if len(extents) >= maxExtents {
					return extents, nil
				}
			}
			continue
		}
		if matches := reSingle.FindAllStringSubmatch(line, -1); len(matches) > 0 {
			for _, m := range matches {
				start := parseInt64(m[1])
				extents = append(extents, DiskExtent{
					OffsetBytes: start * blockSize,
					LengthBytes: blockSize,
				})
				if len(extents) >= maxExtents {
					return extents, nil
				}
			}
		}
	}
	return extents, nil
}

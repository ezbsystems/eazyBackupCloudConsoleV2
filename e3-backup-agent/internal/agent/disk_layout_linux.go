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

// lsblkDevice mirrors a single device entry from `lsblk -J`. Numeric columns
// (SIZE, START) are decoded as flexNumber to accept both string-encoded values
// (older util-linux releases) and JSON numbers (util-linux >=2.37 emits raw
// integers when invoked with `-b`).
type lsblkDevice struct {
	Name       string        `json:"name"`
	Path       string        `json:"path"`
	Type       string        `json:"type"`
	Size       flexNumber    `json:"size"`
	Start      flexNumber    `json:"start"`
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

// flexNumber accepts a JSON value that may be an integer, a quoted integer, or
// null and exposes it as a string for downstream parseInt64 calls.
type flexNumber string

func (f *flexNumber) UnmarshalJSON(data []byte) error {
	s := strings.TrimSpace(string(data))
	if s == "" || s == "null" {
		*f = ""
		return nil
	}
	if len(s) >= 2 && s[0] == '"' && s[len(s)-1] == '"' {
		s = s[1 : len(s)-1]
	}
	*f = flexNumber(s)
	return nil
}

func (f flexNumber) String() string { return string(f) }

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
		TotalBytes:     parseInt64(diskDevice.Size.String()),
	}

	for _, d := range flat {
		if d.Type != "part" {
			continue
		}
		if d.PkName != diskDevice.Name {
			continue
		}
		startSectors := parseInt64(d.Start.String())
		if startSectors == 0 {
			startSectors = readPartitionStart(d.Name)
		}
		startBytes := startSectors * diskBlockSize
		part := DiskPartition{
			Index:      parsePartitionIndex(d.Name),
			Name:       d.Name,
			Path:       d.Path,
			StartBytes: startBytes,
			SizeBytes:  parseInt64(d.Size.String()),
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

// lsblkColumns lists the columns we request from lsblk in priority order.
// On older util-linux releases (<2.38) some columns (notably START) are not
// recognized; we degrade gracefully by retrying without the unsupported
// columns instead of failing the entire backup.
var lsblkColumns = []string{
	"NAME", "PATH", "TYPE", "SIZE", "START", "FSTYPE", "MOUNTPOINT",
	"LABEL", "PARTUUID", "UUID", "PARTTYPE", "PARTLABEL", "PKNAME",
	"MODEL", "SERIAL", "TRAN", "PTTYPE",
}

func readLsblk() (*lsblkOutput, error) {
	cols := append([]string(nil), lsblkColumns...)
	for {
		cmd := exec.Command("lsblk", "-J", "-b", "-o", strings.Join(cols, ","))
		var buf bytes.Buffer
		cmd.Stdout = &buf
		cmd.Stderr = &buf
		if err := cmd.Run(); err != nil {
			dropped, ok := dropUnknownLsblkColumns(buf.String(), cols)
			if ok && len(dropped) > 0 && len(dropped) < len(cols) {
				cols = dropped
				continue
			}
			return nil, fmt.Errorf("lsblk failed: %v (%s)", err, strings.TrimSpace(buf.String()))
		}
		var out lsblkOutput
		if err := json.Unmarshal(buf.Bytes(), &out); err != nil {
			return nil, fmt.Errorf("lsblk parse failed: %w", err)
		}
		return &out, nil
	}
}

// dropUnknownLsblkColumns parses lsblk's "unknown column" error output and
// returns the column list with the offending names removed. The second return
// value is false if no unknown column message was found.
func dropUnknownLsblkColumns(stderr string, cols []string) ([]string, bool) {
	const marker = "unknown column:"
	idx := strings.Index(stderr, marker)
	if idx < 0 {
		return cols, false
	}
	rest := strings.TrimSpace(stderr[idx+len(marker):])
	rest = strings.SplitN(rest, "\n", 2)[0]
	bad := map[string]struct{}{}
	for _, name := range strings.Split(rest, ",") {
		name = strings.TrimSpace(name)
		if name != "" {
			bad[strings.ToUpper(name)] = struct{}{}
		}
	}
	if len(bad) == 0 {
		return cols, false
	}
	kept := make([]string, 0, len(cols))
	for _, c := range cols {
		if _, drop := bad[strings.ToUpper(c)]; drop {
			continue
		}
		kept = append(kept, c)
	}
	return kept, true
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
	candidates := map[string]struct{}{source: {}}
	if resolved, err := filepath.EvalSymlinks(source); err == nil && resolved != "" {
		candidates[resolved] = struct{}{}
	}

	matchPath := func(d lsblkDevice) bool {
		if _, ok := candidates[d.Path]; ok {
			return true
		}
		return false
	}

	for i := range flat {
		if matchPath(flat[i]) && flat[i].Type == "disk" {
			return &flat[i]
		}
	}
	// Logical volumes (LVM2) and device-mapper targets are valid disk-image
	// sources too. Treat the LV itself as the "disk" — it has no partitions of
	// its own, but we still need its size, label, and filesystem metadata.
	for i := range flat {
		if matchPath(flat[i]) && (flat[i].Type == "lvm" || flat[i].Type == "crypt" || flat[i].Type == "raid0" || flat[i].Type == "raid1" || flat[i].Type == "raid5" || flat[i].Type == "raid6" || flat[i].Type == "raid10") {
			return &flat[i]
		}
	}
	for i := range flat {
		if matchPath(flat[i]) && flat[i].Type == "part" {
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

// readPartitionStart returns a partition's starting LBA (in 512-byte sectors)
// from sysfs. This is used as a fallback when lsblk does not expose the START
// column (older util-linux releases such as Ubuntu 22.04's 2.37.2).
func readPartitionStart(partName string) int64 {
	if partName == "" {
		return 0
	}
	candidates := []string{
		filepath.Join("/sys/class/block", partName, "start"),
	}
	if entries, err := os.ReadDir("/sys/block"); err == nil {
		for _, e := range entries {
			candidates = append(candidates, filepath.Join("/sys/block", e.Name(), partName, "start"))
		}
	}
	for _, p := range candidates {
		if b, err := os.ReadFile(p); err == nil {
			if n := parseInt64(strings.TrimSpace(string(b))); n > 0 {
				return n
			}
		}
	}
	return 0
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

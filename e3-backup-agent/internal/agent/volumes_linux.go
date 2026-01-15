//go:build linux
// +build linux

package agent

import (
	"encoding/json"
	"fmt"
	"os/exec"
	"strconv"
	"strings"
)

type lsblkOutput struct {
	Blockdevices []lsblkDevice `json:"blockdevices"`
}

type lsblkDevice struct {
	Name     string        `json:"name"`
	Type     string        `json:"type"`
	Size     any           `json:"size"` // can be string or number
	Label    string        `json:"label"`
	FSType   string        `json:"fstype"`
	Children []lsblkDevice `json:"children"`
}

func enumerateVolumes() ([]VolumeInfo, error) {
	out, err := exec.Command("lsblk", "-J", "-b", "-o", "NAME,TYPE,SIZE,LABEL,FSTYPE").Output()
	if err != nil {
		return nil, fmt.Errorf("lsblk: %w", err)
	}

	var parsed lsblkOutput
	if err := json.Unmarshal(out, &parsed); err != nil {
		return nil, fmt.Errorf("parse lsblk: %w", err)
	}

	var vols []VolumeInfo
	walkDevices(parsed.Blockdevices, &vols)
	return vols, nil
}

func walkDevices(devs []lsblkDevice, out *[]VolumeInfo) {
	for _, d := range devs {
		if !shouldIncludeType(d.Type) {
			walkDevices(d.Children, out)
			continue
		}
		path := devicePath(d.Name)
		if path == "" {
			walkDevices(d.Children, out)
			continue
		}
		size := parseSize(d.Size)
		*out = append(*out, VolumeInfo{
			Path:       path,
			Label:      strings.TrimSpace(d.Label),
			FileSystem: strings.TrimSpace(d.FSType),
			SizeBytes:  size,
			Type:       strings.TrimSpace(d.Type),
		})
		walkDevices(d.Children, out)
	}
}

func shouldIncludeType(t string) bool {
	switch strings.ToLower(t) {
	case "disk", "part", "lvm":
		return true
	default:
		return false
	}
}

func devicePath(name string) string {
	name = strings.TrimSpace(name)
	if name == "" {
		return ""
	}
	if strings.HasPrefix(name, "/") {
		return name
	}
	return "/dev/" + name
}

func parseSize(val any) uint64 {
	switch v := val.(type) {
	case float64:
		return uint64(v)
	case json.Number:
		if n, err := v.Int64(); err == nil {
			return uint64(n)
		}
	case string:
		if n, err := strconv.ParseUint(v, 10, 64); err == nil {
			return n
		}
	}
	return 0
}


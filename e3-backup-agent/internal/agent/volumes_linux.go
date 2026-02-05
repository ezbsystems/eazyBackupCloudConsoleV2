//go:build linux
// +build linux

package agent

import (
	"strings"
)

func enumerateVolumes() ([]VolumeInfo, error) {
	out, err := readLsblk()
	if err != nil {
		return nil, err
	}

	var vols []VolumeInfo
	walkDevices(out.Blockdevices, &vols)
	return vols, nil
}

func walkDevices(devs []lsblkDevice, out *[]VolumeInfo) {
	for _, d := range devs {
		if !shouldIncludeType(d.Type) {
			walkDevices(d.Children, out)
			continue
		}
		path := d.Path
		if path == "" {
			path = devicePath(d.Name)
		}
		if path == "" {
			walkDevices(d.Children, out)
			continue
		}
		size := uint64(parseInt64(d.Size))
		*out = append(*out, VolumeInfo{
			Path:       path,
			Label:      strings.TrimSpace(d.Label),
			FileSystem: strings.TrimSpace(d.Fstype),
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


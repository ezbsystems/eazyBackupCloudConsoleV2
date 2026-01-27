//go:build linux
// +build linux

package agent

import (
	"fmt"
	"strings"
)

func enumerateDisks() ([]DiskInfo, error) {
	out, err := readLsblk()
	if err != nil {
		return nil, err
	}
	flat := flattenLsblk(out)
	var disks []DiskInfo
	for _, d := range flat {
		if d.Type != "disk" {
			continue
		}
		info := DiskInfo{
			Path:           d.Path,
			Name:           d.Name,
			Model:          d.Model,
			Serial:         d.Serial,
			BusType:        d.Tran,
			PartitionStyle: normalizePartitionStyle(d.PTType),
			SizeBytes:      uint64(parseInt64(d.Size)),
		}
		for _, p := range flat {
			if p.Type != "part" || p.PkName != d.Name {
				continue
			}
			blockSize := readBlockSize(d.Name)
			if blockSize == 0 {
				blockSize = 512
			}
			startBytes := parseInt64(p.Start) * blockSize
			part := DiskPartitionSummary{
				Name:       p.Name,
				Path:       p.Path,
				StartBytes: startBytes,
				SizeBytes:  parseInt64(p.Size),
				FileSystem: strings.ToLower(strings.TrimSpace(p.Fstype)),
				Label:      p.Label,
				PartType:   p.PartType,
				Mountpoint: p.Mountpoint,
			}
			info.Partitions = append(info.Partitions, part)
		}
		disks = append(disks, info)
	}
	return disks, nil
}

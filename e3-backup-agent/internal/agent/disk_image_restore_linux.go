//go:build linux
// +build linux

package agent

import (
	"bytes"
	"fmt"
	"log"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
)

func applyDiskLayout(targetDisk string, layout *DiskLayout, plan []partitionPlan) error {
	if layout == nil || len(plan) == 0 {
		return fmt.Errorf("disk layout plan missing")
	}

	label := "gpt"
	if layout.PartitionStyle == "mbr" {
		label = "dos"
	}

	blockSize := readBlockSize(filepath.Base(targetDisk))
	if blockSize == 0 {
		blockSize = 512
	}

	var buf bytes.Buffer
	buf.WriteString(fmt.Sprintf("label: %s\n", label))
	buf.WriteString("unit: sectors\n\n")
	for _, p := range plan {
		startSector := p.StartBytes / blockSize
		sizeSectors := p.SizeBytes / blockSize
		line := fmt.Sprintf("start=%d, size=%d", startSector, sizeSectors)
		if p.PartType != "" {
			line += fmt.Sprintf(", type=%s", p.PartType)
		}
		buf.WriteString(line + "\n")
	}

	cmd := exec.Command("sfdisk", "--force", targetDisk)
	cmd.Stdin = &buf
	out, err := cmd.CombinedOutput()
	if err != nil {
		return fmt.Errorf("sfdisk failed: %v (%s)", err, string(out))
	}
	_ = exec.Command("partprobe", targetDisk).Run()
	return nil
}

func repairBoot(targetDisk string, layout *DiskLayout, plan []partitionPlan) error {
	// Best-effort GRUB install in Linux recovery environment.
	if layout == nil {
		return nil
	}
	rootPart := findLinuxRootPartition(layout)
	if rootPart == "" {
		return nil
	}
	mountPoint := "/mnt/recovery-root"
	_ = exec.Command("mkdir", "-p", mountPoint).Run()
	if err := exec.Command("mount", rootPart, mountPoint).Run(); err != nil {
		return err
	}
	defer exec.Command("umount", "-f", mountPoint).Run()

	efiPart := findLinuxEFIPartition(layout)
	if efiPart != "" {
		efiMount := filepath.Join(mountPoint, "boot/efi")
		_ = exec.Command("mkdir", "-p", efiMount).Run()
		_ = exec.Command("mount", efiPart, efiMount).Run()
		defer exec.Command("umount", "-f", efiMount).Run()
		_ = exec.Command("grub-install", "--target=x86_64-efi", "--efi-directory", efiMount, "--bootloader-id=E3Backup", "--root-directory", mountPoint).Run()
	} else {
		_ = exec.Command("grub-install", "--target=i386-pc", "--root-directory", mountPoint, targetDisk).Run()
	}
	// Regenerate initramfs inside chroot (best effort).
	_ = exec.Command("chroot", mountPoint, "sh", "-c", "command -v dracut >/dev/null 2>&1 && dracut -f || true").Run()
	_ = exec.Command("chroot", mountPoint, "sh", "-c", "command -v update-initramfs >/dev/null 2>&1 && update-initramfs -u || true").Run()
	_ = exec.Command("chroot", mountPoint, "update-grub").Run()
	return nil
}

func findLinuxRootPartition(layout *DiskLayout) string {
	var best string
	var bestUsed int64
	for _, p := range layout.Partitions {
		if p.FileSystem != "ext4" {
			continue
		}
		if p.UsedBytes > bestUsed {
			bestUsed = p.UsedBytes
			best = p.Path
		}
	}
	return best
}

func findLinuxEFIPartition(layout *DiskLayout) string {
	for _, p := range layout.Partitions {
		if p.IsEFI {
			return p.Path
		}
		if strings.Contains(strings.ToLower(p.PartType), "efi") {
			return p.Path
		}
	}
	return ""
}

func openBlockDeviceForWrite(target string) (*os.File, error) {
	return os.OpenFile(target, os.O_RDWR, 0)
}

func currentBootMode() string {
	if _, err := os.Stat("/sys/firmware/efi"); err == nil {
		return "uefi"
	}
	return "bios"
}

func resizeFileSystems(layout *DiskLayout, plan []partitionPlan) error {
	if layout == nil {
		return nil
	}
	partByIndex := map[int]DiskPartition{}
	for _, p := range layout.Partitions {
		partByIndex[p.Index] = p
	}
	for _, p := range plan {
		part, ok := partByIndex[p.Index]
		if !ok || part.Path == "" {
			continue
		}
		switch p.FileSystem {
		case "ext4":
			_ = exec.Command("e2fsck", "-fy", part.Path).Run()
			if err := exec.Command("resize2fs", "-p", part.Path).Run(); err != nil {
				log.Printf("resize2fs failed for %s: %v", part.Path, err)
			}
		case "ntfs":
			sizeArg := fmt.Sprintf("%dB", p.SizeBytes)
			if err := exec.Command("ntfsresize", "-f", "--size", sizeArg, part.Path).Run(); err != nil {
				log.Printf("ntfsresize failed for %s: %v", part.Path, err)
			}
		}
	}
	return nil
}

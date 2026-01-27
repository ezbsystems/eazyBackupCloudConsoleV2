//go:build windows
// +build windows

package agent

import (
	"fmt"
	"os"
	"os/exec"
	"strings"
	"syscall"
)

func applyDiskLayout(targetDisk string, layout *DiskLayout, plan []partitionPlan) error {
	return fmt.Errorf("disk layout apply not supported on Windows recovery mode")
}

func repairBoot(targetDisk string, layout *DiskLayout, plan []partitionPlan) error {
	winDir := findWindowsDir()
	if winDir != "" {
		_ = enableWindowsStorageDrivers(winDir)
		efiDrive := findEFIDrive()
		if efiDrive != "" {
			_ = exec.Command("bcdboot", winDir, "/s", efiDrive, "/f", "ALL").Run()
		} else {
			_ = exec.Command("bcdboot", winDir, "/f", "ALL").Run()
		}
	}
	_ = exec.Command("bootrec", "/fixmbr").Run()
	_ = exec.Command("bootrec", "/fixboot").Run()
	_ = exec.Command("bootrec", "/rebuildbcd").Run()
	return nil
}

func findWindowsDir() string {
	for _, drive := range []string{"C", "D", "E", "F", "G", "H"} {
		path := drive + `:\Windows\System32`
		if _, err := os.Stat(path); err == nil {
			return drive + `:\Windows`
		}
	}
	return ""
}

func findEFIDrive() string {
	for _, drive := range []string{"S", "T", "U", "V", "W", "X", "Y", "Z"} {
		path := drive + `:\EFI\Microsoft\Boot`
		if _, err := os.Stat(path); err == nil {
			return drive + `:`
		}
	}
	// fallback: look for FAT32 volumes with EFI folder
	for _, drive := range []string{"C", "D", "E", "F", "G", "H"} {
		path := drive + `:\EFI`
		if _, err := os.Stat(path); err == nil {
			return drive + `:`
		}
	}
	return ""
}

func normalizeWindowsDiskTarget(target string) string {
	trimmed := strings.TrimSpace(target)
	if strings.HasPrefix(strings.ToLower(trimmed), `\\.\physicaldrive`) {
		return trimmed
	}
	if strings.HasPrefix(strings.ToLower(trimmed), `physicaldrive`) {
		return `\\.\` + trimmed
	}
	return trimmed
}

func openBlockDeviceForWrite(target string) (*os.File, error) {
	path := normalizeWindowsDiskTarget(target)
	h, err := syscall.CreateFile(syscall.StringToUTF16Ptr(path), syscall.GENERIC_WRITE|syscall.GENERIC_READ, syscall.FILE_SHARE_READ|syscall.FILE_SHARE_WRITE, nil, syscall.OPEN_EXISTING, 0, 0)
	if err != nil {
		return nil, fmt.Errorf("open target disk: %w", err)
	}
	return os.NewFile(uintptr(h), path), nil
}

func enableWindowsStorageDrivers(winDir string) error {
	systemHive := winDir + `\System32\Config\SYSTEM`
	if _, err := os.Stat(systemHive); err != nil {
		return err
	}
	_ = exec.Command("reg", "load", "HKLM\\RECOVERY_SYSTEM", systemHive).Run()
	drivers := []string{"storahci", "stornvme", "iaStorV"}
	for _, svc := range drivers {
		_ = exec.Command("reg", "add", "HKLM\\RECOVERY_SYSTEM\\ControlSet001\\Services\\"+svc, "/v", "Start", "/t", "REG_DWORD", "/d", "0", "/f").Run()
	}
	_ = exec.Command("reg", "unload", "HKLM\\RECOVERY_SYSTEM").Run()
	return nil
}

func currentBootMode() string {
	out, err := exec.Command("powershell", "-NoProfile", "-Command", "(Get-ItemProperty -Path 'HKLM:\\SYSTEM\\CurrentControlSet\\Control' -Name PEFirmwareType -ErrorAction SilentlyContinue).PEFirmwareType").Output()
	if err != nil {
		return "unknown"
	}
	val := strings.TrimSpace(string(out))
	if val == "2" {
		return "uefi"
	}
	if val == "1" {
		return "bios"
	}
	return "unknown"
}

func resizeFileSystems(layout *DiskLayout, plan []partitionPlan) error {
	// Shrink operations are performed in Linux recovery environment.
	return nil
}

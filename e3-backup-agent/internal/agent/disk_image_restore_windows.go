//go:build windows
// +build windows

package agent

import (
	"fmt"
	"os"
	"os/exec"
	"regexp"
	"strconv"
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
	if err := prepareBlockDeviceForWrite(target); err != nil {
		return nil, err
	}
	return openBlockDeviceForWriteNoPreflight(target)
}

func prepareBlockDeviceForWrite(target string) error {
	path := normalizeWindowsDiskTarget(target)
	return preflightDiskForWrite(path)
}

func openBlockDeviceForWriteNoPreflight(target string) (*os.File, error) {
	path := normalizeWindowsDiskTarget(target)
	h, err := syscall.CreateFile(syscall.StringToUTF16Ptr(path), syscall.GENERIC_WRITE|syscall.GENERIC_READ, syscall.FILE_SHARE_READ|syscall.FILE_SHARE_WRITE, nil, syscall.OPEN_EXISTING, 0, 0)
	if err != nil {
		return nil, fmt.Errorf("open target disk: %w", err)
	}
	return os.NewFile(uintptr(h), path), nil
}

type diskpartVolume struct {
	number int
	letter string
}

func preflightDiskForWrite(target string) error {
	diskNum, err := parseDiskNumber(target)
	if err != nil {
		return err
	}

	if err := tryPowerShellDiskPrepare(diskNum); err == nil {
		return nil
	}

	detailOut, _ := runDiskpart([]string{
		fmt.Sprintf("select disk %d", diskNum),
		"detail disk",
	})
	volumes := parseDiskpartVolumes(detailOut)

	cmds := []string{
		"automount disable",
		fmt.Sprintf("select disk %d", diskNum),
	}
	for _, v := range volumes {
		if v.letter == "" {
			continue
		}
		cmds = append(cmds,
			fmt.Sprintf("select volume %d", v.number),
			fmt.Sprintf("remove letter=%s", v.letter),
		)
	}
	cmds = append(cmds,
		fmt.Sprintf("select disk %d", diskNum),
		"offline disk",
	)

	if out, err := runDiskpart(cmds); err != nil {
		return fmt.Errorf("diskpart preflight failed: %w (output=%s)", err, strings.TrimSpace(out))
	}
	return nil
}

func parseDiskNumber(target string) (int, error) {
	re := regexp.MustCompile(`(?i)physicaldrive\s*(\d+)`)
	m := re.FindStringSubmatch(target)
	if len(m) < 2 {
		return 0, fmt.Errorf("unable to determine disk number from target %q", target)
	}
	n, err := strconv.Atoi(m[1])
	if err != nil {
		return 0, fmt.Errorf("invalid disk number %q: %w", m[1], err)
	}
	return n, nil
}

func tryPowerShellDiskPrepare(diskNum int) error {
	if _, err := exec.LookPath("powershell.exe"); err != nil {
		return err
	}
	cmd := fmt.Sprintf(
		"$ErrorActionPreference='Stop'; "+
			"$disk=%d; "+
			"$parts=Get-Partition -DiskNumber $disk -ErrorAction SilentlyContinue | Where-Object { $_.DriveLetter }; "+
			"foreach ($p in $parts) { "+
			"  try { Remove-PartitionAccessPath -DiskNumber $disk -PartitionNumber $p.PartitionNumber -AccessPath ($p.DriveLetter + ':\\') -ErrorAction SilentlyContinue } catch {} "+
			"}; "+
			"try { Set-Disk -Number $disk -IsOffline $true -ErrorAction SilentlyContinue } catch {}",
		diskNum,
	)
	out, err := exec.Command("powershell.exe", "-NoProfile", "-Command", cmd).CombinedOutput()
	if err != nil {
		return fmt.Errorf("powershell preflight failed: %w output=%s", err, strings.TrimSpace(string(out)))
	}
	return nil
}

func runDiskpart(commands []string) (string, error) {
	script := strings.Join(commands, "\r\n") + "\r\n"
	tmp, err := os.CreateTemp("", "diskpart-*.txt")
	if err != nil {
		return "", err
	}
	defer os.Remove(tmp.Name())
	if _, err := tmp.WriteString(script); err != nil {
		_ = tmp.Close()
		return "", err
	}
	_ = tmp.Close()

	out, err := exec.Command("diskpart", "/s", tmp.Name()).CombinedOutput()
	if err != nil {
		return string(out), err
	}
	return string(out), nil
}

func parseDiskpartVolumes(detail string) []diskpartVolume {
	var vols []diskpartVolume
	for _, line := range strings.Split(detail, "\n") {
		fields := strings.Fields(line)
		if len(fields) < 2 {
			continue
		}
		if !strings.EqualFold(fields[0], "Volume") {
			continue
		}
		num, err := strconv.Atoi(fields[1])
		if err != nil {
			continue
		}
		letter := ""
		if len(fields) >= 3 {
			cand := strings.TrimSpace(fields[2])
			if len(cand) == 2 && strings.HasSuffix(cand, ":") {
				cand = strings.TrimSuffix(cand, ":")
			}
			if len(cand) == 1 && cand[0] >= 'A' && cand[0] <= 'Z' {
				letter = cand
			}
		}
		vols = append(vols, diskpartVolume{number: num, letter: letter})
	}
	return vols
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
